<?php

use App\Http\Controllers\NearbyStoresController;
use App\Http\Controllers\ReverseGeocodeController;
use App\Http\Controllers\SalesDepositExportController;
use App\Http\Controllers\SalesReceiptController;
use App\Http\Controllers\StoreMapDataController;
use App\Http\Controllers\StoreTransactionExportController;
use App\Http\Controllers\UpdateSalesLocationController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', EnsureUserIsActive::class])->group(function (): void {
    Route::get('/internal/store-map-data', StoreMapDataController::class)->name('internal.store-map-data');
    Route::get('/internal/nearby-stores', NearbyStoresController::class)->middleware('throttle:30,1')->name('internal.nearby-stores');
    Route::get('/internal/reverse-geocode', ReverseGeocodeController::class)->middleware('throttle:20,1')->name('internal.reverse-geocode');
    Route::get('/sales/receipts/{visit}', SalesReceiptController::class)->name('sales.receipts.show');
    Route::get('/reports/sales-deposits/export', SalesDepositExportController::class)->name('reports.sales-deposits.export');
    Route::get('/reports/stores/{store}/export', StoreTransactionExportController::class)->name('reports.stores.export');
    Route::post('/internal/sales-location', UpdateSalesLocationController::class)
        ->middleware('throttle:10,1')
        ->name('internal.sales-location.update');
});
