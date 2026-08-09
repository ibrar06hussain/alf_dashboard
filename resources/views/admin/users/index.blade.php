@extends('layouts.admin')

@section('title', 'Users')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Admin Users',
    'subtitle' => 'Roles, department scope, and account status.',
    'icon' => 'fa-solid fa-users',
    'actions' => [
        ['label' => 'Add User', 'url' => route('admin.users.create'), 'class' => 'btn btn-primary'],
    ],
])

@include('admin.partials.stats', ['stats' => $pageStats])

<div class="card alf-panel mb-3">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table alf-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>District</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td><strong>{{ $user->name }}</strong></td>
                        <td>{{ $user->email }}</td>
                        <td><span class="alf-badge alf-badge--blue">{{ $user->getRoleNames()->implode(', ') ?: '—' }}</span></td>
                        <td>{{ $user->department?->name ?: '—' }}</td>
                        <td>{{ $user->district?->name ?: '—' }}</td>
                        <td>
                            <span class="alf-badge alf-badge--{{ $user->is_active ? 'green' : 'rose' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td><a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-primary">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="alf-empty">No users found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $users->links() }}</div>
    </div>
</div>
@endsection
