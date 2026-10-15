<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('form_fields')) {
            Schema::table('form_fields', function (Blueprint $table) {
                if (! Schema::hasColumn('form_fields', 'field_label_ur')) {
                    $table->string('field_label_ur')->nullable()->after('field_label');
                }
                if (! Schema::hasColumn('form_fields', 'placeholder_ur')) {
                    $table->string('placeholder_ur')->nullable()->after('placeholder');
                }
                if (! Schema::hasColumn('form_fields', 'help_text_ur')) {
                    $table->string('help_text_ur')->nullable()->after('help_text');
                }
            });
        }

        if (Schema::hasTable('form_documents')) {
            Schema::table('form_documents', function (Blueprint $table) {
                if (! Schema::hasColumn('form_documents', 'document_name_ur')) {
                    $table->string('document_name_ur')->nullable()->after('document_name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('form_fields')) {
            Schema::table('form_fields', function (Blueprint $table) {
                $columnsToDrop = [];
                if (Schema::hasColumn('form_fields', 'field_label_ur')) {
                    $columnsToDrop[] = 'field_label_ur';
                }
                if (Schema::hasColumn('form_fields', 'placeholder_ur')) {
                    $columnsToDrop[] = 'placeholder_ur';
                }
                if (Schema::hasColumn('form_fields', 'help_text_ur')) {
                    $columnsToDrop[] = 'help_text_ur';
                }
                if (! empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }

        if (Schema::hasTable('form_documents')) {
            Schema::table('form_documents', function (Blueprint $table) {
                if (Schema::hasColumn('form_documents', 'document_name_ur')) {
                    $table->dropColumn('document_name_ur');
                }
            });
        }
    }
};
