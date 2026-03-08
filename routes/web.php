<?php

declare(strict_types=1);

use AIArmada\Jnt\Http\Controllers\AwbController;
use Illuminate\Support\Facades\Route;

Route::get('jnt/awb/{orderId}', [AwbController::class, 'show'])
    ->name('jnt.awb.show')
    ->middleware('web');
