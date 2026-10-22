<?php

return [
    'retail_price_per_piece' => 2_000,
    'sales_fee_per_equivalent_renteng' => 3_500,
    'sales_fee_basis' => 20_000,

    /*
    |--------------------------------------------------------------------------
    | Struk pembayaran (printer thermal ESC/POS)
    |--------------------------------------------------------------------------
    |
    | Printer thermal hanya mencetak pada area kepala cetak, bukan selebar
    | kertas. Kertas 58mm dapat mencetak ~48mm (32 karakter), kertas 80mm
    | ~72mm (48 karakter). Melebar selebar kertas membuat sisi kanan terpotong.
    |
    | Ubah RECEIPT_PAPER_WIDTH_MM menjadi 80 jika memakai printer EPPOS 80mm.
    |
    */
    'receipt' => [
        'paper_width_mm' => (int) env('RECEIPT_PAPER_WIDTH_MM', 58),
        'store_name' => env('RECEIPT_STORE_NAME', 'PANCOR MAS'),
        'store_tagline' => env('RECEIPT_STORE_TAGLINE', 'Kopi Bubuk'),
        'footer' => env('RECEIPT_FOOTER', 'Terima kasih'),
    ],
];
