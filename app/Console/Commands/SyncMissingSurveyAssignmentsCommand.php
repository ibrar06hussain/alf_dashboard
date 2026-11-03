<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ApplicationAssignment;
use App\Models\ApplicationSurvey;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncMissingSurveyAssignmentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alf:sync-missing-surveys {--dry-run : Only display what would be fixed without updating} {--app-id= : Specific application ID to fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finds applications in field_survey_assigned status that lack application_surveys and application_assignments, and creates them.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $specificAppId = $this->option('app-id');

        $query = Application::query()
            ->where('current_stage', Application::STAGE_FIELD_SURVEY_ASSIGNED)
            ->whereDoesntHave('assignments')
            ->with(['district', 'scheme', 'statusLogs']);

        if ($specificAppId) {
            $query->where('id', $specificAppId);
        }

        $apps = $query->get();

        if ($apps->isEmpty()) {
            $this->info('No orphan field_survey_assigned applications found! Everything is in sync.');
            return self::SUCCESS;
        }

        $this->warn("Found {$apps->count()} applications in 'field_survey_assigned' missing survey/assignment records.");

        foreach ($apps as $app) {
            $this->line("----------------------------------------------------------------");
            $this->info("Processing App #{$app->id} ({$app->application_no}) - District: " . ($app->district->name ?? 'ID '.$app->district_id));

            // 1. Determine assigning Deputy Director
            $assigningDdId = $app->assigned_to;
            if (! $assigningDdId) {
                $statusLog = $app->statusLogs()
                    ->where('to_status', Application::STAGE_FIELD_SURVEY_ASSIGNED)
                    ->latest()
                    ->first();
                $assigningDdId = $statusLog?->user_id;
            }

            // Fallback: Find district Deputy Director
            if (! $assigningDdId) {
                $dd = User::role(['District Deputy Director', 'District Officer'])
                    ->where('district_id', $app->district_id)
                    ->where('is_active', true)
                    ->first();
                $assigningDdId = $dd?->id;
            }

            // 2. Find eligible Field Officer for this district
            $fieldOfficer = User::role('Field Officer')
                ->where('is_active', true)
                ->where(function ($q) use ($app) {
                    $q->where('district_id', $app->district_id)
                      ->orWhereHas('officeAssignments', fn($sq) => $sq->where('district_id', $app->district_id));
                })
                ->first();

            // If not found by district, fallback to any active Field Officer in that district or system
            if (! $fieldOfficer) {
                $fieldOfficer = User::role('Field Officer')
                    ->where('district_id', $app->district_id)
                    ->first();
            }

            if (! $fieldOfficer) {
                $this->error("Cannot auto-assign App #{$app->id}: No Field Officer found for District ID {$app->district_id}. Please create a Field Officer for this district first.");
                continue;
            }

            $instructions = 'Field verification of applicant land and scheme eligibility.';
            $statusLog = $app->statusLogs()
                ->where('to_status', Application::STAGE_FIELD_SURVEY_ASSIGNED)
                ->latest()
                ->first();
            if ($statusLog && ! empty($statusLog->note) && ! str_starts_with($statusLog->note, 'Transitioned to')) {
                $instructions = $statusLog->note;
            }

            $this->line("   -> Assigning DD ID: " . ($assigningDdId ?? 'None (will use Super Admin/System)'));
            $this->line("   -> Target Field Officer: {$fieldOfficer->name} (ID: {$fieldOfficer->id}, {$fieldOfficer->email})");
            $this->line("   -> Instructions: {$instructions}");

            if ($isDryRun) {
                $this->comment("   [DRY RUN] Would create application_surveys & application_assignments entries.");
                continue;
            }

            DB::transaction(function () use ($app, $assigningDdId, $fieldOfficer, $instructions) {
                // Ensure application_surveys record exists
                $survey = ApplicationSurvey::firstOrCreate(
                    [
                        'application_id' => $app->id,
                        'assigned_to' => $fieldOfficer->id,
                    ],
                    [
                        'assigned_by' => $assigningDdId ?: $fieldOfficer->id,
                        'status' => ApplicationSurvey::STATUS_ASSIGNED,
                        'findings_summary' => $instructions,
                    ]
                );

                // Ensure application_assignments record exists
                $assignment = ApplicationAssignment::firstOrCreate(
                    [
                        'application_id' => $app->id,
                        'assigned_to' => $fieldOfficer->id,
                        'task_type' => ApplicationAssignment::TASK_LAND_VERIFICATION,
                    ],
                    [
                        'assigned_by' => $assigningDdId ?: $fieldOfficer->id,
                        'status' => ApplicationAssignment::STATUS_ASSIGNED,
                        'due_date' => now()->addDays(3),
                        'notes' => $instructions,
                    ]
                );

                // Ensure app status & stage are synchronized
                $app->update([
                    'current_stage' => Application::STAGE_FIELD_SURVEY_ASSIGNED,
                    'status' => 'district_verified',
                    'stage_updated_at' => now(),
                ]);
            });

            $this->info("   [SUCCESS] Created survey and assignment for App #{$app->id}.");
        }

        $this->info("Finished sync.");
        return self::SUCCESS;
    }
}
