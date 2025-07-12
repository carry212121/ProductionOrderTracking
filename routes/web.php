<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProformaInvoiceController;
use App\Http\Controllers\JobControlController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/dashboard', [ProformaInvoiceController::class, 'SummaryPI'])->middleware(['auth'])->name('dashboard');
Route::get('/PI', [ProformaInvoiceController::class, 'index'])->middleware(['auth'])->name('proformaInvoice.index');
Route::get('/proforma-invoice/{id}/detail', [ProformaInvoiceController::class, 'show'])->name('proformaInvoice.show');
Route::post('/jobcontrols/store-or-update', [JobControlController::class, 'storeOrUpdate'])->name('jobcontrols.storeOrUpdate');
Route::put('/products/{id}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggleStatus');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
