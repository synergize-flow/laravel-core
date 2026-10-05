<?php

use Illuminate\Support\Facades\Route;
use SynergizeFlow\Laravel\Http\Controllers\SynergizeFlowController;
use SynergizeFlow\Laravel\Http\Middleware\VerifySynergizeFlowToken;

Route::prefix('api/synergizeflow/v2')
    ->middleware(['api', VerifySynergizeFlowToken::class])
    ->group(function () {
        Route::match(['get', 'post'], 'validate-license', [SynergizeFlowController::class, 'validateLicense']);
        Route::post('get-website-data', [SynergizeFlowController::class, 'getWebsiteData']);
        Route::post('helper-data', [SynergizeFlowController::class, 'helperData']);
        Route::post('insert-blog', [SynergizeFlowController::class, 'insertBlog']);
        Route::post('submit-blog-faq', [SynergizeFlowController::class, 'submitBlogFaq']);
    });
