<?php

namespace App\Http\Controllers;

use App\Models\StoreVisit;
use Illuminate\Contracts\View\View;

class SalesReceiptController extends Controller
{
    public function __invoke(StoreVisit $visit): View
    {
        abort_unless(auth()->user()->isAdmin() || $visit->sales_id === auth()->id(), 403);

        return view('sales.receipt', [
            'visit' => $visit->load(['store', 'sales', 'items.product']),
        ]);
    }
}
