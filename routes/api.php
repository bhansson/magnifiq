<?php

use App\Http\Controllers\ShopifyWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Shopify Webhooks
|--------------------------------------------------------------------------
|
| These routes handle incoming webhooks from Shopify. They are verified
| using HMAC signatures to ensure authenticity.
|
*/

Route::post('/webhooks/shopify/app-uninstalled', [ShopifyWebhookController::class, 'appUninstalled'])
    ->name('webhooks.shopify.app-uninstalled');
