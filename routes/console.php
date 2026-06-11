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

// Published supplier lists: re-download everything with a source_url, SHA-gate,
// and re-parse + approve only changed editions (old editions are archived with
// a supersede pointer, never deleted). Pattern-mode documents re-parse for $0;
// LLM-path documents are skipped gracefully where no ANTHROPIC_API_KEY exists.
Schedule::command('wine:refresh-documents --process --approve')->weekly()->mondays()->at('06:00');
