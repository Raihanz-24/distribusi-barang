<?php

namespace App\Http\Controllers;

use App\Models\StoreVisit;
use App\Services\EscPos\ReceiptEscPosBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Mengirim struk sebagai byte ESC/POS mentah (Content-Type: application/octet-stream).
 *
 * Byte ini kemudian diteruskan browser ke printer:
 * - Android/Chrome: lewat WebUSB
 * - Windows: lewat print agent lokal (tools/print-agent)
 *
 * Tidak ada CSS/HTML di sini karena printer thermal hanya mengerti ESC/POS.
 */
class SalesReceiptRawController extends Controller
{
    public function __invoke(Request $request, StoreVisit $visit, ReceiptEscPosBuilder $builder): Response
    {
        abort_unless($request->user()->isAdmin() || $visit->sales_id === $request->user()->id, 403);

        return response($builder->build($visit), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="struk-'.$visit->id.'.bin"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
