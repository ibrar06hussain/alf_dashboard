<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\CollectionCenter;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SchemeStockItem;
use App\Models\StockDamage;
use App\Models\StockDisbursement;
use App\Models\StockReceipt;
use App\Models\StockTransfer;
use App\Models\Tehsil;
use App\Services\StockManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockManagementController extends Controller
{
    public function __construct(
        protected StockManagementService $stockService
    ) {}

    /**
     * Determine user's active district scope.
     */
    protected function getEffectiveDistrictId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            return $request->filled('district_id') ? (int) $request->district_id : null;
        }

        $districts = $user->authorizedDistrictIds();
        return ! empty($districts) ? (int) $districts[0] : null;
    }

    /**
     * Stock & Distribution Dashboard Overview.
     */
    public function index(Request $request): View
    {
        $districtId = $this->getEffectiveDistrictId($request);
        $schemeId = $request->filled('scheme_id') ? (int) $request->scheme_id : null;

        $metrics = $this->stockService->getDemandVsStockMetrics($districtId, $schemeId);

        $districts = District::orderBy('name')->get();
        $schemes = Scheme::where('status', 'active')->orderBy('title')->get();

        // Recent receipts
        $recentReceiptsQuery = StockReceipt::with(['item.scheme', 'center.district', 'receiver'])
            ->latest();
        if ($districtId) {
            $recentReceiptsQuery->where('district_id', $districtId);
        }
        $recentReceipts = $recentReceiptsQuery->take(5)->get();

        // Recent transfers
        $recentTransfersQuery = StockTransfer::with(['item.scheme', 'fromCenter', 'toCenter', 'transferredBy'])
            ->latest();
        if ($districtId) {
            $recentTransfersQuery->whereHas('fromCenter', fn($q) => $q->where('district_id', $districtId))
                ->orWhereHas('toCenter', fn($q) => $q->where('district_id', $districtId));
        }
        $recentTransfers = $recentTransfersQuery->take(5)->get();

        // Recent damages
        $recentDamagesQuery = StockDamage::with(['item.scheme', 'center', 'recordedBy'])
            ->latest();
        if ($districtId) {
            $recentDamagesQuery->whereHas('center', fn($q) => $q->where('district_id', $districtId));
        }
        $recentDamages = $recentDamagesQuery->take(5)->get();

        return view('admin.stock.index', compact(
            'metrics',
            'districts',
            'schemes',
            'districtId',
            'schemeId',
            'recentReceipts',
            'recentTransfers',
            'recentDamages'
        ));
    }

    /**
     * Demand vs Available Stock Analysis View.
     */
    public function demandVsStock(Request $request): View
    {
        $districtId = $this->getEffectiveDistrictId($request);
        $schemeId = $request->filled('scheme_id') ? (int) $request->scheme_id : null;

        $metrics = $this->stockService->getDemandVsStockMetrics($districtId, $schemeId);

        $districts = District::orderBy('name')->get();
        $schemes = Scheme::where('status', 'active')->orderBy('title')->get();

        return view('admin.stock.demand_vs_stock', compact(
            'metrics',
            'districts',
            'schemes',
            'districtId',
            'schemeId'
        ));
    }

    /**
     * List and manage collection centers at district, tehsil, and village level.
     */
    public function centersIndex(Request $request): View
    {
        $districtId = $this->getEffectiveDistrictId($request);

        $query = CollectionCenter::with(['district', 'tehsil', 'creator', 'inventories.item.scheme'])
            ->withCount(['receipts', 'outgoingTransfers', 'incomingTransfers', 'damages']);

        if ($districtId) {
            $query->where('district_id', $districtId);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhere('village_name', 'like', "%{$s}%")
                    ->orWhere('focal_person_name', 'like', "%{$s}%");
            });
        }

        $centers = $query->latest()->paginate(15)->withQueryString();

        $districts = District::orderBy('name')->get();
        $tehsils = Tehsil::orderBy('name')->get();

        return view('admin.stock.centers', compact('centers', 'districts', 'tehsils', 'districtId'));
    }

    /**
     * Create a new Collection Center.
     */
    public function centersStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:collection_centers,code',
            'level' => 'required|in:district,tehsil,village',
            'district_id' => 'required|exists:districts,id',
            'tehsil_id' => 'nullable|exists:tehsils,id',
            'village_name' => 'nullable|string|max:255',
            'focal_person_name' => 'nullable|string|max:255',
            'focal_person_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'capacity_units' => 'nullable|numeric|min:0',
        ]);

        $user = $request->user();

        // Enforce district scope for field/distribution officers
        if (! $user->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            $districts = $user->authorizedDistrictIds();
            if (! in_array((int) $validated['district_id'], $districts, true)) {
                return back()->with('error', 'You cannot create collection centers outside your assigned district.');
            }
        }

        $validated['created_by'] = $user->id;
        $validated['is_active'] = true;

        CollectionCenter::create($validated);

        return redirect()->route('admin.stock.centers')
            ->with('success', "Collection Center '{$validated['name']}' created successfully.");
    }

    /**
     * Update an existing Collection Center.
     */
    public function centersUpdate(Request $request, CollectionCenter $center): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            $districts = $user->authorizedDistrictIds();
            if (! in_array($center->district_id, $districts, true)) {
                return back()->with('error', 'Unauthorized to modify this collection center.');
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'level' => 'required|in:district,tehsil,village',
            'tehsil_id' => 'nullable|exists:tehsils,id',
            'village_name' => 'nullable|string|max:255',
            'focal_person_name' => 'nullable|string|max:255',
            'focal_person_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'capacity_units' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $center->update($validated);

        return redirect()->route('admin.stock.centers')
            ->with('success', "Collection Center '{$center->name}' updated successfully.");
    }

    /**
     * List stock receipts (Schemes items received).
     */
    public function receiptsIndex(Request $request): View
    {
        $districtId = $this->getEffectiveDistrictId($request);

        $query = StockReceipt::with(['item.scheme', 'center.district', 'receiver']);

        if ($districtId) {
            $query->where('district_id', $districtId);
        }

        if ($request->filled('center_id')) {
            $query->where('collection_center_id', $request->center_id);
        }

        if ($request->filled('item_id')) {
            $query->where('scheme_stock_item_id', $request->item_id);
        }

        $receipts = $query->latest()->paginate(15)->withQueryString();

        $centersQuery = CollectionCenter::where('is_active', true);
        if ($districtId) {
            $centersQuery->where('district_id', $districtId);
        }
        $centers = $centersQuery->orderBy('name')->get();

        $items = SchemeStockItem::with('scheme')->where('is_active', true)->get();
        $districts = District::orderBy('name')->get();

        return view('admin.stock.receipts', compact('receipts', 'centers', 'items', 'districts', 'districtId'));
    }

    /**
     * Record a new stock receipt into a collection center.
     */
    public function receiptsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'collection_center_id' => 'required|exists:collection_centers,id',
            'scheme_stock_item_id' => 'required|exists:scheme_stock_items,id',
            'quantity_received' => 'required|numeric|min:0.01',
            'received_date' => 'required|date',
            'supplier_source' => 'nullable|string|max:255',
            'batch_no' => 'nullable|string|max:100',
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'notes' => 'nullable|string|max:1000',
        ]);

        $receipt = $this->stockService->recordStockReceipt(
            $validated,
            $request->user(),
            $request->file('proof_file')
        );

        return redirect()->route('admin.stock.receipts')
            ->with('success', "Stock receipt #{$receipt->receipt_no} recorded successfully. Inventory updated.");
    }

    /**
     * List stock transfers between collection centers.
     */
    public function transfersIndex(Request $request): View
    {
        $districtId = $this->getEffectiveDistrictId($request);

        $query = StockTransfer::with(['item.scheme', 'fromCenter.district', 'toCenter.district', 'transferredBy']);

        if ($districtId) {
            $query->where(function ($q) use ($districtId) {
                $q->whereHas('fromCenter', fn($sq) => $sq->where('district_id', $districtId))
                    ->orWhereHas('toCenter', fn($sq) => $sq->where('district_id', $districtId));
            });
        }

        $transfers = $query->latest()->paginate(15)->withQueryString();

        $centersQuery = CollectionCenter::where('is_active', true);
        if ($districtId) {
            $centersQuery->where('district_id', $districtId);
        }
        $centers = $centersQuery->orderBy('name')->get();

        $items = SchemeStockItem::with('scheme')->where('is_active', true)->get();

        return view('admin.stock.transfers', compact('transfers', 'centers', 'items', 'districtId'));
    }

    /**
     * Execute a stock transfer between collection centers with demand justification.
     */
    public function transfersStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_center_id' => 'required|exists:collection_centers,id',
            'to_center_id' => 'required|different:from_center_id|exists:collection_centers,id',
            'scheme_stock_item_id' => 'required|exists:scheme_stock_items,id',
            'quantity' => 'required|numeric|min:0.01',
            'demand_justification' => 'required|string|min:10|max:2000', // Mandatory justification based on demand
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ], [
            'demand_justification.required' => 'A valid justification explaining the demand requirement is mandatory for stock transfers.',
            'to_center_id.different' => 'The destination center must be different from the source center.',
        ]);

        $transfer = $this->stockService->transferStock($validated, $request->user());

        return redirect()->route('admin.stock.transfers')
            ->with('success', "Stock transfer #{$transfer->transfer_no} executed successfully. Inventories updated.");
    }

    /**
     * List damaged / wasted stock with proof.
     */
    public function damagesIndex(Request $request): View
    {
        $districtId = $this->getEffectiveDistrictId($request);

        $query = StockDamage::with(['item.scheme', 'center.district', 'recordedBy']);

        if ($districtId) {
            $query->whereHas('center', fn($q) => $q->where('district_id', $districtId));
        }

        $damages = $query->latest()->paginate(15)->withQueryString();

        $centersQuery = CollectionCenter::where('is_active', true);
        if ($districtId) {
            $centersQuery->where('district_id', $districtId);
        }
        $centers = $centersQuery->orderBy('name')->get();

        $items = SchemeStockItem::with('scheme')->where('is_active', true)->get();

        return view('admin.stock.damages', compact('damages', 'centers', 'items', 'districtId'));
    }

    /**
     * Record damaged or wasted stock with mandatory proof photo or report.
     */
    public function damagesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'collection_center_id' => 'required|exists:collection_centers,id',
            'scheme_stock_item_id' => 'required|exists:scheme_stock_items,id',
            'quantity' => 'required|numeric|min:0.01',
            'damage_type' => 'required|in:spoilage,packaging_tear,water_moisture,pest_infestation,transit_breakage,expiry,other',
            'reason' => 'required|string|min:10|max:2000',
            'proof_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // Proof image or inspection report
            'action_taken' => 'nullable|string|max:255',
            'incident_date' => 'required|date',
        ], [
            'proof_file.required' => 'Proof (inspection photo or damage report) is required to record stock damage.',
        ]);

        $damage = $this->stockService->recordStockDamage(
            $validated,
            $request->user(),
            $request->file('proof_file')
        );

        return redirect()->route('admin.stock.damages')
            ->with('success', "Damaged/wasted stock #{$damage->reference_no} recorded and deducted from inventory.");
    }

    /**
     * Create a new Scheme Stock Item (e.g. Wheat Seed Bag 50kg).
     */
    public function itemsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scheme_id' => 'required|exists:schemes,id',
            'item_name' => 'required|string|max:255',
            'item_code' => 'nullable|string|max:50',
            'unit' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $validated['is_active'] = true;

        $item = SchemeStockItem::create($validated);

        return back()->with('success', "Scheme Item '{$item->item_name}' created successfully.");
    }

    /**
     * Stock Disbursement Dashboard & Ledger.
     * Displays a dedicated tab for Eligible Applicants awaiting disbursement
     * and a tab for Historical Disbursements with proof and printable vouchers.
     */
    public function disbursementsIndex(Request $request): View
    {
        $districtId = $this->getEffectiveDistrictId($request);
        $tab = $request->get('tab', 'eligible');
        $search = trim($request->get('search', ''));
        $schemeId = $request->filled('scheme_id') ? (int) $request->scheme_id : null;

        // 1. Eligible Applicants waiting for disbursement
        $eligibleQuery = $this->stockService->getEligibleApplicationsQuery($districtId, $search);
        if ($schemeId) {
            $eligibleQuery->where('scheme_id', $schemeId);
        }
        $eligibleApplications = $eligibleQuery->paginate(15, ['*'], 'eligible_page')->withQueryString();
        $eligibleCount = (clone $eligibleQuery)->count();

        // 2. Historical Disbursements ledger
        $historyQuery = StockDisbursement::with([
            'application.applicant',
            'center.district',
            'item.scheme',
            'district',
            'disbursedBy',
        ])->latest();

        if ($districtId) {
            $historyQuery->where('district_id', $districtId);
        }

        if ($request->filled('center_id')) {
            $historyQuery->where('collection_center_id', $request->center_id);
        }

        if ($schemeId) {
            $historyQuery->whereHas('item', fn($q) => $q->where('scheme_id', $schemeId));
        }

        if ($search) {
            $historyQuery->where(function ($q) use ($search) {
                $q->where('disbursement_no', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_cnic', 'like', "%{$search}%")
                    ->orWhere('challan_no', 'like', "%{$search}%");
            });
        }

        $disbursements = $historyQuery->paginate(15, ['*'], 'history_page')->withQueryString();
        $historyCount = (clone $historyQuery)->count();

        // 3. Collection Centers scoped to the user's district with live inventory balances
        $centersQuery = CollectionCenter::with(['inventories.item'])->where('is_active', true);
        if ($districtId) {
            $centersQuery->where('district_id', $districtId);
        }
        $centers = $centersQuery->orderBy('name')->get();

        $schemes = Scheme::where('status', 'active')->orderBy('title')->get();
        $items = SchemeStockItem::with('scheme')->where('is_active', true)->get();
        $districts = District::orderBy('name')->get();

        // Specific pre-selected application if passed via query (e.g. from review page)
        $preselectedApplication = null;
        if ($request->filled('app_id')) {
            $preselectedApplication = Application::with(['applicant', 'scheme.stockItems', 'district'])->find($request->app_id);
        }

        return view('admin.stock.disbursements', compact(
            'tab',
            'eligibleApplications',
            'eligibleCount',
            'disbursements',
            'historyCount',
            'centers',
            'schemes',
            'items',
            'districts',
            'districtId',
            'schemeId',
            'search',
            'preselectedApplication'
        ));
    }

    /**
     * Execute a stock disbursement to an eligible beneficiary.
     */
    public function disbursementsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'application_id' => 'required|exists:applications,id',
            'collection_center_id' => 'required|exists:collection_centers,id',
            'scheme_stock_item_id' => 'required|exists:scheme_stock_items,id',
            'quantity_disbursed' => 'required|numeric|min:0.01',
            'recipient_name' => 'required|string|max:255',
            'recipient_cnic' => 'required|string|max:20',
            'recipient_phone' => 'nullable|string|max:50',
            'is_original_applicant' => 'sometimes|boolean',
            'representative_relationship' => 'nullable|string|max:100',
            'challan_no' => 'nullable|string|max:100',
            'disbursement_date' => 'required|date',
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        // Cross-district validation for officer
        if (! $user->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            $districts = $user->authorizedDistrictIds();
            $app = Application::findOrFail($validated['application_id']);
            if (! in_array($app->district_id, $districts, true)) {
                return back()->with('error', 'You can only disburse stock to applicants within your assigned district.');
            }
        }

        $disbursement = $this->stockService->disburseStock(
            $validated,
            $user,
            $request->file('proof_file')
        );

        return redirect()->route('admin.stock.disbursements', ['tab' => 'history'])
            ->with('success', "Stock successfully disbursed under Voucher #{$disbursement->disbursement_no}. Inventory updated.")
            ->with('voucher_id', $disbursement->id);
    }

    /**
     * Official Printable Stock Handover Slip / Gate Pass Voucher.
     */
    public function disbursementVoucher(StockDisbursement $disbursement): View
    {
        $user = auth()->user();

        if (! $user->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            $districts = $user->authorizedDistrictIds();
            if (! in_array($disbursement->district_id, $districts, true)) {
                abort(403, 'Unauthorized to view disbursement voucher from another district.');
            }
        }

        $disbursement->load([
            'application.applicant',
            'center.district',
            'center.tehsil',
            'item.scheme',
            'district',
            'disbursedBy',
        ]);

        return view('admin.stock.disbursement_voucher', compact('disbursement'));
    }
}
