<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure 'previous_cultivation' field exists for Form 1 (Wheat scheme)
        $form1 = DB::table('forms')->where('id', 1)->first();
        if ($form1) {
            $field = DB::table('form_fields')
                ->where('form_id', 1)
                ->where('field_key', 'previous_cultivation')
                ->first();

            if (! $field) {
                $fieldId = DB::table('form_fields')->insertGetId([
                    'form_id' => 1,
                    'field_key' => 'previous_cultivation',
                    'field_label' => 'Wheat Cultivated Area Last Year (Kanals)',
                    'field_label_ur' => 'گزشتہ سال گندم زیر کاشت رقبہ (کنال)',
                    'field_type' => 'number',
                    'is_core' => 0,
                    'is_mandatory' => 0,
                    'validation_rules' => null,
                    'display_order' => 12,
                    'placeholder' => 'e.g. 5',
                    'placeholder_ur' => 'مثلاً 5',
                    'help_text' => 'Area in Kanals cultivated with wheat during the previous agricultural cycle.',
                    'help_text_ur' => 'پچھلے زرعی سیزن میں گندم کی کاشت کے لیے استعمال ہونے والا رقبہ کنال میں درج کریں۔',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $fieldId = $field->id;
            }

            // 2. Populate realistic previous_cultivation values for any existing wheat applications that lack it
            $wheatApps = DB::table('applications')->where('scheme_id', 1)->get();
            foreach ($wheatApps as $app) {
                $exists = DB::table('application_field_values')
                    ->where('application_id', $app->id)
                    ->where('form_field_id', $fieldId)
                    ->exists();

                if (! $exists) {
                    $landVal = DB::table('application_field_values')
                        ->join('form_fields', 'application_field_values.form_field_id', '=', 'form_fields.id')
                        ->where('application_field_values.application_id', $app->id)
                        ->where('form_fields.field_key', 'land_kanals')
                        ->value('application_field_values.value');

                    $cultVal = is_numeric($landVal) ? max(1.0, round(((float) $landVal) * 0.75, 1)) : 5.0;

                    DB::table('application_field_values')->insert([
                        'application_id' => $app->id,
                        'form_field_id' => $fieldId,
                        'value' => (string) $cultVal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep non-destructive
    }
};
