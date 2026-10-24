<?php

namespace App\Http\Controllers\Admin;

use App\Exports\WheatSeedBeneficiariesExport;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\District;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\WheatSeedMisService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WheatSeedMisController extends Controller
{
    public function __construct(private WheatSeedMisService $misService)
    {
    }

    /**
     * Display the dedicated Wheat Seed MIS Dashboard.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $this->authorizeAccess($user);

        $filters = $this->extractFilters($request);

        $districts = District::orderBy('name')->get();
        $divisions = array_keys(WheatSeedMisService::DIVISIONS);
        $statuses = Application::STATUSES;

        // Reviewing officers who have logged status changes
        $officers = User::whereHas('statusLogs', function ($q) {
            $q->whereHas('application', fn ($aq) => $aq->where('scheme_id', WheatSeedMisService::SCHEME_ID));
        })->orderBy('name')->get(['id', 'name']);

        // Data payload
        $kpis = $this->misService->getKpis($filters, $user);
        $demandSupplyGap = $this->misService->getDemandSupplyGap($filters, $user);
        $landBands = $this->misService->getLandholdingBands($filters, $user);
        $waterRoad = $this->misService->getWaterRoadBreakdowns($filters, $user);
        $divisionRollup = $this->misService->getDivisionDistrictRollup($filters, $user);
        $gisData = $this->misService->getGisFarmLocations($filters, $user);
        $dataDictionary = $this->misService->getDataDictionary();
        $seedSettings = $this->misService->getSeedDataSettings();
        $configuredRate = $seedSettings['bag_rate'];
        $districtRatesTable = $this->misService->getAllDistrictRatesTable();

        $canUnmask = $user->hasAnyRole(['Super Admin', 'Secretary']);
        $maskPii = ! ($canUnmask && $request->boolean('unmask'));

        $perPage = $request->input('per_page', 25);
        $beneficiaries = $this->misService->getBeneficiaryList($filters, $user, $perPage, $maskPii);

        return view('admin.schemes.wheat-mis.index', compact(
            'filters',
            'districts',
            'divisions',
            'statuses',
            'officers',
            'kpis',
            'demandSupplyGap',
            'landBands',
            'waterRoad',
            'divisionRollup',
            'gisData',
            'dataDictionary',
            'configuredRate',
            'seedSettings',
            'districtRatesTable',
            'beneficiaries',
            'canUnmask',
            'maskPii',
            'perPage'
        ));
    }

    /**
     * Dynamic AJAX endpoint for Chart.js / ApexCharts updates when filters change.
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = auth()->user();
        $this->authorizeAccess($user);

        $filters = $this->extractFilters($request);

        $kpis = $this->misService->getKpis($filters, $user);
        $demandSupplyGap = $this->misService->getDemandSupplyGap($filters, $user);
        $landBands = $this->misService->getLandholdingBands($filters, $user);
        $waterRoad = $this->misService->getWaterRoadBreakdowns($filters, $user);
        $divisionRollup = $this->misService->getDivisionDistrictRollup($filters, $user);

        // Status series for Donut Chart
        $statusLabels = array_values(Application::STATUSES);
        $statusKeys = array_keys(Application::STATUSES);
        $statusSeries = array_map(fn ($k) => $kpis['status_counts'][$k] ?? 0, $statusKeys);

        // Demand Supply Gap Series for Grouped Bar Chart
        $gapLabels = $demandSupplyGap->pluck('district_name')->values();
        $quotaSeries = $demandSupplyGap->pluck('quota')->values();
        $approvedSeries = $demandSupplyGap->pluck('approved_bags')->values();
        $requestedSeries = $demandSupplyGap->pluck('requested_bags')->values();
        $disbursedSeries = $demandSupplyGap->pluck('disbursed_bags')->values();

        // Division Series
        $divLabels = array_keys($divisionRollup);
        $divApps = array_map(fn ($d) => $divisionRollup[$d]['total'] ?? 0, $divLabels);
        $divBags = array_map(fn ($d) => $divisionRollup[$d]['bags'] ?? 0, $divLabels);

        // Landholding Bands
        $bandLabels = array_keys($landBands);
        $bandCounts = array_map(fn ($b) => $landBands[$b]['count'] ?? 0, $bandLabels);

        // Water Donut
        $waterLabels = array_keys($waterRoad['water']['totals']);
        $waterSeries = array_values($waterRoad['water']['totals']);

        // Road Bar
        $roadLabels = array_keys($waterRoad['road']['totals']);
        $roadSeries = array_values($waterRoad['road']['totals']);

        return response()->json([
            'kpis' => $kpis,
            'by_status' => [
                'labels' => $statusLabels,
                'series' => $statusSeries,
            ],
            'demand_supply' => [
                'labels' => $gapLabels,
                'quotas' => $quotaSeries,
                'approved' => $approvedSeries,
                'requested' => $requestedSeries,
                'disbursed' => $disbursedSeries,
            ],
            'by_division' => [
                'labels' => $divLabels,
                'applications' => $divApps,
                'bags' => $divBags,
            ],
            'land_bands' => [
                'labels' => $bandLabels,
                'counts' => $bandCounts,
            ],
            'water' => [
                'labels' => $waterLabels,
                'series' => $waterSeries,
            ],
            'road' => [
                'labels' => $roadLabels,
                'series' => $roadSeries,
            ],
        ]);
    }

    /**
     * AJAX endpoint for paginated beneficiary table.
     */
    public function beneficiaries(Request $request): JsonResponse
    {
        $user = auth()->user();
        $this->authorizeAccess($user);

        $filters = $this->extractFilters($request);
        $canUnmask = $user->hasAnyRole(['Super Admin', 'Secretary']);
        $maskPii = ! ($canUnmask && $request->boolean('unmask'));

        $perPage = $request->input('per_page', 25);
        $beneficiaries = $this->misService->getBeneficiaryList($filters, $user, $perPage, $maskPii);

        $html = view('admin.schemes.wheat-mis.partials.beneficiary-grid', compact('beneficiaries', 'maskPii', 'perPage'))->render();

        return response()->json([
            'html' => $html,
            'data' => $beneficiaries->items(),
            'current_page' => $beneficiaries->currentPage(),
            'last_page' => $beneficiaries->lastPage(),
            'total' => $beneficiaries->total(),
            'per_page' => $beneficiaries->perPage(),
            'count' => $beneficiaries->count(),
            'first_item' => $beneficiaries->firstItem() ?? 0,
            'last_item' => $beneficiaries->lastItem() ?? 0,
        ]);
    }

    /**
     * Export Wheat Seed Applications and Beneficiaries to Excel, CSV, or PDF.
     */
    public function export(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        $user = auth()->user();
        $this->authorizeAccess($user);

        $filters = $this->extractFilters($request);
        $format = strtolower($request->string('format', 'xlsx')->toString());

        $maskPii = false; // Unmasked as requested

        $query = $this->misService->getFilteredQuery($filters, $user)
            ->with(['applicant', 'district', 'statusLogs.user', 'fieldValues.formField'])
            ->latest('applications.id');

        $rows = $query->limit(5000)->get();
        $filename = 'wheat-seed-beneficiaries-' . now()->format('Ymd_His');
        $generatedAt = now()->format('d M Y, h:i A');
        $generatedBy = $user->name . ' (' . ($user->getRoleNames()->first() ?? 'Officer') . ')';

        if ($format === 'csv') {
            return Excel::download(
                new WheatSeedBeneficiariesExport($rows, $maskPii, $generatedAt, $generatedBy),
                $filename . '.csv',
                \Maatwebsite\Excel\Excel::CSV
            );
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('admin.schemes.wheat-mis.pdf', [
                'applications' => $rows,
                'filters' => $filters,
                'maskPii' => $maskPii,
                'generatedAt' => $generatedAt,
                'generatedBy' => $generatedBy,
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename . '.pdf');
        }

        return Excel::download(
            new WheatSeedBeneficiariesExport($rows, $maskPii, $generatedAt, $generatedBy),
            $filename . '.xlsx'
        );
    }

    /**
     * Update or configure the official approved seed data parameters (rate, bag weight, cost-share %, sowing rate).
     */
    public function updateRate(Request $request): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture'])) {
            abort(403, 'Unauthorized to configure wheat seed rate.');
        }

        $validated = $request->validate([
            'wheat_seed_bag_rate' => 'nullable|numeric|min:0|max:500000',
            'wheat_seed_bag_weight_kg' => 'nullable|numeric|min:1|max:1000',
            'wheat_seed_govt_share_pct' => 'nullable|numeric|min:0|max:100',
            'wheat_seed_farmer_share_pct' => 'nullable|numeric|min:0|max:100',
            'wheat_seed_kanals_per_bag' => 'nullable|numeric|min:0.1|max:100',
            'wheat_seed_variety_name' => 'nullable|string|max:255',
            'wheat_seed_fiscal_year' => 'nullable|string|max:100',
            'district_transport' => 'nullable|array',
            'district_transport.*' => 'nullable|numeric|min:0|max:100000',
            'district_rate' => 'nullable|array',
            'district_rate.*' => 'nullable|numeric|min:0|max:500000',
        ]);

        // 1. Approved Bag Rate (PKR)
        $rate = array_key_exists('wheat_seed_bag_rate', $validated) ? $validated['wheat_seed_bag_rate'] : null;
        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_bag_rate'],
            [
                'label' => 'Approved Wheat Seed Bag Rate (PKR)',
                'group' => 'schemes',
                'value' => $rate !== null && $rate !== '' ? (string) $rate : null,
                'help_text' => 'Official approved cost-sharing price per standard wheat seed bag.',
            ]
        );

        // 2. Standard Bag Weight (kg)
        if (array_key_exists('wheat_seed_bag_weight_kg', $validated)) {
            $bagWeight = $validated['wheat_seed_bag_weight_kg'];
            SiteSetting::updateOrCreate(
                ['key' => 'wheat_seed_bag_weight_kg'],
                [
                    'label' => 'Standard Wheat Seed Bag Weight (kg)',
                    'group' => 'schemes',
                    'value' => $bagWeight !== null && $bagWeight !== '' ? (string) $bagWeight : '50',
                    'help_text' => 'Standard packaging weight in kilograms for wheat seed distribution bags.',
                ]
            );
        }

        // 3. Government Cost-Share Percentage
        if (array_key_exists('wheat_seed_govt_share_pct', $validated)) {
            $govtShare = $validated['wheat_seed_govt_share_pct'];
            SiteSetting::updateOrCreate(
                ['key' => 'wheat_seed_govt_share_pct'],
                [
                    'label' => 'Government Cost-Share Percentage (%)',
                    'group' => 'schemes',
                    'value' => $govtShare !== null && $govtShare !== '' ? (string) $govtShare : '50',
                    'help_text' => 'Percentage of wheat seed bag rate paid as government subsidy.',
                ]
            );
        }

        // 4. Farmer Contribution Percentage
        if (array_key_exists('wheat_seed_farmer_share_pct', $validated)) {
            $farmerShare = $validated['wheat_seed_farmer_share_pct'];
            SiteSetting::updateOrCreate(
                ['key' => 'wheat_seed_farmer_share_pct'],
                [
                    'label' => 'Farmer Contribution Percentage (%)',
                    'group' => 'schemes',
                    'value' => $farmerShare !== null && $farmerShare !== '' ? (string) $farmerShare : '50',
                    'help_text' => 'Percentage of wheat seed bag rate contributed by the farmer beneficiary.',
                ]
            );
        }

        // 5. Baseline Sowing Rate (Kanals per Bag)
        if (array_key_exists('wheat_seed_kanals_per_bag', $validated)) {
            $kanalsPerBag = $validated['wheat_seed_kanals_per_bag'];
            SiteSetting::updateOrCreate(
                ['key' => 'wheat_seed_kanals_per_bag'],
                [
                    'label' => 'Baseline Agronomic Sowing Rate (Kanals per Bag)',
                    'group' => 'schemes',
                    'value' => $kanalsPerBag !== null && $kanalsPerBag !== '' ? (string) $kanalsPerBag : '4',
                    'help_text' => 'Agronomic standard for wheat seed application per land area in Kanals.',
                ]
            );
        }

        // 6. Certified Seed Variety & Fiscal Year
        if (array_key_exists('wheat_seed_variety_name', $validated)) {
            SiteSetting::updateOrCreate(
                ['key' => 'wheat_seed_variety_name'],
                [
                    'label' => 'Certified Wheat Seed Variety',
                    'group' => 'schemes',
                    'value' => $validated['wheat_seed_variety_name'],
                    'help_text' => 'Designated certified seed variety for the current agricultural cycle.',
                ]
            );
        }

        if (array_key_exists('wheat_seed_fiscal_year', $validated)) {
            SiteSetting::updateOrCreate(
                ['key' => 'wheat_seed_fiscal_year'],
                [
                    'label' => 'Wheat Seed Scheme Fiscal Year',
                    'group' => 'schemes',
                    'value' => $validated['wheat_seed_fiscal_year'],
                    'help_text' => 'Target fiscal cycle / season for seed distribution operations.',
                ]
            );
        }

        // 7. District-Specific Transportation Carriage Charges & Delivered Rates
        if ($request->has('district_transport') || $request->has('district_rate')) {
            $transports = $request->input('district_transport', []);
            $rates = $request->input('district_rate', []);
            $districtMap = [];

            foreach (District::all() as $dist) {
                $dId = (int) $dist->id;
                $tCost = isset($transports[$dId]) && is_numeric($transports[$dId]) ? (float) $transports[$dId] : 0.0;

                $dRate = null;
                if (isset($rates[$dId]) && is_numeric($rates[$dId]) && (float) $rates[$dId] > 0) {
                    $dRate = (float) $rates[$dId];
                } elseif ($rate !== null) {
                    $dRate = round((float) $rate + $tCost, 2);
                }

                if ($tCost > 0 || ($dRate !== null && $rate !== null && $dRate != (float) $rate)) {
                    $districtMap[$dId] = [
                        'transport_cost' => $tCost,
                        'rate' => $dRate,
                    ];
                }
            }

            SiteSetting::updateOrCreate(
                ['key' => 'wheat_seed_district_rates'],
                [
                    'label' => 'Wheat Seed District-Wise Transportation & Delivered Rates',
                    'group' => 'schemes',
                    'value' => ! empty($districtMap) ? json_encode($districtMap) : null,
                    'help_text' => 'District-specific transportation carriage charges and delivered seed rates per bag (PKR).',
                ]
            );
        }

        return back()->with('success', 'Seed data configuration, district transport charges, and approved rates successfully updated.');
    }

    /**
     * Authorize user access to Wheat Seed MIS.
     */
    private function authorizeAccess(User $user): void
    {
        $allowed = $user->hasAnyRole([
            'Super Admin',
            'Secretary',
            'Director Agriculture',
            'District Officer',
        ]);

        if (! $allowed) {
            abort(403, 'Unauthorized access to Wheat Seed MIS dashboard.');
        }
    }

    /**
     * Sanitize and extract incoming request filters.
     */
    private function extractFilters(Request $request): array
    {
        $filters = [];

        if ($request->filled('division')) {
            $filters['division'] = $request->string('division')->toString();
        }
        if ($request->filled('district_id')) {
            $filters['district_id'] = $request->integer('district_id');
        }
        if ($request->filled('tehsil')) {
            $filters['tehsil'] = $request->string('tehsil')->toString();
        }
        if ($request->filled('union_council')) {
            $filters['union_council'] = $request->string('union_council')->toString();
        }
        if ($request->filled('village')) {
            $filters['village'] = $request->string('village')->toString();
        }
        if ($request->filled('status')) {
            $filters['status'] = $request->string('status')->toString();
        }
        if ($request->filled('officer_id')) {
            $filters['officer_id'] = $request->integer('officer_id');
        }
        if ($request->filled('survey_status')) {
            $filters['survey_status'] = $request->string('survey_status')->toString();
        }
        if ($request->filled('from_date')) {
            $filters['from_date'] = $request->date('from_date')->format('Y-m-d');
        }
        if ($request->filled('to_date')) {
            $filters['to_date'] = $request->date('to_date')->format('Y-m-d');
        }
        if ($request->filled('q')) {
            $filters['q'] = $request->string('q')->toString();
        }
        if ($request->filled('min_land')) {
            $filters['min_land'] = $request->input('min_land');
        }
        if ($request->filled('max_land')) {
            $filters['max_land'] = $request->input('max_land');
        }
        if ($request->filled('min_bags')) {
            $filters['min_bags'] = $request->input('min_bags');
        }
        if ($request->filled('max_bags')) {
            $filters['max_bags'] = $request->input('max_bags');
        }
        if ($request->filled('per_page')) {
            $filters['per_page'] = $request->input('per_page');
        }

        return $filters;
    }
}
