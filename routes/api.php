<?php

use App\Http\Controllers\Api\GbpayApiController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GBPay API Routes (Public - Authenticated via username/password)
|--------------------------------------------------------------------------
| These endpoints are called by GBPay / 1Link to inquire, process payments,
| and search PSIDs for ALF Gilgit-Baltistan scheme challans.
*/
Route::post('gb-pay/bill-inquiry', [GbpayApiController::class, 'billInquiry'])->name('api.gbpay.bill-inquiry');
Route::post('gb-pay/bill-payment', [GbpayApiController::class, 'billPayment'])->name('api.gbpay.bill-payment');
Route::post('gb-pay/search-psid', [GbpayApiController::class, 'searchPsid'])->name('api.gbpay.search-psid');

/*
|--------------------------------------------------------------------------
| Meta WhatsApp Business Cloud API Webhook Endpoint
|--------------------------------------------------------------------------
| Handles Meta verification challenge (GET) and delivery/inbound callbacks (POST).
*/
Route::match(['get', 'post'], 'whatsapp/webhook', [WhatsappWebhookController::class, 'handle'])
    ->name('api.whatsapp.webhook');

