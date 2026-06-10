<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Pushes the local golden snapshot to a remote CellarOS through its ingestion
 * API — parse locally (LLM key + review UI live here), push the approved
 * results; the remote never needs an LLM key. Batched to the API's limits.
 */
class PushGoldenSnapshot extends Command
{
    private const BATCH = 200;

    protected $signature = 'wine:push-golden {base_url : e.g. https://cellaros.example.com} {--token= : ingestion API token (or CELLAROS_INGEST_TOKEN env)} {--dir=golden}';

    protected $description = 'Push the local golden snapshot to a remote CellarOS ingestion API.';

    public function handle(): int
    {
        $base = rtrim((string) $this->argument('base_url'), '/');
        $token = (string) ($this->option('token') ?: config('services.cellaros.ingest_token', ''));
        $dir = trim((string) $this->option('dir'), '/');

        if ($token === '') {
            $this->error('No token — pass --token= or set CELLAROS_INGEST_TOKEN.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists("{$dir}/manifest.json")) {
            $this->error('No golden snapshot — run wine:export-golden first.');

            return self::FAILURE;
        }

        // Order matters: suppliers before the rows that reference them by name.
        $sections = [
            'suppliers' => 'suppliers.json',
            'wines' => 'wines.json',
            'parse-profiles' => 'parse-profiles.json',
            'facts' => 'wine-facts.json',
        ];

        foreach ($sections as $endpoint => $file) {
            $rows = json_decode($disk->get("{$dir}/{$file}") ?: '[]', true) ?: [];

            if ($rows === []) {
                $this->line("{$endpoint}: nothing to push.");

                continue;
            }

            $pushed = 0;
            foreach (array_chunk($rows, self::BATCH) as $chunk) {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(60)
                    ->retry(2, 500)
                    ->post("{$base}/api/ingest/{$endpoint}", ['rows' => $chunk]);

                if ($response->failed()) {
                    $this->error("{$endpoint}: HTTP {$response->status()} — ".substr($response->body(), 0, 200));

                    return self::FAILURE;
                }

                $pushed += count($chunk);
            }

            $this->info("{$endpoint}: pushed {$pushed} row(s).");
        }

        $status = Http::withToken($token)->acceptJson()->get("{$base}/api/ingest/status");

        if ($status->failed()) {
            $this->warn('Push completed but the status check failed: HTTP '.$status->status());

            return self::SUCCESS; // data is in; only verification failed
        }

        $this->line('Remote now: '.$status->body());

        return self::SUCCESS;
    }
}
