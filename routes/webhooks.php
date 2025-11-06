<?php

declare(strict_types=1);

use AIArmada\Jnt\Http\Controllers\WebhookController;
use AIArmada\Jnt\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| J&T Express Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhook notifications from J&T Express
| servers. All requests must pass signature verification via the
| VerifyWebhookSignature middleware before being processed.
|
*/

Route::post(
    config('jnt.webhooks.route', 'webhooks/jnt/status'),
    [WebhookController::class, 'handle']
)
    ->middleware(config('jnt.webhooks.middleware', ['api', VerifyWebhookSignature::class]))
    ->name('jnt.webhooks.status');
