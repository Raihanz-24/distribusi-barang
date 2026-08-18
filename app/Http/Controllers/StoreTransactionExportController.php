<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\StoreTransactionReport;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoreTransactionExportController extends Controller
{
    public function __invoke(Request $request, Store $store, StoreTransactionReport $report): BinaryFileResponse
    {
        abort_unless($request->user()?->can('view', $store), 403);

        $data = $report->data($store);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'laporan-toko-');
        $styles = $this->styles();
        $writer = new Writer($this->options());
        $writer->openToFile($temporaryFile);

        $this->writeSummarySheet($writer, $data, $styles);
        $this->writeProductDetailSheet($writer, $data, $styles);
        $writer->close();

        $safeCode = preg_replace('/[^A-Za-z0-9_-]+/', '-', $store->code ?: (string) $store->id);
        $filename = 'laporan-toko-'.$safeCode.'-'.now()->format('Y-m-d').'.xlsx';

        return response()->download($temporaryFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function writeSummarySheet(Writer $writer, array $data, array $styles): void
    {
        $store = $data['store'];
        $writer->getCurrentSheet()->setName('Ringkasan Toko');
        $writer->addRow(Row::fromValues(['LAPORAN TRANSAKSI TOKO PANCOR MAS'], $styles['title'])->setHeight(28));
        $writer->addRow(Row::fromValues([$this->safeText($store->name).' · '.$this->safeText($store->code)], $styles['subtitle'])->setHeight(22));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['INFORMASI TOKO'], $styles['section']));
        $writer->addRow(Row::fromValues(['Nama toko', $this->safeText($store->name), 'Kode', $this->safeText($store->code)]));
        $writer->addRow(Row::fromValues(['Pemilik', $this->safeText($store->owner_name ?: '-'), 'Telepon', $this->safeText($store->phone ?: '-')]));
        $writer->addRow(Row::fromValues(['Alamat', $this->safeText($store->address ?: '-')]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['RINGKASAN SAAT INI'], $styles['section']));
        $writer->addRow(Row::fromValuesWithStyles([
            'Total hutang', $data['outstandingDebt'], 'Sisa stok', $data['stockTotal'], 'Jumlah transaksi', $data['visits']->count(), '', '',
        ], null, [1 => $styles['currency'], 3 => $styles['number'], 5 => $styles['number']]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['SISA STOK DI TOKO'], $styles['section']));
        $writer->addRow(Row::fromValues(['Kode Produk', 'Produk', 'Sisa (Bungkus)', 'Konversi'], $styles['header']));

        if ($data['stockBalances']->isEmpty()) {
            $writer->addRow(Row::fromValues(['-', 'Tidak ada stok tersisa', 0, '0 bungkus'], $styles['muted']));
        } else {
            foreach ($data['stockBalances']->values() as $index => $balance) {
                $product = $balance->product;
                $writer->addRow(Row::fromValues([
                    $this->safeText($product?->code ?? '-'),
                    $this->safeText($product?->name ?? 'Produk dihapus'),
                    $balance->quantity_pieces,
                    $this->safeText($product?->formatStockQuantity($balance->quantity_pieces) ?? number_format($balance->quantity_pieces).' bungkus'),
                ], $index % 2 ? $styles['alternate'] : $styles['data']));
            }
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['RIWAYAT TRANSAKSI'], $styles['section']));
        $writer->addRow(Row::fromValues([
            'Tanggal', 'Sales', 'Wajib Bayar', 'Dibayarkan', 'Hutang Baru', 'Produk', 'Laku (Bungkus)', 'Ditarik (Bungkus)',
        ], $styles['header'])->setHeight(30));

        foreach ($data['visits']->values() as $index => $visit) {
            $writer->addRow(Row::fromValuesWithStyles([
                optional($visit->completed_at ?? $visit->arrived_at)->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                $this->safeText($visit->sales?->name ?? '-'),
                $visit->amount_due,
                $visit->amount_paid,
                $visit->debt_amount,
                $visit->items->count(),
                $visit->items->sum('sold_pieces'),
                $visit->items->sum('withdrawn_pieces'),
            ], $index % 2 ? $styles['alternate'] : $styles['data'], [
                2 => $styles['currency'], 3 => $styles['currency'], 4 => $styles['currency'],
            ]));
        }
    }

    private function writeProductDetailSheet(Writer $writer, array $data, array $styles): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Detail Produk');
        $writer->addRow(Row::fromValues(['DETAIL PRODUK PER TRANSAKSI'], $styles['title'])->setHeight(28));
        $writer->addRow(Row::fromValues([$this->safeText($data['store']->name).' · '.$this->safeText($data['store']->code)], $styles['subtitle'])->setHeight(22));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            'Tanggal', 'Sales', 'Produk', 'Laku (Bungkus)', 'Dititipkan (Bungkus)', 'Ditarik (Bungkus)', 'Tagihan Produk', 'Catatan Kunjungan',
        ], $styles['header'])->setHeight(34));

        $rowIndex = 0;
        foreach ($data['visits'] as $visit) {
            foreach ($visit->items as $item) {
                $writer->addRow(Row::fromValuesWithStyles([
                    optional($visit->completed_at ?? $visit->arrived_at)->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    $this->safeText($visit->sales?->name ?? '-'),
                    $this->safeText($item->product?->name ?? 'Produk dihapus'),
                    $item->sold_pieces,
                    $item->deposited_pieces,
                    $item->withdrawn_pieces,
                    $item->amount_due,
                    $this->safeText($visit->visit_notes ?: '-'),
                ], $rowIndex++ % 2 ? $styles['alternate'] : $styles['data'], [6 => $styles['currency']]));
            }
        }
    }

    private function options(): Options
    {
        $options = new Options;
        $options->setColumnWidth(19, 1);
        $options->setColumnWidth(24, 2, 3);
        $options->setColumnWidth(20, 4, 5, 6, 7, 8);
        $options->mergeCells(0, 1, 7, 1, 0);
        $options->mergeCells(0, 2, 7, 2, 0);
        $options->mergeCells(0, 4, 7, 4, 0);
        $options->mergeCells(0, 9, 7, 9, 0);
        $options->mergeCells(0, 12, 7, 12, 0);
        $options->mergeCells(0, 1, 7, 1, 1);
        $options->mergeCells(0, 2, 7, 2, 1);

        return $options;
    }

    private function styles(): array
    {
        return [
            'title' => (new Style)->setFontBold()->setFontSize(17)->setFontColor('FFFFFF')->setBackgroundColor('3F2A1D')->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER),
            'subtitle' => (new Style)->setFontColor('374151')->setBackgroundColor('F3F4F6')->setCellAlignment(CellAlignment::CENTER),
            'section' => (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('166534'),
            'header' => (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('1F2937')->setShouldWrapText()->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER),
            'data' => (new Style)->setCellVerticalAlignment(CellVerticalAlignment::CENTER),
            'alternate' => (new Style)->setBackgroundColor('F3F4F6')->setCellVerticalAlignment(CellVerticalAlignment::CENTER),
            'muted' => (new Style)->setFontColor('6B7280')->setBackgroundColor('F9FAFB'),
            'currency' => (new Style)->setFormat('"Rp" #,##0'),
            'number' => (new Style)->setFormat('#,##0'),
        ];
    }

    private function safeText(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
