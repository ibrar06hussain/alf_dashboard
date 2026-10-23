<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Divisions
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('headquarters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Division Districts Pivot
        Schema::create('division_districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['division_id', 'district_id']);
        });

        // 3. Organizational Units / Offices
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('name');
            $table->enum('office_type', [
                'secretariat',
                'division_directorate',
                'district_deputy_directorate',
                'field_office',
                'accounts_unit',
                'distribution_depot',
            ])->default('district_deputy_directorate');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Multi-Scoped User Office Assignments
        Schema::create('user_office_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('designation')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['department_id', 'district_id']);
            $table->index(['division_id']);
        });

        // 5. Assignment Histories (Audit Trail)
        Schema::create('assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->enum('action', ['assigned', 'updated', 'revoked', 'expired'])->default('assigned');
            $table->json('old_state_json')->nullable();
            $table->json('new_state_json')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 6. Application Task Assignments (Operational Delegation to Field Officers)
        Schema::create('application_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete(); // Field Officer
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete(); // District DD
            $table->enum('task_type', ['land_verification', 'seed_distribution', 'technical_audit'])->default('land_verification');
            $table->enum('status', ['assigned', 'in_progress', 'completed', 'cancelled'])->default('assigned');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('report_data')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
        });

        // 7. Immutable Access & Action Audit Logs
        Schema::create('access_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_audit_logs');
        Schema::dropIfExists('application_assignments');
        Schema::dropIfExists('assignment_histories');
        Schema::dropIfExists('user_office_assignments');
        Schema::dropIfExists('offices');
        Schema::dropIfExists('division_districts');
        Schema::dropIfExists('divisions');
    }
};
