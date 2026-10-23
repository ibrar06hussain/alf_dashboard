@extends('layouts.admin')

@section('title', 'Office Scope Assignments — ' . $user->name)

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Office Scope Assignments',
    'subtitle' => $user->name . ' (' . $user->email . ') · Multi-Scoped Access Control',
    'icon' => 'fa-solid fa-sitemap',
    'actions' => [
        ['label' => 'Back to Users', 'url' => route('admin.users.index'), 'class' => 'btn btn-outline-secondary'],
    ],
])

<div class="row g-3">
    <!-- Left Column: Active & Historical Assignments -->
    <div class="col-lg-8">
        <div class="card alf-panel mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Active Office & Jurisdiction Assignments</span>
                <span class="badge bg-primary">{{ $user->activeOfficeAssignments->count() }} Active</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table alf-table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Office & Designation</th>
                            <th>Role</th>
                            <th>Scope Jurisdiction</th>
                            <th>Period</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($user->officeAssignments->where('is_active', true) as $assignment)
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">{{ $assignment->office?->name ?? 'Custom Jurisdiction' }}</div>
                                    <div class="small text-muted">{{ $assignment->designation ?: 'Officer' }}</div>
                                    @if($assignment->is_primary)
                                        <span class="badge bg-success py-0 px-2 mt-1">Primary Assignment</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="alf-badge alf-badge--blue">{{ $assignment->role?->name ?? '—' }}</span>
                                </td>
                                <td>
                                    <div class="small">
                                        @if($assignment->department)
                                            <div><strong>Dept:</strong> {{ $assignment->department->name }}</div>
                                        @endif
                                        @if($assignment->division)
                                            <div><strong>Division:</strong> {{ $assignment->division->name }}</div>
                                        @endif
                                        @if($assignment->district)
                                            <div><strong>District:</strong> {{ $assignment->district->name }}</div>
                                        @endif
                                        @if(!$assignment->department && !$assignment->division && !$assignment->district)
                                            <span class="text-muted">Global / All Jurisdiction</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-muted">
                                        From: {{ $assignment->start_date?->format('d M Y') ?? 'Immediate' }}
                                        @if($assignment->end_date)
                                            <br>To: {{ $assignment->end_date->format('d M Y') }}
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.users.assignments.destroy', $assignment) }}" onsubmit="return confirm('Revoke this office scope assignment?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Revoke Scope">
                                            <i class="fa-solid fa-user-xmark me-1"></i> Revoke
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="alf-empty py-4">No active office assignments for this user.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Assignment History Trail -->
        <div class="card alf-panel">
            <div class="card-header">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Assignment History & Audit Trail
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($assignmentHistories as $history)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-{{ $history->action === 'assigned' ? 'success' : ($history->action === 'revoked' ? 'danger' : 'info') }} me-2">
                                    {{ strtoupper($history->action) }}
                                </span>
                                <strong>{{ $history->reason ?: 'Assignment update' }}</strong>
                                <div class="small text-muted">
                                    By: {{ $history->performer?->name ?? 'System' }} · {{ $history->created_at?->format('d M Y, h:i A') }}
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No historical changes logged.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Add New Office Scope Assignment -->
    <div class="col-lg-4">
        <div class="card alf-panel mb-3">
            <div class="card-header">
                <i class="fa-solid fa-plus-circle me-1"></i> Assign New Office Scope
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.users.assignments.store', $user) }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-bold">Role <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="">Select role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Office Unit</label>
                        <select name="office_id" class="form-select">
                            <option value="">Select office (Optional)</option>
                            @foreach($offices as $office)
                                <option value="{{ $office->id }}">{{ $office->name }} ({{ ucfirst(str_replace('_', ' ', $office->office_type)) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Department Scope</label>
                        <select name="department_id" class="form-select">
                            <option value="">All / Global (No restriction)</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Division Scope</label>
                        <select name="division_id" class="form-select">
                            <option value="">Select division (if division-scoped)</option>
                            @foreach($divisions as $div)
                                <option value="{{ $div->id }}">{{ $div->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">District Scope</label>
                        <select name="district_id" class="form-select">
                            <option value="">Select district (if district-scoped)</option>
                            @foreach($districts as $dist)
                                <option value="{{ $dist->id }}">{{ $dist->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Designation Title</label>
                        <input type="text" name="designation" class="form-control" placeholder="e.g. Deputy Director Agriculture Gilgit">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">End Date</label>
                            <input type="date" name="end_date" class="form-control" placeholder="Optional">
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_primary" value="1" id="is_primary_check" class="form-check-input" checked>
                        <label for="is_primary_check" class="form-check-label">Set as Primary Active Scope</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-check me-1"></i> Grant & Save Assignment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
