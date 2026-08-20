<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Redirect root to Anima Workbench
Route::get('/', function () {
    return redirect('/anima');
});

// Mock Stripe Webhook Endpoint
Route::post('/api/webhooks/stripe', function (Request $request) {
    return response()->json([
        'status' => 'acknowledged',
        'event_id' => $request->input('id', 'evt_' . uniqid()),
        'type' => $request->input('type', 'payment_intent.succeeded'),
        'amount' => $request->input('data.object.amount', 2500),
        'currency' => $request->input('data.object.currency', 'usd'),
        'received_at' => now()->toIso8601String(),
    ]);
})->middleware('anima.capture:stripe,billing');

// Mock GitHub Webhook Endpoint
Route::post('/api/webhooks/github', function (Request $request) {
    return response()->json([
        'status' => 'processed',
        'ref' => $request->input('ref', 'refs/heads/main'),
        'sender' => $request->input('sender.login', 'github-user'),
        'event' => $request->header('X-GitHub-Event', 'push'),
        'received_at' => now()->toIso8601String(),
    ]);
})->middleware('anima.capture:github,vcs');

// Mock Generic Webhook Endpoint
Route::post('/api/webhooks/generic', function (Request $request) {
    return response()->json([
        'success' => true,
        'payload_received' => $request->all(),
        'received_at' => now()->toIso8601String(),
    ]);
})->middleware('anima.capture:generic');
