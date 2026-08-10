<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carousel_slides', function (Blueprint $table) {
            $table->string('title_ur')->nullable()->after('title');
            $table->text('subtitle_ur')->nullable()->after('subtitle');
            $table->string('button_label_ur')->nullable()->after('button_label');
            $table->string('button2_label_ur')->nullable()->after('button2_label');
        });

        Schema::table('schemes', function (Blueprint $table) {
            $table->string('title_ur')->nullable()->after('title');
            $table->longText('description_ur')->nullable()->after('description');
            $table->longText('eligibility_criteria_ur')->nullable()->after('eligibility_criteria');
            $table->longText('terms_conditions_ur')->nullable()->after('terms_conditions');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->string('name_ur')->nullable()->after('name');
            $table->text('vision_ur')->nullable()->after('vision');
            $table->text('mission_ur')->nullable()->after('mission');
            $table->text('objectives_ur')->nullable()->after('objectives');
            $table->text('leadership_info_ur')->nullable()->after('leadership_info');
        });

        Schema::table('districts', function (Blueprint $table) {
            $table->string('name_ur')->nullable()->after('name');
        });

        if (Schema::hasTable('department_stats')) {
            Schema::table('department_stats', function (Blueprint $table) {
                if (! Schema::hasColumn('department_stats', 'stat_label_ur')) {
                    $table->string('stat_label_ur')->nullable()->after('stat_label');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('carousel_slides', function (Blueprint $table) {
            $table->dropColumn(['title_ur', 'subtitle_ur', 'button_label_ur', 'button2_label_ur']);
        });

        Schema::table('schemes', function (Blueprint $table) {
            $table->dropColumn(['title_ur', 'description_ur', 'eligibility_criteria_ur', 'terms_conditions_ur']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['name_ur', 'vision_ur', 'mission_ur', 'objectives_ur', 'leadership_info_ur']);
        });

        Schema::table('districts', function (Blueprint $table) {
            $table->dropColumn(['name_ur']);
        });

        if (Schema::hasTable('department_stats') && Schema::hasColumn('department_stats', 'stat_label_ur')) {
            Schema::table('department_stats', function (Blueprint $table) {
                $table->dropColumn(['stat_label_ur']);
            });
        }
    }
};
