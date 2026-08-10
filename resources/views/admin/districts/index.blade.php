@extends('layouts.admin')

@section('title', 'Districts')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Districts',
    'subtitle' => 'Gilgit-Baltistan district registry used for quotas and applications.',
    'icon' => 'fa-solid fa-location-dot',
])

@include('admin.partials.stats', ['stats' => $pageStats])

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card alf-panel mb-3">
            <div class="card-header">Add District</div>
            <div class="card-body alf-form-grid">
                <form method="POST" action="{{ route('admin.districts.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Name (English)</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name (Urdu)</label>
                        <input type="text" name="name_ur" class="form-control" value="{{ old('name_ur') }}" dir="rtl" lang="ur">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" class="form-control" value="{{ old('code') }}" required maxlength="20">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="is_active" value="1" id="new_active" checked>
                        <label class="form-check-label" for="new_active">Active</label>
                    </div>
                    <div class="alf-form-actions">
                        <button class="btn btn-primary">Create District</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card alf-panel mb-3">
            <div class="card-header">All Districts</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table alf-table">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Urdu</th>
                            <th>Code</th>
                            <th>Apps</th>
                            <th>Status</th>
                            <th>Update</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($districts as $district)
                            <tr>
                                <td colspan="6" class="p-2">
                                    <form method="POST" action="{{ route('admin.districts.update', $district) }}" class="row g-2 align-items-center">
                                        @csrf @method('PUT')
                                        <div class="col-md-2">
                                            <input type="text" name="name" class="form-control" value="{{ old('name_'.$district->id, $district->name) }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="text" name="name_ur" class="form-control" value="{{ old('name_ur_'.$district->id, $district->name_ur) }}" dir="rtl" lang="ur" placeholder="اردو">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="text" name="code" class="form-control" value="{{ old('code_'.$district->id, $district->code) }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <span class="alf-badge alf-badge--blue">{{ $district->applications_count }} apps</span>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" name="is_active" value="1" id="active_{{ $district->id }}"
                                                    {{ $district->is_active ? 'checked' : '' }}>
                                                <label class="form-check-label" for="active_{{ $district->id }}">Active</label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-sm btn-primary">Save</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="alf-empty">No districts found.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $districts->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
