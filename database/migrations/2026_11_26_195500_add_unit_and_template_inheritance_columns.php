<?php

use App\Models\ApplicationItem;
use App\Models\FormField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schemes', function (Blueprint $table) {
            if (! Schema::hasColumn('schemes', 'unit_label')) {
                $table->string('unit_label', 60)->nullable()->after('challan_template_id');
            }
        });

        Schema::table('application_items', function (Blueprint $table) {
            if (! Schema::hasColumn('application_items', 'inherits_template_share')) {
                $table->boolean('inherits_template_share')->default(false)->after('govt_share_pct');
            }
        });

        // Backfill inherits_template_share for existing paid items whose Form Builder option left govt_share_pct blank
        if (Schema::hasTable('application_items') && Schema::hasTable('form_fields')) {
            $fieldsById = FormField::whereNotNull('option_configs_json')->get()->keyBy('id');

            ApplicationItem::query()
                ->where('is_free', false)
                ->whereNotNull('form_field_id')
                ->orderBy('id')
                ->chunk(200, function ($items) use ($fieldsById) {
                    foreach ($items as $item) {
                        $field = $fieldsById->get($item->form_field_id);
                        if (! $field) {
                            continue;
                        }
                        $cfg = $field->resolveOptionConfig($item->item_key ?: $item->item_label);
                        if ($cfg !== null && $cfg['govt_share_pct'] === null) {
                            DB::table('application_items')
                                ->where('id', $item->id)
                                ->update(['inherits_template_share' => true]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('application_items', function (Blueprint $table) {
            if (Schema::hasColumn('application_items', 'inherits_template_share')) {
                $table->dropColumn('inherits_template_share');
            }
        });

        Schema::table('schemes', function (Blueprint $table) {
            if (Schema::hasColumn('schemes', 'unit_label')) {
                $table->dropColumn('unit_label');
            }
        });
    }
};
