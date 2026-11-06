<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessAuditLog;
use App\Models\AssignmentHistory;
use App\Models\Department;
use App\Models\District;
use App\Models\Division;
use App\Models\Office;
use App\Models\User;
use App\Models\UserOfficeAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with(['roles', 'permissions', 'department', 'district', 'activeOfficeAssignments.office', 'activeOfficeAssignments.role'])
            ->latest()
            ->paginate(25);

        $pageStats = [
            ['label' => 'Users', 'value' => User::count(), 'icon' => 'fa-solid fa-users', 'tone' => 'green'],
            ['label' => 'Active', 'value' => User::where('is_active', true)->count(), 'icon' => 'fa-solid fa-user-check', 'tone' => 'teal'],
            ['label' => 'Assignments', 'value' => UserOfficeAssignment::where('is_active', true)->count(), 'icon' => 'fa-solid fa-building-user', 'tone' => 'amber'],
            ['label' => 'Roles', 'value' => Role::count(), 'icon' => 'fa-solid fa-user-shield', 'tone' => 'blue'],
        ];

        return view('admin.users.index', compact('users', 'pageStats'));
    }

    public function create(): View
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();
        $departments = Department::orderBy('display_order')->get();
        $districts = District::with('division')->orderBy('name')->get();
        $divisions = Division::with('districts')->where('is_active', true)->orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'permissions', 'departments', 'districts', 'divisions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
            'department_id' => ['nullable', 'exists:departments,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'district_ids' => ['nullable', 'array'],
            'district_ids.*' => ['exists:districts,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'is_active' => ['nullable'],
        ]);

        $roleNames = ! empty($data['roles']) ? array_values(array_unique((array) $data['roles'])) : (! empty($data['role']) ? [$data['role']] : []);
        if (empty($roleNames)) {
            return back()->withInput()->withErrors(['roles' => 'At least one role must be assigned to the user.']);
        }

        $permissionNames = ! empty($data['permissions']) ? array_values(array_unique((array) $data['permissions'])) : [];

        // Normalize departments
        $departmentIds = ! empty($data['department_ids']) ? array_map('intval', (array) $data['department_ids']) : [];
        if (! empty($data['department_id']) && ! in_array((int) $data['department_id'], $departmentIds, true)) {
            $departmentIds[] = (int) $data['department_id'];
        }

        // Auto-infer department if Director of a specific department and none selected
        if (empty($departmentIds)) {
            foreach ($roleNames as $rName) {
                if ($rName === 'Director Agriculture') {
                    $agriId = Department::where('slug', 'agriculture')->value('id');
                    if ($agriId && ! in_array((int) $agriId, $departmentIds, true)) {
                        $departmentIds[] = (int) $agriId;
                    }
                } elseif ($rName === 'Director Livestock') {
                    $liveId = Department::where('slug', 'livestock')->value('id');
                    if ($liveId && ! in_array((int) $liveId, $departmentIds, true)) {
                        $departmentIds[] = (int) $liveId;
                    }
                } elseif ($rName === 'Director Fisheries') {
                    $fishId = Department::where('slug', 'fisheries')->value('id');
                    if ($fishId && ! in_array((int) $fishId, $departmentIds, true)) {
                        $departmentIds[] = (int) $fishId;
                    }
                }
            }
        }

        // Normalize districts and division
        $divisionId = ! empty($data['division_id']) ? (int) $data['division_id'] : null;
        $districtIds = ! empty($data['district_ids']) ? array_map('intval', (array) $data['district_ids']) : [];
        if (! empty($data['district_id']) && ! in_array((int) $data['district_id'], $districtIds, true)) {
            $districtIds[] = (int) $data['district_id'];
        }

        // If a division was selected and district_ids was empty, fill with division districts
        if ($divisionId && empty($districtIds)) {
            $districtIds = \Illuminate\Support\Facades\DB::table('division_districts')
                ->where('division_id', $divisionId)
                ->pluck('district_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $hasDistrictScopedRole = false;
        foreach ($roleNames as $r) {
            if (in_array($r, ['District Officer', 'District Deputy Director', 'Field Officer'], true)) {
                $hasDistrictScopedRole = true;
                break;
            }
        }
        if ($hasDistrictScopedRole && empty($districtIds)) {
            return back()->withInput()->withErrors(['district_ids' => 'District-scoped roles require an assigned district.']);
        }

        $hasDirectorRole = false;
        foreach ($roleNames as $r) {
            if (str_starts_with($r, 'Director') && $r !== 'Division Director') {
                $hasDirectorRole = true;
                break;
            }
        }
        if ($hasDirectorRole && empty($departmentIds)) {
            return back()->withInput()->withErrors(['department_ids' => 'Director roles require an assigned department.']);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'department_id' => ! empty($departmentIds) ? $departmentIds[0] : null,
            'district_id' => (count($districtIds) === 1) ? $districtIds[0] : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->syncRoles($roleNames);
        $user->syncPermissions($permissionNames);

        $this->syncUserScopeAndAssignments($user, $roleNames, $departmentIds, $districtIds, $divisionId);

        AccessAuditLog::log('user_created', User::class, (string) $user->id, [
            'email' => $user->email,
            'roles' => $roleNames,
            'permissions' => $permissionNames,
            'departments' => $departmentIds,
            'districts' => $districtIds,
            'division_id' => $divisionId,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully with assigned role(s), page permissions, and jurisdiction scope.');
    }

    public function edit(User $user): View
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();
        $departments = Department::orderBy('display_order')->get();
        $districts = District::with('division')->orderBy('name')->get();
        $divisions = Division::with('districts')->where('is_active', true)->orderBy('name')->get();

        $userAuthorizedDistrictIds = $user->authorizedDistrictIds();
        $userAuthorizedDepartmentIds = $user->authorizedDepartmentIds();
        $userAuthorizedDivisionIds = $user->authorizedDivisionIds();
        $userRoles = $user->getRoleNames()->all();
        $userDirectPermissions = $user->getDirectPermissions()->pluck('name')->all();

        return view('admin.users.edit', compact(
            'user', 'roles', 'permissions', 'departments', 'districts', 'divisions',
            'userAuthorizedDistrictIds', 'userAuthorizedDepartmentIds', 'userAuthorizedDivisionIds',
            'userRoles', 'userDirectPermissions'
        ));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
            'department_id' => ['nullable', 'exists:departments,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'district_ids' => ['nullable', 'array'],
            'district_ids.*' => ['exists:districts,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'is_active' => ['nullable'],
        ]);

        $roleNames = ! empty($data['roles']) ? array_values(array_unique((array) $data['roles'])) : (! empty($data['role']) ? [$data['role']] : []);
        if (empty($roleNames)) {
            return back()->withInput()->withErrors(['roles' => 'At least one role must be assigned to the user.']);
        }

        $permissionNames = ! empty($data['permissions']) ? array_values(array_unique((array) $data['permissions'])) : [];

        // Normalize departments
        $departmentIds = ! empty($data['department_ids']) ? array_map('intval', (array) $data['department_ids']) : [];
        if (! empty($data['department_id']) && ! in_array((int) $data['department_id'], $departmentIds, true)) {
            $departmentIds[] = (int) $data['department_id'];
        }

        if (empty($departmentIds)) {
            foreach ($roleNames as $rName) {
                if ($rName === 'Director Agriculture') {
                    $agriId = Department::where('slug', 'agriculture')->value('id');
                    if ($agriId && ! in_array((int) $agriId, $departmentIds, true)) {
                        $departmentIds[] = (int) $agriId;
                    }
                } elseif ($rName === 'Director Livestock') {
                    $liveId = Department::where('slug', 'livestock')->value('id');
                    if ($liveId && ! in_array((int) $liveId, $departmentIds, true)) {
                        $departmentIds[] = (int) $liveId;
                    }
                } elseif ($rName === 'Director Fisheries') {
                    $fishId = Department::where('slug', 'fisheries')->value('id');
                    if ($fishId && ! in_array((int) $fishId, $departmentIds, true)) {
                        $departmentIds[] = (int) $fishId;
                    }
                }
            }
        }

        // Normalize districts and division
        $divisionId = ! empty($data['division_id']) ? (int) $data['division_id'] : null;
        $districtIds = ! empty($data['district_ids']) ? array_map('intval', (array) $data['district_ids']) : [];
        if (! empty($data['district_id']) && ! in_array((int) $data['district_id'], $districtIds, true)) {
            $districtIds[] = (int) $data['district_id'];
        }

        if ($divisionId && empty($districtIds)) {
            $districtIds = \Illuminate\Support\Facades\DB::table('division_districts')
                ->where('division_id', $divisionId)
                ->pluck('district_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $hasDistrictScopedRole = false;
        foreach ($roleNames as $r) {
            if (in_array($r, ['District Officer', 'District Deputy Director', 'Field Officer'], true)) {
                $hasDistrictScopedRole = true;
                break;
            }
        }
        if ($hasDistrictScopedRole && empty($districtIds)) {
            return back()->withInput()->withErrors(['district_ids' => 'District-scoped roles require an assigned district.']);
        }

        $hasDirectorRole = false;
        foreach ($roleNames as $r) {
            if (str_starts_with($r, 'Director') && $r !== 'Division Director') {
                $hasDirectorRole = true;
                break;
            }
        }
        if ($hasDirectorRole && empty($departmentIds)) {
            return back()->withInput()->withErrors(['department_ids' => 'Director roles require an assigned department.']);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'department_id' => ! empty($departmentIds) ? $departmentIds[0] : null,
            'district_id' => (count($districtIds) === 1) ? $districtIds[0] : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        $user->syncRoles($roleNames);
        $user->syncPermissions($permissionNames);

        $this->syncUserScopeAndAssignments($user, $roleNames, $departmentIds, $districtIds, $divisionId);

        AccessAuditLog::log('user_updated', User::class, (string) $user->id, [
            'email' => $user->email,
            'roles' => $roleNames,
            'permissions' => $permissionNames,
            'departments' => $departmentIds,
            'districts' => $districtIds,
            'division_id' => $divisionId,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully with updated roles, page permissions, and jurisdiction scope.');
    }

    private function syncUserScopeAndAssignments(
        User $user,
        array|string $roles,
        array $departmentIds,
        array $districtIds,
        ?int $divisionId
    ): void {
        $roleNames = is_array($roles) ? $roles : [$roles];
        $primaryRoleName = $roleNames[0] ?? 'User';

        $primaryDeptId = ! empty($departmentIds) ? (int) $departmentIds[0] : null;
        $primaryDistId = (count($districtIds) === 1) ? (int) $districtIds[0] : null;

        $user->update([
            'department_id' => $primaryDeptId,
            'district_id' => $primaryDistId,
        ]);

        $user->syncRoles($roleNames);

        $roleModel = Role::where('name', $primaryRoleName)->first();
        if (! $roleModel) {
            return;
        }

        // Deactivate existing assignments
        UserOfficeAssignment::where('user_id', $user->id)->update([
            'is_active' => false,
            'end_date' => now()->toDateString(),
        ]);

        $deptsToAssign = ! empty($departmentIds) ? $departmentIds : [null];
        $designationStr = implode(' / ', $roleNames);

        // 1. Division Assignment if whole division is chosen
        if ($divisionId) {
            $divOffice = Office::where('office_type', Office::TYPE_DIVISION_DIRECTORATE)
                ->where('division_id', $divisionId)
                ->first();

            foreach ($deptsToAssign as $deptId) {
                UserOfficeAssignment::create([
                    'user_id' => $user->id,
                    'office_id' => $divOffice?->id,
                    'role_id' => $roleModel->id,
                    'department_id' => $deptId,
                    'division_id' => $divisionId,
                    'district_id' => null,
                    'designation' => $designationStr,
                    'is_primary' => true,
                    'is_active' => true,
                    'start_date' => now()->toDateString(),
                    'assigned_by' => auth()->id(),
                ]);
            }
        }

        // 2. Explicit District Assignments
        $isFirst = ! $divisionId;
        foreach ($districtIds as $distId) {
            $distModel = District::find($distId);
            $dDivId = $distModel?->division()->first()?->id ?? $divisionId;

            foreach ($deptsToAssign as $deptId) {
                $matchingOffice = Office::where('district_id', $distId)
                    ->when($deptId, fn ($q) => $q->where('department_id', $deptId))
                    ->first();

                UserOfficeAssignment::create([
                    'user_id' => $user->id,
                    'office_id' => $matchingOffice?->id,
                    'role_id' => $roleModel->id,
                    'department_id' => $deptId,
                    'division_id' => $dDivId,
                    'district_id' => $distId,
                    'designation' => $designationStr,
                    'is_primary' => $isFirst,
                    'is_active' => true,
                    'start_date' => now()->toDateString(),
                    'assigned_by' => auth()->id(),
                ]);

                $isFirst = false;
            }
        }

        // 3. Fallback for Global / Secretariat / Province-wide scope
        if (empty($divisionId) && empty($districtIds)) {
            $secretariatOffice = Office::where('office_type', Office::TYPE_SECRETARIAT)->first();

            foreach ($deptsToAssign as $deptId) {
                UserOfficeAssignment::create([
                    'user_id' => $user->id,
                    'office_id' => $secretariatOffice?->id,
                    'role_id' => $roleModel->id,
                    'department_id' => $deptId,
                    'division_id' => null,
                    'district_id' => null,
                    'designation' => $designationStr,
                    'is_primary' => true,
                    'is_active' => true,
                    'start_date' => now()->toDateString(),
                    'assigned_by' => auth()->id(),
                ]);
            }
        }
    }

    public function assignments(User $user): View
    {
        $user->load([
            'officeAssignments.office',
            'officeAssignments.role',
            'officeAssignments.department',
            'officeAssignments.division',
            'officeAssignments.district',
            'officeAssignments.assigner',
        ]);

        $offices = Office::where('is_active', true)->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();
        $departments = Department::orderBy('display_order')->get();
        $divisions = Division::where('is_active', true)->orderBy('name')->get();
        $districts = District::orderBy('name')->get();

        $assignmentHistories = AssignmentHistory::where('user_id', $user->id)
            ->with('performer')
            ->latest('id')
            ->take(20)
            ->get();

        return view('admin.users.assignments', compact(
            'user', 'offices', 'roles', 'departments', 'divisions', 'districts', 'assignmentHistories'
        ));
    }

    public function storeAssignment(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'office_id' => ['nullable', 'exists:offices,id'],
            'role_id' => ['required', 'exists:roles,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'designation' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_primary' => ['nullable'],
        ]);

        $role = Role::findOrFail($data['role_id']);

        if ($request->boolean('is_primary')) {
            UserOfficeAssignment::where('user_id', $user->id)->update(['is_primary' => false]);
        }

        $assignment = UserOfficeAssignment::create([
            'user_id' => $user->id,
            'office_id' => $data['office_id'] ?? null,
            'role_id' => $role->id,
            'department_id' => $data['department_id'] ?? null,
            'division_id' => $data['division_id'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'designation' => $data['designation'] ?? null,
            'is_primary' => $request->boolean('is_primary', false),
            'is_active' => true,
            'start_date' => $data['start_date'] ?? now()->toDateString(),
            'end_date' => $data['end_date'] ?? null,
            'assigned_by' => auth()->id(),
        ]);

        if (! $user->hasRole($role->name)) {
            $user->assignRole($role->name);
        }

        AssignmentHistory::create([
            'user_id' => $user->id,
            'assignment_id' => $assignment->id,
            'action' => 'assigned',
            'new_state_json' => $assignment->toArray(),
            'reason' => 'Administrative scope assignment',
            'performed_by' => auth()->id(),
        ]);

        AccessAuditLog::log('user_scope_assigned', User::class, (string) $user->id, [
            'assignment_id' => $assignment->id,
            'role' => $role->name,
            'office_id' => $assignment->office_id,
            'district_id' => $assignment->district_id,
            'department_id' => $assignment->department_id,
        ]);

        return back()->with('success', "Office scope assignment created for {$user->name}.");
    }

    public function destroyAssignment(UserOfficeAssignment $assignment): RedirectResponse
    {
        $user = $assignment->user;
        $oldState = $assignment->toArray();

        $assignment->is_active = false;
        $assignment->end_date = now()->toDateString();
        $assignment->save();

        AssignmentHistory::create([
            'user_id' => $user->id,
            'assignment_id' => $assignment->id,
            'action' => 'revoked',
            'old_state_json' => $oldState,
            'new_state_json' => $assignment->toArray(),
            'reason' => 'Revoked by administrator',
            'performed_by' => auth()->id(),
        ]);

        AccessAuditLog::log('user_scope_revoked', User::class, (string) $user->id, [
            'assignment_id' => $assignment->id,
        ]);

        return back()->with('success', 'Assignment scope revoked successfully.');
    }

    public function rolesMatrix(): View
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        return view('admin.users.roles-matrix', compact('roles', 'permissions'));
    }

    public function auditLogs(Request $request): View
    {
        $query = AccessAuditLog::with('user')->latest('id');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $logs = $query->paginate(30)->withQueryString();
        $actions = AccessAuditLog::select('action')->distinct()->pluck('action');
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.audit-logs.index', compact('logs', 'actions', 'users'));
    }
}
