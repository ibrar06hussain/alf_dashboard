<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\District;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with(['roles', 'department', 'district'])
            ->latest()
            ->paginate(25);

        $pageStats = [
            ['label' => 'Users', 'value' => User::count(), 'icon' => 'fa-solid fa-users', 'tone' => 'green'],
            ['label' => 'Active', 'value' => User::where('is_active', true)->count(), 'icon' => 'fa-solid fa-user-check', 'tone' => 'teal'],
            ['label' => 'Inactive', 'value' => User::where('is_active', false)->count(), 'icon' => 'fa-solid fa-user-slash', 'tone' => 'rose'],
            ['label' => 'Roles', 'value' => Role::count(), 'icon' => 'fa-solid fa-user-shield', 'tone' => 'blue'],
        ];

        return view('admin.users.index', compact('users', 'pageStats'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->pluck('name');
        $departments = Department::orderBy('display_order')->get();
        $districts = District::orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'departments', 'districts'));
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

        if ($data['role'] === 'District Officer' && empty($data['district_id'])) {
            return back()->withInput()->withErrors(['district_id' => 'District Officer requires a district.']);
        }

        if (str_starts_with($data['role'], 'Director') && empty($data['department_id'])) {
            return back()->withInput()->withErrors(['department_id' => 'Director roles require a department.']);
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

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->pluck('name');
        $departments = Department::orderBy('display_order')->get();
        $districts = District::orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'departments', 'districts'));
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

        if ($data['role'] === 'District Officer' && empty($data['district_id'])) {
            return back()->withInput()->withErrors(['district_id' => 'District Officer requires a district.']);
        }

        if (str_starts_with($data['role'], 'Director') && empty($data['department_id'])) {
            return back()->withInput()->withErrors(['department_id' => 'Director roles require a department.']);
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

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }
}
