<?php

use App\Http\Middleware\VerifyDummyWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/stripe', function (Request $request) {
    return response()->json([
        'status' => 'acknowledged',
        'event_id' => $request->input('id'),
        'type' => $request->input('type'),
    ]);
})->middleware([VerifyDummyWebhookSignature::class, 'anima.capture:stripe,billing']);

Route::post('/webhooks/github', function (Request $request) {
    return response()->json([
        'status' => 'processed',
        'ref' => $request->input('ref'),
        'sender' => $request->input('sender.login'),
    ]);
})->middleware([VerifyDummyWebhookSignature::class, 'anima.capture:github,vcs']);
