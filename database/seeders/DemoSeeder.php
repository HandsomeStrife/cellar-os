<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\BuildsDemoData;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Actions\UpsertProductAction;
use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Enums\PriceState;
use Domain\Catalogue\Enums\SellingUnit;
use Domain\Catalogue\Enums\WineSubType;
use Domain\Catalogue\Enums\WineType;
use Domain\Catalogue\Models\Product;
use Domain\Company\Models\Company;
use Domain\Order\Enums\OrderStatus;
use Domain\Supplier\Actions\RecordUnmappedTypeLabelsAction;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Models\ParsedWine;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Models\SupplierParseProfile;
use Domain\Supplier\Models\SupplierUser;
use Domain\User\Enums\Role;
use Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * The demo dataset: everything a person needs to show CellarOS end to end.
 *
 * Two principles, both deliberate:
 *
 * 1. The suppliers are FICTIONAL, the wines are REALISTIC. Wine names,
 *    producers, regions and grapes are sampled from whatever real catalogue
 *    this environment has parsed, so the demo looks like the trade rather than
 *    like lorem ipsum — but they are attached to invented merchants. Nothing in
 *    the demo depends on, or exposes, a real supplier relationship.
 *
 * 2. Demo suppliers are PRIVATE to the demo companies (`created_by_company_id`),
 *    which makes the whole dataset safe to seed anywhere: no other tenant can
 *    see it, and it can't leak into Discover. It also makes MORE of the product
 *    demonstrable, because a buyer may only edit prices, delete wines and
 *    commit parsed lists for their own private suppliers.
 *
 * The data is arranged so that every headline feature has something to show —
 * see the coverage list in demo:reset. Re-running is idempotent; use
 * `php artisan demo:reset` to return to a known-good state.
 */
class DemoSeeder extends Seeder
{
    use BuildsDemoData;

    /** Demo companies, by name — the unit `demo:reset` tears down. */
    public const COMPANIES = ['Cellar Door Group', 'Anand Restaurant Group'];

    /** Fictional merchants. Deliberately not names of real trade suppliers. */
    public const SUPPLIERS = [
        'Northbank Wine Traders',
        'Ashgrove Cellars',
        'Corvina & Company',
        'Halliwell Fine Wine',
        'Saltmarsh Wine Co',
    ];

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $stock = $this->sampleRealWines();

        $this->seedPro($stock);
        $this->seedGroup($stock);
        $this->seedSupplierPortal();
    }

    /**
     * The supplier side of the story: a merchant with portal logins and
     * documents at different points of the analysis lifecycle, plus one whose
     * invite hasn't been accepted — so the portal, the invite flow and admin
     * impersonation can all be shown.
     */
    private function seedSupplierPortal(): void
    {
        $northbank = Supplier::firstWhere('name', 'Northbank Wine Traders');
        $saltmarsh = Supplier::firstWhere('name', 'Saltmarsh Wine Co');

        if ($northbank !== null) {
            $northbank->update([
                'website' => 'https://northbank-wine.example',
                'address' => '18 Wharf Road',
                'postcode' => 'N1 7GR',
                'onboarded_at' => now()->subMonths(4),
            ]);

            $marie = $this->supplierUser($northbank, 'supplier@cellaros.test', 'Marie Fontaine');
            $this->supplierUser($northbank, 'supplier.team@cellaros.test', 'Hugo Marchand');

            $this->portalDocument($northbank, $marie, 'northbank-spring-2026.csv', 'Spring 2026 portfolio', SupplierDocumentStatus::AwaitingAnalysis);
            $this->portalDocument($northbank, $marie, 'northbank-winter-2025.xlsx', 'Winter 2025 price list', SupplierDocumentStatus::Analysed, 'Read 142 wines from a learned column mapping.', 6);
            $this->portalDocument($northbank, $marie, 'northbank-scan.pdf', 'Scanned catalogue', SupplierDocumentStatus::Failed, 'No text layer — this looks like a scan. Send a digital copy and we\'ll read it.', 2);
        }

        // A merchant who has been invited but hasn't set a password yet.
        if ($saltmarsh !== null) {
            $this->supplierUser($saltmarsh, 'newsupplier@cellaros.test', 'Owen Saltmarsh', invited: true);
        }
    }

    private function supplierUser(Supplier $supplier, string $email, string $name, bool $invited = false): SupplierUser
    {
        return SupplierUser::updateOrCreate(
            ['email' => $email],
            [
                'supplier_id' => $supplier->id,
                'name' => $name,
                'password' => $invited ? null : Hash::make('password'),
            ],
        );
    }

    private function portalDocument(
        Supplier $supplier,
        SupplierUser $uploader,
        string $fileName,
        string $title,
        SupplierDocumentStatus $status,
        ?string $notes = null,
        ?int $analysedDaysAgo = null,
    ): void {
        SupplierDocument::updateOrCreate(
            ['supplier_id' => $supplier->id, 'file_name' => $fileName],
            [
                'uploaded_by_supplier_user_id' => $uploader->id,
                'title' => $title,
                'file_type' => str($fileName)->endsWith('.pdf') ? 'application/pdf' : 'text/csv',
                'file_size' => 24_000,
                'storage_path' => 'supplier-documents/demo-'.$fileName,
                'status' => $status->value,
                'analysis_notes' => $notes,
                'analysed_at' => $analysedDaysAgo !== null ? now()->subDays($analysedDaysAgo) : null,
            ],
        );
    }

    // ------------------------------------------------------------------ Pro --

    /**
     * The Pro account: one venue, three merchants, and the wines arranged so
     * that price comparison, POA, styles and re-ordering all have something to
     * show.
     *
     * @param  Collection<int, array<string, mixed>>  $stock
     */
    private function seedPro(Collection $stock): void
    {
        $company = $this->company('Cellar Door Group', Plan::Pro);
        $owner = $this->owner($company, 'demo@cellaros.test', 'Demo Sommelier');
        $venue = $this->venue($company, 'The Cellar Door', 'London');
        $this->assignVenues($owner, [$venue->id]);

        $northbank = $this->supplier($company, 'Northbank Wine Traders', 'Marie Fontaine', 'London');
        $ashgrove = $this->supplier($company, 'Ashgrove Cellars', 'Tom Ashgrove', 'Bristol');
        $corvina = $this->supplier($company, 'Corvina & Company', 'Elena Rossi', 'Leeds');

        foreach ([$northbank, $ashgrove, $corvina] as $supplier) {
            $this->connectSupplier($company, $supplier, [$venue]);
        }

        $wines = $stock->values();

        // A broad list from the main merchant, in a mix of selling units.
        $northbankWines = $this->list($northbank, $wines->slice(0, 14), fn (int $i) => [
            'unit_price' => $this->price(14.00 + $i * 4.25),
            'sold_by' => $i % 4 === 3 ? SellingUnit::Case : SellingUnit::Bottle,
        ]);

        // The SAME four wines from a second merchant, two of them cheaper —
        // this is what the catalogue's cross-supplier comparison shows.
        $shared = $wines->slice(0, 4);
        // Three of the four undercut Northbank, one is dearer — a real
        // comparison shows both, and the saving is the interesting case.
        $this->list($ashgrove, $shared, fn (int $i) => [
            'unit_price' => $this->price((14.00 + $i * 4.25) + ($i === 3 ? 3.10 : -2.40)),
        ]);

        // A specialist with a shorter list, including the wines whose prices
        // are withheld.
        $this->list($corvina, $wines->slice(14, 6), fn (int $i) => [
            'unit_price' => $this->price(28.00 + $i * 9.00),
        ]);

        $this->poaWine($corvina, $wines->get(20));
        $this->sparklingWines($northbank);
        $this->comparisonPair($northbank, $ashgrove);

        // A working cellar, including two lines low enough to raise the
        // dashboard's low-stock alerts.
        $inStock = Product::where('supplier_id', $northbank->id)->orderBy('id')->take(8)->get();
        foreach ([[0, 24, 5], [1, 18, 12], [3, 36, 3], [5, 30, 8], [6, 2, 25], [7, 3, 30]] as [$i, $qty, $days]) {
            if (isset($inStock[$i])) {
                $this->inventory($venue, $inStock[$i], $qty, $days);
            }
        }

        // Orders at every point of the lifecycle. The received one is the
        // natural candidate for "repeat this order".
        if ($inStock->count() >= 8) {
            $this->order($owner, $venue, $northbank, OrderStatus::Draft, 'Autumn list: building the next order.', [[$inStock[2], 12], [$inStock[4], 6]]);
            $this->order($owner, $venue, $northbank, OrderStatus::Sent, 'Cellar restock for the autumn list.', [[$inStock[1], 12], [$inStock[6], 12]]);
            // Placed when prices were ~8% lower, so repeating it demonstrates
            // the change report rather than silently matching.
            $this->order($owner, $venue, $northbank, OrderStatus::Received, 'Last month’s by-the-glass order.', [[$inStock[0], 24], [$inStock[3], 12]], priceDrift: 0.92);
        }

        $this->reviewableDocument($ashgrove, $wines->slice(21, 5));
    }

    // ---------------------------------------------------------------- Group --

    /**
     * The Group account: two venues with their own merchants and stock, plus a
     * team member who can only see one of them.
     *
     * @param  Collection<int, array<string, mixed>>  $stock
     */
    private function seedGroup(Collection $stock): void
    {
        $company = $this->company('Anand Restaurant Group', Plan::Group);
        $owner = $this->owner($company, 'group@cellaros.test', 'Priya Anand');
        $member = $this->teammate($company, 'group.member@cellaros.test', 'Leo Carter', Role::Member);

        $hq = $this->venue($company, 'Group HQ Cellar', 'Manchester');
        $riverside = $this->venue($company, 'Riverside Brasserie', 'Leeds');
        $this->assignVenues($owner, [$hq->id, $riverside->id]);
        $this->assignVenues($member, [$riverside->id]);

        $halliwell = $this->supplier($company, 'Halliwell Fine Wine', 'Grace Halliwell', 'Manchester');
        $saltmarsh = $this->supplier($company, 'Saltmarsh Wine Co', 'Owen Saltmarsh', 'Newcastle');

        // Halliwell serves both venues; Saltmarsh only Riverside — the venue
        // allocation a group actually uses.
        $this->connectSupplier($company, $halliwell, [$hq, $riverside]);
        $this->connectSupplier($company, $saltmarsh, [$riverside]);

        $wines = $stock->values();
        $this->list($halliwell, $wines->slice(4, 10), fn (int $i) => ['unit_price' => $this->price(22.00 + $i * 6.50)]);
        $this->list($saltmarsh, $wines->slice(9, 6), fn (int $i) => ['unit_price' => $this->price(17.50 + $i * 5.00)]);

        $hqStock = Product::where('supplier_id', $halliwell->id)->orderBy('id')->take(6)->get();
        $riverStock = Product::where('supplier_id', $saltmarsh->id)->orderBy('id')->take(4)->get();

        foreach ([[0, 36, 4], [1, 24, 12], [4, 4, 21]] as [$i, $qty, $days]) {
            if (isset($hqStock[$i])) {
                $this->inventory($hq, $hqStock[$i], $qty, $days);
            }
        }
        foreach ([[0, 30, 7], [1, 12, 14]] as [$i, $qty, $days]) {
            if (isset($riverStock[$i])) {
                $this->inventory($riverside, $riverStock[$i], $qty, $days);
            }
        }

        if ($hqStock->count() >= 6) {
            $this->order($owner, $hq, $halliwell, OrderStatus::Received, 'HQ: flagship restock.', [[$hqStock[2], 12]]);
            $this->order($owner, $hq, $halliwell, OrderStatus::Sent, 'HQ: cellar plan for December.', [[$hqStock[3], 12], [$hqStock[0], 6]]);
        }
        if ($riverStock->count() >= 3) {
            $this->order($member, $riverside, $saltmarsh, OrderStatus::Draft, 'Riverside: summer list ideas.', [[$riverStock[2], 12]]);
        }
    }

    // --------------------------------------------------------------- Pieces --

    private function supplier(Company $company, string $name, string $contact, string $city): Supplier
    {
        return Supplier::updateOrCreate(
            ['name' => $name],
            [
                // Private to the demo company: invisible to every other tenant,
                // and editable in the demo (prices, deletions, parsed lists).
                'created_by_company_id' => $company->id,
                'contact' => $contact,
                'email' => str(str($name)->slug())->append('@example.test')->value(),
                'phone' => '+44 20 7946 '.str_pad((string) (1000 + strlen($name)), 4, '0', STR_PAD_LEFT),
                'location' => $city.', United Kingdom',
                'city' => $city,
                'country' => 'United Kingdom',
                'status' => SupplierStatus::Active->value,
            ],
        );
    }

    /**
     * Attach a slice of realistic wine data to a fictional merchant.
     *
     * @param  Collection<int, array<string, mixed>>  $wines
     * @param  callable(int): array<string, mixed>  $overrides  per-position pricing
     */
    private function list(Supplier $supplier, Collection $wines, callable $overrides): Collection
    {
        $upsert = new UpsertProductAction;
        $created = collect();

        foreach ($wines->values() as $i => $wine) {
            $extra = $overrides($i);
            $soldBy = $extra['sold_by'] ?? SellingUnit::Bottle;
            $caseSize = $soldBy === SellingUnit::Case ? 6 : 12;

            $created->push($upsert->execute(contributeFacts: false, data: new ProductData(
                id: null,
                uuid: null,
                supplier_id: $supplier->id,
                raw_upload_id: null,
                wine_name: $wine['wine_name'],
                producer: $wine['producer'],
                country: $wine['country'],
                region: $wine['region'],
                sub_region: $wine['sub_region'],
                grape: $wine['grape'],
                colour: $wine['colour'],
                sub_type: $wine['sub_type'],
                vintage: $wine['vintage'],
                format_ml: 750,
                case_size: $caseSize,
                unit_price: $extra['unit_price'],
                price_per_litre: null,
                stock: 0,
                latitude: $wine['latitude'],
                longitude: $wine['longitude'],
                sold_by: $soldBy,
            )));
        }

        return $created;
    }

    /**
     * One wine, listed by two merchants at a known difference.
     *
     * The sampled wines already produce overlaps, but their NAMES depend on
     * whatever this environment parsed — so the demo guide couldn't tell
     * anyone what to search for. This pair is fixed, which is what makes
     * "search for Fontclaire" a reliable instruction anywhere.
     */
    private function comparisonPair(Supplier $dearer, Supplier $cheaper): void
    {
        $upsert = new UpsertProductAction;

        foreach ([[$dearer, '14.00'], [$cheaper, '11.60']] as [$supplier, $price]) {
            $upsert->execute(contributeFacts: false, data: new ProductData(
                id: null,
                uuid: null,
                supplier_id: $supplier->id,
                raw_upload_id: null,
                wine_name: 'Côtes du Rhône Villages "Les Galets"',
                producer: 'Domaine de la Fontclaire',
                country: 'France',
                region: 'Rhône',
                sub_region: null,
                grape: ['Grenache', 'Syrah'],
                colour: WineType::Red,
                sub_type: null,
                vintage: 2023,
                format_ml: 750,
                case_size: 12,
                unit_price: $price,
                price_per_litre: null,
                stock: 0,
                latitude: null,
                longitude: null,
            ));
        }
    }

    /**
     * A wine the merchant won't print a price for — the POA state, with the
     * kind of wording a real list uses.
     *
     * @param  array<string, mixed>|null  $wine
     */
    private function poaWine(Supplier $supplier, ?array $wine): void
    {
        if ($wine === null) {
            return;
        }

        (new UpsertProductAction)->execute(contributeFacts: false, data: new ProductData(
            id: null,
            uuid: null,
            supplier_id: $supplier->id,
            raw_upload_id: null,
            wine_name: $wine['wine_name'],
            producer: $wine['producer'],
            country: $wine['country'],
            region: $wine['region'],
            sub_region: $wine['sub_region'],
            grape: $wine['grape'],
            colour: $wine['colour'],
            sub_type: $wine['sub_type'],
            vintage: $wine['vintage'],
            format_ml: 750,
            case_size: 6,
            unit_price: null,
            price_per_litre: null,
            stock: 0,
            latitude: $wine['latitude'],
            longitude: $wine['longitude'],
            price_state: PriceState::Poa,
            price_note: 'We receive tiny allocations from this grower, so we rarely hold stock — please ask your rep about availability.',
        ));
    }

    /**
     * Sparkling wines in three styles, so Type and Style have something to
     * separate, and one fortified wine for the same reason.
     */
    private function sparklingWines(Supplier $supplier): void
    {
        $upsert = new UpsertProductAction;

        // Enough of a sparkling range that a real question — "something fizzy
        // and pink for a wedding, under £40" — comes back with a choice.
        $bubbles = [
            ['Blanc de Blancs Brut, Grand Cru', 'Maison Perrelet', WineType::Sparkling, WineSubType::SparklingWhite, '48.00', 'France', 'Champagne'],
            ['Crémant de Loire Rosé Brut', 'Domaine des Ormes', WineType::Sparkling, WineSubType::SparklingRose, '21.50', 'France', 'Loire'],
            ['Rosé Brut Réserve', 'Maison Perrelet', WineType::Sparkling, WineSubType::SparklingRose, '38.00', 'France', 'Champagne'],
            ['Franciacorta Rosé Saten', 'Cantina Bellavista Nuova', WineType::Sparkling, WineSubType::SparklingRose, '32.50', 'Italy', 'Lombardia'],
            ['Prosecco Superiore Extra Dry', 'Colline di Asolo', WineType::Sparkling, WineSubType::SparklingWhite, '15.00', 'Italy', 'Veneto'],
            ['Lambrusco Grasparossa Secco', 'Cantina Vecchia Corte', WineType::Sparkling, WineSubType::SparklingRed, '16.00', 'Italy', 'Emilia-Romagna'],
            ['Pétillant Naturel Blanc', 'Domaine des Ormes', WineType::Sparkling, WineSubType::PetNat, '19.00', 'France', 'Loire'],
            ['Tawny Port 10 Year Old', 'Quinta do Ribeiro', WineType::Fortified, WineSubType::Port, '32.00', 'Portugal', 'Douro'],
            ['Oloroso Seco', 'Bodegas Marisma', WineType::Fortified, WineSubType::Sherry, '24.00', 'Spain', 'Jerez'],
        ];

        foreach ($bubbles as [$name, $producer, $type, $subType, $price, $country, $region]) {
            $upsert->execute(contributeFacts: false, data: new ProductData(
                id: null,
                uuid: null,
                supplier_id: $supplier->id,
                raw_upload_id: null,
                wine_name: $name,
                producer: $producer,
                country: $country,
                region: $region,
                sub_region: null,
                grape: null,
                colour: $type,
                sub_type: $subType,
                vintage: null,
                format_ml: 750,
                case_size: 6,
                unit_price: $price,
                price_per_litre: null,
                stock: 0,
                latitude: null,
                longitude: null,
            ));
        }
    }

    /**
     * A price list waiting to be reviewed: proposed wines, a couple flagged,
     * and a type word the merchant uses that we can't place — so the review
     * screen, the flags and the type-mapping editor all have something in them.
     *
     * @param  Collection<int, array<string, mixed>>  $wines
     */
    private function reviewableDocument(Supplier $supplier, Collection $wines): void
    {
        Storage::disk('local')->put(
            'supplier-documents/demo-ashgrove-list.csv',
            "Wine,Producer,Type,Vintage,Price\nDemo Row,Demo Estate,Skin Contact,2022,18.00\n",
        );

        $document = SupplierDocument::updateOrCreate(
            ['supplier_id' => $supplier->id, 'file_name' => 'ashgrove-spring-list.csv'],
            [
                'uploaded_by_company_id' => $supplier->created_by_company_id,
                'title' => 'Ashgrove spring list',
                'storage_path' => 'supplier-documents/demo-ashgrove-list.csv',
                'file_type' => 'text/csv',
                'file_size' => 512,
                'status' => SupplierDocumentStatus::Analysed->value,
                'analysed_at' => now()->subDays(2),
                'analysis_notes' => 'Read 6 wines from a learned column mapping — re-imports of this supplier are free.',
            ],
        );

        SupplierParseProfile::updateOrCreate(
            ['supplier_id' => $supplier->id, 'mode' => ParseMode::Tabular->value, 'company_id' => $supplier->created_by_company_id],
            [
                'recipe' => ['mapping' => ['wine_name' => 'Wine', 'producer' => 'Producer', 'colour' => 'Type', 'vintage' => 'Vintage', 'unit_price' => 'Price']],
                'confidence' => 0.94,
                'model' => 'claude-haiku-4-5',
                'is_active' => true,
            ],
        );

        ParsedWine::where('supplier_document_id', $document->id)->delete();

        foreach ($wines->values() as $i => $wine) {
            ParsedWine::create([
                'supplier_id' => $supplier->id,
                'supplier_document_id' => $document->id,
                'status' => ParsedWineStatus::Proposed->value,
                'confidence' => 0.9,
                // One row is flagged so the reviewer can see what a flag looks
                // like, and that "approve all" skips it.
                'flag' => $i === 2 ? 'suspicious_price' : null,
                'source_ref' => 'row '.($i + 2),
                'payload' => [
                    'wine_name' => $wine['wine_name'],
                    'producer' => $wine['producer'],
                    'supplier_id' => $supplier->id,
                    'country' => $wine['country'],
                    'region' => $wine['region'],
                    'colour' => $wine['colour']?->value,
                    'vintage' => $wine['vintage'],
                    'format_ml' => 750,
                    'case_size' => 6,
                    'unit_price' => $i === 2 ? '1450.00' : number_format(19.5 + $i * 3, 2, '.', ''),
                    'price_state' => PriceState::Priced->value,
                    'stock' => 0,
                ],
            ]);
        }

        // A word this merchant uses that CellarOS can't place, waiting to be
        // mapped on the review screen.
        (new RecordUnmappedTypeLabelsAction)->execute($supplier->id, ['Skin Contact', 'Vin de Voile']);
    }

    // -------------------------------------------------------------- Sources --

    /**
     * Wine DATA to build the demo from: real names, producers and origins
     * sampled deterministically from whatever this environment has parsed, so
     * the demo reads like the trade. Falls back to a curated list when there is
     * no catalogue yet, so a bare install still demos.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sampleRealWines(): Collection
    {
        $demoSupplierIds = Supplier::whereIn('name', self::SUPPLIERS)->pluck('id');

        $real = Product::query()
            // Only PUBLIC suppliers' catalogues — the trade data this
            // environment has actually parsed. Private suppliers belong to a
            // tenant (including previous demo runs), and sampling those would
            // dress the demo in other demos' leftovers.
            ->whereIn('supplier_id', Supplier::whereNull('created_by_company_id')->select('id'))
            ->whereNotIn('supplier_id', $demoSupplierIds)
            ->whereNull('archived_at')
            ->whereNotNull('producer')
            ->where('wine_name', '!=', '')
            // Prefer richly-described wines so the demo table isn't full of dashes.
            ->whereNotNull('country')
            ->orderByRaw('(region IS NULL OR region = "")')
            ->orderByRaw('(grape IS NULL)')
            ->orderBy('id')
            // Take a wide slice, then thin it below — a catalogue is usually
            // lopsided towards whichever lists were parsed first, and a demo
            // list that is entirely one country doesn't look like a merchant.
            ->limit(600)
            ->get();

        $real = $real
            ->groupBy(fn (Product $p) => $p->country)
            ->map(fn (Collection $forCountry) => $forCountry->take(3))
            ->flatten()
            ->sortBy('country')
            ->values()
            ->take(30);

        if ($real->count() >= 26) {
            return $real->map(fn (Product $p) => [
                'wine_name' => $p->wine_name,
                'producer' => $p->producer,
                'country' => $p->country,
                'region' => $p->region,
                'sub_region' => $p->sub_region,
                'grape' => $p->grape,
                'colour' => $p->colour,
                'sub_type' => $p->sub_type,
                'vintage' => $p->vintage,
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
            ])->values();
        }

        return $this->fallbackWines();
    }

    /**
     * Used only when this environment has no parsed catalogue to draw on.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function fallbackWines(): Collection
    {
        $rows = [
            ['Chablis Premier Cru Montmains', 'Domaine Séguinot', 'France', 'Bourgogne', ['Chardonnay'], WineType::White, 2022],
            ['Sancerre Les Perriers', 'Henri Vaudelle', 'France', 'Loire', ['Sauvignon Blanc'], WineType::White, 2023],
            ['Gevrey-Chambertin Vieilles Vignes', 'Domaine Brossard', 'France', 'Bourgogne', ['Pinot Noir'], WineType::Red, 2020],
            ['Barolo Bussia', 'Cascina Verduno', 'Italy', 'Piemonte', ['Nebbiolo'], WineType::Red, 2018],
            ['Brunello di Montalcino', 'Podere San Filippo', 'Italy', 'Toscana', ['Sangiovese'], WineType::Red, 2018],
            ['Rioja Reserva', 'Bodegas Valcarga', 'Spain', 'Rioja', ['Tempranillo'], WineType::Red, 2017],
            ['Albariño Rías Baixas', 'Adega do Mar', 'Spain', 'Rías Baixas', ['Albariño'], WineType::White, 2023],
            ['Côtes du Rhône Villages', 'Domaine La Ferrande', 'France', 'Rhône', ['Grenache', 'Syrah'], WineType::Red, 2021],
            ['Provence Rosé', 'Château Bellevue', 'France', 'Provence', ['Cinsault'], WineType::Rose, 2023],
            ['Marlborough Sauvignon Blanc', 'Kaituna Ridge', 'New Zealand', 'Marlborough', ['Sauvignon Blanc'], WineType::White, 2023],
            ['Napa Cabernet Sauvignon', 'Redstone Creek', 'United States', 'Napa Valley', ['Cabernet Sauvignon'], WineType::Red, 2019],
            ['Barossa Shiraz', 'Kalgoorlie Estate', 'Australia', 'Barossa', ['Syrah'], WineType::Red, 2020],
            ['Mosel Riesling Kabinett', 'Weingut Steinmann', 'Germany', 'Mosel', ['Riesling'], WineType::White, 2022],
            ['Douro Tinto Reserva', 'Quinta do Ribeiro', 'Portugal', 'Douro', ['Touriga Nacional'], WineType::Red, 2019],
            ['Chianti Classico Riserva', 'Fattoria Le Corti', 'Italy', 'Toscana', ['Sangiovese'], WineType::Red, 2019],
            ['Pouilly-Fumé', 'Domaine Bergerat', 'France', 'Loire', ['Sauvignon Blanc'], WineType::White, 2022],
            ['Meursault Les Tillets', 'Domaine Charpin', 'France', 'Bourgogne', ['Chardonnay'], WineType::White, 2021],
            ['Etna Rosso', 'Tenuta Fiamma', 'Italy', 'Sicilia', ['Nerello Mascalese'], WineType::Red, 2021],
            ['Ribera del Duero Crianza', 'Bodega Peñalba', 'Spain', 'Ribera del Duero', ['Tempranillo'], WineType::Red, 2020],
            ['Grüner Veltliner Federspiel', 'Weingut Donauhof', 'Austria', 'Wachau', ['Grüner Veltliner'], WineType::White, 2022],
            ['Vouvray Demi-Sec', 'Domaine Loiret', 'France', 'Loire', ['Chenin Blanc'], WineType::White, 2021],
            ['Pupillin Ploussard', 'Domaine Renaud Bruyère', 'France', 'Jura', ['Poulsard'], WineType::Red, 2021],
            ['Bandol Rouge', 'Domaine du Cap', 'France', 'Provence', ['Mourvèdre'], WineType::Red, 2019],
            ['Assyrtiko Santorini', 'Ktima Aegeas', 'Greece', 'Santorini', ['Assyrtiko'], WineType::White, 2022],
            ['Malbec Uco Valley', 'Finca Los Andes', 'Argentina', 'Mendoza', ['Malbec'], WineType::Red, 2021],
            ['Stellenbosch Chenin Blanc', 'Kloofzicht Estate', 'South Africa', 'Stellenbosch', ['Chenin Blanc'], WineType::White, 2022],
        ];

        return collect($rows)->map(fn (array $r) => [
            'wine_name' => $r[0],
            'producer' => $r[1],
            'country' => $r[2],
            'region' => $r[3],
            'sub_region' => null,
            'grape' => $r[4],
            'colour' => $r[5],
            'sub_type' => null,
            'vintage' => $r[6],
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    private function price(float $value): string
    {
        return number_format(max(4.5, round($value, 2)), 2, '.', '');
    }

    /**
     * Every demo user, for the reset command to report.
     *
     * @return array<int, string>
     */
    public static function logins(): array
    {
        return User::whereIn('company_id', Company::whereIn('name', self::COMPANIES)->pluck('id'))
            ->orderBy('id')
            ->pluck('email')
            ->all();
    }
}
