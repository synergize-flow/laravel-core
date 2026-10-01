<?php

use Illuminate\Support\Facades\Route;
use SynergizeFlow\Laravel\Http\Controllers\SynergizeFlowController;
use SynergizeFlow\Laravel\Http\Middleware\VerifySynergizeFlowToken;

Route::prefix('api/synergizeflow/v2')
    ->middleware('api')
    ->group(function () {
        // Public license check matching WordPress validate-license endpoint
        Route::post('validate-license', [SynergizeFlowController::class, 'validateLicense']);

        // Authenticated endpoints protected by security key
        Route::middleware(VerifySynergizeFlowToken::class)->group(function () {
            Route::post('get-website-data', [SynergizeFlowController::class, 'getWebsiteData']);
            Route::post('helper-data', [SynergizeFlowController::class, 'helperData']);
            Route::post('insert-blog', [SynergizeFlowController::class, 'insertBlog']);
        });
    });
