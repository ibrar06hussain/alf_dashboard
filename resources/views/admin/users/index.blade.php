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

<div class="card alf-panel mb-3">
    <div class="card-body">
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
                                @if($user->department)
                                    <span class="d-block"><i class="fa-solid fa-layer-group text-muted me-1"></i>{{ $user->department->name }}</span>
                                @endif
                                @if($user->district)
                                    <span class="d-block"><i class="fa-solid fa-location-dot text-muted me-1"></i>{{ $user->district->name }}</span>
                                @endif
                                @if(!$user->department && !$user->district)
                                    <span class="text-muted">Global / All</span>
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
