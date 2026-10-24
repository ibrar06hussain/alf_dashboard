@extends('layouts.admin')

@section('content')
<div class="app-page-title">
    <div class="page-title-wrapper">
        <div class="page-title-heading">
            <div class="page-title-icon bg-primary text-white">
                <i class="fa-solid fa-warehouse"></i>
            </div>
            <div>
                Collection & Distribution Centers
                <div class="page-title-subheading">
                    Create and manage scheme supply depots at District, Tehsil, and Village levels across Gilgit-Baltistan.
                </div>
            </div>
        </div>
        <div class="page-title-actions">
            <button type="button" class="btn btn-success shadow-sm" data-toggle="modal" data-target="#createCenterModal">
                <i class="fa-solid fa-plus-circle mr-1"></i> Add Collection Center
            </button>
        </div>
    </div>
</div>

{{-- Filters Card --}}
<div class="main-card mb-3 card shadow-sm">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.stock.centers') }}" class="form-row align-items-center">
            <div class="col-md-3 my-1">
                <select name="level" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">All Administrative Levels</option>
                    <option value="district" {{ request('level') === 'district' ? 'selected' : '' }}>District Main Depots</option>
                    <option value="tehsil" {{ request('level') === 'tehsil' ? 'selected' : '' }}>Tehsil Centers</option>
                    <option value="village" {{ request('level') === 'village' ? 'selected' : '' }}>Village Distribution Points</option>
                </select>
            </div>
            <div class="col-md-6 my-1">
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, code, village, focal person..." value="{{ request('search') }}">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass mr-1"></i> Search</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3 my-1 text-right">
                <a href="{{ route('admin.stock.centers') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left mr-1"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- Centers Table --}}
<div class="main-card mb-4 card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark"><i class="fa-solid fa-list mr-2 text-primary"></i> Registered Collection Centers ({{ $centers->total() }})</span>
    </div>
    <div class="table-responsive">
        <table class="align-middle mb-0 table table-borderless table-striped table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Center Code & Name</th>
                    <th>Level</th>
                    <th>Jurisdiction</th>
                    <th>Focal Person</th>
                    <th>Stock In Store</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($centers as $c)
                <tr>
                    <td>
                        <span class="badge badge-light border text-muted mr-1">{{ $c->code }}</span>
                        <strong>{{ $c->name }}</strong>
                        @if($c->address)
                            <div class="text-muted small text-truncate" style="max-width: 280px;" title="{{ $c->address }}">
                                <i class="fa-solid fa-location-dot text-muted mr-1"></i> {{ $c->address }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($c->level === 'district')
                            <span class="badge badge-primary">District Depot</span>
                        @elseif($c->level === 'tehsil')
                            <span class="badge badge-info">Tehsil Center</span>
                        @else
                            <span class="badge badge-secondary">Village Point</span>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $c->district->name ?? 'N/A' }}</strong>
                        @if($c->tehsil) <br><small class="text-muted">Tehsil: {{ $c->tehsil->name }}</small> @endif
                        @if($c->village_name) <br><small class="text-muted">Village: {{ $c->village_name }}</small> @endif
                    </td>
                    <td>
                        <div class="font-weight-bold">{{ $c->focal_person_name ?? 'Not Assigned' }}</div>
                        <small class="text-muted"><i class="fa-solid fa-phone mr-1"></i> {{ $c->focal_person_phone ?? 'N/A' }}</small>
                    </td>
                    <td>
                        @php
                            $totalBal = $c->inventories->sum('current_balance');
                        @endphp
                        <span class="font-weight-bold font-size-md {{ $totalBal > 0 ? 'text-success' : 'text-muted' }}">
                            {{ number_format($totalBal) }}
                        </span>
                        <div class="small text-muted">{{ $c->inventories->count() }} scheme item(s)</div>
                    </td>
                    <td class="text-center">
                        @if($c->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-xs btn-outline-primary" data-toggle="modal" data-target="#editCenterModal{{ $c->id }}" title="Edit Center">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <a href="{{ route('admin.stock.receipts') }}?center_id={{ $c->id }}" class="btn btn-xs btn-outline-success" title="Record Received Stock">
                            <i class="fa-solid fa-plus"></i>
                        </a>
                        <a href="{{ route('admin.stock.transfers') }}?from_center_id={{ $c->id }}" class="btn btn-xs btn-outline-info" title="Transfer Stock">
                            <i class="fa-solid fa-arrow-right-arrow-left"></i>
                        </a>
                    </td>
                </tr>

                {{-- Edit Modal for each Center --}}
                <div class="modal fade" id="editCenterModal{{ $c->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.stock.centers.update', $c->id) }}">
                                @csrf
                                @method('PUT')
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-pen-to-square mr-2"></i> Edit Collection Center: {{ $c->name }}</h5>
                                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-row">
                                        <div class="col-md-6 form-group">
                                            <label class="font-weight-bold">Center Name *</label>
                                            <input type="text" name="name" class="form-control" value="{{ $c->name }}" required>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label class="font-weight-bold">Administrative Level *</label>
                                            <select name="level" class="form-control" required>
                                                <option value="district" {{ $c->level === 'district' ? 'selected' : '' }}>District Central Depot</option>
                                                <option value="tehsil" {{ $c->level === 'tehsil' ? 'selected' : '' }}>Tehsil Center</option>
                                                <option value="village" {{ $c->level === 'village' ? 'selected' : '' }}>Village Distribution Point</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="col-md-6 form-group">
                                            <label class="font-weight-bold">Tehsil</label>
                                            <select name="tehsil_id" class="form-control">
                                                <option value="">None / District Central</option>
                                                @foreach($tehsils->where('district_id', $c->district_id) as $t)
                                                    <option value="{{ $t->id }}" {{ $c->tehsil_id == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label class="font-weight-bold">Village / Ward / Location</label>
                                            <input type="text" name="village_name" class="form-control" value="{{ $c->village_name }}">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="col-md-6 form-group">
                                            <label class="font-weight-bold">Focal Person Name</label>
                                            <input type="text" name="focal_person_name" class="form-control" value="{{ $c->focal_person_name }}">
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label class="font-weight-bold">Focal Person Phone</label>
                                            <input type="text" name="focal_person_phone" class="form-control" value="{{ $c->focal_person_phone }}">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="font-weight-bold">Address / Landmarks</label>
                                        <textarea name="address" rows="2" class="form-control">{{ $c->address }}</textarea>
                                    </div>
                                    <div class="form-row">
                                        <div class="col-md-6 form-group">
                                            <label class="font-weight-bold">Storage Capacity (Units)</label>
                                            <input type="number" step="any" name="capacity_units" class="form-control" value="{{ $c->capacity_units }}">
                                        </div>
                                        <div class="col-md-6 form-group d-flex align-items-center mt-4">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="activeSwitch{{ $c->id }}" name="is_active" value="1" {{ $c->is_active ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-bold" for="activeSwitch{{ $c->id }}">Active Center</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary font-weight-bold"><i class="fa-solid fa-save mr-1"></i> Update Center</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No collection centers found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="text-muted small">Showing {{ $centers->firstItem() ?? 0 }} to {{ $centers->lastItem() ?? 0 }} of {{ $centers->total() }}</span>
        {{ $centers->links() }}
    </div>
</div>

{{-- Create Modal --}}
<div class="modal fade" id="createCenterModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.stock.centers.store') }}">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-plus-circle mr-2"></i> Register New Collection Center</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Center Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Danyore Seed Distribution Center" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Center Code *</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. CC-GLT-DNY-01" required>
                            <small class="text-muted">Unique identifier for this facility</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Administrative Level *</label>
                            <select name="level" class="form-control" required>
                                <option value="district">District Central Depot</option>
                                <option value="tehsil" selected>Tehsil Center</option>
                                <option value="village">Village Distribution Point</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">District *</label>
                            <select name="district_id" class="form-control" required>
                                @foreach($districts as $d)
                                    <option value="{{ $d->id }}" {{ $districtId == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Tehsil</label>
                            <select name="tehsil_id" class="form-control">
                                <option value="">Select Tehsil (if applicable)</option>
                                @foreach($tehsils as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->district->name ?? '' }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Village / Ward / Location</label>
                            <input type="text" name="village_name" class="form-control" placeholder="e.g. Village Danyore, Near Jamia Masjid">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Storage Capacity (Units)</label>
                            <input type="number" step="any" name="capacity_units" class="form-control" placeholder="e.g. 5000">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Focal Person Name</label>
                            <input type="text" name="focal_person_name" class="form-control" placeholder="e.g. Muhammad Ali">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Focal Person Phone</label>
                            <input type="text" name="focal_person_phone" class="form-control" placeholder="e.g. 0345-1234567">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Full Address & Location Details</label>
                        <textarea name="address" rows="2" class="form-control" placeholder="Physical location, road landmarks, building description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success font-weight-bold"><i class="fa-solid fa-check mr-1"></i> Save Collection Center</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
