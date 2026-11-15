<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('forms')) {
            Schema::table('forms', function (Blueprint $table) {
                if (! Schema::hasColumn('forms', 'is_group_application')) {
                    $table->boolean('is_group_application')->default(false)->after('is_published');
                }
                if (! Schema::hasColumn('forms', 'min_group_members')) {
                    $table->unsignedSmallInteger('min_group_members')->default(1)->after('is_group_application');
                }
                if (! Schema::hasColumn('forms', 'max_group_members')) {
                    $table->unsignedSmallInteger('max_group_members')->default(10)->after('min_group_members');
                }
                if (! Schema::hasColumn('forms', 'group_section_title')) {
                    $table->string('group_section_title')->nullable()->after('max_group_members');
                }
                if (! Schema::hasColumn('forms', 'group_section_title_ur')) {
                    $table->string('group_section_title_ur')->nullable()->after('group_section_title');
                }
                if (! Schema::hasColumn('forms', 'member_custom_fields_json')) {
                    $table->json('member_custom_fields_json')->nullable()->after('group_section_title_ur');
                }
            });
        }

        if (! Schema::hasTable('application_members')) {
            Schema::create('application_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
                $table->unsignedSmallInteger('member_order')->default(1);
                $table->string('role', 30)->default('member'); // leader or member
                $table->string('full_name', 150);
                $table->string('father_name', 150)->nullable();
                $table->string('cnic', 20)->index();
                $table->string('phone', 25)->index();
                $table->string('gender', 20)->nullable();
                $table->string('village', 150)->nullable();
                $table->decimal('land_kanals', 10, 2)->nullable();
                $table->json('extra_data')->nullable();
                $table->timestamps();

                $table->index(['application_id', 'member_order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_members');

        if (Schema::hasTable('forms')) {
            Schema::table('forms', function (Blueprint $table) {
                $cols = array_filter([
                    Schema::hasColumn('forms', 'is_group_application') ? 'is_group_application' : null,
                    Schema::hasColumn('forms', 'min_group_members') ? 'min_group_members' : null,
                    Schema::hasColumn('forms', 'max_group_members') ? 'max_group_members' : null,
                    Schema::hasColumn('forms', 'group_section_title') ? 'group_section_title' : null,
                    Schema::hasColumn('forms', 'group_section_title_ur') ? 'group_section_title_ur' : null,
                    Schema::hasColumn('forms', 'member_custom_fields_json') ? 'member_custom_fields_json' : null,
                ]);
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};

