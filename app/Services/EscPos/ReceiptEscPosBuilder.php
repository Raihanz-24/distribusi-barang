<?php

namespace App\Services\EscPos;

use App\Models\Product;
use App\Models\StoreVisit;

/**
 * Menyusun byte perintah ESC/POS untuk struk pembayaran thermal.
 *
 * Byte ini dikirim mentah (raw) ke printer, baik lewat WebUSB di browser
 * maupun lewat print agent lokal di Windows. Karena printer thermal tidak
 * mengenal CSS, tata letak dibuat dengan spasi dan perataan per baris.
 *
 * Lebar baris mengikuti area cetak, bukan lebar kertas:
 * - kertas 58mm dapat mencetak 32 karakter (area ~48mm)
 * - kertas 80mm dapat mencetak 48 karakter (area ~72mm)
 */
class ReceiptEscPosBuilder
{
    private const ESC = "\x1B";

    private const GS = "\x1D";

    private const LF = "\x0A";

    private int $columns;

    private string $storeName;

    private string $storeTagline;

    private string $footer;

    public function __construct()
    {
        $config = config('sales.receipt');

        $paperWidth = (int) ($config['paper_width_mm'] ?? 58);

        $this->columns = $paperWidth >= 80 ? 48 : 32;
        $this->storeName = (string) ($config['store_name'] ?? 'PANCOR MAS');
        $this->storeTagline = (string) ($config['store_tagline'] ?? '');
        $this->footer = (string) ($config['footer'] ?? 'Terima kasih');
    }

    public function build(StoreVisit $visit): string
    {
        $visit->loadMissing(['store', 'sales', 'items.product']);

        $out = self::ESC.'@';                 // inisialisasi printer
        $out .= self::ESC.'a'.chr(1);         // rata tengah

        $out .= self::ESC.'E'.chr(1).$this->wrappedLine($this->storeName).self::ESC.'E'.chr(0);

        if ($this->storeTagline !== '') {
            $out .= $this->wrappedLine($this->storeTagline);
        }

        $out .= $this->wrappedLine('Bukti Pembayaran Toko');
        $out .= $this->rule();

        $out .= self::ESC.'a'.chr(0);         // rata kiri

        $out .= $this->wrappedLine(
            $visit->completed_at?->format('d/m/Y H:i') ?? $visit->arrived_at?->format('d/m/Y H:i') ?? ''
        );
        $out .= $this->wrappedLine('Toko: '.$visit->store->name);
        $out .= $this->wrappedLine('Sales: '.$visit->sales->name);
        $out .= $this->wrappedLine('No: KJN-'.str_pad((string) $visit->id, 6, '0', STR_PAD_LEFT));
        $out .= $this->rule();

        foreach ($visit->items as $item) {
            /** @var Product $product */
            $product = $item->product;

            // Nama produk panjang dibungkus agar tidak melewati area cetak.
            foreach ($this->wrap($product->name, $this->columns) as $nameLine) {
                $out .= self::ESC.'E'.chr(1).$this->line($nameLine).self::ESC.'E'.chr(0);
            }

            $out .= $this->twoColumns(
                'Laku '.number_format($item->sold_pieces).' bks',
                'Rp '.$this->money($item->amount_due),
            );

            if ($item->deposited_pieces > 0) {
                $out .= $this->twoColumns(
                    'Dititipkan',
                    $product->formatStockQuantity($item->deposited_pieces),
                );
            }

            if ($item->withdrawn_pieces > 0) {
                $out .= $this->twoColumns(
                    'Ditarik',
                    $product->formatStockQuantity($item->withdrawn_pieces),
                );
            }

            $out .= $this->twoColumns('Fee toko', 'Rp '.$this->money($item->store_fee));
        }

        $out .= $this->rule();

        $out .= $this->twoColumns('Tagihan transaksi', 'Rp '.$this->money($visit->amount_due));
        $out .= $this->twoColumns('Bayar transaksi', 'Rp '.$this->money($visit->current_payment_amount));

        if ($visit->opening_debt > 0) {
            $out .= $this->twoColumns('Hutang sebelumnya', 'Rp '.$this->money($visit->opening_debt));
        }

        if ($visit->debt_payment_amount > 0) {
            $out .= $this->twoColumns('Bayar hutang lama', 'Rp '.$this->money($visit->debt_payment_amount));
        }

        $out .= self::ESC.'E'.chr(1);
        $out .= $this->twoColumns('DIBAYARKAN', 'Rp '.$this->money($visit->amount_paid));
        $out .= self::ESC.'E'.chr(0);
        $out .= $this->twoColumns('Sisa hutang', 'Rp '.$this->money($visit->closing_debt));

        $out .= $this->rule();
        $out .= self::ESC.'a'.chr(1);
        $out .= $this->wrappedLine($this->footer);
        $out .= $this->wrappedLine('Struk ini sah sebagai bukti pembayaran.');

        $out .= self::ESC.'d'.chr(3);         // majukan kertas
        $out .= self::GS.'V'.chr(0);          // potong kertas (diabaikan printer tanpa cutter)

        return $out;
    }

    private function line(string $text): string
    {
        return $this->safe($text).self::LF;
    }

    /**
     * Baris teks yang dibungkus agar tidak melewati area cetak printer.
     */
    private function wrappedLine(string $text): string
    {
        $out = '';

        foreach ($this->wrap($text, $this->columns) as $line) {
            $out .= $line.self::LF;
        }

        return $out;
    }

    private function rule(): string
    {
        return str_repeat('-', $this->columns).self::LF;
    }

    /**
     * Baris dua kolom rata kiri-kanan. Jika isi terlalu panjang, nilai
     * dipindah ke baris berikutnya supaya tidak terpotong printer.
     */
    private function twoColumns(string $left, string $right): string
    {
        $left = $this->safe($left);
        $right = $this->safe($right);

        $space = $this->columns - $this->width($left) - $this->width($right);

        if ($space < 1) {
            return $this->line($left).$this->line(str_pad('', max(0, $this->columns - $this->width($right))).$right);
        }

        return $this->line($left.str_repeat(' ', $space).$right);
    }

    /**
     * Bungkus teks per sejumlah kolom, memotong pada batas kata bila ada.
     *
     * @return array<int, string>
     */
    private function wrap(string $text, int $columns): array
    {
        $text = trim($this->safe($text));

        if ($text === '') {
            return [''];
        }

        $lines = [];

        while ($this->width($text) > $columns) {
            $chunk = substr($text, 0, $columns);
            $lastSpace = strrpos($chunk, ' ');

            if ($lastSpace !== false && $lastSpace > 0) {
                $chunk = substr($text, 0, $lastSpace);
            }

            $lines[] = $chunk;
            $text = ltrim(substr($text, strlen($chunk)));
        }

        $lines[] = $text;

        return $lines;
    }

    private function width(string $text): int
    {
        return strlen($text);
    }

    private function money(int|float|string|null $value): string
    {
        return number_format((int) $value, 0, ',', '.');
    }

    /**
     * Printer thermal memakai code page, bukan UTF-8. Karakter di luar CP437
     * diganti agar tidak menjadi simbol acak.
     */
    private function safe(string $text): string
    {
        $converted = @iconv('UTF-8', 'CP437//TRANSLIT//IGNORE', $text);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $text) : $converted;
    }
}
