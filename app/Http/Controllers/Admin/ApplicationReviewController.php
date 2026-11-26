<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ApplicationsExport;
use App\Http\Controllers\Controller;
use App\Models\AccessAuditLog;
use App\Models\Application;
use App\Models\ApplicationAssignment;
use App\Models\ApplicationStatusLog;
use App\Models\Department;
use App\Models\District;
use App\Models\Scheme;
use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicationReviewController extends Controller
{
    public function __construct(
        private QuotaService $quotas,
        private \App\Services\ApplicationLifecycleService $lifecycle
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $isAjaxPagination = (bool) $request->header('X-Alf-Ajax-Pagination');
        $query = $this->filteredQuery($request, true);

        $departments = Department::when(! $user->isGlobalScope() && ! empty($user->authorizedDepartmentIds()), function ($q) use ($user) {
            $q->whereIn('id', $user->authorizedDepartmentIds());
        })->orderBy('display_order')->get(['id', 'name']);

        $districts = District::when(! $user->isGlobalScope() && ! empty($user->authorizedDistrictIds()), function ($q) use ($user) {
            $q->whereIn('id', $user->authorizedDistrictIds());
        })->orderBy('name')->get(['id', 'name']);

        $schemes = Scheme::when(! $user->isGlobalScope(), function ($q) use ($user) {
            $authorizedDepts = $user->authorizedDepartmentIds();
            $authorizedDistricts = $user->authorizedDistrictIds();
            if (! empty($authorizedDepts)) {
                $q->whereIn('department_id', $authorizedDepts);
            }
            if (! empty($authorizedDistricts)) {
                $q->where(function ($sq) use ($authorizedDistricts) {
                    $sq->whereIn('scope', ['gb', 'province_wide'])
                        ->orWhereHas('districts', fn ($dq) => $dq->whereIn('districts.id', $authorizedDistricts));
                });
            }
        })->orderBy('title')->get(['id', 'title', 'department_id']);

        $statuses = Application::STATUSES;

        $isFieldOfficer = $user->hasRole('Field Officer') && ! $user->hasRole('Super Admin');
        $pendingCount = null;
        $completedCount = null;
        $pageStats = [];

        if (! $isAjaxPagination) {
            if ($isFieldOfficer) {
                $baseFoQuery = Application::query()->authorizedFor($user);

                $pendingCount = (clone $baseFoQuery)->where(function ($sub) use ($user) {
                    $sub->where(function ($sqSub) use ($user) {
                        $sqSub->whereHas('surveys', function ($sq) use ($user) {
                            $sq->where('assigned_to', $user->id)
                               ->whereNotIn('status', ['findings_submitted']);
                        })->whereIn('current_stage', [
                            Application::STAGE_FIELD_SURVEY_ASSIGNED,
                            Application::STAGE_VISIT_SCHEDULED,
                            Application::STAGE_VISIT_COMPLETED,
                        ]);
                    })->orWhere(function ($aqSub) use ($user) {
                        $aqSub->whereHas('assignments', function ($aq) use ($user) {
                            $aq->where('assigned_to', $user->id)
                               ->whereIn('status', ['assigned', 'in_progress']);
                        })->whereNotIn('current_stage', [
                            Application::STAGE_FINDINGS_SUBMITTED,
                            Application::STAGE_APPROVED,
                            Application::STAGE_REJECTED,
                            Application::STAGE_COMPLETED,
                            Application::STAGE_CHALLAN_ISSUED,
                            Application::STAGE_PAYMENT_VERIFIED,
                            Application::STAGE_ELIGIBLE_BENEFICIARY,
                            Application::STAGE_PARTIALLY_DISTRIBUTED,
                            Application::STAGE_FULLY_DISTRIBUTED,
                            Application::STAGE_DISBURSED,
                        ]);
                    });
                })->count();

                $scheduledCount = (clone $baseFoQuery)->where(function ($sqSub) use ($user) {
                    $sqSub->whereHas('surveys', function ($sq) use ($user) {
                        $sq->where('assigned_to', $user->id)
                           ->where('status', 'visit_scheduled');
                    })->orWhere('current_stage', Application::STAGE_VISIT_SCHEDULED);
                })->count();

                $completedCount = (clone $baseFoQuery)->where(function ($sub) use ($user) {
                    $sub->whereHas('surveys', function ($sq) use ($user) {
                        $sq->where('assigned_to', $user->id)
                           ->where('status', 'findings_submitted');
                    })->orWhereHas('assignments', function ($aq) use ($user) {
                        $aq->where('assigned_to', $user->id)
                           ->where('status', 'completed');
                    });
                })->count();

                $pageStats = [
                    ['label' => 'Pending Verification', 'value' => $pendingCount, 'icon' => 'fa-solid fa-clock-rotate-left', 'tone' => 'amber'],
                    ['label' => 'Visits Scheduled', 'value' => $scheduledCount, 'icon' => 'fa-solid fa-calendar-check', 'tone' => 'teal'],
                    ['label' => 'Completed Verifications', 'value' => $completedCount, 'icon' => 'fa-solid fa-circle-check', 'tone' => 'green'],
                ];
            } else {
                $agg = (clone $query)->reorder()->toBase()
                    ->selectRaw("
                        COUNT(*) as matching_count,
                        COALESCE(SUM(CASE WHEN current_stage IN ('submitted', 'under_review') THEN 1 ELSE 0 END), 0) as under_review_count,
                        COALESCE(SUM(CASE WHEN current_stage IN ('field_survey_assigned', 'visit_scheduled', 'visit_completed', 'findings_submitted') THEN 1 ELSE 0 END), 0) as field_survey_count,
                        COALESCE(SUM(CASE WHEN current_stage IN ('approved', 'challan_issued', 'payment_proof_submitted', 'payment_verified', 'eligible_beneficiary') THEN 1 ELSE 0 END), 0) as approved_count,
                        COALESCE(SUM(CASE WHEN current_stage IN ('partially_distributed', 'fully_distributed', 'completed', 'disbursed') THEN 1 ELSE 0 END), 0) as disbursed_count,
                        COALESCE(SUM(CASE WHEN is_unassigned = 1 THEN 1 ELSE 0 END), 0) as unassigned_count
                    ")
                    ->first();

                $pageStats = [
                    ['label' => 'Matching', 'value' => (int) ($agg->matching_count ?? 0), 'icon' => 'fa-solid fa-folder-open', 'tone' => 'green'],
                    ['label' => 'Under Review', 'value' => (int) ($agg->under_review_count ?? 0), 'icon' => 'fa-solid fa-clock', 'tone' => 'amber'],
                    ['label' => 'Field Survey', 'value' => (int) ($agg->field_survey_count ?? 0), 'icon' => 'fa-solid fa-magnifying-glass-location', 'tone' => 'teal'],
                    ['label' => 'Approved / Challan', 'value' => (int) ($agg->approved_count ?? 0), 'icon' => 'fa-solid fa-thumbs-up', 'tone' => 'blue'],
                    ['label' => 'Disbursed / Done', 'value' => (int) ($agg->disbursed_count ?? 0), 'icon' => 'fa-solid fa-hand-holding-dollar', 'tone' => 'purple'],
                    ['label' => 'Unassigned Queue', 'value' => (int) ($agg->unassigned_count ?? 0), 'icon' => 'fa-solid fa-triangle-exclamation', 'tone' => 'rose'],
                ];
            }
        }

        $applications = $query->paginate(25)->withQueryString();

        return view('admin.applications.index', compact(
            'applications', 'departments', 'districts', 'schemes', 'statuses', 'pageStats',
            'isFieldOfficer', 'pendingCount', 'completedCount'
        ));
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = auth()->user();
        $format = strtolower($request->string('format', 'xlsx')->toString());
        $filename = 'alf-applications-'.now()->format('Ymd_His');
        $masked = false;

        $generatedAt = now()->format('d M Y, h:i A');
        $generatedBy = $user->name . ' (' . ($user->getRoleNames()->first() ?? 'Officer') . ')';

        $query = $this->filteredQuery($request);

        if ($format === 'pdf') {
            ini_set('memory_limit', '512M');
            set_time_limit(180);

            $totalCount = (clone $query)->count();
            $requestedLimit = (int) $request->input('per_page', 500);
            $limit = ($requestedLimit > 0 && $requestedLimit <= 500) ? $requestedLimit : 500;

            $apps = $query->limit($limit)->get();

            AccessAuditLog::log('beneficiaries_exported', Application::class, null, [
                'count' => $apps->count(),
                'format' => $format,
                'is_masked' => false,
            ]);

            $rowsData = $apps->map(function ($app) {
                $applicant = $app->applicant;
                $cnic = \App\Support\Cnic::dashed($applicant?->cnic);
                $phone = $applicant?->phone ?: '—';
                $land = $app->getAvailableLandKanals();
                $bags = $app->getBagsRequired();
                $statusClass = match($app->status) {
                    'directorate_approved' => 'badge-approved',
                    'district_verified' => 'badge-verified',
                    'disbursed' => 'badge-disbursed',
                    'rejected' => 'badge-rejected',
                    default => 'badge-pending',
                };
                $tehsil = $applicant?->tehsil;
                $village = $applicant?->village;
                $location = trim(($tehsil ? $tehsil : '') . ($tehsil && $village ? ', ' : '') . ($village ? $village : ''));

                return [
                    'application_no' => $app->application_no,
                    'full_name' => $applicant?->full_name ?? '—',
                    'father_name' => $applicant?->father_name ?? '',
                    'cnic' => $cnic,
                    'phone' => $phone,
                    'district' => $app->district?->name ?? '—',
                    'location' => $location ?: '—',
                    'scheme' => $app->scheme?->title ?? '—',
                    'department' => $app->scheme?->department?->name ?? '',
                    'land' => $land !== null ? number_format($land, 1) : null,
                    'bags' => $bags,
                    'status_label' => $app->statusLabel(),
                    'status_class' => $statusClass,
                    'submitted_at' => optional($app->submitted_at ?? $app->created_at)?->format('d M Y') ?? '—',
                ];
            });

            $filters = [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
                'department_id' => $request->integer('department_id'),
                'district_id' => $request->integer('district_id'),
                'scheme_id' => $request->integer('scheme_id'),
                'min_land' => $request->input('min_land'),
                'max_land' => $request->input('max_land'),
                'min_bags' => $request->input('min_bags'),
                'max_bags' => $request->input('max_bags'),
            ];

            $pdf = Pdf::loadView('admin.applications.export-pdf', [
                'rowsData' => $rowsData,
                'totalCount' => $totalCount,
                'limit' => $limit,
                'masked' => $masked,
                'generatedAt' => $generatedAt,
                'generatedBy' => $generatedBy,
                'filters' => $filters,
            ])->setPaper('a4', 'landscape')
              ->setOption([
                  'isHtml5ParserEnabled' => true,
                  'isRemoteEnabled' => false,
                  'dpi' => 96,
                  'defaultFont' => 'DejaVu Sans',
              ]);

            return $pdf->download($filename.'.pdf');
        }

        $rows = $query->limit(5000)->get();

        AccessAuditLog::log('beneficiaries_exported', Application::class, null, [
            'count' => $rows->count(),
            'format' => $format,
            'is_masked' => false,
        ]);

        if ($format === 'csv') {
            return Excel::download(
                new ApplicationsExport($rows, $masked, $generatedAt, $generatedBy),
                $filename.'.csv',
                \Maatwebsite\Excel\Excel::CSV
            );
        }

        return Excel::download(
            new ApplicationsExport($rows, $masked, $generatedAt, $generatedBy),
            $filename.'.xlsx'
        );
    }

    private function filteredQuery(Request $request, bool $forListing = false)
    {
        $user = auth()->user();
        $relations = $forListing
            ? [
                'scheme:id,title,department_id,slug',
                'scheme.department:id,name',
                'applicant:id,full_name,cnic,tehsil,village',
                'district:id,name',
                'fieldValues.formField:id,field_key',
            ]
            : [
                'scheme.department',
                'applicant',
                'district',
                'assignments.assignee',
                'fieldValues.formField',
            ];

        $query = Application::with($relations)
            ->authorizedFor($user)
            ->latest();

        $authorizedDistricts = $user->authorizedDistrictIds();
        $authorizedDepts = $user->authorizedDepartmentIds();

        // Field Officers: show either active assigned (unprocessed) queue OR completed verifications
        if ($user->hasRole('Field Officer') && ! $user->hasRole('Super Admin')) {
            $viewMode = $request->input('view', 'pending');
            if ($viewMode === 'completed') {
                $query->where(function ($sub) use ($user) {
                    $sub->whereHas('surveys', function ($sq) use ($user) {
                        $sq->where('assigned_to', $user->id)
                           ->where('status', 'findings_submitted');
                    })->orWhereHas('assignments', function ($aq) use ($user) {
                        $aq->where('assigned_to', $user->id)
                           ->where('status', 'completed');
                    });
                });
            } else {
                // Pending verification queue: assigned to this officer and not yet processed
                $query->where(function ($sub) use ($user) {
                    $sub->where(function ($sqSub) use ($user) {
                        $sqSub->whereHas('surveys', function ($sq) use ($user) {
                            $sq->where('assigned_to', $user->id)
                               ->whereNotIn('status', ['findings_submitted']);
                        })->whereIn('current_stage', [
                            Application::STAGE_FIELD_SURVEY_ASSIGNED,
                            Application::STAGE_VISIT_SCHEDULED,
                            Application::STAGE_VISIT_COMPLETED,
                        ]);
                    })->orWhere(function ($aqSub) use ($user) {
                        $aqSub->whereHas('assignments', function ($aq) use ($user) {
                            $aq->where('assigned_to', $user->id)
                               ->whereIn('status', ['assigned', 'in_progress']);
                        })->whereNotIn('current_stage', [
                            Application::STAGE_FINDINGS_SUBMITTED,
                            Application::STAGE_APPROVED,
                            Application::STAGE_REJECTED,
                            Application::STAGE_COMPLETED,
                            Application::STAGE_CHALLAN_ISSUED,
                            Application::STAGE_PAYMENT_VERIFIED,
                            Application::STAGE_ELIGIBLE_BENEFICIARY,
                            Application::STAGE_PARTIALLY_DISTRIBUTED,
                            Application::STAGE_FULLY_DISTRIBUTED,
                            Application::STAGE_DISBURSED,
                        ]);
                    });
                });
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('department_id')) {
            $deptId = $request->integer('department_id');
            if ($user->isGlobalScope() || in_array($deptId, $authorizedDepts, true)) {
                $query->whereHas('scheme', fn ($q) => $q->where('department_id', $deptId));
            }
        }

        if ($request->filled('district_id')) {
            $distId = $request->integer('district_id');
            if ($user->isGlobalScope() || in_array($distId, $authorizedDistricts, true)) {
                $query->where('district_id', $distId);
            }
        }

        if ($request->filled('scheme_id')) {
            $query->where('scheme_id', $request->integer('scheme_id'));
        }

        // Available Land Filter (Min / Max Kanals)
        $minLand = $request->input('min_land') ?? $request->input('land_kanals');
        if ($minLand !== null && $minLand !== '' && is_numeric($minLand)) {
            $val = (float) $minLand;
            $query->whereHas('fieldValues', function ($fq) use ($val) {
                $fq->where(function ($sub) {
                    $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                        ->orWhere('form_field_id', 8);
                })->whereRaw('CAST(value AS DECIMAL(10,2)) >= ?', [$val]);
            });
        }

        if ($request->filled('max_land') && is_numeric($request->input('max_land'))) {
            $val = (float) $request->input('max_land');
            $query->whereHas('fieldValues', function ($fq) use ($val) {
                $fq->where(function ($sub) {
                    $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                        ->orWhere('form_field_id', 8);
                })->whereRaw('CAST(value AS DECIMAL(10,2)) <= ?', [$val]);
            });
        }

        // Bags Required Filter (Min / Max Bags)
        $minBags = $request->input('min_bags') ?? $request->input('bags_required');
        if ($minBags !== null && $minBags !== '' && is_numeric($minBags)) {
            $val = (int) $minBags;
            $query->where(function ($bq) use ($val) {
                $bq->whereHas('fieldValues', function ($fq) use ($val) {
                    $fq->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['bags_required', 'seed_bags', 'seed_bags_required', 'wheat_seed_bags']))
                       ->whereRaw('CAST(value AS UNSIGNED) >= ?', [$val]);
                })->orWhere(function ($wheatQ) use ($val) {
                    $minLandForBags = max(0, ($val - 1) * 4 + 0.01);
                    $wheatQ->whereHas('scheme', fn ($sq) => $sq->where('slug', 'like', '%wheat%'))
                           ->whereHas('fieldValues', function ($fq) use ($minLandForBags) {
                               $fq->where(function ($sub) {
                                   $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                                       ->orWhere('form_field_id', 8);
                               })->whereRaw('CAST(value AS DECIMAL(10,2)) >= ?', [$minLandForBags]);
                           });
                });
            });
        }

        if ($request->filled('max_bags') && is_numeric($request->input('max_bags'))) {
            $val = (int) $request->input('max_bags');
            $query->where(function ($bq) use ($val) {
                $bq->whereHas('fieldValues', function ($fq) use ($val) {
                    $fq->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['bags_required', 'seed_bags', 'seed_bags_required', 'wheat_seed_bags']))
                       ->whereRaw('CAST(value AS UNSIGNED) <= ?', [$val]);
                })->orWhere(function ($wheatQ) use ($val) {
                    $maxLandForBags = $val * 4;
                    $wheatQ->whereHas('scheme', fn ($sq) => $sq->where('slug', 'like', '%wheat%'))
                           ->whereHas('fieldValues', function ($fq) use ($maxLandForBags) {
                               $fq->where(function ($sub) {
                                   $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                                       ->orWhere('form_field_id', 8);
                               })->whereRaw('CAST(value AS DECIMAL(10,2)) <= ?', [$maxLandForBags]);
                           });
                });
            });
        }

        if ($request->filled('stage')) {
            $query->where('current_stage', $request->string('stage'));
        }

        if ($request->filled('unassigned') && $request->boolean('unassigned')) {
            $query->where('is_unassigned', true);
        }

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($builder) use ($q) {
                $builder->where('application_no', 'like', "%{$q}%")
                    ->orWhereHas('applicant', function ($aq) use ($q) {
                        $aq->where('full_name', 'like', "%{$q}%")
                            ->orWhere('cnic', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%")
                            ->orWhere('tehsil', 'like', "%{$q}%")
                            ->orWhere('union_council', 'like', "%{$q}%")
                            ->orWhere('village', 'like', "%{$q}%");
                    });
            });
        }

        return $query;
    }

    public function show(Application $application): View
    {
        $user = auth()->user();

        if (! $user->can('view', $application)) {
            abort(403, 'Unauthorized access to application in another administrative scope.');
        }

        // Self-heal: expire unpaid challan into dormant status if past due date
        if ($application->current_stage === Application::STAGE_CHALLAN_ISSUED && $application->isChallanExpired()) {
            $this->lifecycle->expireApplicationChallan($application);
            $application->refresh();
        }

        // Self-heal: if application is in a field survey stage but missing ApplicationSurvey or ApplicationAssignment record, create it automatically
        if (in_array($application->current_stage, [
            Application::STAGE_FIELD_SURVEY_ASSIGNED,
            Application::STAGE_VISIT_SCHEDULED,
            Application::STAGE_VISIT_COMPLETED,
        ], true)) {
            $existingAssignment = $application->assignments()->first();
            $existingSurvey = $application->surveys()->first();

            if (! $existingSurvey || ! $existingAssignment) {
                $targetFoId = $existingSurvey?->assigned_to
                    ?? $existingAssignment?->assigned_to
                    ?? ($user->hasRole('Field Officer') ? $user->id : null);

                if (! $targetFoId) {
                    $fo = User::role('Field Officer')
                        ->where('is_active', true)
                        ->where(function ($q) use ($application) {
                            $q->where('district_id', $application->district_id)
                              ->orWhereHas('officeAssignments', fn ($sq) => $sq->where('district_id', $application->district_id));
                        })
                        ->first();
                    $targetFoId = $fo?->id;
                }

                if ($targetFoId) {
                    $assignerId = $application->assigned_to ?: $targetFoId;
                    if (! $existingSurvey) {
                        \App\Models\ApplicationSurvey::firstOrCreate(
                            ['application_id' => $application->id, 'assigned_to' => $targetFoId],
                            [
                                'assigned_by' => $assignerId,
                                'status' => \App\Models\ApplicationSurvey::STATUS_ASSIGNED,
                                'findings_summary' => 'Field verification required for scheme application.',
                            ]
                        );
                    }
                    if (! $existingAssignment) {
                        \App\Models\ApplicationAssignment::firstOrCreate(
                            [
                                'application_id' => $application->id,
                                'assigned_to' => $targetFoId,
                                'task_type' => \App\Models\ApplicationAssignment::TASK_LAND_VERIFICATION,
                            ],
                            [
                                'assigned_by' => $assignerId,
                                'status' => \App\Models\ApplicationAssignment::STATUS_ASSIGNED,
                                'due_date' => now()->addDays(3),
                                'notes' => 'Field verification required for scheme application.',
                            ]
                        );
                    }
                }
            }
        }

        $application->load([
            'scheme.department',
            'applicant.district',
            'district',
            'division',
            'assignedUser',
            'responsibleOffice',
            'form',
            'fieldValues.formField',
            'items.stockItem',
            'documents.formDocument',
            'statusLogs.user',
            'assignments.assignee',
            'assignments.assigner',
            'surveys.surveyor',
            'surveys.assignedBy',
            'paymentVerifiedBy',
            'reactivatedBy',
            'bills',
            'smsLogs',
            'editAuditLogs.user',
        ]);

        $allowedTransitions = $this->allowedTransitions($user, $application);
        $quotaStatus = $this->quotas->status($application->scheme, $application->district_id);
        $slaStatus = $application->slaStatus();

        $fieldOfficers = $user->can('assignFieldOfficer', $application)
            ? User::whereHas('roles', fn ($rq) => $rq->where('name', 'Field Officer'))
                ->where(function ($q) use ($application) {
                    $q->where('district_id', $application->district_id)
                        ->orWhereHas('officeAssignments', function ($aq) use ($application) {
                            $aq->active()->where('district_id', $application->district_id);
                        });
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        $deputyDirectors = $user->hasRole('Super Admin') || $user->hasRole('Secretary') || $user->hasRole('Division Director')
            ? User::whereHas('roles', fn ($rq) => $rq->whereIn('name', ['District Deputy Director', 'District Officer']))
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        AccessAuditLog::log('application_viewed', Application::class, (string) $application->id);

        return view('admin.applications.show', compact(
            'application', 'allowedTransitions', 'quotaStatus', 'slaStatus', 'fieldOfficers', 'deputyDirectors'
        ));
    }

    public function updateStatus(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('view', $application)) {
            abort(403, 'Unauthorized access.');
        }

        $data = $request->validate([
            'status' => ['required', 'string'],
            'approved_quantity' => ['nullable', 'numeric', 'min:0'],
            'approved_items' => ['nullable', 'array'],
            'approved_items.*' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
            'verification_note' => ['nullable', 'string', 'max:2000'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $targetStatus = $data['status'];

        // Legacy mapping if needed
        $allowed = $this->allowedTransitions($user, $application);
        if (! in_array($targetStatus, $allowed, true)) {
            return back()->with('error', 'You are not authorized to transition this application to the requested status.');
        }

        if ($request->has('approved_items') && is_array($data['approved_items'] ?? null)) {
            $qtyToApprove = array_sum(array_map(fn ($v) => max(0.0, (float) $v), $data['approved_items']));
        } else {
            $qtyToApprove = isset($data['approved_quantity']) && is_numeric($data['approved_quantity'])
                ? (float) $data['approved_quantity']
                : (float) ($application->approved_quantity ?? $application->getDemandedQuantity());
        }

        if (in_array($targetStatus, ['directorate_approved', 'approved', 'disbursed', 'fully_distributed'], true)
            && ! $this->quotas->canApprove($application->scheme, $application->district_id, $qtyToApprove)
            && ! in_array($application->status, ['directorate_approved', 'approved', 'disbursed', 'fully_distributed'], true)) {
            $quota = $this->quotas->status($application->scheme, $application->district_id);

            return back()->with('error', sprintf(
                'District quota reached (%d / %d %s). Cannot approve further applications for this district.',
                $quota['used'] ?? 0,
                $quota['quota'] ?? 0,
                strtolower($application->resolveUnitLabel())
            ));
        }

        try {
            // If DD transitions directly to field_survey_assigned via the general status form, auto-assign to the district's Field Officer
            if ($targetStatus === Application::STAGE_FIELD_SURVEY_ASSIGNED) {
                $fo = User::role('Field Officer')
                    ->where('is_active', true)
                    ->where(function ($q) use ($application) {
                        $q->where('district_id', $application->district_id)
                          ->orWhereHas('officeAssignments', fn ($sq) => $sq->where('district_id', $application->district_id));
                    })
                    ->first();

                if ($fo) {
                    $this->lifecycle->assignSurvey($application, $user, $fo, [
                        'instructions' => $data['note'] ?? $data['verification_note'] ?? 'Field verification required for scheme application.',
                    ]);

                    return back()->with('success', "Application transitioned to Field Survey Assigned and delegated to Field Officer {$fo->name}.");
                }
            }

            $expectedLock = $request->filled('lock_version') ? (int) $request->input('lock_version') : null;
            $this->lifecycle->transition($application, $targetStatus, $user, [
                'note' => $data['note'] ?? $data['verification_note'] ?? null,
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'verification_note' => $data['verification_note'] ?? null,
                'approved_quantity' => $data['approved_quantity'] ?? null,
                'approved_items' => $data['approved_items'] ?? null,
            ], $expectedLock);

            return back()->with('success', 'Application transitioned to ' . Application::translateStatus($targetStatus) . '.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function assignSurvey(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('assignSurvey', $application)) {
            abort(403, 'Unauthorized to delegate field survey for this application.');
        }

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
        ]);

        $fieldOfficer = User::findOrFail($data['assigned_to']);

        try {
            $this->lifecycle->assignSurvey($application, $user, $fieldOfficer, $data);
            return back()->with('success', "Field survey successfully assigned to Field Officer {$fieldOfficer->name}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function scheduleSurveyVisit(Request $request, \App\Models\ApplicationSurvey $survey): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'scheduled_visit_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->lifecycle->scheduleVisit($survey, $user, Carbon::parse($data['scheduled_visit_at']), $data['notes'] ?? null);
            return back()->with('success', 'Field survey visit scheduled successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function completeSurveyVisit(Request $request, \App\Models\ApplicationSurvey $survey): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->lifecycle->completeVisit($survey, $user, $data['notes'] ?? null);
            return back()->with('success', 'Field survey visit marked completed.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function submitSurveyFindings(Request $request, \App\Models\ApplicationSurvey $survey): RedirectResponse
    {
        $user = auth()->user();

        // 1. Process GPS points (Optional: capture coordinates or proceed without them)
        $rawGps = $request->input('gps_points');
        if (is_string($rawGps)) {
            $rawGps = json_decode($rawGps, true) ?: [];
        } elseif (! is_array($rawGps)) {
            $rawGps = [];
        }

        $validPoints = [];
        foreach ($rawGps as $pt) {
            if (isset($pt['lat'], $pt['lng']) && is_numeric($pt['lat']) && is_numeric($pt['lng'])) {
                $validPoints[] = [
                    'point_number' => count($validPoints) + 1,
                    'lat' => round((float) $pt['lat'], 7),
                    'lng' => round((float) $pt['lng'], 7),
                    'accuracy' => isset($pt['accuracy']) && is_numeric($pt['accuracy']) ? round((float) $pt['accuracy'], 2) : null,
                    'label' => ! empty($pt['label']) ? (string) $pt['label'] : 'Point ' . (count($validPoints) + 1),
                    'timestamp' => $pt['timestamp'] ?? now()->toIso8601String(),
                ];
            }
        }

        // 2. Validate request fields and land photos (max 3 photos, max 5MB each)
        $data = $request->validate([
            'land_verified_kanals' => ['nullable', 'numeric', 'min:0'],
            'water_source_verified' => ['nullable', 'string', 'max:255'],
            'road_access_verified' => ['nullable', 'string', 'max:255'],
            'findings_summary' => ['required', 'string', 'max:2000'],
            'recommendation' => ['required', 'in:recommended,not_recommended,needs_further_audit'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // Max 5 MB each
            'photo_1' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'photo_2' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'photo_3' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        // 3. Process uploaded photos
        $uploadedPhotos = [];
        $photoFiles = [];

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $idx => $file) {
                if ($file && $file->isValid()) {
                    $photoFiles[] = ['file' => $file, 'label' => 'Land Photo ' . (count($photoFiles) + 1)];
                }
            }
        }

        foreach (['photo_1' => 'General Land View', 'photo_2' => 'Irrigation / Water Source', 'photo_3' => 'Road Access / Parcel Corner'] as $key => $defaultLabel) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                if ($file && $file->isValid()) {
                    $photoFiles[] = ['file' => $file, 'label' => $defaultLabel];
                }
            }
        }

        $photoFiles = array_slice($photoFiles, 0, 3);

        foreach ($photoFiles as $item) {
            $file = $item['file'];
            $path = $file->store('surveys/photos/' . $survey->application_id, 'public');
            $uploadedPhotos[] = [
                'path' => $path,
                'url' => asset('storage/' . $path),
                'name' => $file->getClientOriginalName(),
                'size_kb' => round($file->getSize() / 1024, 1),
                'label' => $item['label'],
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        $data['gps_points'] = $validPoints;
        $data['photos'] = $uploadedPhotos;

        try {
            $this->lifecycle->submitFindings($survey, $user, $data);
            $gpsNote = count($validPoints) > 0 ? (count($validPoints) . ' GPS boundary points and ') : '';
            return back()->with('success', 'Field survey recorded: ' . $gpsNote . count($uploadedPhotos) . ' land photo(s) submitted to Deputy Director.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function requestCorrection(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('requestCorrection', $application)) {
            abort(403, 'Unauthorized to request correction on this application.');
        }

        $data = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->lifecycle->transition($application, Application::STAGE_CORRECTION_REQUIRED, $user, [
                'remarks' => $data['remarks'],
            ]);
            return back()->with('success', 'Correction requested from applicant. Application status updated.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function verifyPayment(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('verifyPayment', $application)) {
            abort(403, 'Only authorized Accounts Officers can verify payments.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->lifecycle->transition($application, Application::STAGE_PAYMENT_VERIFIED, $user, [
                'note' => $data['note'] ?? 'Challan payment deposit verified by Accounts Officer.',
            ]);

            // Advance to eligible beneficiary
            $this->lifecycle->transition($application, Application::STAGE_ELIGIBLE_BENEFICIARY, $user, [
                'note' => 'Beneficiary confirmed eligible following complete payment verification.',
            ]);

            return back()->with('success', 'Payment verified and beneficiary cleared for benefit allocation.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function allocateStock(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('allocateStock', $application)) {
            abort(403, 'Unauthorized to allocate stock for this application.');
        }

        $data = $request->validate([
            'allocated_quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->lifecycle->transition($application, Application::STAGE_STOCK_ALLOCATED, $user, $data);
            return back()->with('success', "Stock allocated successfully ({$data['allocated_quantity']} units).");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reactivateDormant(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('reactivate', $application)) {
            abort(403, 'Unauthorized to reactivate dormant applications. DD authority required.');
        }

        $data = $request->validate([
            'documented_reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->lifecycle->reactivateDormant($application, $user, $data['documented_reason']);
            return back()->with('success', 'Dormant application reactivated with fresh 14-day challan issued.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function assignDeputyDirector(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasAnyRole(['Super Admin', 'Secretary', 'Division Director'])) {
            abort(403, 'Unauthorized to assign Deputy Director.');
        }

        $data = $request->validate([
            'deputy_director_id' => ['required', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $dd = User::findOrFail($data['deputy_director_id']);

        DB::transaction(function () use ($application, $dd, $user, $data) {
            $application->update([
                'assigned_to' => $dd->id,
                'assigned_at' => now(),
                'is_unassigned' => false,
                'current_stage' => Application::STAGE_UNDER_REVIEW,
                'status' => 'pending',
                'stage_updated_at' => now(),
                'lock_version' => $application->lock_version + 1,
            ]);

            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => Application::STAGE_SUBMITTED,
                'to_status' => Application::STAGE_UNDER_REVIEW,
                'user_id' => $user->id,
                'note' => "Assigned from unassigned queue to Deputy Director {$dd->name}. Note: " . ($data['note'] ?? 'N/A'),
            ]);

            AccessAuditLog::log('application_dd_manually_assigned', Application::class, (string) $application->id, [
                'assigned_by' => $user->name,
                'assigned_to' => $dd->name,
                'note' => $data['note'] ?? null,
            ]);
        });

        return back()->with('success', "Application successfully assigned to Deputy Director {$dd->name}.");
    }

    public function assignFieldOfficer(Request $request, Application $application): RedirectResponse
    {
        return $this->assignSurvey($request, $application);
    }

    public function submitTaskReport(Request $request, ApplicationAssignment $assignment): RedirectResponse
    {
        $user = auth()->user();

        if ((int) $assignment->assigned_to !== (int) $user->id && ! $user->hasRole('Super Admin')) {
            abort(403, 'You are not assigned to this task.');
        }

        $rawGps = $request->input('gps_points');
        if (is_string($rawGps)) {
            $rawGps = json_decode($rawGps, true) ?: [];
        } elseif (! is_array($rawGps)) {
            $rawGps = [];
        }

        $validPoints = [];
        foreach ($rawGps as $pt) {
            if (isset($pt['lat'], $pt['lng']) && is_numeric($pt['lat']) && is_numeric($pt['lng'])) {
                $validPoints[] = [
                    'point_number' => count($validPoints) + 1,
                    'lat' => round((float) $pt['lat'], 7),
                    'lng' => round((float) $pt['lng'], 7),
                    'accuracy' => isset($pt['accuracy']) && is_numeric($pt['accuracy']) ? round((float) $pt['accuracy'], 2) : null,
                    'label' => ! empty($pt['label']) ? (string) $pt['label'] : 'Point ' . (count($validPoints) + 1),
                    'timestamp' => $pt['timestamp'] ?? now()->toIso8601String(),
                ];
            }
        }

        $data = $request->validate([
            'status' => ['required', 'in:in_progress,completed'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'land_verified_kanals' => ['nullable', 'numeric'],
            'recommendation' => ['nullable', 'in:recommended,not_recommended,needs_further_audit'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'photo_1' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'photo_2' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'photo_3' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $uploadedPhotos = [];
        $photoFiles = [];

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $file) {
                if ($file && $file->isValid()) {
                    $photoFiles[] = ['file' => $file, 'label' => 'Land Photo ' . (count($photoFiles) + 1)];
                }
            }
        }

        foreach (['photo_1' => 'General Land View', 'photo_2' => 'Irrigation / Water Source', 'photo_3' => 'Road Access / Parcel Corner'] as $key => $defaultLabel) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                if ($file && $file->isValid()) {
                    $photoFiles[] = ['file' => $file, 'label' => $defaultLabel];
                }
            }
        }

        $photoFiles = array_slice($photoFiles, 0, 3);

        foreach ($photoFiles as $item) {
            $file = $item['file'];
            $path = $file->store('surveys/photos/' . $assignment->application_id, 'public');
            $uploadedPhotos[] = [
                'path' => $path,
                'url' => asset('storage/' . $path),
                'name' => $file->getClientOriginalName(),
                'size_kb' => round($file->getSize() / 1024, 1),
                'label' => $item['label'],
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        $assignment->status = $data['status'];
        $assignment->notes = $data['notes'] ?? $assignment->notes;
        $assignment->report_data = [
            'land_verified_kanals' => $data['land_verified_kanals'] ?? null,
            'recommendation' => $data['recommendation'] ?? null,
            'gps_points' => $validPoints,
            'photos' => $uploadedPhotos,
            'submitted_at' => now()->toIso8601String(),
            'ip' => $request->ip(),
        ];

        if ($data['status'] === 'completed') {
            $assignment->completed_at = now();
        }

        $assignment->save();

        // Also sync with active ApplicationSurvey if linked
        $survey = \App\Models\ApplicationSurvey::where('application_id', $assignment->application_id)
            ->where('assigned_to', $user->id)
            ->where('status', '!=', \App\Models\ApplicationSurvey::STATUS_FINDINGS_SUBMITTED)
            ->first();

        if ($survey && $data['status'] === 'completed') {
            $this->lifecycle->submitFindings($survey, $user, [
                'land_verified_kanals' => $data['land_verified_kanals'] ?? null,
                'recommendation' => $data['recommendation'] ?? \App\Models\ApplicationSurvey::REC_RECOMMENDED,
                'findings_summary' => $data['notes'] ?? 'Task verification completed.',
                'gps_points' => $validPoints,
                'photos' => $uploadedPhotos,
            ]);
        }

        AccessAuditLog::log('task_report_submitted', ApplicationAssignment::class, (string) $assignment->id, [
            'status' => $data['status'],
            'application_id' => $assignment->application_id,
            'gps_points_count' => count($validPoints),
            'photos_count' => count($uploadedPhotos),
        ]);

        return back()->with('success', 'Task report recorded successfully.');
    }

    private function allowedTransitions($user, Application $application): array
    {
        // Technical Super Admin, Secretary, and Division Directors have ZERO default approval authority
        if ($user->hasAnyRole(['Super Admin', 'Secretary', 'Division Director']) &&
            ! $user->hasAnyRole(['District Deputy Director', 'District Officer'])) {
            return [];
        }

        $allowed = [];
        $currentStage = $application->current_stage ?: $application->status;

        // Deputy Director permissions
        if ($user->hasAnyRole(['District Deputy Director', 'District Officer'])) {
            $canRejectUnpaid = ! $application->isChallanPaid() && (int) ($application->distributed_quantity ?? 0) <= 0;

            if (in_array($currentStage, ['pending', Application::STAGE_SUBMITTED], true)) {
                $allowed[] = Application::STAGE_UNDER_REVIEW;
                $allowed[] = Application::STAGE_FIELD_SURVEY_ASSIGNED;
                $allowed[] = Application::STAGE_APPROVED;
                $allowed[] = Application::STAGE_REJECTED;
                $allowed[] = Application::STAGE_CORRECTION_REQUIRED;
                // legacy
                $allowed[] = 'district_verified';
                $allowed[] = 'directorate_approved';
                $allowed[] = 'rejected';
            } elseif ($currentStage === Application::STAGE_UNDER_REVIEW) {
                $allowed[] = Application::STAGE_FIELD_SURVEY_ASSIGNED;
                $allowed[] = Application::STAGE_APPROVED;
                $allowed[] = Application::STAGE_REJECTED;
                $allowed[] = Application::STAGE_CORRECTION_REQUIRED;
                $allowed[] = 'directorate_approved';
            } elseif (in_array($currentStage, [
                Application::STAGE_CORRECTION_REQUIRED,
                Application::STAGE_FIELD_SURVEY_ASSIGNED,
                Application::STAGE_VISIT_SCHEDULED,
                Application::STAGE_VISIT_COMPLETED,
            ], true)) {
                $allowed[] = Application::STAGE_REJECTED;
            } elseif ($currentStage === Application::STAGE_FINDINGS_SUBMITTED) {
                $allowed[] = Application::STAGE_APPROVED;
                $allowed[] = Application::STAGE_REJECTED;
                $allowed[] = Application::STAGE_CORRECTION_REQUIRED;
                $allowed[] = 'directorate_approved';
            } elseif ($currentStage === 'district_verified') {
                $allowed[] = Application::STAGE_APPROVED;
                $allowed[] = Application::STAGE_REJECTED;
                $allowed[] = 'directorate_approved';
            } elseif ($currentStage === Application::STAGE_DORMANT) {
                $allowed[] = Application::STAGE_UNDER_REVIEW;
                $allowed[] = Application::STAGE_CHALLAN_ISSUED;
                if ($canRejectUnpaid) {
                    $allowed[] = Application::STAGE_REJECTED;
                }
            } elseif (in_array($currentStage, [Application::STAGE_APPROVED, 'directorate_approved'], true)) {
                $allowed[] = Application::STAGE_CHALLAN_ISSUED;
                $allowed[] = Application::STAGE_ELIGIBLE_BENEFICIARY;
                if ($canRejectUnpaid) {
                    $allowed[] = Application::STAGE_REJECTED;
                }
            } elseif (in_array($currentStage, [
                Application::STAGE_CHALLAN_ISSUED,
                Application::STAGE_PAYMENT_PROOF_SUBMITTED,
                Application::STAGE_ELIGIBLE_BENEFICIARY,
                Application::STAGE_STOCK_ALLOCATED,
            ], true)) {
                if ($currentStage === Application::STAGE_CHALLAN_ISSUED && ! $application->isChallanPaid()) {
                    $allowed[] = Application::STAGE_APPROVED;
                }
                if ($canRejectUnpaid) {
                    $allowed[] = Application::STAGE_REJECTED;
                }
            }
        }

        // Accounts Officer
        if ($user->can('payments.verify') || $user->hasRole('Accounts Officer')) {
            if ($currentStage === Application::STAGE_PAYMENT_PROOF_SUBMITTED) {
                $allowed[] = Application::STAGE_PAYMENT_VERIFIED;
                $allowed[] = Application::STAGE_CORRECTION_REQUIRED;
            }
        }

        // Distribution Officer
        if ($user->can('distribution.confirm_delivery') || $user->hasRole('Distribution Officer')) {
            if ($currentStage === Application::STAGE_ELIGIBLE_BENEFICIARY) {
                $allowed[] = Application::STAGE_STOCK_ALLOCATED;
            } elseif ($currentStage === Application::STAGE_STOCK_ALLOCATED) {
                $allowed[] = Application::STAGE_PARTIALLY_DISTRIBUTED;
                $allowed[] = Application::STAGE_FULLY_DISTRIBUTED;
                $allowed[] = 'disbursed';
            } elseif ($currentStage === Application::STAGE_PARTIALLY_DISTRIBUTED) {
                $allowed[] = Application::STAGE_FULLY_DISTRIBUTED;
                $allowed[] = 'disbursed';
            }
        }

        return array_values(array_unique($allowed));
    }

    /**
     * Show form to edit application data.
     */
    public function edit(Application $application): View
    {
        $user = auth()->user();

        if (! $user->can('update', $application)) {
            abort(403, 'Unauthorized: You do not have permission to modify this application.');
        }

        $application->loadMissing([
            'scheme.department',
            'applicant.district',
            'district',
            'division',
            'form.fields',
            'fieldValues.formField',
            'editAuditLogs.user',
        ]);

        $districts = District::when(! $user->isGlobalScope(), function ($q) use ($user) {
            $q->whereIn('id', $user->authorizedDistrictIds());
        })->orderBy('name')->get();

        $formFields = $application->form?->fields()->orderBy('display_order')->get() ?? collect();
        $fieldValuesKeyed = $application->fieldValues->keyBy('form_field_id');

        return view('admin.applications.edit', compact(
            'application',
            'districts',
            'formFields',
            'fieldValuesKeyed'
        ));
    }

    /**
     * Update application data and log changes with previous/new values and user session details.
     */
    public function update(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('update', $application)) {
            abort(403, 'Unauthorized: You do not have permission to modify this application.');
        }

        $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'district_id' => ['required', 'exists:districts,id'],
            'tehsil' => ['nullable', 'string', 'max:150'],
            'union_council' => ['nullable', 'string', 'max:150'],
            'village' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'demanded_quantity' => ['nullable', 'numeric', 'min:0.01'],
            'approved_quantity' => ['nullable', 'numeric', 'min:0'],
            'allocated_quantity' => ['nullable', 'integer', 'min:0'],
            'distributed_quantity' => ['nullable', 'integer', 'min:0'],
            'challan_amount' => ['nullable', 'numeric', 'min:0'],
            'challan_due_date' => ['nullable', 'date'],
            'verification_note' => ['nullable', 'string', 'max:2000'],
            'modification_reason' => ['required', 'string', 'min:5', 'max:1000'],
            'approved_items' => ['nullable', 'array'],
            'approved_items.*' => ['nullable', 'numeric', 'min:0'],
            'fields' => ['nullable', 'array'],
        ]);

        $application->loadMissing(['applicant', 'fieldValues.formField', 'items', 'bills', 'scheme.challanTemplate']);
        $applicant = $application->applicant;
        $isPaidOrDisbursedLocked = $application->isChallanPaid() || (int) ($application->distributed_quantity ?? 0) > 0;

        $oldValues = [];
        $newValues = [];
        $changedFields = [];

        // Track Applicant changes
        $applicantMap = [
            'full_name' => 'Full Name',
            'father_name' => 'Father / Husband Name',
            'phone' => 'Contact Phone',
            'email' => 'Email Address',
            'gender' => 'Gender',
            'tehsil' => 'Tehsil',
            'union_council' => 'Union Council',
            'village' => 'Village',
            'address' => 'Residential Address',
        ];

        foreach ($applicantMap as $attr => $label) {
            $oldVal = (string) ($applicant?->{$attr} ?? '');
            $newVal = (string) ($request->input($attr) ?? '');

            if ($oldVal !== $newVal) {
                $oldValues["applicant.{$attr}"] = ['label' => $label, 'value' => $oldVal];
                $newValues["applicant.{$attr}"] = ['label' => $label, 'value' => $newVal];
                $changedFields[] = "applicant.{$attr}";
            }
        }

        $canEditChallanFee = ! $isPaidOrDisbursedLocked
            && $user->hasAnyRole(['Super Admin', 'Director Agriculture', 'Director Livestock', 'Director Fisheries']);

        // Track Application core attributes
        $appAttributes = [
            'district_id' => 'District ID',
            'demanded_quantity' => 'Demanded Quantity',
            'approved_quantity' => 'Approved Quantity',
            'allocated_quantity' => 'Allocated Quantity',
            'distributed_quantity' => 'Distributed Quantity',
        ];

        if ($canEditChallanFee) {
            $appAttributes['challan_amount'] = 'Challan Amount';
        }

        $appAttributes['challan_due_date'] = 'Challan Due Date';
        $appAttributes['verification_note'] = 'Verification Note';

        foreach ($appAttributes as $attr => $label) {
            // If optional numeric/date fields are omitted from request, don't treat omitted inputs as clearing changes
            if (in_array($attr, ['demanded_quantity', 'approved_quantity', 'allocated_quantity', 'distributed_quantity', 'challan_amount', 'challan_due_date'], true) && ! $request->filled($attr)) {
                continue;
            }

            $oldRaw = $application->{$attr};
            $oldVal = $oldRaw instanceof \Carbon\Carbon ? $oldRaw->format('Y-m-d') : (string) ($oldRaw ?? '');
            
            $newRaw = $request->input($attr);
            if ($attr === 'challan_amount' && is_numeric($newRaw)) {
                $newVal = (string) ((int) ceil((float) $newRaw));
            } else {
                $newVal = (string) ($newRaw ?? '');
            }

            if ($oldVal !== $newVal && ! ($oldVal === '' && $newVal === '')) {
                $oldValues["application.{$attr}"] = ['label' => $label, 'value' => $oldVal];
                $newValues["application.{$attr}"] = ['label' => $label, 'value' => $newVal];
                $changedFields[] = "application.{$attr}";
            }
        }

        // Enforce Paid/Disbursed immutability on quantities, itemized approvals, and fee modifications
        if ($isPaidOrDisbursedLocked) {
            $attemptedFeeEdit = $request->filled('challan_amount')
                && (int) ceil((float) $request->input('challan_amount')) !== (int) ceil((float) ($application->challan_amount ?? 0));
            $attemptedQtyEdit = isset($newValues['application.demanded_quantity'])
                || isset($newValues['application.approved_quantity'])
                || isset($newValues['application.allocated_quantity'])
                || isset($newValues['application.distributed_quantity']);

            $attemptedItemEdit = false;
            $submittedApprovedItemsCheck = (array) $request->input('approved_items', []);
            if (! empty($submittedApprovedItemsCheck) && $application->items->isNotEmpty()) {
                foreach ($application->items as $item) {
                    $key = array_key_exists((string) $item->id, $submittedApprovedItemsCheck) ? (string) $item->id : (array_key_exists($item->id, $submittedApprovedItemsCheck) ? $item->id : null);
                    if ($key !== null && abs((float) $submittedApprovedItemsCheck[$key] - (float) ($item->approved_qty ?? $item->demanded_qty)) > 0.001) {
                        $attemptedItemEdit = true;
                        break;
                    }
                }
            }

            if ($attemptedFeeEdit || $attemptedQtyEdit || $attemptedItemEdit) {
                return back()->with('error', 'Cannot modify item quantities, allocations, or challan fee because challan payment has already been verified or stock has been disbursed.')->withInput();
            }
        }

        // Track Itemized Approved Quantities changes
        $submittedApprovedItems = (array) $request->input('approved_items', []);
        $approvedItemsTotal = null;
        if (! empty($submittedApprovedItems) && $application->items->isNotEmpty()) {
            $sum = 0.0;
            foreach ($application->items as $item) {
                $key = array_key_exists((string) $item->id, $submittedApprovedItems) ? (string) $item->id : (array_key_exists($item->id, $submittedApprovedItems) ? $item->id : null);
                $oldQty = (float) ($item->approved_qty ?? $item->demanded_qty);
                $newQty = $key !== null ? max(0.0, (float) $submittedApprovedItems[$key]) : $oldQty;
                $sum += $newQty;
                if (abs($newQty - $oldQty) > 0.001) {
                    $oldValues["application_item.{$item->id}"] = ['label' => "Item: {$item->item_label}", 'value' => (string) $oldQty];
                    $newValues["application_item.{$item->id}"] = ['label' => "Item: {$item->item_label}", 'value' => (string) $newQty];
                    $changedFields[] = "application_item.{$item->id}";
                }
            }
            if ($sum <= 0) {
                return back()->with('error', 'At least one requested item must have an approved quantity greater than 0. To deny all items, please reject the application.')->withInput();
            }
            $approvedItemsTotal = $sum;
        }

        // Track Dynamic Form Fields
        $submittedFields = (array) $request->input('fields', []);
        $formFieldsById = $application->form?->fields->keyBy('id') ?? collect();
        $existingValuesByFieldId = $application->fieldValues->keyBy('form_field_id');

        foreach ($submittedFields as $fieldId => $subVal) {
            $formField = $formFieldsById->get($fieldId);
            $fieldLabel = $formField ? $formField->field_label : "Field #{$fieldId}";
            $existingRecord = $existingValuesByFieldId->get($fieldId);
            $oldVal = (string) ($existingRecord?->value ?? '');
            $newVal = is_array($subVal) ? json_encode($subVal) : (string) ($subVal ?? '');

            if ($oldVal !== $newVal) {
                if ($isPaidOrDisbursedLocked && $formField?->field_type === 'checkbox_with_text') {
                    return back()->with('error', 'Cannot modify selected tree/scheme items after challan payment has been verified or stock has been disbursed.')->withInput();
                }
                $oldValues["form_field.{$fieldId}"] = ['label' => $fieldLabel, 'value' => $oldVal];
                $newValues["form_field.{$fieldId}"] = ['label' => $fieldLabel, 'value' => $newVal];
                $changedFields[] = "form_field.{$fieldId}";
            }
        }

        if (empty($changedFields)) {
            return back()->with('info', 'No modifications were detected. All values remain unchanged.');
        }

        DB::transaction(function () use ($application, $applicant, $request, $submittedFields, $submittedApprovedItems, $approvedItemsTotal, $oldValues, $newValues, $changedFields, $user, $canEditChallanFee, $isPaidOrDisbursedLocked) {
            $lockedApp = Application::where('id', $application->id)->with(['items', 'bills', 'scheme.challanTemplate', 'district', 'applicant'])->lockForUpdate()->firstOrFail();

            // 1. Update Applicant
            if ($applicant) {
                $applicant->update([
                    'full_name' => $request->input('full_name'),
                    'father_name' => $request->input('father_name'),
                    'phone' => $request->input('phone'),
                    'email' => $request->input('email'),
                    'gender' => $request->input('gender'),
                    'district_id' => $request->input('district_id'),
                    'tehsil' => $request->input('tehsil'),
                    'union_council' => $request->input('union_council'),
                    'village' => $request->input('village'),
                    'address' => $request->input('address'),
                ]);
            }

            // 2. Sync itemized approved quantities if provided
            $hasItemQtyChanges = false;
            $schemeTemplate = $lockedApp->scheme?->getChallanTemplate();
            if (! empty($submittedApprovedItems) && $lockedApp->items->isNotEmpty()) {
                foreach ($lockedApp->items as $item) {
                    $key = array_key_exists((string) $item->id, $submittedApprovedItems) ? (string) $item->id : (array_key_exists($item->id, $submittedApprovedItems) ? $item->id : null);
                    if ($key !== null) {
                        $newQty = max(0.0, (float) $submittedApprovedItems[$key]);
                        if (abs($newQty - (float) ($item->approved_qty ?? $item->demanded_qty)) > 0.001) {
                            $hasItemQtyChanges = true;
                        }
                        $item->approved_qty = $newQty;
                        $item->recalculateFinancials($schemeTemplate, $isPaidOrDisbursedLocked)->save();
                    }
                }
                $lockedApp->load('items');
            }

            // 3. Update Application
            $newDistrictId = (int) $request->input('district_id');
            $divisionId = DB::table('division_districts')->where('district_id', $newDistrictId)->value('division_id');

            $resolvedApprovedQty = $approvedItemsTotal !== null
                ? $approvedItemsTotal
                : ($request->filled('approved_quantity') ? (float) $request->input('approved_quantity') : $lockedApp->approved_quantity);

            $appUpdates = [
                'district_id' => $newDistrictId,
                'division_id' => $divisionId ?: $lockedApp->division_id,
                'demanded_quantity' => $request->filled('demanded_quantity') ? (float) $request->input('demanded_quantity') : $lockedApp->demanded_quantity,
                'approved_quantity' => $resolvedApprovedQty,
                'allocated_quantity' => $approvedItemsTotal !== null
                    ? (int) round($approvedItemsTotal)
                    : ($request->filled('allocated_quantity') ? (int) $request->input('allocated_quantity') : $lockedApp->allocated_quantity),
                'distributed_quantity' => $request->filled('distributed_quantity') ? (int) $request->input('distributed_quantity') : $lockedApp->distributed_quantity,
                'verification_note' => $request->input('verification_note'),
                'lock_version' => ($lockedApp->lock_version ?? 1) + 1,
            ];

            if ($canEditChallanFee && $request->filled('challan_amount')) {
                $appUpdates['challan_amount'] = (int) ceil((float) $request->input('challan_amount'));
            }

            if ($request->filled('challan_due_date')) {
                $appUpdates['challan_due_date'] = $request->input('challan_due_date');
            }

            $lockedApp->update($appUpdates);

            // Sync pending Bill/PSID or convert to Rs. 0 eligible_beneficiary on unpaid challan_issued edits
            $qtyOrFeeModified = $hasItemQtyChanges
                || isset($newValues['application.approved_quantity'])
                || ($canEditChallanFee && isset($newValues['application.challan_amount']));

            if (
                ! $isPaidOrDisbursedLocked
                && $qtyOrFeeModified
                && in_array($lockedApp->current_stage, [Application::STAGE_CHALLAN_ISSUED, Application::STAGE_APPROVED, 'directorate_approved'], true)
            ) {
                $lockedApp->load(['items', 'scheme.challanTemplate', 'district', 'applicant', 'bills']);
                $breakdown = $lockedApp->getChallanBreakdown();
                $effectivePayable = ($canEditChallanFee && isset($newValues['application.challan_amount']))
                    ? (int) ceil((float) $request->input('challan_amount'))
                    : (int) ceil((float) ($breakdown['final_bill'] ?? 0));

                if ($lockedApp->scheme?->requires_challan && $effectivePayable > 0) {
                    $lockedApp->challan_amount = $effectivePayable;
                    if (! $lockedApp->challan_no) {
                        $lockedApp->challan_no = 'CHL-' . date('Y') . '-' . str_pad((string) $lockedApp->id, 6, '0', STR_PAD_LEFT);
                    }
                    $lockedApp->saveQuietly();
                    app(\App\Services\GbpayService::class)->createOrUpdateBillForApplication($lockedApp, (float) $effectivePayable, false);
                } else {
                    $lockedApp->bills()->where('status', 'pending')->update(['status' => 'cancelled']);
                    $fromStage = $lockedApp->current_stage ?: $lockedApp->status;
                    $lockedApp->update([
                        'current_stage' => Application::STAGE_ELIGIBLE_BENEFICIARY,
                        'status' => 'directorate_approved',
                        'challan_no' => null,
                        'challan_amount' => null,
                        'challan_due_date' => null,
                        'challan_issued_at' => null,
                        'psid' => null,
                        'stage_updated_at' => now(),
                        'lock_version' => ($lockedApp->lock_version ?? 1) + 1,
                    ]);

                    ApplicationStatusLog::create([
                        'application_id' => $lockedApp->id,
                        'from_status' => $fromStage,
                        'to_status' => Application::STAGE_ELIGIBLE_BENEFICIARY,
                        'user_id' => $user->id,
                        'note' => 'Net payable dropped to Rs. 0 after approved item/fee update; pending PSID cancelled and application promoted to Eligible Beneficiary.',
                    ]);
                }
            }

            // 4. Update Dynamic Form Field Values
            foreach ($submittedFields as $fieldId => $val) {
                $stringVal = is_array($val) ? json_encode($val) : (string) ($val ?? '');
                \App\Models\ApplicationFieldValue::updateOrCreate(
                    [
                        'application_id' => $lockedApp->id,
                        'form_field_id' => $fieldId,
                    ],
                    [
                        'value' => $stringVal,
                    ]
                );
            }

            // 5. Record Detailed Audit Log in application_edit_audit_logs
            \App\Models\ApplicationEditAuditLog::create([
                'application_id' => $lockedApp->id,
                'application_no' => $lockedApp->application_no,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_role' => $user->getRoleNames()->first() ?? 'Admin',
                'action' => 'application_data_modified',
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => $changedFields,
                'modification_reason' => $request->input('modification_reason'),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'modified_at' => now(),
            ]);

            // 6. Also log into universal AccessAuditLog for cross-system query compliance
            AccessAuditLog::log('application_modified', Application::class, (string) $lockedApp->id, [
                'application_no' => $lockedApp->application_no,
                'changed_count' => count($changedFields),
                'changed_fields' => $changedFields,
                'modification_reason' => $request->input('modification_reason'),
            ]);

            // 7. Record Application Status / Action Log
            ApplicationStatusLog::create([
                'application_id' => $lockedApp->id,
                'from_status' => $lockedApp->current_stage ?: $lockedApp->status,
                'to_status' => $lockedApp->current_stage ?: $lockedApp->status,
                'user_id' => $user->id,
                'note' => 'Application data modified by ' . $user->name . '. Reason: ' . $request->input('modification_reason') . ' (' . count($changedFields) . ' field(s) updated)',
            ]);
        });

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', 'Application data modified successfully and audit log recorded (' . count($changedFields) . ' field(s) updated).');
    }
}
