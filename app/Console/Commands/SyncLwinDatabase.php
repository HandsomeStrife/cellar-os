<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Catalogue\Services\LwinMatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Weekly LWIN sync: download the published database, compare its SHA-256 to
 * the last imported copy, and only on change re-import + re-match (the
 * deterministic passes — free; LLM residue matching stays a manual, dev-side
 * decision since production has no API key). Scheduled weekly in
 * routes/console.php; --force re-imports regardless.
 */
class SyncLwinDatabase extends Command
{
    private const FILE = 'lwin/lwin-database.xlsx';

    private const SHA_FILE = 'lwin/lwin-database.sha256';

    protected $signature = 'wine:lwin-sync {--force : import even if the file is unchanged}';

    protected $description = 'Download the LWIN database and re-import it if it changed.';

    public function handle(LwinMatchService $matcher): int
    {
        $url = (string) config('services.lwin.url');
        $disk = Storage::disk('local');

        $this->line("Downloading {$url}…");

        try {
            $response = Http::timeout(300)->retry(2, 5000)->get($url);
        } catch (\Throwable $e) {
            $this->error('Download failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Download failed: HTTP '.$response->status());

            return self::FAILURE;
        }

        $body = $response->body();
        $sha = hash('sha256', $body);
        $previous = $disk->exists(self::SHA_FILE) ? trim($disk->get(self::SHA_FILE)) : null;

        if ($sha === $previous && ! $this->option('force')) {
            $this->info("LWIN database unchanged (sha256 {$sha}) — nothing to do.");

            return self::SUCCESS;
        }

        $disk->put(self::FILE, $body);

        $this->line($previous === null ? 'First sync — importing.' : 'File changed — importing.');

        $exit = $this->call('wine:lwin-refresh', ['path' => self::FILE]);

        if ($exit !== self::SUCCESS) {
            return self::FAILURE;
        }

        // Only after a successful import does the new hash become the baseline.
        $disk->put(self::SHA_FILE, $sha);

        $this->line('Re-matching (deterministic passes only)…');
        $stats = $matcher->match();
        $this->info(sprintf(
            'New links: %d products, %d facts.',
            $stats['products']['identity'] + $stats['products']['name'],
            $stats['facts']['identity'] + $stats['facts']['name'] + ($stats['facts']['product'] ?? 0),
        ));

        return self::SUCCESS;
    }
}
