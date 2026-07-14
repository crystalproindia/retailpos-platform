<?php

use App\Http\Controllers\Api\PublicLeadIntakeController;
use Illuminate\Support\Facades\Route;

Route::post('public/leads', PublicLeadIntakeController::class)
    ->middleware(['public.lead.token', 'public.lead.payload', 'throttle:public-leads']);
