<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Documentation-style user guide. One Livewire page that takes a `{section}`
 * route param, resolves which Blade partial to render, and stamps the title
 * from the sidenav config. The sticky sidenav + doc chrome live in the
 * {@see layouts.guide} layout — NOT the authenticated app shell.
 */
#[Layout('layouts.guide')]
class Guide extends Component
{
    public string $section = 'welcome';

    public function mount(string $section = 'welcome'): void
    {
        $this->section = $section;
    }

    public function render(): View
    {
        $sections = self::sections();
        [$groupKey, $entry] = $this->resolveOrFallback($sections);

        return view('livewire.guide.page', [
            'sections' => $sections,
            'partial' => $entry['partial'],
            'sectionSlug' => $this->section,
            'breadcrumb' => $sections[$groupKey]['title'],
            'title' => $entry['title'],
        ])->title('Guide · '.$entry['title']);
    }

    /**
     * @return array{0:string,1:array{title:string,partial:string}}
     */
    private function resolveOrFallback(array $sections): array
    {
        foreach ($sections as $groupKey => $group) {
            foreach ($group['items'] as $slug => $entry) {
                if ($slug === $this->section) {
                    return [$groupKey, $entry];
                }
            }
        }

        $this->section = 'welcome';

        return ['getting-started', $sections['getting-started']['items']['welcome']];
    }

    /**
     * Single source of truth for the sidenav: URL slug → title + partial.
     * Slug order within each group is the order shown in the sidenav.
     */
    public static function sections(): array
    {
        // Sections documenting a flagged-off area (config/features.php) are
        // dropped so the guide never describes something the user can't reach.
        return array_map(
            fn (array $group) => [
                ...$group,
                'items' => array_filter(
                    $group['items'],
                    fn (array $item) => ($item['flag'] ?? null) === null || config('features.'.$item['flag']),
                ),
            ],
            self::allSections(),
        );
    }

    /**
     * Every guide section, including those currently hidden behind a flag.
     *
     * @return array<string, array{title: string, items: array<string, array{title: string, partial: string, flag?: string}>}>
     */
    private static function allSections(): array
    {
        return [
            'getting-started' => [
                'title' => 'Getting started',
                'items' => [
                    'welcome' => ['title' => 'Welcome', 'partial' => 'guide.sections.welcome'],
                    'demo-logins' => ['title' => 'Demo logins', 'partial' => 'guide.sections.demo-logins'],
                    'accounts' => ['title' => 'Accounts, venues & plans', 'partial' => 'guide.sections.accounts'],
                ],
            ],
            'using' => [
                'title' => 'Using CellarOS',
                'items' => [
                    'dashboard' => ['title' => 'Dashboard', 'partial' => 'guide.sections.dashboard'],
                    'suppliers' => ['title' => 'Suppliers', 'partial' => 'guide.sections.suppliers'],
                    'import' => ['title' => 'Importing price lists', 'partial' => 'guide.sections.import'],
                    'catalogue' => ['title' => 'Catalogue', 'partial' => 'guide.sections.catalogue'],
                    'orders' => ['title' => 'Purchase orders', 'partial' => 'guide.sections.orders'],
                    'inventory' => ['title' => 'Inventory', 'partial' => 'guide.sections.inventory'],
                    'map' => ['title' => 'Sourcing map', 'partial' => 'guide.sections.map', 'flag' => 'map'],
                ],
            ],
            'billing-admin' => [
                'title' => 'Billing & administration',
                'items' => [
                    'billing' => ['title' => 'Plans & billing', 'partial' => 'guide.sections.billing', 'flag' => 'pricing'],
                    'admin' => ['title' => 'Admin back-office', 'partial' => 'guide.sections.admin'],
                ],
            ],
            'reference' => [
                'title' => 'Reference',
                'items' => [
                    'plans' => ['title' => 'Plan & feature matrix', 'partial' => 'guide.sections.plans'],
                ],
            ],
        ];
    }
}
