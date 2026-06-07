<?php

declare(strict_types=1);

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Http\Controllers\Inventory\DownloadAttachmentController;
use App\Livewire\Catalogue\Index as CatalogueIndex;
use App\Livewire\Dashboard;
use App\Livewire\Import\Index as ImportIndex;
use App\Livewire\Inventory\Index as InventoryIndex;
use App\Livewire\Suppliers\Index as SupplierIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/suppliers', SupplierIndex::class)->name('suppliers');
    Route::get('/catalogue', CatalogueIndex::class)->name('catalogue');
    Route::get('/import', ImportIndex::class)->name('import');
    Route::get('/inventory', InventoryIndex::class)->name('inventory');
    Route::get('/inventory/attachments/{id}/download', DownloadAttachmentController::class)->name('inventory.attachments.download');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});
