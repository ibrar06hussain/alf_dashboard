<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\District;
use App\Models\Scheme;
use App\Services\SchemeAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchemeDashboardController extends Controller
{
    public function __construct(private SchemeAnalyticsService $analytics)
    {
    }

    public function show(Request $request, Scheme $scheme): View
    {
        $user = auth()->user();
        $this->analytics->authorizeSchemeAccess($user, $scheme);

        $filters = $this->extractFilters($request, $user);

        if ($request->header('X-Alf-Ajax-Pagination')) {
            $perPage = (int) $request->input('per_page', 25);
            $applications = $this->analytics->filteredApplicationsQuery($scheme, $filters, $user)
                ->with(['applicant', 'district', 'items', 'bills'])
                ->latest()
                ->paginate($perPage)
                ->withQueryString();

            $canUnmask = $user->hasAnyRole(['Super Admin', 'Secretary']);
            $maskPii = ! ($canUnmask && $request->boolean('unmask'));

            return view('admin.scheme-dashboards.show', [
                'scheme' => $scheme,
                'filters' => $filters,
                'districts' => collect(),
                'stages' => Application::STATUSES,
                'data' => [
                    'kpis' => [],
                    'has_items' => false,
                    'item_breakdown' => collect(),
                    'stage_distribution' => collect(),
                    'district_rollup' => collect(),
                    'field_breakdowns' => [],
                    'gis_markers' => [],
                ],
                'applications' => $applications,
                'canUnmask' => $canUnmask,
                'maskPii' => $maskPii,
                'perPage' => $perPage,
            ]);
        }

        $districts = ! $user->isGlobalScope() && ! empty($user->authorizedDistrictIds())
            ? District::whereIn('id', $user->authorizedDistrictIds())->orderBy('name')->get()
            : District::where('is_active', true)->orderBy('name')->get();

        $data = $this->analytics->getDashboardData($scheme, $filters, $user);

        $perPage = (int) $request->input('per_page', 25);
        $applications = $this->analytics->filteredApplicationsQuery($scheme, $filters, $user)
            ->with(['applicant', 'district', 'items', 'bills'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $canUnmask = $user->hasAnyRole(['Super Admin', 'Secretary']);
        $maskPii = ! ($canUnmask && $request->boolean('unmask'));
        $stages = Application::STATUSES;

        return view('admin.scheme-dashboards.show', compact(
            'scheme',
            'filters',
            'districts',
            'stages',
            'data',
            'applications',
            'canUnmask',
            'maskPii',
            'perPage'
        ));
    }

    public function analytics(Request $request, Scheme $scheme): JsonResponse
    {
        $user = auth()->user();
        $this->analytics->authorizeSchemeAccess($user, $scheme);

        $filters = $this->extractFilters($request, $user);

        return response()->json([
            'ok' => true,
            'scheme' => [
                'id' => $scheme->id,
                'title' => $scheme->title,
            ],
            'data' => $this->analytics->getDashboardData($scheme, $filters, $user),
        ]);
    }

    public function export(Request $request, Scheme $scheme): StreamedResponse
    {
        $user = auth()->user();
        $this->analytics->authorizeSchemeAccess($user, $scheme);

        $filters = $this->extractFilters($request, $user);
        $canUnmask = $user->hasAnyRole(['Super Admin', 'Secretary']);
        $maskPii = ! ($canUnmask && $request->boolean('unmask'));

        $rows = $this->analytics->filteredApplicationsQuery($scheme, $filters, $user)
            ->with(['applicant', 'district', 'items', 'bills'])
            ->latest()
            ->get();

        $filename = 'scheme-mis-' . $scheme->slug . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows, $maskPii) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Application No',
                'Applicant Name',
                'CNIC',
                'Phone',
                'District',
                'Stage',
                'Items Breakdown',
                'Demanded Qty',
                'Approved Qty',
                'Disbursed Qty',
                'Challan No',
                'Payable Amount (PKR)',
                'PSID',
                'Submitted Date',
            ]);

            foreach ($rows as $app) {
                $cnic = (string) ($app->applicant?->cnic ?? '');
                $phone = (string) ($app->applicant?->phone ?? '');
                if ($maskPii) {
                    $cnic = strlen($cnic) >= 6 ? (substr($cnic, 0, 5) . '******' . substr($cnic, -2)) : '***';
                    $phone = strlen($phone) >= 6 ? (substr($phone, 0, 4) . '****' . substr($phone, -2)) : '***';
                }

                $itemsStr = $app->items->map(function ($it) {
                    $tag = $it->is_free ? 'Free' : 'Paid';
                    $qty = $it->approved_qty ?? $it->demanded_qty;

                    return "{$it->item_label}: {$qty} ({$tag})";
                })->implode(' | ');

                fputcsv($out, [
                    $app->application_no,
                    $app->applicant?->full_name ?? '',
                    $cnic,
                    $phone,
                    $app->district?->name ?? '',
                    Application::translateStatus($app->current_stage ?: $app->status),
                    $itemsStr,
                    $app->getDemandedQuantity(),
                    $app->getApprovedQuantity() ?? '',
                    (float) ($app->distributed_quantity ?? 0),
                    $app->challan_no ?: 'No Challan (Free)',
                    (float) ($app->challan_amount ?? 0),
                    $app->psid ?: '',
                    $app->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function extractFilters(Request $request, $user): array
    {
        $districtId = $request->filled('district_id') ? (int) $request->input('district_id') : null;
        if ($districtId && ! $user->isGlobalScope()) {
            $authorized = $user->authorizedDistrictIds();
            if (! empty($authorized) && ! in_array($districtId, $authorized, true)) {
                $districtId = null;
            }
        }

        return [
            'district_id' => $districtId,
            'stage' => $request->input('stage') ?: null,
            'billing_type' => $request->input('billing_type') ?: null,
            'date_from' => $request->input('date_from') ?: null,
            'date_to' => $request->input('date_to') ?: null,
            'q' => $request->input('q') ? trim((string) $request->input('q')) : null,
        ];
    }
}
