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

// Today's numbers, refreshed often enough that an admin trusts them, and cheaply
// enough that they cost nothing: the rollup is a recompute of one day, not a scan
// of all history.
Schedule::command('analytics:rollup --days=1')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Just after midnight, close out yesterday — including the runs that landed in the
// last quarter hour, which the 23:45 pass could not have seen.
Schedule::command('analytics:rollup --days=2')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->runInBackground();
