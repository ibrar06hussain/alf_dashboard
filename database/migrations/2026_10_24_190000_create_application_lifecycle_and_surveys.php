<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update applications table: change status column to varchar(50) and add lifecycle tracking columns
        Schema::table('applications', function (Blueprint $table) {
            // Modify status column from ENUM to VARCHAR(50) so all lifecycle stages are supported without data loss
            // We use DB::statement for compatibility across MySQL versions
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `applications` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'submitted'");
        }

        Schema::table('applications', function (Blueprint $table) {
            // Organizational hierarchy & routing
            $table->foreignId('division_id')->nullable()->after('district_id')->constrained('divisions')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('scheme_id')->constrained('departments')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->after('district_id')->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_office_id')->nullable()->after('assigned_to')->constrained('offices')->nullOnDelete();

            // SLA, stage ageing, and unassigned queue
            $table->string('current_stage', 50)->default('submitted')->after('status')->index();
            $table->timestamp('received_at')->nullable()->after('submitted_at');
            $table->timestamp('assigned_at')->nullable()->after('received_at');
            $table->timestamp('stage_updated_at')->nullable()->after('assigned_at');
            $table->boolean('is_unassigned')->default(false)->after('responsible_office_id')->index();

            // Citizen correction workflow
            $table->text('correction_remarks')->nullable()->after('rejection_reason');
            $table->timestamp('correction_submitted_at')->nullable()->after('correction_remarks');

            // Challan & Payment tracking
            $table->string('challan_no', 100)->nullable()->after('correction_submitted_at')->index();
            $table->decimal('challan_amount', 12, 2)->nullable()->after('challan_no');
            $table->date('challan_due_date')->nullable()->after('challan_amount')->index();
            $table->timestamp('challan_issued_at')->nullable()->after('challan_due_date');
            $table->timestamp('challan_paid_at')->nullable()->after('challan_issued_at');
            $table->string('challan_file_path', 255)->nullable()->after('challan_paid_at');
            $table->string('payment_receipt_path', 255)->nullable()->after('challan_file_path');
            $table->timestamp('payment_verified_at')->nullable()->after('payment_receipt_path');
            $table->foreignId('payment_verified_by')->nullable()->after('payment_verified_at')->constrained('users')->nullOnDelete();

            // Reactivation from dormant
            $table->timestamp('reactivated_at')->nullable()->after('payment_verified_by');
            $table->foreignId('reactivated_by')->nullable()->after('reactivated_at')->constrained('users')->nullOnDelete();
            $table->text('reactivation_reason')->nullable()->after('reactivated_by');

            // Distribution & allocation
            $table->unsignedInteger('allocated_quantity')->nullable()->after('reactivation_reason');
            $table->unsignedInteger('distributed_quantity')->default(0)->after('allocated_quantity');
            $table->timestamp('distributed_at')->nullable()->after('distributed_quantity');

            // Optimistic concurrency control
            $table->unsignedInteger('lock_version')->default(0)->after('distributed_at');
        });

        // 2. Add scheme-level rules for challan requirement and unit fee
        Schema::table('schemes', function (Blueprint $table) {
            $table->boolean('requires_challan')->default(false)->after('scope');
            $table->decimal('challan_fee_per_unit', 10, 2)->default(0.00)->after('requires_challan');
        });

        // 3. Create application_surveys table
        Schema::create('application_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->comment('Field Officer');
            $table->foreignId('assigned_by')->constrained('users')->comment('Deputy Director');
            $table->string('status', 50)->default('assigned')->index(); // assigned, visit_scheduled, visit_completed, findings_submitted, cancelled
            $table->timestamp('scheduled_visit_at')->nullable();
            $table->timestamp('visit_completed_at')->nullable();
            $table->timestamp('findings_submitted_at')->nullable();
            $table->decimal('land_verified_kanals', 10, 2)->nullable();
            $table->string('water_source_verified')->nullable();
            $table->string('road_access_verified')->nullable();
            $table->text('findings_summary')->nullable();
            $table->string('recommendation', 50)->nullable(); // recommended, not_recommended, needs_further_audit
            $table->json('evidence_data')->nullable();
            $table->string('gps_coordinates', 100)->nullable();
            $table->timestamps();
        });

        // 4. Backfill existing applications with resolved department, division, and timestamps
        $divisionDistricts = DB::table('division_districts')->pluck('division_id', 'district_id');
        $schemeDepts = DB::table('schemes')->pluck('department_id', 'id');

        DB::table('applications')->orderBy('id')->chunk(250, function ($apps) use ($divisionDistricts, $schemeDepts) {
            foreach ($apps as $app) {
                $divisionId = $divisionDistricts[$app->district_id] ?? null;
                $departmentId = $schemeDepts[$app->scheme_id] ?? null;

                $currentStage = match ($app->status) {
                    'pending' => 'submitted',
                    'district_verified' => 'approved',
                    'directorate_approved' => 'approved',
                    'disbursed' => 'fully_distributed',
                    'rejected' => 'rejected',
                    default => $app->status ?: 'submitted',
                };

                DB::table('applications')->where('id', $app->id)->update([
                    'department_id' => $departmentId,
                    'division_id' => $divisionId,
                    'received_at' => $app->submitted_at ?? $app->created_at ?? now(),
                    'stage_updated_at' => $app->updated_at ?? now(),
                    'current_stage' => $currentStage,
                    'is_unassigned' => true,
                    'lock_version' => 1,
                ]);
            }
        });

        // 5. Configure Wheat Seed Cost Sharing scheme to require challan
        DB::table('schemes')
            ->where('title', 'like', '%Wheat%')
            ->orWhere('slug', 'like', '%wheat%')
            ->update([
                'requires_challan' => true,
                'challan_fee_per_unit' => 3000.00, // 50% subsidized farmer contribution per 50kg bag
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('application_surveys');

        Schema::table('schemes', function (Blueprint $table) {
            $table->dropColumn(['requires_challan', 'challan_fee_per_unit']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['responsible_office_id']);
            $table->dropForeign(['payment_verified_by']);
            $table->dropForeign(['reactivated_by']);

            $table->dropColumn([
                'division_id',
                'department_id',
                'assigned_to',
                'responsible_office_id',
                'current_stage',
                'received_at',
                'assigned_at',
                'stage_updated_at',
                'is_unassigned',
                'correction_remarks',
                'correction_submitted_at',
                'challan_no',
                'challan_amount',
                'challan_due_date',
                'challan_issued_at',
                'challan_paid_at',
                'challan_file_path',
                'payment_receipt_path',
                'payment_verified_at',
                'payment_verified_by',
                'reactivated_at',
                'reactivated_by',
                'reactivation_reason',
                'allocated_quantity',
                'distributed_quantity',
                'distributed_at',
                'lock_version',
            ]);
        });
    }
};
