<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('vision')->nullable();
            $table->text('mission')->nullable();
            $table->text('objectives')->nullable();
            $table->text('leadership_info')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('password')->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('district_id');
        });

        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('type', ['running', 'proposed'])->default('running');
            $table->enum('scope', ['gb', 'district'])->default('gb');
            $table->decimal('budget', 15, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('last_date')->nullable();
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->longText('description')->nullable();
            $table->longText('eligibility_criteria')->nullable();
            $table->longText('terms_conditions')->nullable();
            $table->string('featured_image')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::create('scheme_districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quota')->default(0);
            $table->timestamps();
            $table->unique(['scheme_id', 'district_id']);
        });

        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('field_label');
            $table->string('field_type'); // text, textarea, number, radio, checkbox, select, switch, district, cnic, phone
            $table->boolean('is_core')->default(false);
            $table->boolean('is_mandatory')->default(false);
            $table->json('validation_rules')->nullable();
            $table->json('options_json')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->string('placeholder')->nullable();
            $table->string('help_text')->nullable();
            $table->timestamps();
        });

        Schema::create('form_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->string('document_name');
            $table->boolean('is_mandatory')->default(true);
            $table->string('allowed_extensions')->default('pdf,jpg,jpeg,png');
            $table->unsignedInteger('max_size_mb')->default(5);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('department_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('stat_label');
            $table->string('stat_value');
            $table->string('icon')->nullable();
            $table->enum('source', ['manual', 'auto'])->default('manual');
            $table->string('auto_metric')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('cnic', 13)->unique();
            $table->string('full_name');
            $table->string('father_name')->nullable();
            $table->string('phone', 20);
            $table->boolean('phone_verified')->default(false);
            $table->foreignId('district_id')->constrained();
            $table->string('tehsil')->nullable();
            $table->string('union_council')->nullable();
            $table->string('village')->nullable();
            $table->string('address')->nullable();
            $table->string('land_location')->nullable();
            $table->string('email')->nullable();
            $table->string('gender')->nullable();
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no')->unique();
            $table->foreignId('scheme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->constrained();
            $table->foreignId('form_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', [
                'pending',
                'district_verified',
                'directorate_approved',
                'disbursed',
                'rejected',
            ])->default('pending');
            $table->text('verification_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['scheme_id', 'applicant_id']);
        });

        Schema::create('application_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_field_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'form_field_id'], 'app_field_unique');
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_document_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('application_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code', 10);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scheme_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('cnic', 13);
            $table->string('phone', 20);
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
            $table->unique(['scheme_id', 'cnic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_interests');
        Schema::dropIfExists('otp_verifications');
        Schema::dropIfExists('application_status_logs');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('application_field_values');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('department_stats');
        Schema::dropIfExists('form_documents');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('forms');
        Schema::dropIfExists('scheme_districts');
        Schema::dropIfExists('schemes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('district_id');
            $table->dropColumn('is_active');
        });
        Schema::dropIfExists('districts');
        Schema::dropIfExists('departments');
    }
};
