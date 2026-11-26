<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\ApplicationItem;
use App\Models\ApplicationSurvey;
use App\Models\CenterInventory;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SchemeStockItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchemeAnalyticsService
{
    public const REPORTING_ROLES = [
        'Super Admin',
        'Secretary',
        'Director Agriculture',
        'Director Livestock',
        'Director Fisheries',
        'Division Director',
        'District Deputy Director',
        'District Officer',
    ];

    /**
     * Only reporting & approval officers access auto-generated scheme dashboards.
     * Field Officers, Accounts Officers, and Distribution Officers remain focused on operational queues.
     */
    public function canAccessDashboards(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(self::REPORTING_ROLES);
    }

    /**
     * Discover all active schemes visible to the given officer based on department & district scope.
     */
    public function visibleSchemesFor(?User $user): Collection
    {
        if (! $this->canAccessDashboards($user)) {
            return collect();
        }

        $query = Scheme::query()
            ->with(['department:id,name,slug', 'challanTemplate'])
            ->where('status', 'active')
            ->where('type', '!=', 'proposed')
            ->where(function ($q) {
                $q->where('has_auto_dashboard', true)
                    ->orWhereNull('has_auto_dashboard');
            });

        if (! $user->isGlobalScope()) {
            $deptIds = $user->authorizedDepartmentIds();
            if (! empty($deptIds)) {
                $query->whereIn('department_id', $deptIds);
            }

            $districtIds = $user->authorizedDistrictIds();
            if (! empty($districtIds)) {
                $query->where(function ($sq) use ($districtIds) {
                    $sq->whereIn('scope', ['gb', 'province_wide'])
                        ->orWhereHas('districts', fn ($dq) => $dq->whereIn('districts.id', $districtIds))
                        ->orWhereHas('applications', fn ($aq) => $aq->whereIn('district_id', $districtIds));
                });
            }
        }

        return $query->orderBy('department_id')
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();
    }

    public function authorizeSchemeAccess(User $user, Scheme $scheme): void
    {
        if (! $this->canAccessDashboards($user)) {
            abort(403, 'You do not have access to Scheme MIS Dashboards.');
        }

        if ($user->isGlobalScope()) {
            return;
        }

        $visibleIds = $this->visibleSchemesFor($user)->pluck('id')->all();
        if (! in_array((int) $scheme->id, array_map('intval', $visibleIds), true)) {
            abort(403, 'Unauthorized: this scheme is outside your department or district scope.');
        }
    }

    public function __construct(private WheatSeedMisService $wheatMis)
    {
    }

    public function dashboardUrlFor(Scheme $scheme): string
    {
        if ($this->wheatMis->isWheatSeedScheme($scheme->id, $scheme->slug, $scheme->title)) {
            return route('admin.wheat-mis.index');
        }

        return route('admin.scheme-dashboards.show', $scheme);
    }

    /**
     * Lightweight scheme dashboard cards with officer-scoped live metrics for login home / sidebar.
     */
    public function getOfficerDashboardCards(User $user): Collection
    {
        $schemes = $this->visibleSchemesFor($user);
        if ($schemes->isEmpty()) {
            return collect();
        }

        $schemeIds = $schemes->pluck('id')->all();
        $appQuery = Application::query()->whereIn('scheme_id', $schemeIds);
        $this->applyOfficerDistrictScope($appQuery, $user);

        $appStats = (clone $appQuery)
            ->selectRaw("
                scheme_id,
                COUNT(*) as total_applications,
                COALESCE(SUM(CASE WHEN current_stage IN ('approved','challan_issued','payment_proof_submitted','payment_verified','eligible_beneficiary','stock_allocated','partially_distributed','fully_distributed','disbursed','completed') OR status IN ('directorate_approved','disbursed') THEN 1 ELSE 0 END), 0) as approved_count,
                COALESCE(SUM(CASE WHEN current_stage IN ('fully_distributed','disbursed','completed') OR status = 'disbursed' THEN 1 ELSE 0 END), 0) as disbursed_count,
                COALESCE(SUM(COALESCE(approved_quantity, demanded_quantity, 0)), 0) as total_units
            ")
            ->groupBy('scheme_id')
            ->get()
            ->keyBy('scheme_id');

        $itemStats = ApplicationItem::query()
            ->join('applications', 'application_items.application_id', '=', 'applications.id')
            ->whereIn('applications.scheme_id', $schemeIds)
            ->when(! $user->isGlobalScope() && ! empty($user->authorizedDistrictIds()), function ($q) use ($user) {
                $q->whereIn('applications.district_id', $user->authorizedDistrictIds());
            })
            ->selectRaw("
                applications.scheme_id as scheme_id,
                COALESCE(SUM(CASE WHEN application_items.is_free = 1 THEN COALESCE(application_items.approved_qty, application_items.demanded_qty) ELSE 0 END), 0) as free_units,
                COALESCE(SUM(CASE WHEN application_items.is_free = 0 THEN COALESCE(application_items.approved_qty, application_items.demanded_qty) ELSE 0 END), 0) as paid_units,
                COALESCE(SUM(application_items.farmer_payable_amount), 0) as net_payable
            ")
            ->groupBy('applications.scheme_id')
            ->get()
            ->keyBy('scheme_id');

        return $schemes->map(function (Scheme $scheme) use ($appStats, $itemStats) {
            $s = $appStats->get($scheme->id);
            $i = $itemStats->get($scheme->id);
            $isWheat = $this->wheatMis->isWheatSeedScheme($scheme->id, $scheme->slug, $scheme->title);

            return [
                'id' => $scheme->id,
                'title' => $scheme->title,
                'slug' => $scheme->slug,
                'department_name' => $scheme->department?->name ?? 'Department',
                'department_slug' => $scheme->department?->slug ?? 'agriculture',
                'scope' => $scheme->scope,
                'is_wheat' => $isWheat,
                'url' => $this->dashboardUrlFor($scheme),
                'total_applications' => (int) ($s->total_applications ?? 0),
                'approved_count' => (int) ($s->approved_count ?? 0),
                'disbursed_count' => (int) ($s->disbursed_count ?? 0),
                'total_units' => (float) ($s->total_units ?? 0),
                'has_items' => $i !== null,
                'free_units' => (float) ($i->free_units ?? 0),
                'paid_units' => (float) ($i->paid_units ?? 0),
                'net_payable' => (float) ($i->net_payable ?? 0),
            ];
        });
    }

    /**
     * Build complete scoped analytics payload for a specific scheme.
     */
    public function getDashboardData(Scheme $scheme, array $filters, User $user): array
    {
        $scheme->loadMissing(['department', 'challanTemplate', 'publishedForm.fields', 'stockItems']);

        $baseQuery = $this->filteredApplicationsQuery($scheme, $filters, $user);

        // 1. Core KPIs
        $agg = (clone $baseQuery)->toBase()->selectRaw("
            COUNT(*) as total_applications,
            COALESCE(SUM(CASE WHEN current_stage IN ('submitted','under_review','field_survey_assigned','visit_scheduled','visit_completed','findings_submitted','correction_required') OR status IN ('pending','submitted') THEN 1 ELSE 0 END), 0) as pending_review,
            COALESCE(SUM(CASE WHEN current_stage IN ('approved','challan_issued','payment_proof_submitted','payment_verified','eligible_beneficiary','stock_allocated','partially_distributed','fully_distributed','disbursed','completed') OR status IN ('directorate_approved','disbursed') THEN 1 ELSE 0 END), 0) as approved_cases,
            COALESCE(SUM(CASE WHEN current_stage = 'challan_issued' THEN 1 ELSE 0 END), 0) as challan_issued,
            COALESCE(SUM(CASE WHEN current_stage IN ('payment_verified','eligible_beneficiary','stock_allocated') THEN 1 ELSE 0 END), 0) as ready_for_disbursement,
            COALESCE(SUM(CASE WHEN current_stage IN ('partially_distributed','fully_distributed','disbursed','completed') OR status = 'disbursed' THEN 1 ELSE 0 END), 0) as disbursed_cases,
            COALESCE(SUM(CASE WHEN current_stage = 'rejected' OR status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected_cases,
            COALESCE(SUM(CASE WHEN current_stage = 'dormant' THEN 1 ELSE 0 END), 0) as dormant_cases,
            COALESCE(SUM(COALESCE(demanded_quantity, 0)), 0) as total_demanded_qty,
            COALESCE(SUM(COALESCE(approved_quantity, 0)), 0) as total_approved_qty,
            COALESCE(SUM(COALESCE(distributed_quantity, 0)), 0) as total_disbursed_qty
        ")->first();

        $appIdsSub = (clone $baseQuery)->select('applications.id');

        // 2. Itemized breakdown + financial breakdown across items
        $itemBreakdown = ApplicationItem::query()
            ->whereIn('application_id', $appIdsSub)
            ->selectRaw("
                item_key,
                MAX(item_label) as item_label,
                MAX(unit_label) as unit_label,
                is_free,
                COUNT(DISTINCT application_id) as applications_count,
                COALESCE(SUM(demanded_qty), 0) as demanded_qty,
                COALESCE(SUM(COALESCE(approved_qty, 0)), 0) as approved_qty,
                COALESCE(SUM(disbursed_qty), 0) as disbursed_qty,
                COALESCE(SUM(gross_amount), 0) as gross_amount,
                COALESCE(SUM(govt_share_amount), 0) as govt_share_amount,
                COALESCE(SUM(farmer_payable_amount), 0) as farmer_payable_amount
            ")
            ->groupBy('item_key', 'is_free')
            ->orderByDesc('demanded_qty')
            ->get();

        // Map center inventory balance per stock item name/key
        $stockByKey = $this->getAvailableStockMapForScheme($scheme, $filters, $user);

        $defaultUnitLabel = $scheme->resolveUnitLabel();

        $itemBreakdown = $itemBreakdown->map(function ($row) use ($stockByKey, $defaultUnitLabel) {
            $stockInfo = $stockByKey[$row->item_key] ?? null;
            $rawUnit = trim((string) ($row->unit_label ?? ''));
            $unitLabel = ($rawUnit !== '' && strcasecmp($rawUnit, 'Units') !== 0) ? $rawUnit : $defaultUnitLabel;

            return [
                'item_key' => $row->item_key,
                'item_label' => $row->item_label,
                'unit_label' => $unitLabel,
                'is_free' => (bool) $row->is_free,
                'applications_count' => (int) $row->applications_count,
                'demanded_qty' => (float) $row->demanded_qty,
                'approved_qty' => (float) $row->approved_qty,
                'disbursed_qty' => (float) $row->disbursed_qty,
                'pending_disburse_qty' => max(0.0, round((float) $row->approved_qty - (float) $row->disbursed_qty, 2)),
                'available_stock' => $stockInfo ? (float) $stockInfo['available'] : null,
                'stock_item_name' => $stockInfo['item_name'] ?? null,
                'gross_amount' => (float) $row->gross_amount,
                'govt_share_amount' => (float) $row->govt_share_amount,
                'farmer_payable_amount' => (float) $row->farmer_payable_amount,
            ];
        });

        $hasItems = $itemBreakdown->isNotEmpty();

        // Revenue metrics (works for both itemized and non-itemized schemes)
        $billsAgg = DB::table('bills')
            ->whereIn('application_id', $appIdsSub)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'paid' THEN bill_amount ELSE 0 END), 0) as collected_amount,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN bill_amount ELSE 0 END), 0) as pending_collection_amount
            ")
            ->first();

        $paidQty = $hasItems
            ? (float) $itemBreakdown->where('is_free', false)->sum(fn ($i) => $i['approved_qty'] > 0 ? $i['approved_qty'] : $i['demanded_qty'])
            : ($scheme->requires_challan ? (float) ($agg->total_approved_qty ?? 0) : 0.0);

        $freeQty = $hasItems
            ? (float) $itemBreakdown->where('is_free', true)->sum(fn ($i) => $i['approved_qty'] > 0 ? $i['approved_qty'] : $i['demanded_qty'])
            : ($scheme->requires_challan ? 0.0 : (float) ($agg->total_approved_qty ?? 0));

        $kpis = [
            'total_applications' => (int) ($agg->total_applications ?? 0),
            'pending_review' => (int) ($agg->pending_review ?? 0),
            'approved_cases' => (int) ($agg->approved_cases ?? 0),
            'challan_issued' => (int) ($agg->challan_issued ?? 0),
            'ready_for_disbursement' => (int) ($agg->ready_for_disbursement ?? 0),
            'disbursed_cases' => (int) ($agg->disbursed_cases ?? 0),
            'rejected_cases' => (int) ($agg->rejected_cases ?? 0),
            'dormant_cases' => (int) ($agg->dormant_cases ?? 0),
            'total_demanded_qty' => (float) ($agg->total_demanded_qty ?? 0),
            'total_approved_qty' => (float) ($agg->total_approved_qty ?? 0),
            'total_disbursed_qty' => (float) ($agg->total_disbursed_qty ?? 0),
            'paid_qty' => $paidQty,
            'free_qty' => $freeQty,
            'gross_paid_amount' => (float) $itemBreakdown->sum('gross_amount'),
            'govt_subsidy_amount' => (float) $itemBreakdown->sum('govt_share_amount'),
            'farmer_payable_amount' => $hasItems
                ? (float) $itemBreakdown->sum('farmer_payable_amount')
                : (float) (clone $baseQuery)->sum('challan_amount'),
            'collected_amount' => (float) ($billsAgg->collected_amount ?? 0),
            'pending_collection_amount' => (float) ($billsAgg->pending_collection_amount ?? 0),
        ];

        // 3. Stage Distribution
        $stageDistribution = (clone $baseQuery)
            ->selectRaw("COALESCE(current_stage, status, 'submitted') as stage, COUNT(*) as total")
            ->groupBy('stage')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'stage' => $r->stage,
                'label' => Application::translateStatus($r->stage),
                'total' => (int) $r->total,
            ]);

        // 4. District Rollup
        $districtRollup = (clone $baseQuery)
            ->join('districts', 'applications.district_id', '=', 'districts.id')
            ->leftJoin('scheme_districts', function ($j) use ($scheme) {
                $j->on('scheme_districts.district_id', '=', 'districts.id')
                    ->where('scheme_districts.scheme_id', '=', $scheme->id);
            })
            ->selectRaw("
                districts.id as district_id,
                districts.name as district_name,
                MAX(scheme_districts.quota) as quota,
                COUNT(applications.id) as total_applications,
                COALESCE(SUM(CASE WHEN applications.current_stage IN ('approved','challan_issued','payment_proof_submitted','payment_verified','eligible_beneficiary','stock_allocated','partially_distributed','fully_distributed','disbursed','completed') OR applications.status IN ('directorate_approved','disbursed') THEN 1 ELSE 0 END), 0) as approved_cases,
                COALESCE(SUM(CASE WHEN applications.current_stage IN ('partially_distributed','fully_distributed','disbursed','completed') OR applications.status = 'disbursed' THEN 1 ELSE 0 END), 0) as disbursed_cases,
                COALESCE(SUM(COALESCE(applications.demanded_quantity, 0)), 0) as demanded_qty,
                COALESCE(SUM(COALESCE(applications.approved_quantity, 0)), 0) as approved_qty,
                COALESCE(SUM(COALESCE(applications.distributed_quantity, 0)), 0) as disbursed_qty
            ")
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total_applications')
            ->get();

        // 5. Dynamic Form Option Aggregations (for non-billable select/radio/checkbox fields)
        $fieldBreakdowns = $this->getDynamicFieldBreakdowns($scheme, $appIdsSub);

        // 6. Survey GIS Markers (if any field surveys submitted with GPS)
        $gisMarkers = $this->getGisSurveyPoints($appIdsSub);

        return [
            'kpis' => $kpis,
            'has_items' => $hasItems,
            'item_breakdown' => $itemBreakdown,
            'stage_distribution' => $stageDistribution,
            'district_rollup' => $districtRollup,
            'field_breakdowns' => $fieldBreakdowns,
            'gis_markers' => $gisMarkers,
        ];
    }

    public function filteredApplicationsQuery(Scheme $scheme, array $filters, User $user): Builder
    {
        $query = Application::query()->where('applications.scheme_id', $scheme->id);
        $this->applyOfficerDistrictScope($query, $user);

        if (! empty($filters['district_id'])) {
            $query->where('applications.district_id', (int) $filters['district_id']);
        }

        if (! empty($filters['stage'])) {
            $stage = (string) $filters['stage'];
            $query->where(fn ($q) => $q->where('applications.current_stage', $stage)->orWhere('applications.status', $stage));
        }

        if (! empty($filters['billing_type'])) {
            $bType = (string) $filters['billing_type'];
            if ($bType === 'free_only') {
                $query->whereHas('items')
                    ->whereDoesntHave('items', fn ($q) => $q->where('is_free', false)->where('farmer_payable_amount', '>', 0));
            } elseif ($bType === 'has_paid') {
                $query->whereHas('items', fn ($q) => $q->where('is_free', false)->where('farmer_payable_amount', '>', 0));
            }
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('applications.created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('applications.created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['q'])) {
            $search = trim((string) $filters['q']);
            $query->where(function ($q) use ($search) {
                $q->where('applications.application_no', 'like', "%{$search}%")
                    ->orWhere('applications.challan_no', 'like', "%{$search}%")
                    ->orWhere('applications.psid', 'like', "%{$search}%")
                    ->orWhereHas('applicant', function ($aq) use ($search) {
                        $aq->where('full_name', 'like', "%{$search}%")
                            ->orWhere('cnic', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    private function applyOfficerDistrictScope(Builder $query, User $user): void
    {
        if (! $user->isGlobalScope()) {
            $districtIds = $user->authorizedDistrictIds();
            if (! empty($districtIds)) {
                $query->whereIn('applications.district_id', $districtIds);
            }
        }
    }

    private function getAvailableStockMapForScheme(Scheme $scheme, array $filters, User $user): array
    {
        $stockItems = SchemeStockItem::where('scheme_id', $scheme->id)->where('is_active', true)->get();
        if ($stockItems->isEmpty()) {
            return [];
        }

        $invQuery = CenterInventory::query()
            ->join('collection_centers', 'center_inventories.collection_center_id', '=', 'collection_centers.id')
            ->whereIn('center_inventories.scheme_stock_item_id', $stockItems->pluck('id')->all());

        if (! empty($filters['district_id'])) {
            $invQuery->where('collection_centers.district_id', (int) $filters['district_id']);
        } elseif (! $user->isGlobalScope()) {
            $districtIds = $user->authorizedDistrictIds();
            if (! empty($districtIds)) {
                $invQuery->whereIn('collection_centers.district_id', $districtIds);
            }
        }

        $balances = $invQuery->selectRaw('scheme_stock_item_id, COALESCE(SUM(current_balance), 0) as available')
            ->groupBy('scheme_stock_item_id')
            ->pluck('available', 'scheme_stock_item_id');

        $map = [];
        foreach ($stockItems as $si) {
            $normKey = ApplicationItem::normalizeKey((string) $si->item_name);
            $codeKey = $si->item_code ? ApplicationItem::normalizeKey((string) $si->item_code) : null;
            $entry = [
                'id' => $si->id,
                'item_name' => $si->item_name,
                'available' => (float) ($balances[$si->id] ?? 0),
            ];
            $map[$normKey] = $entry;
            if ($codeKey) {
                $map[$codeKey] = $entry;
            }
        }

        return $map;
    }

    private function getDynamicFieldBreakdowns(Scheme $scheme, $appIdsSub): array
    {
        $form = $scheme->publishedForm;
        if (! $form) {
            return [];
        }

        $categoricalFields = $form->fields
            ->filter(fn ($f) => ! $f->is_core && ! $f->is_billable_items_field && in_array($f->field_type, ['select', 'radio', 'checkbox', 'switch'], true))
            ->take(4);

        $results = [];
        foreach ($categoricalFields as $field) {
            $counts = ApplicationFieldValue::query()
                ->where('form_field_id', $field->id)
                ->whereIn('application_id', $appIdsSub)
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->selectRaw('value, COUNT(*) as total')
                ->groupBy('value')
                ->orderByDesc('total')
                ->limit(8)
                ->get()
                ->map(fn ($r) => ['option' => $r->value, 'total' => (int) $r->total])
                ->all();

            if (! empty($counts)) {
                $results[] = [
                    'field_label' => $field->field_label,
                    'field_key' => $field->field_key,
                    'counts' => $counts,
                ];
            }
        }

        return $results;
    }

    private function getGisSurveyPoints($appIdsSub): array
    {
        $surveys = ApplicationSurvey::with(['application:id,application_no,district_id', 'application.district:id,name'])
            ->whereIn('application_id', $appIdsSub)
            ->where(function ($q) {
                $q->whereNotNull('gps_points')
                    ->orWhereNotNull('gps_coordinates')
                    ->orWhereNotNull('evidence_data');
            })
            ->limit(150)
            ->get();

        $markers = [];
        foreach ($surveys as $s) {
            $pts = $s->gps_points_list ?? [];
            if (empty($pts) || ! isset($pts[0]['lat'], $pts[0]['lng'])) {
                continue;
            }
            $markers[] = [
                'application_no' => $s->application?->application_no,
                'district' => $s->application?->district?->name ?? '',
                'lat' => (float) $pts[0]['lat'],
                'lng' => (float) $pts[0]['lng'],
            ];
        }

        return $markers;
    }
}
