<?php

namespace Database\Seeders;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\ForecastRunStatus;
use App\Enums\ForecastType;
use App\Enums\OpportunityStage;
use App\Enums\OpportunityStatus;
use App\Enums\PricingType;
use App\Enums\ProductType;
use App\Enums\Role as RoleEnum;
use App\Enums\TransactionStatus;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Company;
use App\Models\ForecastRecommendation;
use App\Models\ForecastResult;
use App\Models\ForecastRun;
use App\Models\ForecastScenario;
use App\Models\ForecastSimulation;
use App\Models\MasterPrincipal;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityLog;
use App\Models\OpportunityStageHistory;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSpecification;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\User;
use App\Services\Forecast\CombinedForecastService;
use App\Services\Forecast\HistoricalForecastService;
use App\Services\Forecast\MonteCarloService;
use App\Services\Forecast\OpportunityForecastService;
use App\Services\Forecast\RecommendationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Activity::truncate();
        OpportunityLog::truncate();
        OpportunityStageHistory::truncate();
        ForecastRecommendation::truncate();
        OpportunityItem::truncate();
        SalesTransactionItem::truncate();
        SalesTransaction::truncate();
        Opportunity::truncate();
        ForecastScenario::truncate();
        ForecastSimulation::truncate();
        ForecastResult::truncate();
        ForecastRun::truncate();
        PriceList::truncate();
        ProductSpecification::truncate();
        Product::truncate();
        ProductCategory::truncate();
        MasterPrincipal::truncate();
        Company::truncate();
        User::truncate();
        Role::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command?->info('Seeding Roles...');
        $roles = $this->seedRoles();

        $this->command?->info('Seeding Users (Admin, Manager, Management, and 10 Sales/AM from spreadsheet)...');
        $users = $this->seedUsers($roles);

        $this->command?->info('Seeding Master Principals, Categories, Products & Pricelists from spreadsheet reference...');
        [$principals, $products] = $this->seedPrincipalsAndProducts();

        $this->command?->info('Importing 154 Historical Deals & Opportunities (2020 - 2025) from sales_pipeline.xlsx...');
        $this->seedManualSalesPipeline($users, $principals, $products);

        $this->command?->info('Seeding Active Pipeline Opportunities for each of 10 Sales Reps (2025 - 2026)...');
        $this->seedActivePipelineForSales($users, $principals, $products);

        $this->command?->info('Running Monte Carlo Forecast Simulations & Generating Prescriptive Recommendations...');
        $this->seedForecastRuns($users['manager']);

        $this->command?->info('Successfully seeded comprehensive ADI SalesTrack database aligned with spreadsheet reference!');
    }

    private function seedRoles(): array
    {
        $roles = [];
        foreach (RoleEnum::cases() as $role) {
            $roles[$role->value] = Role::create([
                'name' => $role->value,
                'description' => $role->label(),
            ]);
        }

        return $roles;
    }

    private function seedUsers(array $roles): array
    {
        $password = Hash::make('password');

        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@salestrack.app',
            'password' => $password,
            'role_id' => $roles[RoleEnum::ADMIN->value]->id,
            'status' => UserStatus::ACTIVE->value,
        ]);

        $manager = User::create([
            'name' => 'Bambang Sudiro',
            'email' => 'manager@salestrack.app',
            'password' => $password,
            'role_id' => $roles[RoleEnum::SALES_MANAGER->value]->id,
            'status' => UserStatus::ACTIVE->value,
        ]);

        $management = User::create([
            'name' => 'Direksi Management',
            'email' => 'management@salestrack.app',
            'password' => $password,
            'role_id' => $roles[RoleEnum::MANAGEMENT->value]->id,
            'status' => UserStatus::ACTIVE->value,
        ]);

        $salesRepsData = json_decode(file_get_contents(database_path('data/sales_assignments.json')), true) ?: [];
        $sales = [];

        foreach ($salesRepsData as $rep) {
            $user = User::create([
                'name' => $rep['name'],
                'email' => $rep['email'],
                'password' => $password,
                'role_id' => $roles[RoleEnum::SALES->value]->id,
                'status' => UserStatus::ACTIVE->value,
            ]);
            $sales[$rep['name']] = $user;
        }

        return [
            'admin' => $admin,
            'manager' => $manager,
            'management' => $management,
            'sales' => $sales,
        ];
    }

    private function seedPrincipalsAndProducts(): array
    {
        $pricelistData = json_decode(file_get_contents(database_path('data/pricelist_license.json')), true) ?: [];
        $categoriesData = [
            'Data Analytics' => 'Solusi visualisasi data, BI, analitik tingkat lanjut dan dashboarding.',
            'Data Integration' => 'Solusi integrasi data ETL/ELT dan data fabric.',
            'Data Virtualization' => 'Solusi logical data warehouse dan data abstraction layer.',
            'Cloud Computing' => 'Layanan infrastruktur compute cloud elastis.',
            'Cloud Storage' => 'Layanan penyimpanan cloud object, block, dan archive.',
            'Cloud Database' => 'Layanan managed cloud database relational & NoSQL.',
            'Server Infrastructure' => 'Pengadaan server fisik rack & blade enterprise.',
            'Networking' => 'Solusi konektivitas jaringan, SD-WAN dan load balancing.',
            'AI & Machine Learning' => 'Platform kolaboratif enterprise data science & AI.',
            'Data Management' => 'Platform manajemen data terdistribusi dan in-memory caching.',
        ];

        $categories = [];
        foreach ($categoriesData as $name => $desc) {
            $categories[$name] = ProductCategory::create([
                'name' => $name,
                'description' => $desc,
            ]);
        }

        $principals = [];
        $products = [];

        foreach ($pricelistData as $pName => $pInfo) {
            $principal = MasterPrincipal::create([
                'name' => $pName,
                'description' => "Principal resmi {$pName} untuk pasar enterprise Indonesia.",
            ]);
            $principals[$pName] = $principal;

            $catEnum = match ($pInfo['category']) {
                'HARDWARE' => ProductType::HARDWARE,
                'CLOUD' => ProductType::CLOUD,
                default => ProductType::LICENSE,
            };

            $productCategory = match ($pInfo['category']) {
                'HARDWARE' => $categories['Server Infrastructure'],
                'CLOUD' => $categories['Cloud Computing'],
                default => $categories['Data Analytics'],
            };

            // 1. Primary License/Hardware/Cloud Product
            $primaryName = match ($pInfo['category']) {
                'HARDWARE' => "{$pName} Enterprise Server Infrastructure",
                'CLOUD' => "{$pName} Enterprise Cloud Platform",
                default => "{$pName} Enterprise Software License",
            };

            $primaryProduct = Product::create([
                'principal_id' => $principal->id,
                'category_id' => $productCategory->id,
                'name' => $primaryName,
                'sku' => strtoupper(substr(str_replace(' ', '', $pName), 0, 4)) . '-MAIN',
                'product_type' => $catEnum->value,
                'type' => $catEnum->value,
                'price' => $pInfo['license_price'],
                'description' => "Paket utama solusi {$pName} ({$catEnum->value}).",
            ]);

            PriceList::create([
                'product_id' => $primaryProduct->id,
                'pricing_type' => $catEnum === ProductType::CLOUD ? PricingType::SUBSCRIPTION->value : PricingType::LICENSE->value,
                'unit_price' => $pInfo['license_price'],
                'currency' => 'IDR',
                'billing_period' => $catEnum === ProductType::CLOUD ? 'MONTHLY' : 'ONE_TIME',
                'effective_date' => '2020-01-01',
                'expiry_date' => '2030-12-31',
                'is_active' => true,
            ]);

            // 2. Service Product
            $serviceProduct = Product::create([
                'principal_id' => $principal->id,
                'category_id' => $categories['Data Management']->id,
                'name' => "{$pName} Professional Implementation & Support Service",
                'sku' => strtoupper(substr(str_replace(' ', '', $pName), 0, 4)) . '-SVC',
                'product_type' => ProductType::SERVICE->value,
                'type' => ProductType::SERVICE->value,
                'price' => $pInfo['service_price'],
                'description' => "Jasa implementasi profesional, arsitektur, dan support {$pName}.",
            ]);

            PriceList::create([
                'product_id' => $serviceProduct->id,
                'pricing_type' => PricingType::SERVICE->value,
                'unit_price' => $pInfo['service_price'],
                'currency' => 'IDR',
                'billing_period' => 'ONE_TIME',
                'effective_date' => '2020-01-01',
                'expiry_date' => '2030-12-31',
                'is_active' => true,
            ]);

            $products[$pName] = [
                'main' => $primaryProduct,
                'service' => $serviceProduct,
            ];
        }

        // 3. Hardware & Cloud Detailed Specs from server_cloud_specs.json
        $specsData = json_decode(file_get_contents(database_path('data/server_cloud_specs.json')), true) ?: [];
        foreach ($specsData as $spec) {
            $pName = $spec['principal'];
            if (! isset($principals[$pName]) || ! isset($products[$pName])) {
                continue;
            }

            ProductSpecification::create([
                'product_id' => $products[$pName]['main']->id,
                'specification_name' => $spec['component'] . ' - ' . $spec['specification'],
                'specification_value' => 'Rp ' . number_format($spec['price'], 0, ',', '.'),
            ]);
        }

        return [$principals, $products];
    }

    private function seedManualSalesPipeline(array $users, array $principals, array $products): void
    {
        $pipelineData = json_decode(file_get_contents(database_path('data/sales_pipeline.json')), true) ?: [];
        $pricelist = json_decode(file_get_contents(database_path('data/pricelist_license.json')), true) ?: [];

        $wonCount = 0;

        foreach ($pipelineData as $idx => $record) {
            $amName = $record['am'];
            $user = $users['sales'][$amName] ?? reset($users['sales']);

            $clientName = trim($record['client']);
            $company = Company::firstOrCreate(
                ['name' => $clientName],
                [
                    'industry' => 'Enterprise & Commercial',
                ]
            );

            $pName = $record['principal'];
            $principal = $principals[$pName] ?? null;
            $pInfo = $pricelist[$pName] ?? ['license_price' => 200000000, 'service_price' => 75000000, 'category' => 'LICENSE'];
            $productPair = $products[$pName] ?? null;

            $scope = $record['deal_scope'];
            $licPrice = (float) $pInfo['license_price'];
            $svcPrice = (float) $pInfo['service_price'];

            $estimatedLic = 0.0;
            $estimatedSvc = 0.0;
            $itemsToCreate = [];

            if ($scope === 'License + Service' || $scope === 'Hardware + Service') {
                $estimatedLic = $licPrice;
                $estimatedSvc = $svcPrice;
                if ($productPair) {
                    $itemsToCreate[] = ['product' => $productPair['main'], 'price' => $licPrice];
                    $itemsToCreate[] = ['product' => $productPair['service'], 'price' => $svcPrice];
                }
            } elseif ($scope === 'License' || $scope === 'Hardware') {
                $estimatedLic = $licPrice;
                if ($productPair) {
                    $itemsToCreate[] = ['product' => $productPair['main'], 'price' => $licPrice];
                }
            } elseif ($scope === 'Service') {
                $estimatedSvc = $svcPrice;
                if ($productPair) {
                    $itemsToCreate[] = ['product' => $productPair['service'], 'price' => $svcPrice];
                }
            } else {
                $estimatedLic = $licPrice;
                if ($productPair) {
                    $itemsToCreate[] = ['product' => $productPair['main'], 'price' => $licPrice];
                }
            }

            $totalValue = $estimatedLic + $estimatedSvc;

            $closingDate = Carbon::parse($record['closing_date']);
            $leadDate = $closingDate->copy()->subDays(random_int(30, 90));

            $progress = $record['progress'];
            $isWon = ($progress === 'Closed Won' || $progress === 'PO Released');
            $isLost = ($progress === 'Closed Lost');

            $stage = OpportunityStage::CLOSING->value;
            $status = OpportunityStatus::WON->value;
            $prob = 100;

            if ($isWon) {
                $status = OpportunityStatus::WON->value;
                $stage = OpportunityStage::CLOSING->value;
                $prob = 100;
            } elseif ($isLost) {
                $status = OpportunityStatus::LOST->value;
                $stage = OpportunityStage::PROPOSAL->value;
                $prob = 0;
            } else {
                $status = OpportunityStatus::OPEN->value;
                if ($progress === 'Introduction') {
                    $stage = OpportunityStage::LEAD->value;
                    $prob = 10;
                } elseif ($progress === 'Qualification') {
                    $stage = OpportunityStage::QUALIFICATION->value;
                    $prob = 25;
                } elseif ($progress === 'Proposal') {
                    $stage = OpportunityStage::PROPOSAL->value;
                    $prob = 40;
                } elseif ($progress === 'Negotiation') {
                    $stage = OpportunityStage::NEGOTIATION->value;
                    $prob = 60;
                } elseif ($progress === 'Closing') {
                    $stage = OpportunityStage::CLOSING->value;
                    $prob = 90;
                }
            }

            $oppCode = 'OPP-' . $closingDate->format('Y') . '-' . str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT);

            $opp = Opportunity::create([
                'opportunity_code' => $oppCode,
                'company_id' => $company->id,
                'user_id' => $user->id,
                'principal_id' => $principal?->id,
                'title' => $record['project_name'] . ' - ' . $clientName,
                'project_name' => $record['project_name'],
                'client_name' => $clientName,
                'description' => "Proyek solusi {$pName} untuk mendukung kebutuhan operasional dan bisnis {$clientName}.",
                'remarks' => $record['remarks'] ?: ($isWon ? 'Deal closed and contract signed.' : null),
                'value' => $totalValue,
                'estimated_value' => $totalValue,
                'currency' => 'IDR',
                'license_type' => $record['license_type'],
                'progress' => $progress,
                'deal_scope' => $scope,
                'estimated_license_value' => $estimatedLic,
                'estimated_service_value' => $estimatedSvc,
                'estimated_deal_value' => $totalValue,
                'stage' => $stage,
                'progress_percentage' => $prob,
                'status' => $status,
                'closing_date' => $closingDate->format('Y-m-d'),
                'actual_closing_date' => ($isWon || $isLost) ? $closingDate->format('Y-m-d') : null,
                'probability' => $prob,
                'sa_pic' => $record['sa_pic'],
                'deal_registration' => $record['deal_reg'],
                'deal_registration_status' => $record['deal_reg'] === 'Registered' ? 'APPROVED' : ($record['deal_reg'] === 'On Progress' ? 'SUBMITTED' : 'NOT_REQUIRED'),
                'created_at' => $leadDate,
                'updated_at' => $closingDate,
            ]);

            // Opportunity Items
            foreach ($itemsToCreate as $itemData) {
                OpportunityItem::create([
                    'opportunity_id' => $opp->id,
                    'product_id' => $itemData['product']->id,
                    'quantity' => 1,
                    'unit_price' => $itemData['price'],
                    'discount' => 0,
                    'subtotal' => $itemData['price'],
                ]);
            }

            // Stage logs & stage histories
            $this->createOpportunityLogs($opp, $user, $leadDate, $closingDate, $isWon ? true : ($isLost ? false : null), $stage);

            // Activities
            $this->createOpportunityActivities($opp, $user, $leadDate, $closingDate, $isWon ? true : ($isLost ? false : null), $record['sa_pic']);

            // Sales Transaction if WON
            if ($isWon) {
                $wonCount++;

                $trxCode = 'TRX-' . $closingDate->format('Y') . '-' . str_pad((string) $wonCount, 4, '0', STR_PAD_LEFT);
                $trx = SalesTransaction::create([
                    'transaction_number' => $trxCode,
                    'opportunity_id' => $opp->id,
                    'company_id' => $company->id,
                    'principal_id' => $principal?->id,
                    'user_id' => $user->id,
                    'total_amount' => $totalValue,
                    'currency' => 'IDR',
                    'transaction_date' => $closingDate->format('Y-m-d'),
                    'status' => TransactionStatus::PAID->value,
                    'created_at' => $closingDate,
                    'updated_at' => $closingDate,
                ]);

                foreach ($itemsToCreate as $itemData) {
                    SalesTransactionItem::create([
                        'sales_transaction_id' => $trx->id,
                        'product_id' => $itemData['product']->id,
                        'quantity' => 1,
                        'unit_price' => $itemData['price'],
                        'subtotal' => $itemData['price'],
                    ]);
                }
            }
        }
    }

    private function seedActivePipelineForSales(array $users, array $principals, array $products): void
    {
        $pricelist = json_decode(file_get_contents(database_path('data/pricelist_license.json')), true) ?: [];

        // Realistic active pipeline pipeline deals for Q1 2026 across all 10 sales reps
        $pipelineTemplates = [
            'Azelia Nurul Fitria' => [
                ['client' => 'PT Bank Mandiri (Persero) Tbk', 'p' => 'Tableau', 'proj' => 'Executive BI Modernization 2026', 'stage' => OpportunityStage::PROPOSAL->value, 'prob' => 40, 'date' => '2026-02-15', 'scope' => 'License + Service'],
                ['client' => 'PT Astra International Tbk', 'p' => 'Qlik', 'proj' => 'Enterprise Supply Chain Analytics', 'stage' => OpportunityStage::NEGOTIATION->value, 'prob' => 60, 'date' => '2026-03-10', 'scope' => 'License + Service'],
                ['client' => 'PT Telkom Indonesia Tbk', 'p' => 'Dataiku', 'proj' => 'AI Churn & Customer Retention Engine', 'stage' => OpportunityStage::CLOSING->value, 'prob' => 90, 'date' => '2026-01-28', 'scope' => 'License + Service'],
                ['client' => 'PT Bank Central Asia Tbk', 'p' => 'Cloudera', 'proj' => 'Big Data Lakehouse Expansion', 'stage' => OpportunityStage::QUALIFICATION->value, 'prob' => 25, 'date' => '2026-02-20', 'scope' => 'License'],
            ],
            'Rizky Pratama' => [
                ['client' => 'PT Bank Rakyat Indonesia (Persero) Tbk', 'p' => 'Snowflake', 'proj' => 'Cloud Financial Data Warehouse', 'stage' => OpportunityStage::NEGOTIATION->value, 'prob' => 60, 'date' => '2026-01-25', 'scope' => 'License + Service'],
                ['client' => 'PT GoTo Gojek Tokopedia Tbk', 'p' => 'ClickHouse', 'proj' => 'High-Concurrency Real-Time Analytics DB', 'stage' => OpportunityStage::PROPOSAL->value, 'prob' => 40, 'date' => '2026-02-18', 'scope' => 'License + Service'],
                ['client' => 'PT Bank Tabungan Negara (Persero) Tbk', 'p' => 'ClickHouse', 'proj' => 'Core Loan Realtime Reporting Store', 'stage' => OpportunityStage::QUALIFICATION->value, 'prob' => 25, 'date' => '2026-03-15', 'scope' => 'License'],
            ],
            'Dewi Lestari' => [
                ['client' => 'PT Bank Syariah Indonesia Tbk', 'p' => 'AWS', 'proj' => 'Cloud-Native Digital Banking Infrastructure', 'stage' => OpportunityStage::CLOSING->value, 'prob' => 90, 'date' => '2026-02-10', 'scope' => 'License + Service'],
                ['client' => 'PT Traveloka Indonesia', 'p' => 'Hasura', 'proj' => 'Unified GraphQL API Layer Modernization', 'stage' => OpportunityStage::NEGOTIATION->value, 'prob' => 60, 'date' => '2026-01-30', 'scope' => 'License + Service'],
                ['client' => 'PT Bukalapak.com Tbk', 'p' => 'Hasura', 'proj' => 'High-Throughput GraphQL Services Gateway', 'stage' => OpportunityStage::PROPOSAL->value, 'prob' => 40, 'date' => '2026-03-05', 'scope' => 'Service'],
            ],
            'Ahmad Fauzan' => [
                ['client' => 'PT Perusahaan Gas Negara Tbk', 'p' => 'Dell', 'proj' => 'Next-Gen Hyperconverged Datacenter Farm', 'stage' => OpportunityStage::PROPOSAL->value, 'prob' => 40, 'date' => '2026-02-25', 'scope' => 'Hardware + Service'],
                ['client' => 'PT Bank Danamon Indonesia Tbk', 'p' => 'Redis', 'proj' => 'Sub-Millisecond Banking Cache Cluster', 'stage' => OpportunityStage::CLOSING->value, 'prob' => 90, 'date' => '2026-01-20', 'scope' => 'License + Service'],
                ['client' => 'PT Indosat Ooredoo Hutchison Tbk', 'p' => 'Dell', 'proj' => 'Telco Edge Compute Infrastructure Hardware', 'stage' => OpportunityStage::QUALIFICATION->value, 'prob' => 25, 'date' => '2026-03-20', 'scope' => 'Hardware'],
            ],
            'Siti Rahmawati' => [
                ['client' => 'PT Semen Indonesia (Persero) Tbk', 'p' => 'Talend', 'proj' => 'Enterprise Data Integration & Governance', 'stage' => OpportunityStage::NEGOTIATION->value, 'prob' => 60, 'date' => '2026-01-22', 'scope' => 'License + Service'],
                ['client' => 'PT Pegadaian (Persero)', 'p' => 'Denodo', 'proj' => 'Enterprise Data Virtualization & Unified Access', 'stage' => OpportunityStage::PROPOSAL->value, 'prob' => 40, 'date' => '2026-02-28', 'scope' => 'License + Service'],
                ['client' => 'PT Kimia Farma Tbk', 'p' => 'Talend', 'proj' => 'Supply Chain ETL & Data Quality Pipeline', 'stage' => OpportunityStage::CLOSING->value, 'prob' => 90, 'date' => '2026-03-12', 'scope' => 'License + Service'],
            ],
            'Yusuf Hidayat' => [
                ['client' => 'PT Kereta Api Indonesia (Persero)', 'p' => 'Confluent', 'proj' => 'Real-Time Passenger Ticketing Stream', 'stage' => OpportunityStage::CLOSING->value, 'prob' => 90, 'date' => '2026-02-14', 'scope' => 'License + Service'],
                ['client' => 'PT Kalbe Farma Tbk', 'p' => 'Alibaba Cloud', 'proj' => 'Enterprise SAP Cloud Migration on Alibaba', 'stage' => OpportunityStage::PROPOSAL->value, 'prob' => 40, 'date' => '2026-01-18', 'scope' => 'License + Service'],
                ['client' => 'PT Bank Mega Tbk', 'p' => 'Confluent', 'proj' => 'Fraud Detection Kafka Streaming Pipeline', 'stage' => OpportunityStage::NEGOTIATION->value, 'prob' => 60, 'date' => '2026-03-08', 'scope' => 'License + Service'],
            ],
            'Lina Marlina' => [
                ['client' => 'PT Pertamina (Persero)', 'p' => 'HPE', 'proj' => 'Mission-Critical ProLiant Server Farm', 'stage' => OpportunityStage::CLOSING->value, 'prob' => 90, 'date' => '2026-02-12', 'scope' => 'Hardware + Service'],
                ['client' => 'PT Perusahaan Listrik Negara (Persero)', 'p' => 'HPE', 'proj' => 'Synergy Composable Datacenter Hardware', 'stage' => OpportunityStage::NEGOTIATION->value, 'prob' => 60, 'date' => '2026-03-22', 'scope' => 'Hardware + Service'],
                ['client' => 'PT Jasa Marga (Persero) Tbk', 'p' => 'HPE', 'proj' => 'Toll Gate Edge Computing Server Cluster', 'stage' => OpportunityStage::PROPOSAL->value, 'prob' => 40, 'date' => '2026-01-26', 'scope' => 'Hardware'],
            ],
            'Fajar Nugroho' => [
                ['client' => 'PT United Tractors Tbk', 'p' => 'Huawei Cloud', 'proj' => 'Enterprise Cloud ERP & Compute Engine', 'stage' => OpportunityStage::CLOSING->value, 'prob' => 90, 'date' => '2026-02-05', 'scope' => 'License + Service'],
                ['client' => 'PT Aneka Tambang Tbk', 'p' => 'Huawei Cloud', 'proj' => 'Disaster Recovery Cloud Replication', 'stage' => OpportunityStage::NEGOTIATION->value, 'prob' => 60, 'date' => '2026-03-18', 'scope' => 'License + Service'],
                ['client' => 'PT Vale Indonesia Tbk', 'p' => 'Huawei Cloud', 'proj' => 'Big Data Cloud Cluster for Smart Mining', 'stage' => OpportunityStage::PROPOSAL->value, 'prob' => 40, 'date' => '2026-01-15', 'scope' => 'License'],
            ],
            'Nina Oktaviani' => [
                ['client' => 'PT Global Digital Niaga Tbk (Blibli)', 'p' => 'AWS', 'proj' => 'Cloud Data Lake & Modern Stack Analytics', 'stage' => OpportunityStage::NEGOTIATION->value, 'prob' => 60, 'date' => '2026-01-25', 'scope' => 'License + Service'],
                ['client' => 'PT Bank Jago Tbk', 'p' => 'AWS', 'proj' => 'Automated DevSecOps Pipeline on AWS', 'stage' => OpportunityStage::CLOSING->value, 'prob' => 90, 'date' => '2026-02-22', 'scope' => 'License + Service'],
                ['client' => 'PT Tiki Jalur Nugraha Ekakurir (JNE)', 'p' => 'AWS', 'proj' => 'Express Logistics Cloud Tracking Cluster', 'stage' => OpportunityStage::PROPOSAL->value, 'prob' => 40, 'date' => '2026-03-14', 'scope' => 'License + Service'],
            ],
            'Agus Setiawan' => [
                ['client' => 'PT AKR Corporindo Tbk', 'p' => 'Dell', 'proj' => 'PowerEdge Enterprise Server Hardware Farm', 'stage' => OpportunityStage::CLOSING->value, 'prob' => 90, 'date' => '2026-02-16', 'scope' => 'Hardware + Service'],
                ['client' => 'PT XL Axiata Tbk', 'p' => 'HPE', 'proj' => 'Telco Core High-Availability Hardware', 'stage' => OpportunityStage::NEGOTIATION->value, 'prob' => 60, 'date' => '2026-01-19', 'scope' => 'Hardware + Service'],
                ['client' => 'PT Mayora Indah Tbk', 'p' => 'Dell', 'proj' => 'Datacenter Modernization Server Refresh', 'stage' => OpportunityStage::PROPOSAL->value, 'prob' => 40, 'date' => '2026-03-25', 'scope' => 'Hardware'],
            ],
        ];

        $counter = 200;

        foreach ($pipelineTemplates as $repName => $deals) {
            $user = $users['sales'][$repName] ?? null;
            if (! $user) {
                continue;
            }

            foreach ($deals as $deal) {
                $counter++;
                $company = Company::firstOrCreate(
                    ['name' => $deal['client']],
                    ['industry' => 'Enterprise & Conglomerate']
                );

                $pName = $deal['p'];
                $principal = $principals[$pName] ?? null;
                $pInfo = $pricelist[$pName] ?? ['license_price' => 250000000, 'service_price' => 75000000];
                $productPair = $products[$pName] ?? null;

                $scope = $deal['scope'];
                $licPrice = (float) $pInfo['license_price'];
                $svcPrice = (float) $pInfo['service_price'];

                $estimatedLic = 0.0;
                $estimatedSvc = 0.0;
                $itemsToCreate = [];

                if ($scope === 'License + Service' || $scope === 'Hardware + Service') {
                    $estimatedLic = $licPrice;
                    $estimatedSvc = $svcPrice;
                    if ($productPair) {
                        $itemsToCreate[] = ['product' => $productPair['main'], 'price' => $licPrice];
                        $itemsToCreate[] = ['product' => $productPair['service'], 'price' => $svcPrice];
                    }
                } elseif ($scope === 'License' || $scope === 'Hardware') {
                    $estimatedLic = $licPrice;
                    if ($productPair) {
                        $itemsToCreate[] = ['product' => $productPair['main'], 'price' => $licPrice];
                    }
                } elseif ($scope === 'Service') {
                    $estimatedSvc = $svcPrice;
                    if ($productPair) {
                        $itemsToCreate[] = ['product' => $productPair['service'], 'price' => $svcPrice];
                    }
                }

                $totalValue = $estimatedLic + $estimatedSvc;
                $closingDate = Carbon::parse($deal['date']);
                $createdDate = Carbon::parse('2025-10-01')->addDays(random_int(1, 40));

                $opp = Opportunity::create([
                    'opportunity_code' => 'OPP-2026-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'principal_id' => $principal?->id,
                    'title' => $deal['proj'] . ' - ' . $deal['client'],
                    'project_name' => $deal['proj'],
                    'client_name' => $deal['client'],
                    'description' => "Proyek pipeline aktif solusi {$pName} untuk mendukung inovasi dan akselerasi {$deal['client']}.",
                    'remarks' => 'In continuous active commercial and technical engagement.',
                    'value' => $totalValue,
                    'estimated_value' => $totalValue,
                    'currency' => 'IDR',
                    'license_type' => 'New',
                    'progress' => $deal['stage'],
                    'deal_scope' => $scope,
                    'estimated_license_value' => $estimatedLic,
                    'estimated_service_value' => $estimatedSvc,
                    'estimated_deal_value' => $totalValue,
                    'stage' => $deal['stage'],
                    'progress_percentage' => $deal['prob'],
                    'status' => OpportunityStatus::OPEN->value,
                    'closing_date' => $closingDate->format('Y-m-d'),
                    'actual_closing_date' => null,
                    'probability' => $deal['prob'],
                    'sa_pic' => 'Solution Architect Enterprise Team',
                    'deal_registration' => 'Registered',
                    'deal_registration_status' => 'APPROVED',
                    'created_at' => $createdDate,
                    'updated_at' => now(),
                ]);

                foreach ($itemsToCreate as $itemData) {
                    OpportunityItem::create([
                        'opportunity_id' => $opp->id,
                        'product_id' => $itemData['product']->id,
                        'quantity' => 1,
                        'unit_price' => $itemData['price'],
                        'discount' => 0,
                        'subtotal' => $itemData['price'],
                    ]);
                }

                $this->createOpportunityLogs($opp, $user, $createdDate, now(), null, $deal['stage']);
                $this->createOpportunityActivities($opp, $user, $createdDate, now(), null, 'Solution Architect Enterprise Team');
            }
        }
    }

    private function createOpportunityLogs(Opportunity $opp, User $user, Carbon $start, Carbon $end, ?bool $isWon, ?string $currentStage = null): void
    {
        OpportunityLog::create([
            'opportunity_id' => $opp->id,
            'user_id' => $user->id,
            'field_name' => null,
            'old_value' => null,
            'new_value' => null,
            'description' => 'Opportunity created in stage LEAD',
            'created_at' => $start,
        ]);

        OpportunityStageHistory::create([
            'opportunity_id' => $opp->id,
            'previous_stage' => null,
            'current_stage' => OpportunityStage::LEAD->value,
            'previous_probability' => null,
            'current_probability' => OpportunityStage::LEAD->defaultProbability(),
            'changed_at' => $start,
        ]);

        $stages = [
            OpportunityStage::QUALIFICATION->value,
            OpportunityStage::PROPOSAL->value,
            OpportunityStage::NEGOTIATION->value,
            OpportunityStage::CLOSING->value,
        ];

        $prevStage = OpportunityStage::LEAD->value;
        $totalDays = max(1, $start->diffInDays($end));
        $step = (int) floor($totalDays / 5);
        $currTime = clone $start;

        foreach ($stages as $stage) {
            if ($currentStage && $prevStage === $currentStage) {
                break;
            }

            $currTime->addDays(max(1, $step));
            if ($currTime > $end) {
                $currTime = clone $end;
            }

            OpportunityLog::create([
                'opportunity_id' => $opp->id,
                'user_id' => $user->id,
                'field_name' => 'stage',
                'old_value' => $prevStage,
                'new_value' => $stage,
                'description' => "Stage updated from {$prevStage} to {$stage}",
                'created_at' => $currTime,
            ]);

            OpportunityStageHistory::create([
                'opportunity_id' => $opp->id,
                'previous_stage' => $prevStage,
                'current_stage' => $stage,
                'previous_probability' => OpportunityStage::tryFrom($prevStage)?->defaultProbability() ?? 10,
                'current_probability' => OpportunityStage::tryFrom($stage)?->defaultProbability() ?? 25,
                'changed_at' => $currTime,
            ]);

            $prevStage = $stage;

            if ($currentStage && $stage === $currentStage) {
                break;
            }
        }

        if ($isWon === true) {
            OpportunityLog::create([
                'opportunity_id' => $opp->id,
                'user_id' => $user->id,
                'field_name' => 'status',
                'old_value' => OpportunityStatus::OPEN->value,
                'new_value' => OpportunityStatus::WON->value,
                'description' => 'Deal closed and successfully won by sales team. Contract signed.',
                'created_at' => $end,
            ]);
        } elseif ($isWon === false) {
            $lostReasons = [
                'Customer chose alternative vendor with budget-constrained pricing.',
                'Project scope postponed by client to next fiscal year.',
                'Client internal reorganization deferred procurement timeline.',
                'Competitor provided aggressive promotional discount.',
            ];
            OpportunityLog::create([
                'opportunity_id' => $opp->id,
                'user_id' => $user->id,
                'field_name' => 'status',
                'old_value' => OpportunityStatus::OPEN->value,
                'new_value' => OpportunityStatus::LOST->value,
                'description' => $lostReasons[array_rand($lostReasons)],
                'created_at' => $end,
            ]);
        }
    }

    private function createOpportunityActivities(Opportunity $opp, User $user, Carbon $start, Carbon $end, ?bool $isWon, ?string $saPic = null): void
    {
        $pic = $saPic ?: 'Technical Committee';
        $activities = [
            ['type' => ActivityType::CALL->value, 'subject' => 'Initial discovery call with IT decision maker', 'desc' => 'Discussed project scope, budget allocation, and technical requirements.'],
            ['type' => ActivityType::MEETING->value, 'subject' => 'Solution presentation & technical alignment', 'desc' => "Presented architecture blueprint with {$pic} to technical committee."],
            ['type' => ActivityType::EMAIL->value, 'subject' => 'Formal proposal and commercial quote submission', 'desc' => 'Sent finalized commercial pricing matrix and scope of work document.'],
        ];

        $curr = clone $start;
        $totalDays = max(1, $start->diffInDays($end));
        $step = (int) floor($totalDays / (count($activities) + 1));

        foreach ($activities as $act) {
            $curr->addDays(max(1, $step));
            if ($curr > $end) {
                $curr = clone $end;
            }

            Activity::create([
                'opportunity_id' => $opp->id,
                'user_id' => $user->id,
                'type' => $act['type'],
                'activity_type' => $act['type'],
                'subject' => $act['subject'],
                'description' => $act['desc'],
                'activity_date' => $curr,
                'status' => ActivityStatus::DONE->value,
                'created_at' => $curr,
                'updated_at' => $curr,
            ]);
        }
    }

    private function seedForecastRuns(User $manager): void
    {
        $historicalService = app(HistoricalForecastService::class);
        $opportunityService = app(OpportunityForecastService::class);
        $combinedService = app(CombinedForecastService::class);
        $recommendationService = app(RecommendationService::class);

        // Run 1: Historical Forecast Q1 2026
        $run1 = ForecastRun::create([
            'forecast_name' => 'Proyeksi Historis Monte Carlo Kuartal 1 2026',
            'name' => 'Proyeksi Historis Monte Carlo Kuartal 1 2026',
            'forecast_type' => ForecastType::HISTORICAL->value,
            'forecast_scope' => 'OPPORTUNITY',
            'revenue_category' => 'ALL',
            'historical_start_date' => '2020-01-01',
            'historical_end_date' => '2025-09-30',
            'forecast_start_date' => '2026-01-01',
            'forecast_end_date' => '2026-03-31',
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'simulation_count' => 10000,
            'confidence_level' => 90,
            'target_revenue' => 3000000000, // Rp 3 Miliar
            'status' => ForecastRunStatus::COMPLETED->value,
            'started_at' => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(18),
            'created_by' => $manager->id,
            'user_id' => $manager->id,
        ]);

        $stats1 = $historicalService->statistics($run1, 42);
        ForecastResult::create(array_merge($stats1, ['forecast_run_id' => $run1->id]));

        // Run 2: Opportunity Forecast Q1 2026 (Pipeline Simulation)
        $run2 = ForecastRun::create([
            'forecast_name' => 'Proyeksi Pipeline Kuartal 1 2026 (Opportunity Simulation)',
            'name' => 'Proyeksi Pipeline Kuartal 1 2026 (Opportunity Simulation)',
            'forecast_type' => ForecastType::OPPORTUNITY->value,
            'forecast_scope' => 'OPPORTUNITY',
            'revenue_category' => 'ALL',
            'historical_start_date' => '2020-01-01',
            'historical_end_date' => '2025-09-30',
            'forecast_start_date' => '2026-01-01',
            'forecast_end_date' => '2026-03-31',
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'simulation_count' => 10000,
            'confidence_level' => 90,
            'target_revenue' => 3500000000, // Rp 3.5 Miliar
            'status' => ForecastRunStatus::COMPLETED->value,
            'started_at' => now()->subMinutes(15),
            'completed_at' => now()->subMinutes(14),
            'created_by' => $manager->id,
            'user_id' => $manager->id,
        ]);

        $stats2 = $opportunityService->statistics($run2, 101);
        ForecastResult::create(array_merge($stats2, ['forecast_run_id' => $run2->id]));

        // Run 3: Combined Forecast Q1 2026
        $run3 = ForecastRun::create([
            'forecast_name' => 'Proyeksi Terpadu Q1 2026 (Combined Historical & Pipeline)',
            'name' => 'Proyeksi Terpadu Q1 2026 (Combined Historical & Pipeline)',
            'forecast_type' => ForecastType::COMBINED->value,
            'forecast_scope' => 'OPPORTUNITY',
            'revenue_category' => 'ALL',
            'historical_start_date' => '2020-01-01',
            'historical_end_date' => '2025-09-30',
            'forecast_start_date' => '2026-01-01',
            'forecast_end_date' => '2026-03-31',
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'simulation_count' => 10000,
            'confidence_level' => 90,
            'target_revenue' => 4000000000, // Rp 4 Miliar
            'status' => ForecastRunStatus::COMPLETED->value,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(8),
            'created_by' => $manager->id,
            'user_id' => $manager->id,
        ]);

        $combinedResults = $combinedService->statistics($run3, 202);
        ForecastResult::create(array_merge($combinedResults, ['forecast_run_id' => $run3->id]));

        // Run 4: Scenario Analysis Run Q1 2026
        $run4 = ForecastRun::create([
            'forecast_name' => 'Analisis Skenario What-If Pipeline Q1 2026',
            'name' => 'Analisis Skenario What-If Pipeline Q1 2026',
            'forecast_type' => ForecastType::SCENARIO->value,
            'forecast_scope' => 'OPPORTUNITY',
            'revenue_category' => 'ALL',
            'historical_start_date' => '2020-01-01',
            'historical_end_date' => '2025-09-30',
            'forecast_start_date' => '2026-01-01',
            'forecast_end_date' => '2026-03-31',
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'simulation_count' => 10000,
            'confidence_level' => 90,
            'target_revenue' => 3500000000,
            'status' => ForecastRunStatus::COMPLETED->value,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(3),
            'created_by' => $manager->id,
            'user_id' => $manager->id,
        ]);

        $run4->scenarios()->create([
            'name' => 'Baseline Pipeline',
            'description' => 'Estimasi baseline tanpa penyesuaian probabilitas.',
            'win_rate_adjustment' => 0,
            'revenue_adjustment' => 0,
            'opportunity_adjustment' => 0,
            'target_revenue' => 3500000000,
        ]);

        $run4->scenarios()->create([
            'name' => 'Skenario Optimis (+15% Win Rate & Penutupan Cepat)',
            'description' => 'Peningkatan konversi negosiasi dan akselerasi closing kuartal 1.',
            'win_rate_adjustment' => 15,
            'revenue_adjustment' => 200000000,
            'opportunity_adjustment' => 10,
            'target_revenue' => 4500000000,
        ]);

        $run4->scenarios()->create([
            'name' => 'Skenario Konservatif (-10% Win Rate)',
            'description' => 'Antisipasi penundaan belanja anggaran IT kuartal pertama.',
            'win_rate_adjustment' => -10,
            'revenue_adjustment' => 0,
            'opportunity_adjustment' => 0,
            'target_revenue' => 2800000000,
        ]);

        $stats4 = $opportunityService->statistics($run4, 303);
        ForecastResult::create(array_merge($stats4, ['forecast_run_id' => $run4->id]));

        // Run 5: Full Year 2026 Historical Forecast
        $run5 = ForecastRun::create([
            'forecast_name' => 'Proyeksi Tahunan 2026 (Historical Monte Carlo)',
            'name' => 'Proyeksi Tahunan 2026 (Historical Monte Carlo)',
            'forecast_type' => ForecastType::HISTORICAL->value,
            'forecast_scope' => 'OPPORTUNITY',
            'revenue_category' => 'ALL',
            'historical_start_date' => '2020-01-01',
            'historical_end_date' => '2025-09-30',
            'forecast_start_date' => '2026-01-01',
            'forecast_end_date' => '2026-12-31',
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'simulation_count' => 10000,
            'confidence_level' => 90,
            'target_revenue' => 12000000000, // Rp 12 Miliar
            'status' => ForecastRunStatus::COMPLETED->value,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinutes(1),
            'created_by' => $manager->id,
            'user_id' => $manager->id,
        ]);

        $stats5 = $historicalService->statistics($run5, 404);
        ForecastResult::create(array_merge($stats5, ['forecast_run_id' => $run5->id]));

        foreach (ForecastRun::with('result')->get() as $run) {
            $run->update(['user_id' => $manager->id]);

            if ($run->result) {
                $run->update([
                    'predicted_revenue' => $run->result->mean,
                    'best_case' => $run->result->p90,
                    'worst_case' => $run->result->p10,
                    'most_likely' => $run->result->median,
                ]);
            }

            $recommendationService->generateForRun($run);
        }
    }
}
