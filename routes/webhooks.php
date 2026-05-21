<?php

declare(strict_types=1);

use AIArmada\Jnt\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| J&T Express Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhook notifications from J&T Express
| servers. All requests pass signature verification via the configured
| Spatie webhook-client signature validator before being processed.
|
*/

if ((bool) config('jnt.webhooks.enabled', true)) {
    Route::post(
        config('jnt.webhooks.route', 'webhooks/jnt/status'),
        [WebhookController::class, 'handle']
    )
        ->middleware(config('jnt.webhooks.middleware', ['api']))
        ->name('jnt.webhooks.status');
}
