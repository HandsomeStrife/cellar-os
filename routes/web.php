<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DownloadSupplierDocumentController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\Inventory\DownloadAttachmentController;
use App\Http\Controllers\Orders\DownloadOrderPdfController;
use App\Http\Controllers\SupplierPortal\DownloadDocumentController as SupplierDownloadDocumentController;
use App\Http\Controllers\Suppliers\DownloadDocumentController as SupplierDocumentDownloadController;
use App\Livewire\Admin\Auth\Login as AdminLogin;
use App\Livewire\Admin\Companies as AdminCompanies;
use App\Livewire\Admin\CompanyShow as AdminCompanyShow;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Enquiries as AdminEnquiries;
use App\Livewire\Admin\Suppliers as AdminSuppliers;
use App\Livewire\Admin\SupplierShow as AdminSupplierShow;
use App\Livewire\Admin\Users as AdminUsers;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Billing\Pricing;
use App\Livewire\Catalogue\Index as CatalogueIndex;
use App\Livewire\Company\Team as CompanyTeam;
use App\Livewire\Dashboard;
use App\Livewire\Guide;
use App\Livewire\Import\Index as ImportIndex;
use App\Livewire\Inventory\Index as InventoryIndex;
use App\Livewire\Map\Index as MapIndex;
use App\Livewire\Orders\Index as OrderIndex;
use App\Livewire\SupplierPortal\Auth\ForgotPassword as SupplierForgotPassword;
use App\Livewire\SupplierPortal\Auth\Login as SupplierLogin;
use App\Livewire\SupplierPortal\Auth\ResetPassword as SupplierResetPassword;
use App\Livewire\SupplierPortal\Dashboard as SupplierDashboard;
use App\Livewire\SupplierPortal\Documents as SupplierDocuments;
use App\Livewire\SupplierPortal\Profile as SupplierProfile;
use App\Livewire\Suppliers\DocumentReview as BuyerSupplierDocumentReview;
use App\Livewire\Suppliers\Documents as BuyerSupplierDocuments;
use App\Livewire\Suppliers\Index as SupplierIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

// Public contact / enquiry form (stored for admin review). Throttled against spam.
Route::post('/enquiries', [EnquiryController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('enquiries.store');

// Public documentation-style guide. One Livewire page with a sticky sidenav;
// each section is a real URL (e.g. /guide/catalogue, /guide/orders).
Route::get('/guide', Guide::class)->name('guide');
Route::get('/guide/{section}', Guide::class)
    ->where('section', '[a-z0-9-]+')
    ->name('guide.section');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth:web')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/suppliers', SupplierIndex::class)->name('suppliers');
    Route::get('/suppliers/documents/{id}/download', SupplierDocumentDownloadController::class)->name('suppliers.documents.download');
    Route::get('/suppliers/{uuid}/documents', BuyerSupplierDocuments::class)->name('suppliers.documents');
    Route::get('/suppliers/{uuid}/documents/{documentId}/review', BuyerSupplierDocumentReview::class)->name('suppliers.documents.review');
    Route::get('/catalogue', CatalogueIndex::class)->name('catalogue');
    Route::get('/import', ImportIndex::class)->name('import');
    Route::get('/inventory', InventoryIndex::class)->name('inventory');
    Route::get('/inventory/attachments/{id}/download', DownloadAttachmentController::class)->name('inventory.attachments.download');
    Route::get('/orders', OrderIndex::class)->name('orders');
    Route::get('/orders/{id}/pdf', DownloadOrderPdfController::class)->name('orders.pdf');
    Route::get('/map', MapIndex::class)->name('map');
    Route::get('/team', CompanyTeam::class)->name('team');
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
        Route::get('companies', AdminCompanies::class)->name('companies');
        Route::get('companies/{uuid}', AdminCompanyShow::class)->name('companies.show');
        Route::get('users', AdminUsers::class)->name('users');
        Route::get('suppliers', AdminSuppliers::class)->name('suppliers');
        Route::get('suppliers/{uuid}', AdminSupplierShow::class)->name('suppliers.show');
        Route::get('supplier-documents/{id}/download', DownloadSupplierDocumentController::class)->name('supplier-documents.download');

        // Impersonation: view the app exactly as a buyer or portal user sees it.
        Route::post('impersonate/users/{id}', [ImpersonationController::class, 'user'])->name('impersonate.user');
        Route::post('impersonate/supplier-users/{id}', [ImpersonationController::class, 'supplierUser'])->name('impersonate.supplier-user');
        Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
        Route::get('enquiries', AdminEnquiries::class)->name('enquiries');

        Route::post('logout', function (Request $request) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        })->name('logout');
    });
});

/*
 * Supplier portal — a third, fully separate authentication domain (`supplier`
 * guard). Supplier companies' users sign in here to upload their portfolios /
 * price sheets for analysis.
 */
Route::prefix('supplier')->name('supplier.')->group(function () {
    Route::middleware('guest:supplier')->group(function () {
        Route::get('login', SupplierLogin::class)->name('login');
        Route::get('forgot-password', SupplierForgotPassword::class)->name('password.request');
        Route::get('reset-password/{token}', SupplierResetPassword::class)->name('password.reset');
    });

    Route::middleware('auth:supplier')->group(function () {
        Route::get('/', SupplierDashboard::class)->name('dashboard');
        Route::get('documents', SupplierDocuments::class)->name('documents');
        Route::get('documents/{id}/download', SupplierDownloadDocumentController::class)->name('documents.download');
        Route::get('profile', SupplierProfile::class)->name('profile');

        Route::post('logout', function (Request $request) {
            Auth::guard('supplier')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('supplier.login');
        })->name('logout');
    });
});
