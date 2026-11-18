@extends('layouts.admin')

@section('title', 'Users & Hierarchy')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Users & Administrative Hierarchy',
    'subtitle' => 'Organizational hierarchy, multi-scoped office assignments, and role enforcement.',
    'icon' => 'fa-solid fa-users',
    'actions' => [
        ['label' => 'Roles & Permissions', 'url' => route('admin.roles.matrix'), 'class' => 'btn btn-outline-primary'],
        ['label' => 'Audit Trail', 'url' => route('admin.audit-logs.index'), 'class' => 'btn btn-outline-secondary'],
        ['label' => 'Add User', 'url' => route('admin.users.create'), 'class' => 'btn btn-primary'],
    ],
])

@include('admin.partials.stats', ['stats' => $pageStats])

<div class="card alf-panel mb-3" id="admin-users-card">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.users.index') }}" data-ajax-filter="true" class="mb-3 pb-3 border-bottom">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-semibold">Search User</label>
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                        placeholder="Name, email, office, or designation...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Role</label>
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Department</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" @selected((string) request('department_id') === (string) $dept->id)>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">District</label>
                    <select name="district_id" class="form-select form-select-sm">
                        <option value="">All Districts</option>
                        @foreach($districts as $dist)
                            <option value="{{ $dist->id }}" @selected((string) request('district_id') === (string) $dist->id)>{{ $dist->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small text-muted mb-1 fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill fw-semibold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['q', 'role', 'department_id', 'district_id', 'status']))
                        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary" data-ajax-reset="true" title="Reset filters">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    @endif
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <small class="text-muted">
                    Showing <strong>{{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }}</strong> of <strong>{{ $users->total() }}</strong> user(s)
                </small>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table alf-table align-middle">
                <thead>
                <tr>
                    <th>User</th>
                    <th>Role(s)</th>
                    <th>Active Office Scope</th>
                    <th>Dept & District</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $user->name }}</div>
                            <div class="small text-muted">{{ $user->email }}</div>
                        </td>
                        <td>
                            @forelse($user->roles as $role)
                                <span class="alf-badge alf-badge--blue mb-1 d-inline-block">{{ $role->name }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse

                            @if($user->permissions->isNotEmpty())
                                <div>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle small mt-1" title="{{ $user->permissions->pluck('name')->implode(', ') }}">
                                        <i class="fa-solid fa-sliders me-1"></i>+{{ $user->permissions->count() }} custom page(s)
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td>
                            @php
                                $assignments = $user->activeOfficeAssignments;
                            @endphp
                            @if($assignments->isNotEmpty())
                                @foreach($assignments as $asn)
                                    <div class="small mb-1">
                                        <i class="fa-solid fa-building text-primary me-1"></i>
                                        <strong>{{ $asn->office?->name ?? 'Direct Scope' }}</strong>
                                        @if($asn->designation)
                                            <span class="text-muted">({{ $asn->designation }})</span>
                                        @endif
                                        @if($asn->is_primary)
                                            <span class="badge bg-success-subtle text-success py-0 px-1">Primary</span>
                                        @endif
                                    </div>
                                @endforeach
                            @elseif($user->isGlobalScope())
                                <span class="badge bg-purple-subtle text-purple"><i class="fa-solid fa-globe me-1"></i>Global Secretariat</span>
                            @else
                                <span class="text-muted small">Legacy assignment</span>
                            @endif
                        </td>
                        <td>
                            <div class="small">
                                @php
                                    $authDepts = $user->authorizedDepartmentIds();
                                    $authDistricts = $user->authorizedDistrictIds();
                                    $isAllDistricts = count($authDistricts) >= 10;
                                    $divNames = collect($user->authorizedDivisionIds())->map(fn ($id) => $divNameMap[$id] ?? null)->filter()->values()->all();
                                @endphp
                                @if($user->isGlobalScope())
                                    <span class="badge bg-purple-subtle text-purple"><i class="fa-solid fa-globe me-1"></i>Global Scope</span>
                                @else
                                    @if(!empty($authDepts))
                                        <div class="mb-1 text-dark">
                                            <i class="fa-solid fa-building-columns text-primary me-1"></i>
                                            @php
                                                $deptNames = collect($authDepts)->map(fn ($id) => $deptNameMap[$id] ?? null)->filter();
                                            @endphp
                                            <strong>{{ $deptNames->implode(', ') }}</strong>
                                        </div>
                                    @endif
                                    @if($isAllDistricts)
                                        <div class="text-success"><i class="fa-solid fa-map text-success me-1"></i>All 10 Districts</div>
                                    @elseif(!empty($divNames) && count($divNames) === 1 && count($authDistricts) > 1)
                                        <div class="text-dark"><i class="fa-solid fa-map-location-dot text-success me-1"></i><strong>{{ $divNames[0] }}</strong> ({{ count($authDistricts) }} dist.)</div>
                                    @elseif(!empty($authDistricts))
                                        <div class="text-dark">
                                            <i class="fa-solid fa-location-dot text-success me-1"></i>
                                            @php
                                                $distNames = collect($authDistricts)->map(fn ($id) => $distNameMap[$id] ?? null)->filter();
                                            @endphp
                                            {{ $distNames->implode(', ') }}
                                        </div>
                                    @else
                                        <span class="text-muted">Unassigned</span>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="alf-badge alf-badge--{{ $user->is_active ? 'green' : 'rose' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.users.assignments', $user) }}" class="btn btn-outline-info" title="Manage Scope Assignments">
                                    <i class="fa-solid fa-sitemap me-1"></i> Scope
                                </a>
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-primary" title="Edit Profile">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="alf-empty">No users found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $users->links() }}</div>
    </div>
</div>
@endsection
