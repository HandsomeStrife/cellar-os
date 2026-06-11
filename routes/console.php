<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Liv-ex publishes LWIN updates periodically; the sync is SHA-gated so an
// unchanged file costs one download and nothing else. (Requires the
// scheduler cron — on Forge: the site's Scheduler panel.)
Schedule::command('wine:lwin-sync')->weekly()->mondays()->at('05:00');
