<?php

declare(strict_types=1);

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Http\Controllers\Inventory\DownloadAttachmentController;
use App\Http\Controllers\Orders\DownloadOrderPdfController;
use App\Livewire\Admin\Auth\Login as AdminLogin;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Users as AdminUsers;
use App\Livewire\Billing\Pricing;
use App\Livewire\Catalogue\Index as CatalogueIndex;
use App\Livewire\Dashboard;
use App\Livewire\Guide;
use App\Livewire\Import\Index as ImportIndex;
use App\Livewire\Inventory\Index as InventoryIndex;
use App\Livewire\Map\Index as MapIndex;
use App\Livewire\Orders\Index as OrderIndex;
use App\Livewire\Suppliers\Index as SupplierIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

// Public product guide (accessible to guests and authenticated users alike).
Route::get('/guide', Guide::class)->name('guide');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth:web')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/suppliers', SupplierIndex::class)->name('suppliers');
    Route::get('/catalogue', CatalogueIndex::class)->name('catalogue');
    Route::get('/import', ImportIndex::class)->name('import');
    Route::get('/inventory', InventoryIndex::class)->name('inventory');
    Route::get('/inventory/attachments/{id}/download', DownloadAttachmentController::class)->name('inventory.attachments.download');
    Route::get('/orders', OrderIndex::class)->name('orders');
    Route::get('/orders/{id}/pdf', DownloadOrderPdfController::class)->name('orders.pdf');
    Route::get('/map', MapIndex::class)->name('map');
    Route::get('/pricing', Pricing::class)->name('pricing');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});

/*
 * Admin back-office — a fully separate authentication domain (`admin` guard).
 */
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', AdminLogin::class)->name('login');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::get('/', AdminDashboard::class)->name('dashboard');
        Route::get('users', AdminUsers::class)->name('users');

        Route::post('logout', function (Request $request) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        })->name('logout');
    });
});
