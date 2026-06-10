<?php

declare(strict_types=1);

use App\Http\Controllers\Api\IngestController;
use Illuminate\Support\Facades\Route;

/*
 * Machine ingestion of canonical trade data (the golden-snapshot payload
 * format over HTTP). Tokens are issued to admins via `php artisan
 * api:issue-token` with the `ingestion` ability; see wine:push-golden for the
 * client side.
 */
Route::middleware(['auth:sanctum', 'ability:ingestion', 'throttle:120,1'])
    ->prefix('ingest')
    ->group(function () {
        Route::post('suppliers', [IngestController::class, 'suppliers'])->name('api.ingest.suppliers');
        Route::post('wines', [IngestController::class, 'wines'])->name('api.ingest.wines');
        Route::post('facts', [IngestController::class, 'facts'])->name('api.ingest.facts');
        Route::post('parse-profiles', [IngestController::class, 'parseProfiles'])->name('api.ingest.parse-profiles');
        Route::get('status', [IngestController::class, 'status'])->name('api.ingest.status');
    });
