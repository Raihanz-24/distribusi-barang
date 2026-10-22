<?php

namespace App\Http\Controllers;

use App\Models\StoreVisit;
use Illuminate\Contracts\View\View;

class SalesReceiptController extends Controller
{
    public function __invoke(StoreVisit $visit): View
    {
        abort_unless(auth()->user()->isAdmin() || $visit->sales_id === auth()->id(), 403);

        $config = config('sales.receipt');

        return view('sales.receipt', [
            'visit' => $visit->load(['store', 'sales', 'items.product']),
            'paperWidth' => in_array((int) $config['paper_width_mm'], [58, 80], true)
                ? (int) $config['paper_width_mm']
                : 58,
            'storeName' => $config['store_name'],
            'storeTagline' => $config['store_tagline'],
            'footer' => $config['footer'],
        ]);
    }
}
