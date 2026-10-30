<?php

namespace App\Services;

use App\Models\AccessAuditLog;
use App\Models\Application;
use App\Models\ApplicationAssignment;
use App\Models\ApplicationStatusLog;
use App\Models\ApplicationSurvey;
use App\Models\District;
use App\Models\Office;
use App\Models\Scheme;
use App\Models\User;
use App\Models\UserOfficeAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ApplicationLifecycleService
{
    public const LEGAL_TRANSITIONS = [
        Application::STAGE_SUBMITTED => [
            Application::STAGE_UNDER_REVIEW,
            Application::STAGE_FIELD_SURVEY_ASSIGNED,
            Application::STAGE_CORRECTION_REQUIRED,
            Application::STAGE_APPROVED,
            Application::STAGE_REJECTED,
            // Legacy aliases
            'district_verified',
            'directorate_approved',
            'rejected',
        ],
        'pending' => [
            Application::STAGE_UNDER_REVIEW,
            Application::STAGE_FIELD_SURVEY_ASSIGNED,
            Application::STAGE_CORRECTION_REQUIRED,
            Application::STAGE_APPROVED,
            Application::STAGE_REJECTED,
            'district_verified',
            'directorate_approved',
            'rejected',
        ],
        Application::STAGE_UNDER_REVIEW => [
            Application::STAGE_CORRECTION_REQUIRED,
            Application::STAGE_FIELD_SURVEY_ASSIGNED,
            Application::STAGE_APPROVED,
            Application::STAGE_REJECTED,
            'district_verified',
            'directorate_approved',
        ],
        Application::STAGE_CORRECTION_REQUIRED => [
            Application::STAGE_UNDER_REVIEW,
            Application::STAGE_SUBMITTED,
            Application::STAGE_REJECTED,
        ],
        Application::STAGE_FIELD_SURVEY_ASSIGNED => [
            Application::STAGE_VISIT_SCHEDULED,
            Application::STAGE_VISIT_COMPLETED,
            Application::STAGE_FINDINGS_SUBMITTED,
            Application::STAGE_UNDER_REVIEW,
            Application::STAGE_REJECTED,
        ],
        Application::STAGE_VISIT_SCHEDULED => [
            Application::STAGE_VISIT_COMPLETED,
            Application::STAGE_FINDINGS_SUBMITTED,
            Application::STAGE_UNDER_REVIEW,
        ],
        Application::STAGE_VISIT_COMPLETED => [
            Application::STAGE_FINDINGS_SUBMITTED,
            Application::STAGE_UNDER_REVIEW,
        ],
        Application::STAGE_FINDINGS_SUBMITTED => [
            Application::STAGE_APPROVED,
            Application::STAGE_REJECTED,
            Application::STAGE_CORRECTION_REQUIRED,
            Application::STAGE_UNDER_REVIEW,
            'district_verified',
            'directorate_approved',
        ],
        'district_verified' => [
            Application::STAGE_APPROVED,
            Application::STAGE_REJECTED,
            'directorate_approved',
            Application::STAGE_CHALLAN_ISSUED,
            Application::STAGE_ELIGIBLE_BENEFICIARY,
        ],
        Application::STAGE_APPROVED => [
            Application::STAGE_CHALLAN_ISSUED,
            Application::STAGE_ELIGIBLE_BENEFICIARY,
            Application::STAGE_REJECTED,
        ],
        'directorate_approved' => [
            Application::STAGE_CHALLAN_ISSUED,
            Application::STAGE_ELIGIBLE_BENEFICIARY,
            Application::STAGE_STOCK_ALLOCATED,
            Application::STAGE_PARTIALLY_DISTRIBUTED,
            Application::STAGE_FULLY_DISTRIBUTED,
            'disbursed',
            Application::STAGE_REJECTED,
        ],
        Application::STAGE_CHALLAN_ISSUED => [
            Application::STAGE_PAYMENT_PROOF_SUBMITTED,
            Application::STAGE_DORMANT,
            Application::STAGE_REJECTED,
        ],
        Application::STAGE_PAYMENT_PROOF_SUBMITTED => [
            Application::STAGE_PAYMENT_VERIFIED,
            Application::STAGE_CORRECTION_REQUIRED,
            Application::STAGE_REJECTED,
        ],
        Application::STAGE_PAYMENT_VERIFIED => [
            Application::STAGE_ELIGIBLE_BENEFICIARY,
            Application::STAGE_STOCK_ALLOCATED,
        ],
        Application::STAGE_ELIGIBLE_BENEFICIARY => [
            Application::STAGE_STOCK_ALLOCATED,
            Application::STAGE_PARTIALLY_DISTRIBUTED,
            Application::STAGE_FULLY_DISTRIBUTED,
            'disbursed',
        ],
        Application::STAGE_STOCK_ALLOCATED => [
            Application::STAGE_PARTIALLY_DISTRIBUTED,
            Application::STAGE_FULLY_DISTRIBUTED,
            Application::STAGE_COMPLETED,
            'disbursed',
        ],
        Application::STAGE_PARTIALLY_DISTRIBUTED => [
            Application::STAGE_FULLY_DISTRIBUTED,
            Application::STAGE_COMPLETED,
            'disbursed',
        ],
        Application::STAGE_FULLY_DISTRIBUTED => [
            Application::STAGE_COMPLETED,
        ],
        'disbursed' => [
            Application::STAGE_COMPLETED,
        ],
        Application::STAGE_DORMANT => [
            Application::STAGE_UNDER_REVIEW,
            Application::STAGE_APPROVED,
            Application::STAGE_CHALLAN_ISSUED,
            Application::STAGE_REJECTED,
        ],
    ];

    /**
     * Process initial submission: resolve hierarchy, auto-assign to DD or route to unassigned queue.
     */
    public function onSubmission(Application $application): Application
    {
        return DB::transaction(function () use ($application) {
            $scheme = $application->scheme ?? Scheme::find($application->scheme_id);
            $departmentId = $scheme?->department_id;
            $districtId = $application->district_id;

            // 1. Resolve division using configured organizational hierarchy
            $divisionId = null;
            if ($districtId) {
                $divisionId = DB::table('division_districts')
                    ->where('district_id', $districtId)
                    ->value('division_id');
            }

            // 2. Identify active Deputy Director with matching department and district scope
            $dd = $this->findActiveDeputyDirector($departmentId, $districtId);

            $assignedTo = $dd?->id;
            $responsibleOfficeId = null;
            $isUnassigned = ($dd === null);

            if ($dd) {
                $activeAssignment = $dd->officeAssignments()
                    ->active()
                    ->where(function ($q) use ($districtId, $departmentId) {
                        $q->where('district_id', $districtId)
                            ->orWhere('department_id', $departmentId);
                    })
                    ->first();
                $responsibleOfficeId = $activeAssignment?->office_id;
            }

            if (! $responsibleOfficeId && $districtId) {
                $office = Office::where('district_id', $districtId)->first();
                $responsibleOfficeId = $office?->id;
            }

            $application->update([
                'department_id' => $departmentId,
                'division_id' => $divisionId,
                'assigned_to' => $assignedTo,
                'assigned_at' => $assignedTo ? now() : null,
                'responsible_office_id' => $responsibleOfficeId,
                'is_unassigned' => $isUnassigned,
                'demanded_quantity' => (float) ($application->demanded_quantity ?: ($application->getBagsRequired() ?: 1)),
                'current_stage' => Application::STAGE_SUBMITTED,
                'status' => 'pending', // backward compatibility
                'received_at' => now(),
                'stage_updated_at' => now(),
                'lock_version' => 1,
            ]);

            // 3. Complete immutable history recording
            if ($isUnassigned) {
                AccessAuditLog::log('application_unassigned_queue', Application::class, (string) $application->id, [
                    'reason' => 'No active Deputy Director assigned for department ID '.$departmentId.' and district ID '.$districtId,
                    'district_id' => $districtId,
                    'department_id' => $departmentId,
                ]);

                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'from_status' => null,
                    'to_status' => Application::STAGE_SUBMITTED,
                    'note' => 'Application received and placed in unassigned queue (no active DD for district/dept).',
                ]);
            } else {
                AccessAuditLog::log('application_auto_assigned', Application::class, (string) $application->id, [
                    'assigned_to' => $dd->name,
                    'assigned_to_id' => $dd->id,
                    'district_id' => $districtId,
                    'department_id' => $departmentId,
                ]);

                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'from_status' => null,
                    'to_status' => Application::STAGE_SUBMITTED,
                    'user_id' => $dd->id,
                    'note' => "Application received and automatically assigned to Deputy Director {$dd->name}.",
                ]);
            }

            // 4. Send automated Registration SMS (with Application ID & CNIC as Password)
            try {
                app(\App\Services\ZongSmsService::class)->sendForApplicationEvent($application, 'submitted');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Registration SMS dispatch failed', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $application;
        });
    }

    /**
     * Find active Deputy Director matching department and district.
     */
    public function findActiveDeputyDirector(?int $departmentId, ?int $districtId): ?User
    {
        if (! $districtId) {
            return null;
        }

        // Strategy 1: Active UserOfficeAssignment with matching role and district/department
        $query = User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($rq) {
                $rq->whereIn('name', ['District Deputy Director', 'District Officer']);
            })
            ->whereHas('officeAssignments', function ($oq) use ($districtId, $departmentId) {
                $oq->active()->where('district_id', $districtId);
                if ($departmentId) {
                    $oq->where(function ($sq) use ($departmentId) {
                        $sq->whereNull('department_id')
                            ->orWhere('department_id', $departmentId);
                    });
                }
            });

        $user = $query->first();
        if ($user) {
            return $user;
        }

        // Strategy 2: Direct district_id on users table
        return User::query()
            ->where('is_active', true)
            ->where('district_id', $districtId)
            ->whereHas('roles', function ($rq) {
                $rq->whereIn('name', ['District Deputy Director', 'District Officer']);
            })
            ->when($departmentId, function ($dq) use ($departmentId) {
                $dq->where(function ($sub) use ($departmentId) {
                    $sub->whereNull('department_id')
                        ->orWhere('department_id', $departmentId);
                });
            })
            ->first();
    }

    /**
     * Check if a transition from current stage to target stage is legally permitted.
     */
    public function isTransitionAllowed(string $currentStage, string $targetStage): bool
    {
        $allowed = self::LEGAL_TRANSITIONS[$currentStage] ?? [];
        return in_array($targetStage, $allowed, true);
    }

    /**
     * Perform an atomic state transition with validation, optimistic concurrency control, and audit trail.
     */
    public function transition(
        Application $application,
        string $targetStage,
        User $actor,
        array $params = [],
        ?int $expectedLockVersion = null
    ): Application {
        return DB::transaction(function () use ($application, $targetStage, $actor, $params, $expectedLockVersion) {
            // Lock row for update
            $locked = Application::where('id', $application->id)->lockForUpdate()->firstOrFail();

            if ($expectedLockVersion !== null && $locked->lock_version !== $expectedLockVersion) {
                throw new RuntimeException("Concurrent update detected: Application was modified by another session (expected v{$expectedLockVersion}, found v{$locked->lock_version}).");
            }

            $currentStage = $locked->current_stage ?: $locked->status;

            if (! $this->isTransitionAllowed($currentStage, $targetStage)) {
                throw ValidationException::withMessages([
                    'transition' => "Illegal state transition from '{$currentStage}' to '{$targetStage}'.",
                ]);
            }

            // Role & Scope Validation
            $this->validateActorAuthority($locked, $targetStage, $actor);

            $fromStage = $currentStage;
            $updates = [
                'current_stage' => $targetStage,
                'stage_updated_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ];

            // Synchronize legacy status column
            $updates['status'] = match ($targetStage) {
                Application::STAGE_APPROVED => 'directorate_approved',
                Application::STAGE_FULLY_DISTRIBUTED, Application::STAGE_COMPLETED => 'disbursed',
                Application::STAGE_REJECTED => 'rejected',
                Application::STAGE_FIELD_SURVEY_ASSIGNED, Application::STAGE_VISIT_SCHEDULED,
                Application::STAGE_VISIT_COMPLETED, Application::STAGE_FINDINGS_SUBMITTED => 'district_verified',
                default => in_array($targetStage, ['pending', 'district_verified', 'directorate_approved', 'disbursed', 'rejected'], true)
                    ? $targetStage
                    : $locked->status,
            };

            // Specific stage handling
            switch ($targetStage) {
                case Application::STAGE_UNDER_REVIEW:
                    $updates['assigned_to'] = $updates['assigned_to'] ?? $locked->assigned_to ?? $actor->id;
                    $updates['assigned_at'] = $locked->assigned_at ?? now();
                    $updates['is_unassigned'] = false;
                    break;

                case Application::STAGE_CORRECTION_REQUIRED:
                    $remarks = $params['remarks'] ?? $params['note'] ?? 'Correction requested by Deputy Director.';
                    $updates['correction_remarks'] = $remarks;
                    break;

                case Application::STAGE_APPROVED:
                case 'directorate_approved':
                    $note = $params['note'] ?? $params['verification_note'] ?? 'Approved by competent authority.';
                    $updates['verification_note'] = $note;

                    // Capture demanded and approved quantities
                    $demandedQty = $locked->getDemandedQuantity();
                    if ($locked->demanded_quantity === null) {
                        $updates['demanded_quantity'] = $demandedQty;
                    }

                    $approvedQty = isset($params['approved_quantity']) && is_numeric($params['approved_quantity']) && (float) $params['approved_quantity'] > 0
                        ? (float) $params['approved_quantity']
                        : (float) ($locked->approved_quantity ?: $demandedQty);

                    $updates['approved_quantity'] = $approvedQty;
                    $updates['allocated_quantity'] = (int) round($approvedQty);
                    $locked->approved_quantity = $approvedQty;
                    $locked->allocated_quantity = (int) round($approvedQty);

                    // Check if scheme requires challan or is fee-free
                    $scheme = $locked->scheme;
                    if ($scheme?->requires_challan) {
                        $template = $scheme->getChallanTemplate();
                        $validityDays = (int) ($template->validity_days ?: 10);

                        // Issue Challan automatically based on APPROVED quantity and district seed rate
                        $breakdown = $locked->getChallanBreakdown();
                        $amount = isset($params['challan_amount']) && is_numeric($params['challan_amount'])
                            ? (float) ceil((float) $params['challan_amount'])
                            : (float) $breakdown['final_bill'];

                        $updates['current_stage'] = Application::STAGE_CHALLAN_ISSUED;
                        $updates['challan_no'] = 'CHL-'.date('Y').'-'.str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT);
                        $updates['challan_amount'] = $amount;
                        $updates['challan_due_date'] = now()->addDays($validityDays)->toDateString();
                        $updates['challan_issued_at'] = now();
                    } else {
                        // Fee-free scheme directly advances to eligible beneficiary
                        $updates['current_stage'] = Application::STAGE_ELIGIBLE_BENEFICIARY;
                    }
                    break;

                case Application::STAGE_REJECTED:
                case 'rejected':
                    $reason = $params['rejection_reason'] ?? $params['note'] ?? 'Application rejected.';
                    $updates['rejection_reason'] = $reason;
                    break;

                case Application::STAGE_PAYMENT_PROOF_SUBMITTED:
                    if (! empty($params['payment_receipt_path'])) {
                        $updates['payment_receipt_path'] = $params['payment_receipt_path'];
                    }
                    $updates['challan_paid_at'] = now();
                    break;

                case Application::STAGE_PAYMENT_VERIFIED:
                    $updates['payment_verified_at'] = now();
                    $updates['payment_verified_by'] = $actor->id;
                    break;

                case Application::STAGE_ELIGIBLE_BENEFICIARY:
                    // Beneficiary confirmed
                    break;

                case Application::STAGE_STOCK_ALLOCATED:
                    $qty = isset($params['allocated_quantity']) && is_numeric($params['allocated_quantity'])
                        ? (float) $params['allocated_quantity']
                        : (isset($params['approved_quantity']) && is_numeric($params['approved_quantity'])
                            ? (float) $params['approved_quantity']
                            : (float) ($locked->approved_quantity ?: $locked->getBagsRequired() ?: 1));
                    $updates['allocated_quantity'] = (int) round($qty);
                    $updates['approved_quantity'] = $qty;
                    break;

                case Application::STAGE_PARTIALLY_DISTRIBUTED:
                    $distQty = (int) ($params['distributed_quantity'] ?? 1);
                    $updates['distributed_quantity'] = $distQty;
                    $updates['distributed_at'] = now();
                    break;

                case Application::STAGE_FULLY_DISTRIBUTED:
                case 'disbursed':
                    $totalQty = $locked->allocated_quantity ?: ($locked->getBagsRequired() ?: 1);
                    $updates['distributed_quantity'] = $totalQty;
                    $updates['distributed_at'] = now();
                    break;

                case Application::STAGE_COMPLETED:
                    $updates['current_stage'] = Application::STAGE_COMPLETED;
                    break;

                case Application::STAGE_DORMANT:
                    // Handled when 14-day challan expires
                    break;
            }

            $locked->update($updates);

            // Auto-generate or sync GBPay Bill & 20-digit PSID when Challan is issued
            if (($updates['current_stage'] ?? null) === Application::STAGE_CHALLAN_ISSUED) {
                app(\App\Services\GbpayService::class)->createOrUpdateBillForApplication(
                    $locked,
                    isset($updates['challan_amount']) ? (float) $updates['challan_amount'] : null
                );
            }

            // If Accounts Officer manually verifies payment receipt, sync any pending Bill to 'paid'
            if ($targetStage === Application::STAGE_PAYMENT_VERIFIED) {
                $pendingBill = $locked->bills()->where('status', 'pending')->first();
                if ($pendingBill) {
                    $pendingBill->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'transaction_id' => $pendingBill->transaction_id ?: ('MANUAL-' . $locked->id . '-' . time()),
                        'bank_mnemonic' => 'Manual Receipt',
                    ]);
                }
            }

            // Record immutable history log
            ApplicationStatusLog::create([
                'application_id' => $locked->id,
                'from_status' => $fromStage,
                'to_status' => $updates['current_stage'],
                'user_id' => $actor->id,
                'note' => $params['note'] ?? $params['remarks'] ?? $params['rejection_reason'] ?? "Transitioned to {$updates['current_stage']}.",
            ]);

            AccessAuditLog::log('application_lifecycle_transition', Application::class, (string) $locked->id, [
                'from_stage' => $fromStage,
                'to_stage' => $updates['current_stage'],
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'actor_role' => $actor->getRoleNames()->first() ?? 'Officer',
                'lock_version' => $locked->lock_version,
                'params' => $params,
            ]);

            // Send automated SMS notification for the new application status
            try {
                $smsEventKey = match ($targetStage) {
                    Application::STAGE_APPROVED, 'directorate_approved' => 'approved',
                    Application::STAGE_REJECTED, 'rejected' => 'rejected',
                    Application::STAGE_FULLY_DISTRIBUTED, 'disbursed' => 'fully_distributed',
                    default => $targetStage,
                };

                app(\App\Services\ZongSmsService::class)->sendForApplicationEvent(
                    $locked->fresh(['applicant', 'scheme.department', 'district', 'bills']),
                    $smsEventKey,
                    $params,
                    $actor->id
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Status transition SMS dispatch failed', [
                    'application_id' => $locked->id,
                    'target_stage' => $targetStage,
                    'error' => $e->getMessage(),
                ]);
            }

            return $locked;
        });
    }

    /**
     * Validate actor's authority and operational scope for the target stage.
     */
    private function validateActorAuthority(Application $application, string $targetStage, User $actor): void
    {
        // 1. Cross-district isolation check: Actor must have jurisdiction over the application's district
        if (! $actor->isGlobalScope() && ! $actor->hasRole('Super Admin')) {
            $authorizedDistricts = $actor->authorizedDistrictIds();
            if (! empty($authorizedDistricts) && ! in_array($application->district_id, $authorizedDistricts, true)) {
                throw ValidationException::withMessages([
                    'authorization' => "Access denied: You do not have administrative authority for District ID {$application->district_id}.",
                ]);
            }
        }

        // 2. Administrative decisions reserved for Deputy Director / District Officer alone
        $ddStages = [
            Application::STAGE_APPROVED,
            Application::STAGE_REJECTED,
            Application::STAGE_CORRECTION_REQUIRED,
            Application::STAGE_FIELD_SURVEY_ASSIGNED,
            'district_verified',
            'directorate_approved',
            'rejected',
        ];

        if (in_array($targetStage, $ddStages, true)) {
            // Field Officers CANNOT approve or reject cases
            if ($actor->hasRole('Field Officer') && ! $actor->hasAnyRole(['Super Admin', 'District Deputy Director', 'District Officer'])) {
                throw ValidationException::withMessages([
                    'role' => 'Field officers cannot approve, reject, or make final administrative decisions.',
                ]);
            }

            // Secretary, Super Admin, Division Director have zero default approval rights
            if ($actor->hasAnyRole(['Super Admin', 'Secretary', 'Division Director']) &&
                ! $actor->hasAnyRole(['District Deputy Director', 'District Officer'])) {
                throw ValidationException::withMessages([
                    'role' => 'Secretariat and Division Directors have observational oversight and cannot make final operational approvals.',
                ]);
            }
        }

        // 3. Payment verification: Accounts Officer ALONE
        if ($targetStage === Application::STAGE_PAYMENT_VERIFIED) {
            if (! $actor->can('payments.verify') && ! $actor->hasAnyRole(['Accounts Officer', 'Super Admin'])) {
                throw ValidationException::withMessages([
                    'role' => 'Only Accounts Officers are authorized to verify payments.',
                ]);
            }
        }

        // 4. Distribution / delivery confirmation
        if (in_array($targetStage, [Application::STAGE_STOCK_ALLOCATED, Application::STAGE_PARTIALLY_DISTRIBUTED, Application::STAGE_FULLY_DISTRIBUTED], true)) {
            if (! $actor->can('distribution.confirm_delivery') && ! $actor->hasAnyRole(['Distribution Officer', 'District Deputy Director', 'District Officer', 'Super Admin'])) {
                throw ValidationException::withMessages([
                    'role' => 'Only Distribution Officers or Deputy Directors are authorized to allocate or disburse stock.',
                ]);
            }
        }
    }

    /**
     * Deputy Director delegates Field Survey to an authorized Field Officer.
     */
    public function assignSurvey(Application $application, User $dd, User $fieldOfficer, array $data = []): ApplicationSurvey
    {
        return DB::transaction(function () use ($application, $dd, $fieldOfficer, $data) {
            // Verify Field Officer belongs to application district
            if (! in_array($application->district_id, $fieldOfficer->authorizedDistrictIds(), true) && ! $fieldOfficer->hasRole('Super Admin')) {
                throw ValidationException::withMessages([
                    'field_officer' => 'Selected Field Officer does not have operational jurisdiction in this district.',
                ]);
            }

            // Create ApplicationSurvey record
            $survey = ApplicationSurvey::create([
                'application_id' => $application->id,
                'assigned_to' => $fieldOfficer->id,
                'assigned_by' => $dd->id,
                'status' => ApplicationSurvey::STATUS_ASSIGNED,
                'findings_summary' => $data['instructions'] ?? null,
            ]);

            // Create legacy ApplicationAssignment record for backward compatibility
            ApplicationAssignment::create([
                'application_id' => $application->id,
                'assigned_to' => $fieldOfficer->id,
                'assigned_by' => $dd->id,
                'task_type' => ApplicationAssignment::TASK_LAND_VERIFICATION,
                'status' => ApplicationAssignment::STATUS_ASSIGNED,
                'due_date' => $data['due_date'] ?? now()->addDays(3),
                'notes' => $data['instructions'] ?? 'Field verification required for scheme application.',
            ]);

            // Transition application stage
            $this->transition($application, Application::STAGE_FIELD_SURVEY_ASSIGNED, $dd, [
                'note' => "Field survey assigned to Field Officer {$fieldOfficer->name}.",
                'field_officer_id' => $fieldOfficer->id,
            ]);

            return $survey;
        });
    }

    /**
     * Field Officer schedules visit.
     */
    public function scheduleVisit(ApplicationSurvey $survey, User $officer, Carbon $date, ?string $notes = null): ApplicationSurvey
    {
        return DB::transaction(function () use ($survey, $officer, $date, $notes) {
            if ($survey->assigned_to !== $officer->id && ! $officer->hasRole('Super Admin')) {
                throw ValidationException::withMessages(['survey' => 'You are not assigned to this field survey.']);
            }

            $survey->update([
                'status' => ApplicationSurvey::STATUS_VISIT_SCHEDULED,
                'scheduled_visit_at' => $date,
                'findings_summary' => $notes ?: $survey->findings_summary,
            ]);

            $this->transition($survey->application, Application::STAGE_VISIT_SCHEDULED, $officer, [
                'note' => "Field visit scheduled for {$date->format('d M Y, h:i A')}.",
            ]);

            return $survey;
        });
    }

    /**
     * Field Officer completes visit.
     */
    public function completeVisit(ApplicationSurvey $survey, User $officer, ?string $notes = null): ApplicationSurvey
    {
        return DB::transaction(function () use ($survey, $officer, $notes) {
            if ($survey->assigned_to !== $officer->id && ! $officer->hasRole('Super Admin')) {
                throw ValidationException::withMessages(['survey' => 'You are not assigned to this field survey.']);
            }

            $survey->update([
                'status' => ApplicationSurvey::STATUS_VISIT_COMPLETED,
                'visit_completed_at' => now(),
                'findings_summary' => $notes ?: $survey->findings_summary,
            ]);

            $this->transition($survey->application, Application::STAGE_VISIT_COMPLETED, $officer, [
                'note' => 'Field survey visit physically completed on site.',
            ]);

            return $survey;
        });
    }

    /**
     * Field Officer submits findings and recommendation.
     */
    public function submitFindings(ApplicationSurvey $survey, User $officer, array $data): ApplicationSurvey
    {
        return DB::transaction(function () use ($survey, $officer, $data) {
            if ($survey->assigned_to !== $officer->id && ! $officer->hasRole('Super Admin')) {
                throw ValidationException::withMessages(['survey' => 'You are not assigned to this field survey.']);
            }

            $gpsPoints = $data['gps_points'] ?? $survey->gps_points ?? [];
            if (is_string($gpsPoints)) {
                $gpsPoints = json_decode($gpsPoints, true) ?: [];
            }

            $photos = $data['photos'] ?? $survey->photos ?? [];
            if (is_string($photos)) {
                $photos = json_decode($photos, true) ?: [];
            }

            // Generate representative gps_coordinates string
            $gpsCoordsString = $data['gps_coordinates'] ?? $survey->gps_coordinates ?? null;
            if (! empty($gpsPoints)) {
                $p1 = $gpsPoints[0];
                $lat1 = round($p1['lat'] ?? 0, 6);
                $lng1 = round($p1['lng'] ?? 0, 6);
                $ptCount = count($gpsPoints);
                $gpsCoordsString = "{$lat1}, {$lng1} ({$ptCount} boundary points)";
            }

            $evidenceData = $data['evidence_data'] ?? $survey->evidence_data ?? [];
            if (! is_array($evidenceData)) {
                $evidenceData = [];
            }
            $evidenceData['gps_points'] = $gpsPoints;
            $evidenceData['photos'] = $photos;
            $evidenceData['submitted_by'] = [
                'id' => $officer->id,
                'name' => $officer->name,
                'email' => $officer->email,
            ];

            $survey->update([
                'status' => ApplicationSurvey::STATUS_FINDINGS_SUBMITTED,
                'findings_submitted_at' => now(),
                'land_verified_kanals' => $data['land_verified_kanals'] ?? null,
                'water_source_verified' => $data['water_source_verified'] ?? null,
                'road_access_verified' => $data['road_access_verified'] ?? null,
                'recommendation' => $data['recommendation'] ?? ApplicationSurvey::REC_RECOMMENDED,
                'findings_summary' => $data['findings_summary'] ?? 'Survey findings submitted.',
                'evidence_data' => $evidenceData,
                'gps_coordinates' => $gpsCoordsString,
                'gps_points' => $gpsPoints,
                'photos' => $photos,
            ]);

            // Also update legacy assignment if linked
            ApplicationAssignment::where('application_id', $survey->application_id)
                ->where('assigned_to', $officer->id)
                ->update([
                    'status' => ApplicationAssignment::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'report_data' => [
                        'land_verified_kanals' => $data['land_verified_kanals'] ?? null,
                        'recommendation' => $data['recommendation'] ?? ApplicationSurvey::REC_RECOMMENDED,
                        'gps_points' => $gpsPoints,
                        'photos' => $photos,
                        'submitted_at' => now()->toIso8601String(),
                    ],
                ]);

            $ptCount = count($gpsPoints);
            $photoCount = count($photos);
            $note = "Findings submitted with recommendation: {$survey->recommendation}";
            if ($ptCount > 0) {
                $note .= " ({$ptCount} GPS points";
                if ($photoCount > 0) {
                    $note .= ", {$photoCount} land photos";
                }
                $note .= ")";
            }

            $this->transition($survey->application, Application::STAGE_FINDINGS_SUBMITTED, $officer, [
                'note' => $note,
                'recommendation' => $survey->recommendation,
                'land_verified_kanals' => $survey->land_verified_kanals,
            ]);

            return $survey;
        });
    }

    /**
     * Citizen resubmits correction.
     */
    public function submitCorrection(Application $application, array $data = []): Application
    {
        return DB::transaction(function () use ($application, $data) {
            $application->update([
                'correction_submitted_at' => now(),
                'current_stage' => Application::STAGE_UNDER_REVIEW,
                'stage_updated_at' => now(),
                'lock_version' => $application->lock_version + 1,
            ]);

            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => Application::STAGE_CORRECTION_REQUIRED,
                'to_status' => Application::STAGE_UNDER_REVIEW,
                'note' => 'Correction resubmitted by citizen with updated information.',
            ]);

            AccessAuditLog::log('application_correction_resubmitted', Application::class, (string) $application->id, [
                'resubmitted_at' => now(),
            ]);

            return $application;
        });
    }

    /**
     * Expire unpaid challans that have exceeded the 14 calendar day window into Dormant status.
     */
    public function expireUnpaidChallans(): int
    {
        $today = now()->toDateString();
        $expiredApps = Application::where('current_stage', Application::STAGE_CHALLAN_ISSUED)
            ->whereNotNull('challan_due_date')
            ->where('challan_due_date', '<', $today)
            ->get();

        $count = 0;
        foreach ($expiredApps as $app) {
            DB::transaction(function () use ($app) {
                $app->update([
                    'current_stage' => Application::STAGE_DORMANT,
                    'stage_updated_at' => now(),
                    'lock_version' => $app->lock_version + 1,
                ]);

                $app->bills()->where('status', 'pending')->update([
                    'status' => 'cancelled',
                ]);

                ApplicationStatusLog::create([
                    'application_id' => $app->id,
                    'from_status' => Application::STAGE_CHALLAN_ISSUED,
                    'to_status' => Application::STAGE_DORMANT,
                    'note' => 'Unpaid challan expired after 14 calendar days. Status moved to dormant.',
                ]);

                AccessAuditLog::log('challan_expired_to_dormant', Application::class, (string) $app->id, [
                    'due_date' => $app->challan_due_date,
                    'challan_no' => $app->challan_no,
                ]);

                try {
                    app(\App\Services\ZongSmsService::class)->sendForApplicationEvent($app, 'dormant');
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Dormant SMS dispatch failed', ['application_id' => $app->id]);
                }
            });
            $count++;
        }

        return $count;
    }

    /**
     * Reactivate a dormant application with a documented DD decision.
     */
    public function reactivateDormant(Application $application, User $dd, string $documentedReason): Application
    {
        return DB::transaction(function () use ($application, $dd, $documentedReason) {
            $this->validateActorAuthority($application, Application::STAGE_APPROVED, $dd);

            if ($application->current_stage !== Application::STAGE_DORMANT) {
                throw ValidationException::withMessages(['application' => 'Application is not in dormant status.']);
            }

            if (empty(trim($documentedReason))) {
                throw ValidationException::withMessages(['reactivation_reason' => 'Documented administrative decision is required to reactivate a dormant application.']);
            }

            // Generate fresh challan with configured validity expiry
            $scheme = $application->scheme;
            $template = $scheme?->getChallanTemplate() ?? \App\Models\ChallanTemplate::getDefault();
            $validityDays = (int) ($template->validity_days ?: 10);

            $breakdown = $application->getChallanBreakdown();
            $amount = (float) $breakdown['final_bill'];

            $application->update([
                'current_stage' => Application::STAGE_CHALLAN_ISSUED,
                'challan_due_date' => now()->addDays($validityDays)->toDateString(),
                'challan_issued_at' => now(),
                'challan_amount' => $amount,
                'reactivated_at' => now(),
                'reactivated_by' => $dd->id,
                'reactivation_reason' => $documentedReason,
                'stage_updated_at' => now(),
                'lock_version' => $application->lock_version + 1,
            ]);

            // Issue fresh GBPay Bill & 20-digit PSID upon reactivation
            app(\App\Services\GbpayService::class)->createOrUpdateBillForApplication($application, $amount);

            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => Application::STAGE_DORMANT,
                'to_status' => Application::STAGE_CHALLAN_ISSUED,
                'user_id' => $dd->id,
                'note' => "Application reactivated by Deputy Director {$dd->name}. Reason: {$documentedReason}",
            ]);

            AccessAuditLog::log('application_reactivated_from_dormant', Application::class, (string) $application->id, [
                'reactivated_by' => $dd->name,
                'reason' => $documentedReason,
                'new_due_date' => $application->challan_due_date,
            ]);

            try {
                app(\App\Services\ZongSmsService::class)->sendForApplicationEvent(
                    $application->fresh(['applicant', 'scheme.department', 'district', 'bills']),
                    'reactivated',
                    ['reason' => $documentedReason],
                    $dd->id
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Reactivation SMS dispatch failed', ['application_id' => $application->id]);
            }

            return $application;
        });
    }
}
