@extends('layouts.admin')

@section('title', 'Departments')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Departments',
    'subtitle' => 'Manage ALF departments, vision content, and linked schemes.',
    'icon' => 'fa-solid fa-landmark',
    'actions' => [
        ['label' => 'Add Department', 'url' => route('admin.departments.create'), 'class' => 'btn btn-primary'],
    ],
])

@include('admin.partials.stats', ['stats' => $pageStats])

<div class="card alf-panel mb-3">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table alf-table">
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Schemes</th>
                    <th>Stats</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($departments as $department)
                    <tr>
                        <td>{{ $department->display_order }}</td>
                        <td><strong>{{ $department->name }}</strong></td>
                        <td>{{ $department->slug }}</td>
                        <td>{{ $department->schemes_count }}</td>
                        <td>{{ $department->stats_count }}</td>
                        <td>
                            <span class="alf-badge alf-badge--{{ $department->is_active ? 'green' : 'slate' }}">
                                {{ $department->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div class="alf-actions">
                                <a href="{{ route('admin.departments.show', $department) }}" class="btn btn-sm btn-light">View</a>
                                <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-sm btn-primary">Edit</a>
                                <form action="{{ route('admin.departments.destroy', $department) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this department?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="alf-empty">No departments found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $departments->links() }}</div>
    </div>
</div>
@endsection
