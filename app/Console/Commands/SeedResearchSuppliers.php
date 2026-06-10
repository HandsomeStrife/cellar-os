<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Supplier\Actions\ImportListedSuppliersAction;
use Illuminate\Console\Command;

/**
 * Seeds the suppliers from the trade-research document
 * (docs/research/uk-wine-trade-suppliers.json) as LISTED suppliers, each with
 * an initial CRM note carrying the research intel (list availability, format,
 * update cadence, how to get access) — so admins can work the relationship
 * from day one, portal users or not.
 */
class SeedResearchSuppliers extends Command
{
    protected $signature = 'wine:seed-research {path=docs/research/uk-wine-trade-suppliers.json}';

    protected $description = 'Seed researched trade suppliers as Listed entries with research-intel CRM notes.';

    public function handle(): int
    {
        $path = base_path((string) $this->argument('path'));

        if (! is_file($path)) {
            $this->error("Research file not found: {$path}");

            return self::FAILURE;
        }

        $entries = json_decode((string) file_get_contents($path), true)['entries'] ?? [];

        if ($entries === []) {
            $this->error('No entries in the research file.');

            return self::FAILURE;
        }

        $rows = collect($entries)
            ->filter(fn (array $e) => ($e['verified'] ?? '') !== 'not_found' && trim((string) ($e['name'] ?? '')) !== '')
            ->map(function (array $e) {
                $contact = trim((string) ($e['contact'] ?? ''));

                return [
                    'name' => trim((string) $e['name']),
                    'website' => $e['website'] ?? null,
                    'location' => $e['location'] ?? null,
                    'contact' => $contact !== '' && ! str_contains($contact, '@') ? $contact : null,
                    'email' => str_contains($contact, '@') ? trim(explode(' ', preg_replace('/.*?([^\s;\/]+@[^\s;\/]+).*/', '$1', $contact) ?? '')[0], ' ;') : null,
                    'status' => 'Active',
                    'notes' => [[
                        'note' => $this->researchNote($e),
                        'created_at' => now()->toIso8601String(),
                    ]],
                ];
            })
            ->values()
            ->all();

        $result = (new ImportListedSuppliersAction)->execute($rows);

        $this->info("Seeded/updated {$result['count']} Listed suppliers with research-intel notes.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $e
     */
    private function researchNote(array $e): string
    {
        $lines = array_filter([
            'Research intel ('.now()->format('M Y').'):',
            ($e['tier'] ?? null) ? 'Tier: '.str_replace('_', ' ', (string) $e['tier']).' — '.($e['focus'] ?? '') : null,
            'Trade list: '.match ($e['list_public'] ?? 'unknown') {
                'yes_priced' => 'PUBLIC + PRICED',
                'yes_unpriced' => 'public but unpriced',
                'partial' => 'web catalogue / flipbook only',
                'no' => 'gated (trade account / request)',
                default => 'unknown',
            }.(($e['format'] ?? '') !== '' ? ' — '.$e['format'] : ''),
            ($e['list_url'] ?? '') !== '' ? 'List URL: '.$e['list_url'] : null,
            ($e['cadence'] ?? '') !== '' && $e['cadence'] !== 'unknown' ? 'Updates: '.$e['cadence'] : null,
            ($e['access_route'] ?? '') !== '' ? 'Access: '.$e['access_route'] : null,
            ($e['dated_evidence'] ?? '') !== '' ? 'Evidence: '.$e['dated_evidence'] : null,
            ($e['notes'] ?? '') !== '' ? 'Verification: '.$e['notes'] : null,
        ]);

        return implode("\n", $lines);
    }
}
