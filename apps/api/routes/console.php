<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every minute, so a post scheduled for 09:00 goes out at 09:00 and not at 09:15.
// `withoutOverlapping` matters because the scheduler and a manual run can coincide.
Schedule::command('blog:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
