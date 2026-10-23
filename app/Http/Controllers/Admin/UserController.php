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
        $users = User::with(['roles', 'department', 'district', 'activeOfficeAssignments.office', 'activeOfficeAssignments.role'])
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
        $roles = Role::orderBy('name')->pluck('name');
        $departments = Department::orderBy('display_order')->get();
        $districts = District::orderBy('name')->get();
        $divisions = Division::where('is_active', true)->orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'departments', 'districts', 'divisions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'department_id' => ['nullable', 'exists:departments,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'is_active' => ['nullable'],
        ]);

        if (in_array($data['role'], ['District Officer', 'District Deputy Director', 'Field Officer'], true) && empty($data['district_id'])) {
            return back()->withInput()->withErrors(['district_id' => "Role {$data['role']} requires an assigned district."]);
        }

        if (str_starts_with($data['role'], 'Director') && $data['role'] !== 'Division Director' && empty($data['department_id'])) {
            return back()->withInput()->withErrors(['department_id' => 'Director roles require an assigned department.']);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'department_id' => $data['department_id'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->syncRoles([$data['role']]);

        // Auto-create primary office assignment if appropriate
        $roleModel = Role::where('name', $data['role'])->first();
        if ($roleModel) {
            $divisionId = null;
            if (! empty($data['district_id'])) {
                $district = District::find($data['district_id']);
                $divisionId = $district?->division()->first()?->id;
            }

            $matchingOffice = Office::where(function ($q) use ($data, $divisionId) {
                if (! empty($data['district_id']) && ! empty($data['department_id'])) {
                    $q->where('district_id', $data['district_id'])
                        ->where('department_id', $data['department_id']);
                } elseif (! empty($divisionId)) {
                    $q->where('division_id', $divisionId);
                } else {
                    $q->where('office_type', Office::TYPE_SECRETARIAT);
                }
            })->first();

            $assignment = UserOfficeAssignment::create([
                'user_id' => $user->id,
                'office_id' => $matchingOffice?->id,
                'role_id' => $roleModel->id,
                'department_id' => $data['department_id'] ?? null,
                'division_id' => $divisionId,
                'district_id' => $data['district_id'] ?? null,
                'designation' => $data['role'],
                'is_primary' => true,
                'is_active' => true,
                'start_date' => now()->toDateString(),
                'assigned_by' => auth()->id(),
            ]);

            AssignmentHistory::create([
                'user_id' => $user->id,
                'assignment_id' => $assignment->id,
                'action' => 'assigned',
                'new_state_json' => $assignment->toArray(),
                'reason' => 'User creation initial assignment',
                'performed_by' => auth()->id(),
            ]);
        }

        AccessAuditLog::log('user_created', User::class, (string) $user->id, [
            'email' => $user->email,
            'role' => $data['role'],
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully with assigned role and office scope.');
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->pluck('name');
        $departments = Department::orderBy('display_order')->get();
        $districts = District::orderBy('name')->get();
        $divisions = Division::where('is_active', true)->orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'departments', 'districts', 'divisions'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'department_id' => ['nullable', 'exists:departments,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'is_active' => ['nullable'],
        ]);

        if (in_array($data['role'], ['District Officer', 'District Deputy Director', 'Field Officer'], true) && empty($data['district_id'])) {
            return back()->withInput()->withErrors(['district_id' => "Role {$data['role']} requires an assigned district."]);
        }

        if (str_starts_with($data['role'], 'Director') && $data['role'] !== 'Division Director' && empty($data['department_id'])) {
            return back()->withInput()->withErrors(['department_id' => 'Director roles require an assigned department.']);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'department_id' => $data['department_id'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        AccessAuditLog::log('user_updated', User::class, (string) $user->id, [
            'email' => $user->email,
            'role' => $data['role'],
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
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
