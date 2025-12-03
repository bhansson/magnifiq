<?php

use App\Http\Controllers\DownloadPhotoStudioGenerationController;
use App\Http\Controllers\PhotoStudioSourceImageController;
use App\Http\Controllers\ProductController;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::view('/catalog', 'catalog.index')
        ->name('catalog.index');

    Route::view('/products', 'products.index')
        ->name('products.index');

    // Semantic product URL: /products/{catalog-slug}/{sku}/{lang?}
    Route::get('/products/{catalog}/{sku}/{lang?}', [ProductController::class, 'show'])
        ->name('products.show')
        ->where('sku', '[^/]+') // SKU can contain special chars but not slashes
        ->where('lang', '[a-z]{2}'); // Language codes are 2 lowercase letters

    Route::view('/ai-jobs', 'ai-jobs.index')
        ->name('ai-jobs.index');

    Route::view('/ai-templates', 'ai-templates.index')
        ->name('ai-templates.index');

    Route::view('/photo-studio', 'photo-studio.index')
        ->name('photo-studio.index');

    Route::get('/photo-studio/gallery/{generation}/download', DownloadPhotoStudioGenerationController::class)
        ->name('photo-studio.gallery.download');

    Route::get('/photo-studio/generations/{generation}/sources/{index}', PhotoStudioSourceImageController::class)
        ->name('photo-studio.generation.source')
        ->whereNumber('index');
});

// Admin routes (superadmin only)
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'superadmin',
])->group(function () {
    Route::get('/admin/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::get('/admin/partners', function () {
        return view('admin.partners');
    })->name('admin.partners');

    Route::get('/admin/revenue', function () {
        return view('admin.revenue');
    })->name('admin.revenue');

    // Users management
    Route::get('/admin/users', function () {
        return view('admin.users.index');
    })->name('admin.users');

    Route::get('/admin/users/{user}', function (User $user) {
        return view('admin.users.show', ['user' => $user]);
    })->name('admin.users.show');

    // Teams management
    Route::get('/admin/teams', function () {
        return view('admin.teams.index');
    })->name('admin.teams');

    Route::get('/admin/teams/{team}', function (Team $team) {
        return view('admin.teams.show', ['team' => $team]);
    })->name('admin.teams.show');
});
