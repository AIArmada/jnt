<?php

declare(strict_types=1);

use AIArmada\Jnt\Http\Controllers\AwbController;
use Illuminate\Support\Facades\Route;

// AWB download is a signed-URL-gated endpoint. The signed URL cryptographically
// binds the orderId; no session auth is required — possession of a valid
// signed URL is the access token for that specific order.
Route::get('jnt/awb/{orderId}', [AwbController::class, 'show'])
    ->name('jnt.awb.show')
    ->middleware('web');
