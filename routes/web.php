<?php

use App\Http\Controllers\CompareController;
use App\Http\Controllers\FrameAssetController;
use App\Http\Controllers\FrameController;
use App\Http\Controllers\FrameGenerationController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- Public pages -----------------------------------------------------------
Route::get('/', HomeController::class)->name('home');
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
Route::get('/compare', CompareController::class)->name('compare');
Route::get('/privacy', fn () => Inertia::render('privacy'))->name('privacy');
Route::post('/locale/{locale}', LocaleController::class)->name('locale');

// --- Module A: profiles -------------------------------------------------------
Route::post('/guides', [GuideController::class, 'store'])
    ->middleware('throttle:profile-add')
    ->name('guides.store');
Route::get('/guides/{guide}', [GuideController::class, 'show'])->name('guides.show');
Route::get('/guides/{guide}/status', [GuideController::class, 'status'])->name('guides.status');
Route::post('/guides/{guide}/refresh', [GuideController::class, 'refresh'])
    ->middleware('throttle:profile-add')
    ->name('guides.refresh');

// --- Module C: frames ---------------------------------------------------------
Route::get('/frames', [FrameController::class, 'index'])->name('frames.index');
Route::get('/frames/{frame}/asset/{kind}', FrameAssetController::class)
    ->whereIn('kind', ['background', 'overlay', 'thumbnail'])
    ->name('frames.asset');

Route::middleware('auth')->group(function () {
    Route::get('/frames/{frame}/create', [FrameController::class, 'create'])->name('frames.create');

    Route::post('/uploads', [UploadController::class, 'store'])
        ->middleware('throttle:uploads')
        ->name('uploads.store');

    Route::post('/frames/{frame}/generations', [FrameGenerationController::class, 'store'])
        ->middleware('throttle:generations')
        ->name('generations.store');
    Route::delete('/f/{generation}', [FrameGenerationController::class, 'destroy'])->name('generations.destroy');

    Route::get('dashboard', [FrameGenerationController::class, 'mine'])->name('dashboard');
});

// Temporary (signed) access to un-finalized uploads.
Route::get('/uploads/{path}', [UploadController::class, 'show'])
    ->where('path', '[A-Za-z0-9/_\-.]+')
    ->middleware('signed')
    ->name('uploads.show');

// Share page + download counter (public).
Route::get('/f/{generation}', [ShareController::class, 'show'])->name('share.show');
Route::get('/f/{generation}/download', [ShareController::class, 'download'])->name('share.download');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
