<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pancor:check')
    ->dailyAt('23:45')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/system-check.log'));
