<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $pagePermissions = [
            'pages.dashboard',
            'pages.cm_dashboard',
            'pages.wheat_mis',
            'pages.stats',
            'pages.applications',
            'pages.interests',
            'pages.gbpay',
            'pages.schemes',
            'pages.form_builder',
            'pages.challan_templates',
            'pages.stock',
            'pages.organizations',
            'pages.departments',
            'pages.districts',
            'pages.cms',
            'pages.sms',
            'pages.users',
            'pages.roles',
            'pages.audit_logs',
        ];

        foreach ($pagePermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'pages.%')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

