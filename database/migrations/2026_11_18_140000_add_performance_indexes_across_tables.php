<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index definitions: [table, index_name, columns[]].
     *
     * @var array<int, array{0: string, 1: string, 2: string[]}>
     */
    private array $indexes = [
        ['applications', 'idx_apps_district_stage', ['district_id', 'current_stage']],
        ['applications', 'idx_apps_scheme_stage', ['scheme_id', 'current_stage']],
        ['applications', 'idx_apps_status', ['status']],
        ['applications', 'idx_apps_created_at', ['created_at']],
        ['application_field_values', 'idx_app_fv_field_id', ['form_field_id']],
        ['applicants', 'idx_applicants_district_id', ['district_id']],
        ['applicants', 'idx_applicants_full_name', ['full_name']],
        ['sms_logs', 'idx_sms_logs_app_event', ['application_id', 'event_key']],
        ['sms_logs', 'idx_sms_logs_created_at', ['created_at']],
        ['whatsapp_logs', 'idx_wa_logs_app_event', ['application_id', 'event_key']],
        ['whatsapp_logs', 'idx_wa_logs_created_at', ['created_at']],
        ['access_audit_logs', 'idx_audit_logs_action', ['action']],
        ['access_audit_logs', 'idx_audit_logs_user_id', ['user_id']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$tableName, $indexName, $columns]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumns($tableName, $columns)) {
                continue;
            }
            if (! Schema::hasIndex($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                    $table->index($columns, $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$tableName, $indexName, $columns]) {
            if (Schema::hasTable($tableName) && Schema::hasIndex($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }
    }
};
