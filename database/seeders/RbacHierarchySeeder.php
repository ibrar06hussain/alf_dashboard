<?php

namespace Database\Seeders;

use App\Models\AssignmentHistory;
use App\Models\Department;
use App\Models\District;
use App\Models\Division;
use App\Models\Office;
use App\Models\User;
use App\Models\UserOfficeAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RbacHierarchySeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        DB::transaction(function () {
            $this->seedDivisionsAndDistricts();
            $this->seedPermissionsAndRoles();
            $this->seedOffices();
            $this->seedInitialAssignments();
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function seedDivisionsAndDistricts(): void
    {
        $districts = [
            ['name' => 'Gilgit', 'code' => 'GLT'],
            ['name' => 'Hunza', 'code' => 'HNZ'],
            ['name' => 'Nagar', 'code' => 'NGR'],
            ['name' => 'Skardu', 'code' => 'SKD'],
            ['name' => 'Ghanche', 'code' => 'GHC'],
            ['name' => 'Shigar', 'code' => 'SHG'],
            ['name' => 'Kharmang', 'code' => 'KHM'],
            ['name' => 'Astore', 'code' => 'AST'],
            ['name' => 'Diamer', 'code' => 'DMR'],
            ['name' => 'Ghizer', 'code' => 'GHZ'],
        ];

        foreach ($districts as $d) {
            District::firstOrCreate(['code' => $d['code']], $d);
        }

        $departments = [
            ['name' => 'Agriculture', 'slug' => 'agriculture', 'display_order' => 1],
            ['name' => 'Livestock & Dairy Development', 'slug' => 'livestock', 'display_order' => 2],
            ['name' => 'Fisheries', 'slug' => 'fisheries', 'display_order' => 3],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['slug' => $dept['slug']], $dept);
        }

        $divisionsData = [
            [
                'name' => 'Gilgit Division',
                'code' => 'GLT_DIV',
                'headquarters' => 'Gilgit',
                'districts' => ['Gilgit', 'Hunza', 'Nagar', 'Ghizer'],
            ],
            [
                'name' => 'Diamer Division',
                'code' => 'DMR_DIV',
                'headquarters' => 'Chilas',
                'districts' => ['Astore', 'Diamer'],
            ],
            [
                'name' => 'Skardu Division',
                'code' => 'SKD_DIV',
                'headquarters' => 'Skardu',
                'districts' => ['Skardu', 'Ghanche', 'Shigar', 'Kharmang'],
            ],
        ];

        foreach ($divisionsData as $divData) {
            $division = Division::updateOrCreate(
                ['code' => $divData['code']],
                [
                    'name' => $divData['name'],
                    'headquarters' => $divData['headquarters'],
                    'is_active' => true,
                ]
            );

            $districtIds = District::whereIn('name', $divData['districts'])->pluck('id');
            $division->districts()->syncWithoutDetaching($districtIds);
        }
    }

    private function seedPermissionsAndRoles(): void
    {
        $permissions = [
            // Reporting
            'reporting.global.view',
            'reporting.division.view',
            'reporting.district.view',

            // Applications
            'applications.view',
            'applications.verify_district',
            'applications.approve_directorate',
            'applications.reject',
            'applications.assign_field_officer',

            // Surveys & Field tasks
            'surveys.view_assigned',
            'surveys.submit_verification',

            // Financial & Payments
            'payments.verify',

            // Inventory & Distribution
            'distribution.allocate_stock',
            'distribution.confirm_delivery',

            // Beneficiaries & Exports
            'beneficiaries.export_masked',
            'beneficiaries.export_unmasked',

            // System Administration
            'system.manage_users',
            'system.manage_offices',
            'system.audit_logs.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Super Admin: System & Technical Configuration only (ZERO default beneficiary approval rights)
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions([
            'system.manage_users',
            'system.manage_offices',
            'system.audit_logs.view',
            'beneficiaries.export_masked',
            'reporting.global.view',
            'reporting.division.view',
            'reporting.district.view',
            'applications.view',
        ]);

        // 2. Secretary: Read-only Global oversight across all 3 departments & 10 districts
        $secretary = Role::firstOrCreate(['name' => 'Secretary', 'guard_name' => 'web']);
        $secretary->syncPermissions([
            'reporting.global.view',
            'reporting.division.view',
            'reporting.district.view',
            'applications.view',
            'beneficiaries.export_masked',
            'system.audit_logs.view',
        ]);

        // 3. Division Director: Regional oversight and escalation monitoring for assigned division
        $divisionDirector = Role::firstOrCreate(['name' => 'Division Director', 'guard_name' => 'web']);
        $divisionDirector->syncPermissions([
            'reporting.division.view',
            'reporting.district.view',
            'applications.view',
            'beneficiaries.export_masked',
        ]);

        // 4. District Deputy Director: Operational review, verify, approve, reject, assign field officers
        $districtDd = Role::firstOrCreate(['name' => 'District Deputy Director', 'guard_name' => 'web']);
        $districtDd->syncPermissions([
            'reporting.district.view',
            'applications.view',
            'applications.verify_district',
            'applications.approve_directorate',
            'applications.reject',
            'applications.assign_field_officer',
            'beneficiaries.export_masked',
        ]);

        // 5. District Officer: Keep aligned with District Deputy Director for backward compatibility
        $districtOfficer = Role::firstOrCreate(['name' => 'District Officer', 'guard_name' => 'web']);
        $districtOfficer->syncPermissions([
            'reporting.district.view',
            'applications.view',
            'applications.verify_district',
            'applications.approve_directorate',
            'applications.reject',
            'applications.assign_field_officer',
            'beneficiaries.export_masked',
        ]);

        // 6. Field Officer: Operational reporting, survey execution only on assigned applications
        $fieldOfficer = Role::firstOrCreate(['name' => 'Field Officer', 'guard_name' => 'web']);
        $fieldOfficer->syncPermissions([
            'surveys.view_assigned',
            'surveys.submit_verification',
            'applications.view',
        ]);

        // 7. Accounts Officer: Payment verification within assigned scope
        $accountsOfficer = Role::firstOrCreate(['name' => 'Accounts Officer', 'guard_name' => 'web']);
        $accountsOfficer->syncPermissions([
            'payments.verify',
            'applications.view',
        ]);

        // 8. Distribution Officer: Inventory allocation & physical delivery confirmation
        $distributionOfficer = Role::firstOrCreate(['name' => 'Distribution Officer', 'guard_name' => 'web']);
        $distributionOfficer->syncPermissions([
            'distribution.allocate_stock',
            'distribution.confirm_delivery',
            'applications.view',
        ]);

        // Departmental Director roles (Director Agriculture, Director Livestock, Director Fisheries)
        foreach (['Director Agriculture', 'Director Livestock', 'Director Fisheries'] as $roleName) {
            $deptRole = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $deptRole->syncPermissions([
                'reporting.global.view',
                'reporting.division.view',
                'reporting.district.view',
                'applications.view',
                'applications.approve_directorate',
                'applications.reject',
                'beneficiaries.export_masked',
            ]);
        }
    }

    private function seedOffices(): void
    {
        // 1. Secretariat
        $secretariat = Office::updateOrCreate(
            ['name' => 'ALF Secretariat Gilgit-Baltistan'],
            [
                'office_type' => Office::TYPE_SECRETARIAT,
                'is_active' => true,
            ]
        );

        // 2. Division Directorates
        $divisions = Division::all();
        $departments = Department::all();

        foreach ($divisions as $division) {
            $divOffice = Office::updateOrCreate(
                ['name' => "Directorate of {$division->name}"],
                [
                    'parent_id' => $secretariat->id,
                    'office_type' => Office::TYPE_DIVISION_DIRECTORATE,
                    'division_id' => $division->id,
                    'is_active' => true,
                ]
            );

            // Create District Deputy Directorates under this Division
            foreach ($division->districts as $district) {
                foreach ($departments as $dept) {
                    Office::updateOrCreate(
                        ['name' => "Office of Deputy Director {$dept->name} - {$district->name}"],
                        [
                            'parent_id' => $divOffice->id,
                            'office_type' => Office::TYPE_DISTRICT_DEPUTY_DIRECTORATE,
                            'department_id' => $dept->id,
                            'division_id' => $division->id,
                            'district_id' => $district->id,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }

    private function seedInitialAssignments(): void
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        $secretaryRole = Role::where('name', 'Secretary')->first();
        $secretariat = Office::where('office_type', Office::TYPE_SECRETARIAT)->first();

        $superAdminUser = User::updateOrCreate(
            ['email' => 'admin@alf.gb.gov.pk'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $superAdminUser->syncRoles(['Super Admin']);

        $secretaryUser = User::updateOrCreate(
            ['email' => 'secretary@alf.gb.gov.pk'],
            [
                'name' => 'Secretary ALF',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $secretaryUser->syncRoles(['Secretary']);

        if ($superAdminUser && $secretariat && $superAdminRole) {
            $this->assignUser(
                $superAdminUser,
                $secretariat,
                $superAdminRole,
                null,
                null,
                null,
                'System Administrator',
                $superAdminUser->id
            );
        }

        if ($secretaryUser && $secretariat && $secretaryRole) {
            $this->assignUser(
                $secretaryUser,
                $secretariat,
                $secretaryRole,
                null,
                null,
                null,
                'Secretary Agriculture, Livestock & Fisheries',
                $superAdminUser->id
            );
        }

        // Division Directors
        $divDirectorRole = Role::where('name', 'Division Director')->first();
        $divisions = Division::all();

        foreach ($divisions as $div) {
            $divSlug = strtolower(explode(' ', $div->name)[0]);
            $divOffice = Office::where('office_type', Office::TYPE_DIVISION_DIRECTORATE)
                ->where('division_id', $div->id)
                ->first();

            $divUser = User::updateOrCreate(
                ['email' => "director.{$divSlug}@alf.gb.gov.pk"],
                [
                    'name' => "Director {$div->name}",
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );
            $divUser->syncRoles(['Division Director']);

            if ($divOffice && $divDirectorRole) {
                $this->assignUser(
                    $divUser,
                    $divOffice,
                    $divDirectorRole,
                    null,
                    $div->id,
                    null,
                    "Director {$div->name}",
                    $superAdminUser->id
                );
            }
        }

        // District Deputy Director - Gilgit
        $gilgit = District::where('name', 'Gilgit')->first();
        $agriDept = Department::where('slug', 'agriculture')->first();
        $ddRole = Role::where('name', 'District Deputy Director')->first();

        if ($gilgit && $agriDept && $ddRole) {
            $ddGilgitUser = User::updateOrCreate(
                ['email' => 'dd.gilgit@alf.gb.gov.pk'],
                [
                    'name' => 'Deputy Director Agriculture Gilgit',
                    'password' => Hash::make('password'),
                    'department_id' => $agriDept->id,
                    'district_id' => $gilgit->id,
                    'is_active' => true,
                ]
            );
            $ddGilgitUser->syncRoles(['District Deputy Director', 'District Officer']);

            $ddOffice = Office::where('district_id', $gilgit->id)
                ->where('department_id', $agriDept->id)
                ->first();

            $this->assignUser(
                $ddGilgitUser,
                $ddOffice,
                $ddRole,
                $agriDept->id,
                $gilgit->division()->first()?->id,
                $gilgit->id,
                'Deputy Director Agriculture Gilgit',
                $superAdminUser->id
            );
        }

        // District Deputy Director - Diamer (keep name Diamer!)
        $diamer = District::where('name', 'Diamer')->first();
        if ($diamer && $agriDept && $ddRole) {
            $ddDiamerUser = User::updateOrCreate(
                ['email' => 'dd.diamer@alf.gb.gov.pk'],
                [
                    'name' => 'Deputy Director Agriculture Diamer',
                    'password' => Hash::make('password'),
                    'department_id' => $agriDept->id,
                    'district_id' => $diamer->id,
                    'is_active' => true,
                ]
            );
            $ddDiamerUser->syncRoles(['District Deputy Director', 'District Officer']);

            $ddDiamerOffice = Office::where('district_id', $diamer->id)
                ->where('department_id', $agriDept->id)
                ->first();

            $this->assignUser(
                $ddDiamerUser,
                $ddDiamerOffice,
                $ddRole,
                $agriDept->id,
                $diamer->division()->first()?->id,
                $diamer->id,
                'Deputy Director Agriculture Diamer',
                $superAdminUser?->id
            );
        }

        // Field Officer - Gilgit
        $fieldRole = Role::where('name', 'Field Officer')->first();
        if ($gilgit && $agriDept && $fieldRole) {
            $fieldUser = User::updateOrCreate(
                ['email' => 'field.gilgit@alf.gb.gov.pk'],
                [
                    'name' => 'Field Officer Gilgit Agri',
                    'password' => Hash::make('password'),
                    'department_id' => $agriDept->id,
                    'district_id' => $gilgit->id,
                    'is_active' => true,
                ]
            );
            $fieldUser->syncRoles(['Field Officer']);

            $ddOffice = Office::where('district_id', $gilgit->id)
                ->where('department_id', $agriDept->id)
                ->first();

            $this->assignUser(
                $fieldUser,
                $ddOffice,
                $fieldRole,
                $agriDept->id,
                $gilgit->division()->first()?->id,
                $gilgit->id,
                'Field Assistant Agriculture Gilgit',
                $superAdminUser?->id
            );
        }

        // Accounts Officer - Gilgit
        $accountsRole = Role::where('name', 'Accounts Officer')->first();
        if ($gilgit && $agriDept && $accountsRole) {
            $accountsUser = User::updateOrCreate(
                ['email' => 'accounts.gilgit@alf.gb.gov.pk'],
                [
                    'name' => 'Accounts Officer Gilgit',
                    'password' => Hash::make('password'),
                    'department_id' => $agriDept->id,
                    'district_id' => $gilgit->id,
                    'is_active' => true,
                ]
            );
            $accountsUser->syncRoles(['Accounts Officer']);

            $ddOffice = Office::where('district_id', $gilgit->id)
                ->where('department_id', $agriDept->id)
                ->first();

            $this->assignUser(
                $accountsUser,
                $ddOffice,
                $accountsRole,
                $agriDept->id,
                $gilgit->division()->first()?->id,
                $gilgit->id,
                'Accounts Officer Gilgit',
                $superAdminUser?->id
            );
        }

        // Distribution Officer - Gilgit
        $distRole = Role::where('name', 'Distribution Officer')->first();
        if ($gilgit && $agriDept && $distRole) {
            $distUser = User::updateOrCreate(
                ['email' => 'dist.gilgit@alf.gb.gov.pk'],
                [
                    'name' => 'Distribution Officer Gilgit',
                    'password' => Hash::make('password'),
                    'department_id' => $agriDept->id,
                    'district_id' => $gilgit->id,
                    'is_active' => true,
                ]
            );
            $distUser->syncRoles(['Distribution Officer']);

            $ddOffice = Office::where('district_id', $gilgit->id)
                ->where('department_id', $agriDept->id)
                ->first();

            $this->assignUser(
                $distUser,
                $ddOffice,
                $distRole,
                $agriDept->id,
                $gilgit->division()->first()?->id,
                $gilgit->id,
                'Store & Distribution Officer Gilgit',
                $superAdminUser?->id
            );
        }
    }

    private function assignUser(
        User $user,
        ?Office $office,
        Role $role,
        ?int $departmentId,
        ?int $divisionId,
        ?int $districtId,
        string $designation,
        ?int $assignedBy
    ): UserOfficeAssignment {
        $assignment = UserOfficeAssignment::updateOrCreate(
            [
                'user_id' => $user->id,
                'role_id' => $role->id,
                'office_id' => $office?->id,
            ],
            [
                'department_id' => $departmentId,
                'division_id' => $divisionId,
                'district_id' => $districtId,
                'designation' => $designation,
                'is_primary' => true,
                'is_active' => true,
                'start_date' => now()->toDateString(),
                'assigned_by' => $assignedBy,
            ]
        );

        AssignmentHistory::create([
            'user_id' => $user->id,
            'assignment_id' => $assignment->id,
            'action' => 'assigned',
            'new_state_json' => $assignment->toArray(),
            'reason' => 'Initial RBAC and Organization Hierarchy Seeding',
            'performed_by' => $assignedBy,
        ]);

        return $assignment;
    }
}
