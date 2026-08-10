@extends('layouts.admin')

@section('title', request('type') === 'proposed' ? 'Proposed Schemes (CM)' : 'Schemes')

@section('content')
@php $isProposed = request('type') === 'proposed'; @endphp

@include('admin.partials.page-hero', [
    'title' => $isProposed ? 'Proposed Schemes (CM)' : 'Schemes',
    'subtitle' => $isProposed
        ? 'Manage proposals shown on the public CM section. Citizens can express interest.'
        : 'Manage on going and proposed schemes across departments.',
    'icon' => $isProposed ? 'fa-solid fa-flag' : 'fa-solid fa-file-lines',
    'actions' => [
        ['label' => 'Add Proposed (CM)', 'url' => route('admin.schemes.create', ['type' => 'proposed']), 'class' => 'btn btn-success'],
        ['label' => 'Add On Going Scheme', 'url' => route('admin.schemes.create'), 'class' => 'btn btn-primary'],
    ],
])

@include('admin.partials.stats', ['stats' => $pageStats])

<div class="card alf-panel mb-3">
    <div class="card-body">
        <form method="GET" class="alf-filters row g-2">
            <div class="col-md-3">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search title">
            </div>
            <div class="col-md-3">
                <select name="department_id" class="form-control">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-control">
                    <option value="">All types</option>
                    <option value="running" @selected(request('type') === 'running')>On Going</option>
                    <option value="proposed" @selected(request('type') === 'proposed')>Proposed</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-control">
                    <option value="">All statuses</option>
                    @foreach(['draft','active','closed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table alf-table">
                <thead>
                <tr>
                    @if($isProposed)<th>Order</th>@endif
                    <th>Image</th>
                    <th>Title</th>
                    <th>Department</th>
                    <th>Type</th>
                    <th>Budget</th>
                    <th>CM / Featured</th>
                    <th>Status</th>
                    <th>{{ $isProposed ? 'Interests' : 'Apps' }}</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($schemes as $scheme)
                    <tr>
                        @if($isProposed)<td>{{ $scheme->display_order }}</td>@endif
                        <td>
                            <img src="{{ $scheme->imageUrl() }}" alt="" class="alf-table-thumb" width="64" height="44">
                        </td>
                        <td><strong>{{ $scheme->title }}</strong></td>
                        <td>{{ $scheme->department?->name }}</td>
                        <td><span class="alf-badge alf-badge--{{ $scheme->type === 'proposed' ? 'amber' : 'green' }}">{{ $scheme->typeLabel() }}</span></td>
                        <td>
                            @if($scheme->budget)
                                PKR {{ number_format($scheme->budget / 1000000, 1) }} M
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="alf-badge alf-badge--{{ $scheme->is_featured ? 'green' : 'slate' }}">
                                {{ $scheme->is_featured ? ($scheme->type === 'proposed' ? 'CM Home' : 'Featured') : 'Hidden' }}
                            </span>
                        </td>
                        <td><span class="alf-badge alf-badge--slate">{{ ucfirst($scheme->status) }}</span></td>
                        <td>{{ $isProposed ? $scheme->interests_count : $scheme->applications_count }}</td>
                        <td>
                            <div class="alf-actions">
                                <a href="{{ route('admin.schemes.show', $scheme) }}" class="btn btn-sm btn-light">View</a>
                                <a href="{{ route('admin.schemes.edit', $scheme) }}" class="btn btn-sm btn-primary">Edit</a>
                                @if($scheme->type === 'running')
                                    <a href="{{ route('admin.form-builder.edit', $scheme) }}" class="btn btn-sm btn-info">Form</a>
                                @endif
                                <form action="{{ route('admin.schemes.destroy', $scheme) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete scheme?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $isProposed ? 10 : 9 }}"><div class="alf-empty">No schemes found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $schemes->links() }}</div>
    </div>
</div>
@endsection
