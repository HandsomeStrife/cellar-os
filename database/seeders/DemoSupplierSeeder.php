<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\BuildsDemoData;
use Domain\Catalogue\Actions\ContributeWineFactsAction;
use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Domain\Company\Models\Company;
use Domain\Order\Enums\OrderStatus;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Models\ParsedWine;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Models\SupplierParseProfile;
use Domain\Supplier\Models\SupplierUser;
use Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * FICTIONAL demo content — NEVER run on production (real supplier data lives
 * there; dummy suppliers would pollute Discover). Local dev and E2E only:
 *
 *   php artisan db:seed --class=DemoSupplierSeeder
 *
 * Seeds three fictional shared suppliers with a small catalogue, supplier
 * portal demo accounts/documents, a private supplier with a parsed-document
 * review demo, and demo-company journeys against the fictional data. Runs the
 * clean DatabaseSeeder first, so it is self-sufficient. Idempotent.
 */
class DemoSupplierSeeder extends Seeder
{
    use BuildsDemoData;

    /** Fictional shared suppliers owned by this seeder (excluded from real-journey wiring). */
    public const FICTIONAL_SUPPLIERS = ['Bordeaux Imports', 'Italian Fine Wines', 'New World Selections'];

    /** @var array<string, Supplier> */
    private array $suppliers = [];

    /** @var array<string, Product> */
    private array $products = [];

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $this->seedSuppliers();
        $this->seedCatalogue();
        $this->seedJourneys();
        $this->seedSupplierPortal();

        // Fictional wines contribute facts like any others (dev-only data).
        Product::whereIn('supplier_id', collect($this->suppliers)->pluck('id'))
            ->each(fn (Product $product) => (new ContributeWineFactsAction)->execute($product->getData()));
    }

    private function seedSuppliers(): void
    {
        $details = [
            self::FICTIONAL_SUPPLIERS[0] => ['Camille Laurent', 'Bordeaux, France'],
            self::FICTIONAL_SUPPLIERS[1] => ['Marco Bianchi', 'Milan, Italy'],
            self::FICTIONAL_SUPPLIERS[2] => ['Sarah Mitchell', 'London, United Kingdom'],
        ];

        foreach ($details as $name => [$contact, $location]) {
            $this->suppliers[$name] = Supplier::firstOrCreate(
                ['name' => $name],
                [
                    'contact' => $contact,
                    'email' => str(str($name)->slug())->append('@example.test')->value(),
                    'location' => $location,
                    'status' => SupplierStatus::Active->value,
                ],
            );
        }
    }

    private function seedCatalogue(): void
    {
        // wine_name, producer, country, region, colour, vintage, price, lat, lng, supplier
        $wines = [
            ['Chablis Premier Cru', 'Domaine Laroche', 'France', 'Burgundy', WineColour::White, 2021, '28.50', '47.82', '3.80', 'Bordeaux Imports'],
            ['Sancerre Les Monts', 'Henri Bourgeois', 'France', 'Loire', WineColour::White, 2022, '22.00', '47.33', '2.84', 'Bordeaux Imports'],
            ['Provence Rosé', 'Whispering Angel', 'France', 'Provence', WineColour::Rose, 2023, '19.50', '43.53', '6.45', 'Bordeaux Imports'],
            ['Champagne Brut Réserve', 'Pol Roger', 'France', 'Champagne', WineColour::Sparkling, null, '55.00', '49.04', '4.02', 'Bordeaux Imports'],
            ['Barolo Riserva', 'Giacomo Conterno', 'Italy', 'Piedmont', WineColour::Red, 2017, '92.00', '44.61', '7.94', 'Italian Fine Wines'],
            ['Brunello di Montalcino', 'Biondi-Santi', 'Italy', 'Tuscany', WineColour::Red, 2018, '120.00', '43.06', '11.49', 'Italian Fine Wines'],
            ['Rioja Gran Reserva', 'La Rioja Alta', 'Spain', 'Rioja', WineColour::Red, 2015, '45.00', '42.46', '-2.45', 'New World Selections'],
            ['Vintage Port', 'Taylor Fladgate', 'Portugal', 'Douro', WineColour::Fortified, 2016, '68.00', '41.16', '-7.79', 'New World Selections'],
            ['Napa Cabernet Sauvignon', 'Stag\'s Leap', 'United States', 'Napa Valley', WineColour::Red, 2019, '75.00', '38.50', '-122.27', 'New World Selections'],
            ['Marlborough Sauvignon Blanc', 'Cloudy Bay', 'New Zealand', 'Marlborough', WineColour::White, 2023, '21.00', '-41.51', '173.86', 'New World Selections'],
        ];

        foreach ($wines as $i => [$name, $producer, $country, $region, $colour, $vintage, $price, $lat, $lng, $supplierName]) {
            $this->products[$name] = Product::firstOrCreate(
                ['wine_name' => $name, 'supplier_id' => $this->suppliers[$supplierName]->id],
                [
                    'producer' => $producer,
                    'country' => $country,
                    'region' => $region,
                    'colour' => $colour,
                    'vintage' => $vintage,
                    'unit_price' => $price,
                    'price_per_litre' => number_format((float) $price / 0.75, 2, '.', ''),
                    'format_ml' => 750,
                    'case_size' => 6,
                    'stock' => 12 + ($i * 4), // deterministic 12..48
                    'latitude' => $lat,
                    'longitude' => $lng,
                ],
            );
        }

        // Varietals for the New World listing, so the wine-facts store has
        // something to share with the sparser duplicate listing below.
        $this->products['Marlborough Sauvignon Blanc']->update(['grape' => ['Sauvignon Blanc']]);

        // The SAME wine listed by a second supplier with complementary gaps —
        // showcases wine-facts gap-filling ("another vendor" enrichment).
        Product::firstOrCreate(
            ['wine_name' => 'Marlborough Sauvignon Blanc', 'supplier_id' => $this->suppliers['Bordeaux Imports']->id],
            [
                'producer' => 'Cloudy Bay',
                'country' => 'New Zealand',
                'vintage' => 2023,
                'unit_price' => '20.50',
                'price_per_litre' => number_format(20.50 / 0.75, 2, '.', ''),
                'format_ml' => 750,
                'case_size' => 6,
                'stock' => 18,
            ],
        );
    }

    /** Demo-company journeys against the fictional suppliers (mirrors the original demo). */
    private function seedJourneys(): void
    {
        $starter = Company::firstWhere('name', 'Tasting Room Wines');
        $starterOwner = User::firstWhere('email', 'starter@cellaros.test');
        $starterVenue = $this->venue($starter, 'The Tasting Room', 'Bristol');
        $this->inventory($starterVenue, $this->products['Sancerre Les Monts'], 18, 6);
        $this->inventory($starterVenue, $this->products['Provence Rosé'], 12, 9);
        $this->order($starterOwner, $starterVenue, $this->suppliers['Bordeaux Imports'], OrderStatus::Draft, 'First order: house whites and rosé.', [
            [$this->products['Chablis Premier Cru'], 12], [$this->products['Provence Rosé'], 6],
        ]);
        $this->order($starterOwner, $starterVenue, $this->suppliers['New World Selections'], OrderStatus::Sent, 'New World reds for the by-the-glass list.', [
            [$this->products['Rioja Gran Reserva'], 6],
        ]);
        $this->connectSupplier($starter, $this->suppliers['Bordeaux Imports'], [$starterVenue]);
        $this->connectSupplier($starter, $this->suppliers['New World Selections'], [$starterVenue]);

        $pro = Company::firstWhere('name', 'Cellar Door Group');
        $proOwner = User::firstWhere('email', 'demo@cellaros.test');
        $proVenue = $this->venue($pro, 'The Cellar Door', 'London');
        foreach (['Chablis Premier Cru' => [24, 5], 'Barolo Riserva' => [18, 12], 'Rioja Gran Reserva' => [30, 3], 'Champagne Brut Réserve' => [12, 20]] as $wine => [$qty, $daysAgo]) {
            $this->inventory($proVenue, $this->products[$wine], $qty, $daysAgo);
        }
        $this->order($proOwner, $proVenue, $this->suppliers['Italian Fine Wines'], OrderStatus::Draft, 'Restock Italian reds for the autumn list.', [
            [$this->products['Barolo Riserva'], 6], [$this->products['Brunello di Montalcino'], 6],
        ]);
        $this->order($proOwner, $proVenue, $this->suppliers['Bordeaux Imports'], OrderStatus::Sent, 'Champagne for the festive season.', [
            [$this->products['Champagne Brut Réserve'], 24],
        ]);
        $this->order($proOwner, $proVenue, $this->suppliers['New World Selections'], OrderStatus::Received, 'Received: New World mixed case.', [
            [$this->products['Napa Cabernet Sauvignon'], 6], [$this->products['Marlborough Sauvignon Blanc'], 12],
        ]);
        $this->connectSupplier($pro, $this->suppliers['Italian Fine Wines'], [$proVenue]);
        $this->connectSupplier($pro, $this->suppliers['Bordeaux Imports'], [$proVenue]);
        $this->connectSupplier($pro, $this->suppliers['New World Selections'], [$proVenue]);

        // A private (buyer-added) supplier, to showcase the tier + review demo.
        $private = Supplier::firstOrCreate(
            ['name' => 'Borough Wine Co', 'created_by_company_id' => $pro->id],
            ['contact' => 'Jonah Reed', 'location' => 'London, United Kingdom', 'status' => SupplierStatus::Active->value],
        );
        DB::table('company_supplier')->insertOrIgnore([
            'company_id' => $pro->id, 'supplier_id' => $private->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->seedParsedDocument($pro, $proOwner, $private);

        $group = Company::firstWhere('name', 'Anand Restaurant Group');
        $groupOwner = User::firstWhere('email', 'group@cellaros.test');
        $member = User::firstWhere('email', 'group.member@cellaros.test');
        $hq = $this->venue($group, 'Group HQ Cellar', 'Manchester');
        $riverside = $this->venue($group, 'Riverside Brasserie', 'Leeds');
        $this->inventory($hq, $this->products['Brunello di Montalcino'], 36, 4);
        $this->inventory($hq, $this->products['Vintage Port'], 24, 12);
        $this->inventory($riverside, $this->products['Marlborough Sauvignon Blanc'], 48, 3);
        $this->inventory($riverside, $this->products['Provence Rosé'], 30, 7);
        $this->order($groupOwner, $hq, $this->suppliers['Italian Fine Wines'], OrderStatus::Received, 'HQ: Tuscan flagship restock.', [
            [$this->products['Brunello di Montalcino'], 12],
        ]);
        $this->order($member, $riverside, $this->suppliers['New World Selections'], OrderStatus::Draft, 'Riverside: summer whites.', [
            [$this->products['Marlborough Sauvignon Blanc'], 24],
        ]);
        $this->connectSupplier($group, $this->suppliers['Italian Fine Wines'], [$hq]);
        $this->connectSupplier($group, $this->suppliers['New World Selections'], [$riverside]);
    }

    /**
     * A buyer-uploaded, already-analysed document for a private supplier with a
     * few proposed wines awaiting review — showcases parse → review → approve
     * without a live LLM call.
     */
    private function seedParsedDocument(Company $company, User $owner, Supplier $supplier): void
    {
        $wines = [
            ['Borough Reserve Claret', 'France', 'Bordeaux', WineColour::Red, 2020, '18.50', ['Cabernet Sauvignon', 'Merlot']],
            ['Borough White Burgundy', 'France', 'Bourgogne', WineColour::White, 2022, '22.00', ['Chardonnay']],
            ['Borough Provence Rosé', 'France', 'Provence', WineColour::Rose, 2023, '14.75', ['Grenache']],
        ];

        $csv = "Wine,Vintage,Price,Country,Colour\n".implode("\n", array_map(
            fn (array $w) => "{$w[0]},{$w[4]},{$w[5]},{$w[1]},{$w[3]->value}",
            $wines,
        ))."\n";
        Storage::disk('local')->put('supplier-documents/demo-borough-spring-2026.csv', $csv);

        $document = SupplierDocument::firstOrCreate(
            ['supplier_id' => $supplier->id, 'file_name' => 'borough-spring-2026.csv'],
            [
                'uploaded_by_company_id' => $company->id,
                'uploaded_by_user_id' => $owner->id,
                'title' => 'Borough spring 2026 list',
                'file_type' => 'text/csv',
                'file_size' => strlen($csv),
                'storage_path' => 'supplier-documents/demo-borough-spring-2026.csv',
                'status' => SupplierDocumentStatus::Analysed->value,
                'analysis_notes' => 'Parsed 3 wine(s). Reused saved mapping.',
                'analysed_at' => now()->subDay(),
            ],
        );

        SupplierParseProfile::firstOrCreate(
            ['supplier_id' => $supplier->id, 'mode' => ParseMode::Tabular->value, 'company_id' => $company->id],
            [
                'recipe' => ['mapping' => ['wine_name' => 'Wine', 'vintage' => 'Vintage', 'unit_price' => 'Price', 'country' => 'Country', 'colour' => 'Colour']],
                'model' => 'claude-opus-4-8',
                'confidence' => 0.95,
                'source_document_id' => $document->id,
                'is_active' => true,
            ],
        );

        foreach ($wines as $i => $w) {
            [$name, $country, $region, $colour, $vintage, $price, $grape] = $w;
            $payload = (new ProductData(
                id: null, uuid: null, supplier_id: $supplier->id, raw_upload_id: null,
                wine_name: $name, producer: 'Borough Wine Co', country: $country, region: $region, sub_region: null,
                grape: $grape, colour: $colour, vintage: $vintage,
                format_ml: 750, case_size: 6, unit_price: $price,
                price_per_litre: number_format((float) $price / 0.75, 2, '.', ''), stock: 0,
                latitude: null, longitude: null,
            ))->toArray();

            ParsedWine::firstOrCreate(
                ['supplier_document_id' => $document->id, 'supplier_id' => $supplier->id, 'source_ref' => 'row '.($i + 2)],
                ['payload' => $payload, 'status' => ParsedWineStatus::Proposed->value, 'confidence' => 0.95],
            );
        }
    }

    /**
     * Supplier portal demo: three fictional suppliers at different points in
     * their journey. All passwords are `password`.
     */
    private function seedSupplierPortal(): void
    {
        $bordeaux = $this->suppliers['Bordeaux Imports'];
        $bordeaux->update([
            'phone' => '+33 5 56 00 00 00',
            'website' => 'https://bordeaux-imports.example',
            'address' => '12 Quai des Chartrons',
            'city' => 'Bordeaux',
            'postcode' => '33000',
            'country' => 'France',
            'onboarded_at' => now(),
        ]);
        $camille = $this->supplierUser($bordeaux, 'supplier@cellaros.test', 'Camille Laurent');
        $this->supplierUser($bordeaux, 'supplier.team@cellaros.test', 'Hugo Marchand');
        $this->supplierDocument($bordeaux, $camille, 'spring-2026-portfolio.csv', 'Spring 2026 portfolio', SupplierDocumentStatus::AwaitingAnalysis);
        $this->supplierDocument($bordeaux, $camille, 'winter-2025-list.xlsx', 'Winter 2025 price list', SupplierDocumentStatus::Analysed, 'Extracted 142 wines.', 6);

        $italian = $this->suppliers['Italian Fine Wines'];
        $italian->update([
            'phone' => '+39 02 1234 5678',
            'website' => 'https://italian-fine-wines.example',
            'address' => 'Via Montenapoleone 8',
            'city' => 'Milan',
            'postcode' => '20121',
            'country' => 'Italy',
        ]);
        $marco = $this->supplierUser($italian, 'italian-supplier@cellaros.test', 'Marco Bianchi');
        $this->supplierDocument($italian, $marco, 'piedmont-2026.csv', 'Piedmont 2026 allocation', SupplierDocumentStatus::Analysing);
        $this->supplierDocument($italian, $marco, 'scanned-catalogue.pdf', 'Scanned catalogue', SupplierDocumentStatus::Failed, 'Could not read a usable table from the file.', 2);

        $newWorld = $this->suppliers['New World Selections'];
        $newWorld->update([
            'website' => 'https://new-world-selections.example',
            'city' => 'London',
            'country' => 'United Kingdom',
        ]);
        $this->supplierUser($newWorld, 'newworld-supplier@cellaros.test', 'Sarah Mitchell', invited: true);
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

    private function supplierDocument(
        Supplier $supplier,
        SupplierUser $uploader,
        string $fileName,
        string $title,
        SupplierDocumentStatus $status,
        ?string $notes = null,
        ?int $analysedDaysAgo = null,
    ): void {
        SupplierDocument::firstOrCreate(
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
}
