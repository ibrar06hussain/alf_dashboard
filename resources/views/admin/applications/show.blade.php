@extends('layouts.admin')

@section('title', $application->application_no)

@section('content')
@include('admin.partials.page-hero', [
    'title' => $application->application_no,
    'subtitle' => ($application->scheme?->title ?? '—').' · '.$application->statusLabel(),
    'icon' => 'fa-solid fa-id-card',
    'actions' => [
        ['label' => 'Back to list', 'url' => route('admin.applications.index'), 'class' => 'btn btn-light'],
    ],
])

@php
    $sla = $slaStatus ?? $application->slaStatus();
@endphp

@include('admin.partials.stats', ['stats' => [
    ['label' => 'Lifecycle Stage', 'value' => $application->statusLabel(), 'icon' => 'fa-solid fa-diagram-project', 'tone' => 'amber'],
    ['label' => 'SLA Clock', 'value' => $sla['label'], 'icon' => 'fa-solid fa-clock-rotate-left', 'tone' => $sla['badge'] === 'danger' ? 'rose' : ($sla['badge'] === 'warning' ? 'amber' : 'green')],
    ['label' => 'Jurisdiction', 'value' => ($application->division?->name ? $application->division->name . ' · ' : '') . ($application->district?->name ?? '—'), 'icon' => 'fa-solid fa-location-dot', 'tone' => 'blue'],
    ['label' => 'Responsible Office', 'value' => $application->assignedUser?->name ?? ($application->is_unassigned ? 'Unassigned Queue' : 'Pending Allocation'), 'icon' => 'fa-solid fa-user-shield', 'tone' => $application->is_unassigned ? 'rose' : 'slate'],
]])

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card alf-panel mb-3">
            <div class="card-header">Applicant</div>
            <div class="card-body">
                <div class="alf-meta-grid">
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Name</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->full_name }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Father / Husband</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->father_name }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">CNIC</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->cnic }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Phone</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->phone }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">District</span>
                        <span class="alf-meta-item__value">{{ $application->district?->name }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Tehsil</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->tehsil ?: '—' }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Union Council</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->union_council ?: '—' }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Village</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->village ?: '—' }}</span>
                    </div>
                    <div class="alf-meta-item" style="grid-column:1/-1">
                        <span class="alf-meta-item__label">Land location</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->land_location }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Field Survey & Land Verification Section --}}
        @php
            $activeSurvey = $application->surveys->first();
            $activeAssignment = $application->assignments->first();
            $surveyorId = $activeSurvey?->assigned_to ?? $activeAssignment?->assigned_to;
            $canPerformSurvey = (auth()->id() === $surveyorId || auth()->user()->hasRole('Super Admin'));
            
            // Determine if findings have been submitted
            $hasSubmittedFindings = false;
            $surveyPoints = [];
            $surveyPhotos = [];
            $surveyLand = null;
            $surveyWater = null;
            $surveyRoad = null;
            $surveyRec = null;
            $surveySummary = null;
            $surveyTimestamp = null;
            $surveyorUser = null;

            if ($activeSurvey && in_array($activeSurvey->status, [\App\Models\ApplicationSurvey::STATUS_FINDINGS_SUBMITTED, \App\Models\ApplicationSurvey::STATUS_VISIT_COMPLETED])) {
                $hasSubmittedFindings = ($activeSurvey->status === \App\Models\ApplicationSurvey::STATUS_FINDINGS_SUBMITTED);
                $surveyPoints = $activeSurvey->gps_points_list;
                $surveyPhotos = $activeSurvey->photos_list;
                $surveyLand = $activeSurvey->land_verified_kanals;
                $surveyWater = $activeSurvey->water_source_verified;
                $surveyRoad = $activeSurvey->road_access_verified;
                $surveyRec = $activeSurvey->recommendation;
                $surveySummary = $activeSurvey->findings_summary;
                $surveyTimestamp = $activeSurvey->findings_submitted_at ?? $activeSurvey->visit_completed_at;
                $surveyorUser = $activeSurvey->surveyor;
            } elseif ($activeAssignment && !empty($activeAssignment->report_data)) {
                $hasSubmittedFindings = true;
                $surveyPoints = $activeAssignment->gps_points_list;
                $surveyPhotos = $activeAssignment->photos_list;
                $surveyLand = $activeAssignment->report_data['land_verified_kanals'] ?? null;
                $surveyWater = $activeAssignment->report_data['water_source_verified'] ?? null;
                $surveyRoad = $activeAssignment->report_data['road_access_verified'] ?? null;
                $surveyRec = $activeAssignment->report_data['recommendation'] ?? null;
                $surveySummary = $activeAssignment->notes ?? ($activeAssignment->report_data['notes'] ?? null);
                $surveyTimestamp = $activeAssignment->completed_at;
                $surveyorUser = $activeAssignment->assignee;
            }

            $hasActiveSurveyTask = ($activeSurvey !== null || $activeAssignment !== null);
        @endphp

        @if($hasActiveSurveyTask)
            <div class="card alf-panel mb-3 {{ $hasSubmittedFindings ? 'border-success' : 'border-primary' }}" id="survey-section">
                <div class="card-header d-flex justify-content-between align-items-center {{ $hasSubmittedFindings ? 'bg-success-subtle text-success-emphasis' : 'bg-primary-subtle text-primary' }}">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-map-location-dot fs-5"></i>
                        <strong>Field Survey & Land Verification</strong>
                    </div>
                    @if($hasSubmittedFindings)
                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> FINDINGS SUBMITTED</span>
                    @elseif($activeSurvey?->status === \App\Models\ApplicationSurvey::STATUS_VISIT_COMPLETED)
                        <span class="badge bg-info"><i class="fa-solid fa-check-double me-1"></i> VISIT COMPLETED</span>
                    @elseif($activeSurvey?->status === \App\Models\ApplicationSurvey::STATUS_VISIT_SCHEDULED)
                        <span class="badge bg-primary"><i class="fa-solid fa-calendar-check me-1"></i> VISIT SCHEDULED</span>
                    @else
                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> SURVEY ASSIGNED</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($hasSubmittedFindings)
                        {{-- 1. SUBMITTED FINDINGS VIEW --}}
                        <div class="alf-meta-grid mb-3">
                            <div class="alf-meta-item">
                                <span class="alf-meta-item__label">Field Surveyor</span>
                                <span class="alf-meta-item__value"><strong>{{ $surveyorUser?->name ?? 'Field Officer' }}</strong></span>
                            </div>
                            <div class="alf-meta-item">
                                <span class="alf-meta-item__label">Verification Date</span>
                                <span class="alf-meta-item__value">{{ $surveyTimestamp ? \Carbon\Carbon::parse($surveyTimestamp)->format('d M Y, h:i A') : '—' }}</span>
                            </div>
                            <div class="alf-meta-item">
                                <span class="alf-meta-item__label">Verified Land Area</span>
                                <span class="alf-meta-item__value fw-bold text-primary">{{ $surveyLand !== null ? $surveyLand . ' Kanals' : '—' }}</span>
                            </div>
                            <div class="alf-meta-item">
                                <span class="alf-meta-item__label">Survey Recommendation</span>
                                <span class="alf-meta-item__value">
                                    @if($surveyRec === 'recommended')
                                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Recommended</span>
                                    @elseif($surveyRec === 'not_recommended')
                                        <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark me-1"></i> Not Recommended</span>
                                    @else
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i> Needs Further Audit</span>
                                    @endif
                                </span>
                            </div>
                            <div class="alf-meta-item">
                                <span class="alf-meta-item__label">Water Source</span>
                                <span class="alf-meta-item__value">{{ $surveyWater ?: '—' }}</span>
                            </div>
                            <div class="alf-meta-item">
                                <span class="alf-meta-item__label">Road Access</span>
                                <span class="alf-meta-item__value">{{ $surveyRoad ?: '—' }}</span>
                            </div>
                            @if($surveySummary)
                                <div class="alf-meta-item" style="grid-column: 1 / -1;">
                                    <span class="alf-meta-item__label">Field Observations & Notes</span>
                                    <span class="alf-meta-item__value fst-italic">{{ $surveySummary }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- GPS Boundary Coordinates (3 or more) --}}
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div class="fw-bold text-dark">
                                    <i class="fa-solid fa-location-crosshairs text-primary me-1"></i>
                                    Land Boundary GPS Perimeter ({{ count($surveyPoints) }} Points Recorded)
                                </div>
                                @if(!empty($surveyPoints))
                                    @php
                                        $pFirst = $surveyPoints[0];
                                        $mapLat = $pFirst['lat'] ?? null;
                                        $mapLng = $pFirst['lng'] ?? null;
                                    @endphp
                                    @if($mapLat && $mapLng)
                                        <div class="btn-group btn-group-sm">
                                            <a href="https://www.google.com/maps?q={{ $mapLat }},{{ $mapLng }}" target="_blank" rel="noopener" class="btn btn-outline-primary">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Google Maps
                                            </a>
                                            <a href="https://www.openstreetmap.org/?mlat={{ $mapLat }}&mlon={{ $mapLng }}#map=17/{{ $mapLat }}/{{ $mapLng }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                                                <i class="fa-solid fa-map me-1"></i> OpenStreetMap
                                            </a>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            @if(count($surveyPoints) > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-striped mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 75px;" class="text-center">Point #</th>
                                                <th>Boundary / Corner Label</th>
                                                <th>Latitude</th>
                                                <th>Longitude</th>
                                                <th>Accuracy</th>
                                                <th>Timestamp</th>
                                                <th style="width: 60px;" class="text-center">Map</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($surveyPoints as $idx => $pt)
                                                <tr>
                                                    <td class="text-center">
                                                        <span class="badge bg-secondary">Point {{ $pt['point_number'] ?? ($idx + 1) }}</span>
                                                    </td>
                                                    <td><strong>{{ $pt['label'] ?? ('Boundary Point ' . ($idx + 1)) }}</strong></td>
                                                    <td><code>{{ number_format($pt['lat'] ?? 0, 7) }}</code></td>
                                                    <td><code>{{ number_format($pt['lng'] ?? 0, 7) }}</code></td>
                                                    <td>
                                                        @if(!empty($pt['accuracy']))
                                                            <span class="badge bg-info-subtle text-info">± {{ $pt['accuracy'] }} m</span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="small text-muted">{{ !empty($pt['timestamp']) ? \Carbon\Carbon::parse($pt['timestamp'])->format('d M Y H:i') : '—' }}</td>
                                                    <td class="text-center">
                                                        <a href="https://www.google.com/maps?q={{ $pt['lat'] }},{{ $pt['lng'] }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary py-0 px-1" title="View pin on Google Maps">
                                                            <i class="fa-solid fa-location-dot"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-muted small">No GPS points recorded for this survey.</div>
                            @endif
                        </div>

                        {{-- Land Photos Gallery (3 Photos, max 5 MB each) --}}
                        <div>
                            <div class="fw-bold text-dark mb-2">
                                <i class="fa-solid fa-camera text-primary me-1"></i>
                                Land Inspection Photos ({{ count($surveyPhotos) }} / 3 Photos Uploaded, Max 5 MB each)
                            </div>
                            @if(count($surveyPhotos) > 0)
                                <div class="row g-3">
                                    @foreach($surveyPhotos as $idx => $ph)
                                        @php
                                            $photoUrl = !empty($ph['url']) ? $ph['url'] : (!empty($ph['path']) ? asset('storage/'.$ph['path']) : null);
                                            $photoLabel = $ph['label'] ?? ('Land Photo ' . ($idx + 1));
                                            $photoSize = !empty($ph['size_kb']) ? round($ph['size_kb'] / 1024, 2) . ' MB' : null;
                                        @endphp
                                        <div class="col-md-4 col-sm-6">
                                            <div class="card h-100 border shadow-sm">
                                                @if($photoUrl)
                                                    <div class="position-relative" style="height: 180px; overflow: hidden; background: #f8f9fa;">
                                                        <img src="{{ $photoUrl }}" alt="{{ $photoLabel }}" class="w-100 h-100 open-photo-modal" style="object-fit: cover; cursor: pointer;" data-img-url="{{ $photoUrl }}" data-img-caption="{{ $photoLabel }} · {{ $photoSize }}">
                                                        <span class="badge bg-dark bg-opacity-75 position-absolute top-0 start-0 m-2">Photo {{ $idx + 1 }}</span>
                                                        @if($photoSize)
                                                            <span class="badge bg-secondary position-absolute top-0 end-0 m-2">{{ $photoSize }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                                <div class="card-body p-2">
                                                    <div class="fw-semibold small text-truncate" title="{{ $photoLabel }}">{{ $photoLabel }}</div>
                                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                                        <span class="text-muted" style="font-size: 11px;">{{ !empty($ph['uploaded_at']) ? \Carbon\Carbon::parse($ph['uploaded_at'])->format('d M Y') : '' }}</span>
                                                        @if($photoUrl)
                                                            <a href="{{ $photoUrl }}" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 11px;">
                                                                <i class="fa-solid fa-expand me-1"></i> Full Size
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted small">No land photos uploaded for this survey.</div>
                            @endif
                        </div>

                    @elseif($canPerformSurvey)
                        {{-- 2. FIELD OFFICER ACTION FORM: CAPTURE GPS & UPLOAD 3 PHOTOS --}}
                        <div class="alert alert-info py-2 px-3 mb-3">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            <strong>Field Officer Action Required</strong>: You are assigned to physically survey this land parcel. Please record <strong>3 or more GPS perimeter points</strong> using your mobile device/GPS and attach <strong>3 photos of the land</strong> (max 5 MB each).
                        </div>

                        {{-- Visit Scheduling Step (if still in assigned stage) --}}
                        @if($activeSurvey && $activeSurvey->status === \App\Models\ApplicationSurvey::STATUS_ASSIGNED)
                            <div class="mb-3 p-3 bg-light rounded border">
                                <div class="fw-bold text-dark small mb-2">
                                    <i class="fa-solid fa-calendar-plus me-1 text-primary"></i> Step 1: Schedule On-Site Visit (Optional)
                                </div>
                                <form method="POST" action="{{ route('admin.applications.surveys.schedule', $activeSurvey) }}" class="row g-2 align-items-center">
                                    @csrf
                                    <div class="col-sm-6">
                                        <input type="datetime-local" name="scheduled_visit_at" class="form-control form-control-sm" required value="{{ now()->addDay()->setHour(10)->format('Y-m-d\TH:i') }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                            <i class="fa-solid fa-calendar-check me-1"></i> Save Scheduled Visit Date
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        {{-- Step 2: Main Survey Findings, GPS & Photos Form --}}
                        @php
                            $formAction = $activeSurvey 
                                ? route('admin.applications.surveys.findings', $activeSurvey) 
                                : route('admin.applications.assignments.report', $activeAssignment);
                        @endphp
                        <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" id="survey-findings-form">
                            @csrf
                            
                            {{-- Land & Water Verification Inputs --}}
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Verified Land Area (Kanals) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.1" min="0" name="land_verified_kanals" class="form-control form-control-sm" value="{{ old('land_verified_kanals') }}" placeholder="e.g. 8.5" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Water Source</label>
                                    <select name="water_source_verified" class="form-select form-select-sm">
                                        <option value="Perennial Irrigation Channel / Glacier Canal" {{ old('water_source_verified') === 'Perennial Irrigation Channel / Glacier Canal' ? 'selected' : '' }}>Perennial Irrigation Channel (Kuhl)</option>
                                        <option value="Tubewell / Borewell" {{ old('water_source_verified') === 'Tubewell / Borewell' ? 'selected' : '' }}>Tubewell / Borewell</option>
                                        <option value="Spring / Stream" {{ old('water_source_verified') === 'Spring / Stream' ? 'selected' : '' }}>Natural Spring / Stream</option>
                                        <option value="Rainfed / Barani" {{ old('water_source_verified') === 'Rainfed / Barani' ? 'selected' : '' }}>Rainfed / Barani</option>
                                        <option value="Lift Irrigation" {{ old('water_source_verified') === 'Lift Irrigation' ? 'selected' : '' }}>Lift Irrigation System</option>
                                        <option value="No Water Source" {{ old('water_source_verified') === 'No Water Source' ? 'selected' : '' }}>No Water Source</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Road / Track Access</label>
                                    <select name="road_access_verified" class="form-select form-select-sm">
                                        <option value="Paved / Metal Road" {{ old('road_access_verified') === 'Paved / Metal Road' ? 'selected' : '' }}>Paved / Metal Road</option>
                                        <option value="Kacha Jeep Track" {{ old('road_access_verified') === 'Kacha Jeep Track' ? 'selected' : '' }}>Kacha Jeep Track</option>
                                        <option value="Footpath / Walking Trail Only" {{ old('road_access_verified') === 'Footpath / Walking Trail Only' ? 'selected' : '' }}>Footpath / Walking Trail Only</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Field Recommendation <span class="text-danger">*</span></label>
                                    <select name="recommendation" class="form-select form-select-sm" required>
                                        <option value="recommended" {{ old('recommendation') === 'recommended' ? 'selected' : '' }}>✓ Recommended for Benefit</option>
                                        <option value="needs_further_audit" {{ old('recommendation') === 'needs_further_audit' ? 'selected' : '' }}>⚠ Needs Further Audit</option>
                                        <option value="not_recommended" {{ old('recommendation') === 'not_recommended' ? 'selected' : '' }}>✗ Not Recommended (Ineligible)</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Field Observations & Findings Summary <span class="text-danger">*</span></label>
                                    <textarea name="findings_summary" class="form-control form-control-sm" rows="2" placeholder="Record soil condition, water availability, crop history, boundary markings..." required>{{ old('findings_summary') }}</textarea>
                                </div>
                            </div>

                            {{-- SECTION: GPS BOUNDARY POINTS (3 OR MORE REQUIRED) --}}
                            <div class="p-3 mb-3 border rounded bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <div>
                                        <div class="fw-bold text-dark">
                                            <i class="fa-solid fa-location-crosshairs text-primary me-1"></i>
                                            GPS Points of the Land (3 or more required)
                                        </div>
                                        <div class="small text-muted">
                                            Capture GPS coordinates from your mobile device while standing at each corner/boundary of the land, or enter coordinates manually.
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" id="btn-capture-mobile-gps">
                                        <i class="fa-solid fa-location-arrow me-1"></i> Capture Point from Mobile Device GPS
                                    </button>
                                </div>

                                {{-- Manual GPS Coordinate Fallback --}}
                                <div class="card mb-2 border-0 shadow-none bg-white p-2">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-3 col-6">
                                            <label class="form-label small mb-1">Latitude</label>
                                            <input type="number" step="0.0000001" min="-90" max="90" id="manual-lat" class="form-control form-control-sm" placeholder="e.g. 35.920834">
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <label class="form-label small mb-1">Longitude</label>
                                            <input type="number" step="0.0000001" min="-180" max="180" id="manual-lng" class="form-control form-control-sm" placeholder="e.g. 74.308332">
                                        </div>
                                        <div class="col-md-2 col-6">
                                            <label class="form-label small mb-1">Accuracy (m)</label>
                                            <input type="number" step="0.1" id="manual-acc" class="form-control form-control-sm" placeholder="e.g. 3.5">
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <label class="form-label small mb-1">Corner / Label</label>
                                            <input type="text" id="manual-label" class="form-control form-control-sm" placeholder="e.g. North-East Corner">
                                        </div>
                                        <div class="col-md-1 col-12">
                                            <button type="button" class="btn btn-sm btn-secondary w-100" id="btn-add-manual-gps" title="Add manual coordinate">
                                                <i class="fa-solid fa-plus"></i> Add
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Status counter alert --}}
                                <div id="gps-status-box" class="alert alert-warning py-2 px-3 mb-2 d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fa-solid fa-triangle-exclamation me-1" id="gps-status-icon"></i>
                                        <span id="gps-counter-text">0 / 3 GPS points recorded</span>
                                        <span class="small text-muted ms-1">(Perimeter requires minimum 3 points)</span>
                                    </div>
                                    <span class="badge bg-warning text-dark" id="gps-status-badge">Minimum 3 Required</span>
                                </div>

                                {{-- Dynamic Points Container --}}
                                <div id="gps-points-list" class="table-responsive bg-white rounded border" style="max-height: 220px; overflow-y: auto;">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 75px;" class="text-center">Point #</th>
                                                <th>Label</th>
                                                <th>Latitude</th>
                                                <th>Longitude</th>
                                                <th>Accuracy</th>
                                                <th style="width: 50px;" class="text-center">Remove</th>
                                            </tr>
                                        </thead>
                                        <tbody id="gps-points-tbody">
                                            <tr id="gps-empty-row">
                                                <td colspan="6" class="text-center text-muted py-2 small">
                                                    No GPS points captured yet. Tap <strong>Capture Point from Mobile Device GPS</strong> or add coordinates manually above.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <input type="hidden" name="gps_points" id="gps_points_json" value="{{ old('gps_points', '[]') }}">
                            </div>

                            {{-- SECTION: 3 LAND PHOTOS (MAX 5 MB EACH) --}}
                            <div class="p-3 mb-3 border rounded bg-light">
                                <div class="fw-bold text-dark mb-1">
                                    <i class="fa-solid fa-camera text-primary me-1"></i>
                                    Land Photos (Add 3 photos, Max 5 MB each)
                                </div>
                                <div class="small text-muted mb-3">
                                    Take photos on site with your mobile device camera or select image files. Maximum allowed file size is <strong>5 MB per photo</strong> (JPEG, PNG, WEBP).
                                </div>

                                <div class="row g-3">
                                    {{-- Photo 1 --}}
                                    <div class="col-md-4">
                                        <div class="card h-100 border p-2 bg-white">
                                            <label class="form-label small fw-bold mb-1">
                                                Photo 1: General Land View <span class="badge bg-secondary ms-1">Max 5MB</span>
                                            </label>
                                            <input type="file" name="photo_1" id="photo_1" accept="image/jpeg,image/png,image/jpg,image/webp" capture="environment" class="form-control form-control-sm survey-photo-input" data-preview="preview-1" data-info="info-1">
                                            <div id="info-1" class="small text-muted mt-1" style="font-size: 11px;"></div>
                                            <div id="preview-1" class="mt-2 text-center d-none">
                                                <img src="" alt="Photo 1 Preview" class="img-thumbnail rounded" style="max-height: 120px; object-fit: cover;">
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Photo 2 --}}
                                    <div class="col-md-4">
                                        <div class="card h-100 border p-2 bg-white">
                                            <label class="form-label small fw-bold mb-1">
                                                Photo 2: Irrigation / Water Source <span class="badge bg-secondary ms-1">Max 5MB</span>
                                            </label>
                                            <input type="file" name="photo_2" id="photo_2" accept="image/jpeg,image/png,image/jpg,image/webp" capture="environment" class="form-control form-control-sm survey-photo-input" data-preview="preview-2" data-info="info-2">
                                            <div id="info-2" class="small text-muted mt-1" style="font-size: 11px;"></div>
                                            <div id="preview-2" class="mt-2 text-center d-none">
                                                <img src="" alt="Photo 2 Preview" class="img-thumbnail rounded" style="max-height: 120px; object-fit: cover;">
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Photo 3 --}}
                                    <div class="col-md-4">
                                        <div class="card h-100 border p-2 bg-white">
                                            <label class="form-label small fw-bold mb-1">
                                                Photo 3: Road Access / Plot Corner <span class="badge bg-secondary ms-1">Max 5MB</span>
                                            </label>
                                            <input type="file" name="photo_3" id="photo_3" accept="image/jpeg,image/png,image/jpg,image/webp" capture="environment" class="form-control form-control-sm survey-photo-input" data-preview="preview-3" data-info="info-3">
                                            <div id="info-3" class="small text-muted mt-1" style="font-size: 11px;"></div>
                                            <div id="preview-3" class="mt-2 text-center d-none">
                                                <img src="" alt="Photo 3 Preview" class="img-thumbnail rounded" style="max-height: 120px; object-fit: cover;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold" id="btn-submit-findings">
                                <i class="fa-solid fa-paper-plane me-1"></i> Submit Survey Findings & Evidence (3+ GPS Points & Photos)
                            </button>
                        </form>

                    @else
                        {{-- 3. VIEWER IS NOT THE ASSIGNED SURVEYOR --}}
                        <div class="alert alert-secondary py-2 mb-0">
                            <div><strong>Field Survey Delegated</strong></div>
                            <div class="small">
                                Assigned to Field Officer: <strong>{{ $activeSurvey?->surveyor?->name ?? $activeAssignment?->assignee?->name ?? 'Field Officer' }}</strong>
                                @if($activeSurvey?->scheduled_visit_at)
                                    · Scheduled for: <strong>{{ $activeSurvey->scheduled_visit_at->format('d M Y, h:i A') }}</strong>
                                @endif
                            </div>
                            <div class="small text-muted mt-1">Awaiting physical on-site visit, 3+ GPS points perimeter capture, and 3 land photos from the field officer.</div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="card alf-panel mb-3">
            <div class="card-header">Form Field Values</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table alf-table mb-0">
                        <tbody>
                        @forelse($application->fieldValues as $value)
                            <tr>
                                <th style="width:40%">
                                    {{ $value->formField?->field_label }}
                                    @if($value->formField?->field_label_ur)
                                        <div class="small text-muted" dir="rtl" style="font-family:'Noto Nastaliq Urdu', Tahoma, sans-serif;">{{ $value->formField->field_label_ur }}</div>
                                    @endif
                                </th>
                                <td>
                                    @if($value->formField?->field_type === 'checkbox_with_text')
                                        @if(!empty($value->value))
                                            @php
                                                $parts = array_filter(array_map('trim', explode(';', $value->value)));
                                            @endphp
                                            <div class="d-flex flex-column gap-1">
                                                @foreach($parts as $part)
                                                    <div>
                                                        <span class="badge bg-success-subtle text-success me-1"><i class="fa-solid fa-check me-1"></i>Selected</span>
                                                        @if(str_contains($part, ':'))
                                                            @php [$pLabel, $pText] = array_map('trim', explode(':', $part, 2)); @endphp
                                                            <strong class="text-dark">{{ $pLabel }}:</strong>
                                                            <span class="text-secondary">{{ $pText }}</span>
                                                        @else
                                                            <strong class="text-dark">{{ $part }}</strong>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="badge bg-light text-muted border">None Selected / Unchecked</span>
                                        @endif
                                    @else
                                        {{ is_string($value->value) ? $value->value : json_encode($value->value) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-muted p-3">No field values recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card alf-panel mb-3">
            <div class="card-header">Documents</div>
            <ul class="list-group list-group-flush">
                @forelse($application->documents as $doc)
                    @php
                        $ext = strtolower(pathinfo($doc->original_name ?: $doc->file_path, PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
                        $fileUrl = $doc->file_path ? asset('storage/'.$doc->file_path) : null;
                    @endphp
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $doc->formDocument?->document_name }}</div>
                                <div class="small text-muted">{{ $doc->original_name }}</div>
                                @if($isImage && $fileUrl)
                                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="d-inline-block mt-2">
                                        <img src="{{ $fileUrl }}" alt="{{ $doc->original_name }}" class="border rounded" style="max-width:220px;max-height:140px;object-fit:cover;">
                                    </a>
                                @endif
                            </div>
                            <a href="{{ route('admin.applications.documents.download', [$application, $doc]) }}" class="btn btn-sm btn-outline-primary">Download</a>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No documents uploaded.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        @if(!empty($quotaStatus))
            <div class="card alf-panel mb-3">
                <div class="card-header">District Quota</div>
                <div class="card-body">
                    <div class="h4 mb-1">{{ $quotaStatus['used'] }} / {{ $quotaStatus['quota'] }}</div>
                    <div class="small text-muted mb-2">Approved / Disbursed vs quota for {{ $application->district?->name }}</div>
                    <div class="progress" style="height:8px;">
                        @php $pct = $quotaStatus['quota'] > 0 ? min(100, round(($quotaStatus['used'] / $quotaStatus['quota']) * 100)) : 0; @endphp
                        <div class="progress-bar {{ $quotaStatus['exceeded'] ? 'bg-danger' : 'bg-success' }}" style="width: {{ $pct }}%"></div>
                    </div>
                    @if($quotaStatus['exceeded'])
                        <div class="text-danger small mt-2">Quota full — further approvals blocked.</div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Unassigned Queue Alert & Assignment --}}
        @if($application->is_unassigned)
            <div class="card alf-panel border-danger mb-3">
                <div class="card-header bg-danger text-white">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Unassigned Queue
                </div>
                <div class="card-body">
                    <p class="small text-danger mb-3">
                        This application could not be automatically assigned because no active Deputy Director was found matching Department ID <strong>{{ $application->department_id }}</strong> and District <strong>{{ $application->district?->name }}</strong>.
                    </p>
                    @if(auth()->user()->hasAnyRole(['Super Admin', 'Secretary', 'Division Director']) && isset($deputyDirectors) && $deputyDirectors->isNotEmpty())
                        <form method="POST" action="{{ route('admin.applications.assign-dd', $application) }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Assign to Deputy Director</label>
                                <select name="deputy_director_id" class="form-select form-select-sm" required>
                                    <option value="">Select Deputy Director...</option>
                                    @foreach($deputyDirectors as $dd)
                                        <option value="{{ $dd->id }}">{{ $dd->name }} ({{ $dd->district?->name ?? 'All Districts' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <textarea name="note" class="form-control form-control-sm" rows="1" placeholder="Assignment note..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-danger w-100">
                                <i class="fa-solid fa-user-check me-1"></i> Assign Deputy Director
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- Challan & Payment Verification (Accounts Officer / DD) --}}
        @if($application->challan_no || in_array($application->current_stage, [\App\Models\Application::STAGE_CHALLAN_ISSUED, \App\Models\Application::STAGE_PAYMENT_PROOF_SUBMITTED, \App\Models\Application::STAGE_PAYMENT_VERIFIED, \App\Models\Application::STAGE_DORMANT]))
            <div class="card alf-panel mb-3 border-info">
                <div class="card-header bg-info-subtle text-info-emphasis d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-file-invoice-dollar me-1"></i> Challan & Payment</span>
                    @if($application->current_stage === \App\Models\Application::STAGE_DORMANT)
                        <span class="badge bg-secondary">DORMANT (EXPIRED)</span>
                    @elseif($application->payment_verified_at)
                        <span class="badge bg-success">PAYMENT VERIFIED</span>
                    @elseif($application->challan_paid_at)
                        <span class="badge bg-warning text-dark">PROOF SUBMITTED</span>
                    @else
                        <span class="badge bg-info">CHALLAN ISSUED</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="small mb-2">
                        <div><strong>Challan No:</strong> {{ $application->challan_no ?: 'Generated on Approval' }}</div>
                        <div><strong>Amount:</strong> PKR {{ number_format($application->challan_amount ?: 0, 2) }}</div>
                        <div><strong>Due Date:</strong> {{ optional($application->challan_due_date)->format('d M Y') ?: '14 calendar days' }}</div>
                        @if($application->payment_verified_at)
                            <div class="text-success mt-1"><strong>Verified By:</strong> {{ $application->paymentVerifiedBy?->name ?? 'Accounts Officer' }} on {{ $application->payment_verified_at->format('d M Y H:i') }}</div>
                        @endif
                    </div>

                    <div class="d-flex gap-2 flex-wrap mb-2">
                        @if($application->challan_no)
                            <a href="{{ route('applications.challan.download', $application->application_no) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="fa-solid fa-download me-1"></i> Download Challan PDF
                            </a>
                        @endif
                        @if($application->payment_receipt_path)
                            <a href="{{ asset('storage/'.$application->payment_receipt_path) }}" class="btn btn-sm btn-outline-success" target="_blank">
                                <i class="fa-solid fa-receipt me-1"></i> View Payment Receipt
                            </a>
                        @endif
                    </div>

                    {{-- Accounts Officer Verification Action --}}
                    @if(auth()->user()->can('verifyPayment', $application) && $application->current_stage === \App\Models\Application::STAGE_PAYMENT_PROOF_SUBMITTED)
                        <form method="POST" action="{{ route('admin.applications.verify-payment', $application) }}" class="mt-2 pt-2 border-top">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small fw-bold text-success">Verify Bank Deposit & Clear Beneficiary</label>
                                <textarea name="note" class="form-control form-control-sm" rows="1" placeholder="Bank scroll / deposit verification note..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-success w-100">
                                <i class="fa-solid fa-stamp me-1"></i> Verify Payment (Clear Beneficiary)
                            </button>
                        </form>
                    @endif

                    {{-- Reactivate Dormant Application (DD Authority) --}}
                    @if($application->current_stage === \App\Models\Application::STAGE_DORMANT && auth()->user()->can('reactivate', $application))
                        <form method="POST" action="{{ route('admin.applications.reactivate', $application) }}" class="mt-2 pt-2 border-top">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small fw-bold text-danger">Reactivate Dormant Case (Documented DD Decision)</label>
                                <textarea name="documented_reason" class="form-control form-control-sm" rows="2" placeholder="Record reasons for extension / reactivation..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-warning w-100">
                                <i class="fa-solid fa-arrows-rotate me-1"></i> Reactivate & Reissue Challan (14 Days)
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- Correction Request (DD Authority) --}}
        @if(auth()->user()->can('requestCorrection', $application) && in_array($application->current_stage, [\App\Models\Application::STAGE_UNDER_REVIEW, 'pending', 'submitted', \App\Models\Application::STAGE_FINDINGS_SUBMITTED]))
            <div class="card alf-panel mb-3 border-warning">
                <div class="card-header bg-warning-subtle text-warning-emphasis">
                    <i class="fa-solid fa-pencil me-1"></i> Request Applicant Correction
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.applications.correction-request', $application) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Required Corrections / Notes</label>
                            <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="Detail what the citizen needs to update or clarify..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-warning w-100">
                            <i class="fa-solid fa-paper-plane me-1"></i> Send Correction Request
                        </button>
                    </form>
                </div>
            </div>
        @endif

        @if(count($allowedTransitions))
            <div class="card alf-panel mb-3">
                <div class="card-header">Update Status / Transition</div>
                <div class="card-body alf-form-grid">
                    <form method="POST" action="{{ route('admin.applications.status', $application) }}">
                        @csrf
                        <input type="hidden" name="lock_version" value="{{ $application->lock_version }}">
                        <div class="mb-3">
                            <label class="form-label">New status / Lifecycle Stage</label>
                            <select name="status" class="form-control" required id="status-select">
                                @foreach($allowedTransitions as $status)
                                    <option value="{{ $status }}">{{ \App\Models\Application::translateStatus($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Verification note</label>
                            <textarea name="verification_note" class="form-control" rows="2">{{ old('verification_note', $application->verification_note) }}</textarea>
                        </div>
                        <div class="mb-3" id="rejection-box">
                            <label class="form-label">Rejection reason</label>
                            <textarea name="rejection_reason" class="form-control" rows="2">{{ old('rejection_reason', $application->rejection_reason) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Log note</label>
                            <textarea name="note" class="form-control" rows="2"></textarea>
                        </div>
                        <button class="btn btn-primary w-100">Update Status</button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Field Officer Task Delegation (District DD) --}}
        @if($fieldOfficers->isNotEmpty() && auth()->user()->can('assignFieldOfficer', $application))
            <div class="card alf-panel mb-3">
                <div class="card-header bg-primary-subtle text-primary">
                    <i class="fa-solid fa-user-plus me-1"></i> Delegate Field Survey
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.applications.assign-survey', $application) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Field Officer</label>
                            <select name="assigned_to" class="form-select form-select-sm" required>
                                <option value="">Select Field Officer...</option>
                                @foreach($fieldOfficers as $fo)
                                    <option value="{{ $fo->id }}">{{ $fo->name }} ({{ $fo->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Due Date</label>
                            <input type="date" name="due_date" class="form-control form-control-sm" value="{{ now()->addDays(3)->toDateString() }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Instructions / Focus</label>
                            <textarea name="instructions" class="form-control form-control-sm" rows="2" placeholder="e.g. Inspect actual canal water availability, verify cultivated land, record 3+ GPS points and 3 land photos."></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="fa-solid fa-paper-plane me-1"></i> Assign Field Survey
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Operational Field Tasks & Survey Reports --}}
        @if($application->assignments->isNotEmpty())
            <div class="card alf-panel mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-clipboard-check me-1"></i> Field Tasks</span>
                    <span class="badge bg-secondary">{{ $application->assignments->count() }}</span>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach($application->assignments as $asn)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div>
                                    <strong class="d-block">{{ ucwords(str_replace('_', ' ', $asn->task_type)) }}</strong>
                                    <span class="small text-muted">Assigned to: <strong>{{ $asn->assignee?->name ?? 'Unknown' }}</strong></span>
                                </div>
                                <span class="badge bg-{{ $asn->status === 'completed' ? 'success' : ($asn->status === 'in_progress' ? 'info' : 'warning') }}">
                                    {{ strtoupper(str_replace('_', ' ', $asn->status)) }}
                                </span>
                            </div>
                            @if($asn->notes)
                                <div class="small bg-light p-2 rounded mb-2">
                                    <em>"{{ $asn->notes }}"</em>
                                </div>
                            @endif
                            @if($asn->report_data)
                                <div class="small p-2 bg-success-subtle text-success rounded mb-2">
                                    <div><strong>Land Verified:</strong> {{ $asn->report_data['land_verified_kanals'] ?? 'N/A' }} Kanals</div>
                                    <div><strong>Recommendation:</strong> {{ ucfirst(str_replace('_', ' ', $asn->report_data['recommendation'] ?? 'None')) }}</div>
                                    @if(!empty($asn->report_data['gps_points']))
                                        <div><strong>GPS Points:</strong> {{ count($asn->report_data['gps_points']) }} points recorded</div>
                                    @endif
                                    @if(!empty($asn->report_data['photos']))
                                        <div><strong>Land Photos:</strong> {{ count($asn->report_data['photos']) }} photos attached</div>
                                    @endif
                                    @if(!empty($asn->completed_at))
                                        <div class="text-muted" style="font-size: 11px;">Completed: {{ $asn->completed_at->format('d M Y H:i') }}</div>
                                    @endif
                                </div>
                            @endif

                            {{-- Task completion button directing to comprehensive GPS/Photo form --}}
                            @if(($asn->assigned_to === auth()->id() || auth()->user()->hasRole('Super Admin')) && $asn->status !== 'completed')
                                <a href="#survey-section" class="btn btn-sm btn-primary w-100 mt-1">
                                    <i class="fa-solid fa-map-location-dot me-1"></i> Open Field Survey & GPS Form
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card alf-panel mb-3">
            <div class="card-header">Status Log</div>
            <ul class="list-group list-group-flush">
                @forelse($application->statusLogs as $log)
                    <li class="list-group-item">
                        <div><strong>{{ $log->from_status ?: '—' }} → {{ $log->to_status }}</strong></div>
                        <div class="small text-muted">{{ $log->user?->name }} · {{ $log->created_at?->format('d M Y H:i') }}</div>
                        @if($log->note)<div class="small">{{ $log->note }}</div>@endif
                    </li>
                @empty
                    <li class="list-group-item text-muted">No status changes yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="surveyPhotoModal" tabindex="-1" aria-labelledby="surveyPhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="surveyPhotoModalLabel"><i class="fa-solid fa-image me-2 text-primary"></i>Land Inspection Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-2 bg-dark">
                <img id="surveyPhotoModalImg" src="" alt="Land Photo" class="img-fluid rounded" style="max-height: 75vh;">
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted" id="surveyPhotoModalCaption"></span>
                <a id="surveyPhotoModalDownload" href="" target="_blank" download class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-download me-1"></i> Download Original
                </a>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Status rejection reason toggle
    const select = document.getElementById('status-select');
    const box = document.getElementById('rejection-box');
    if (select && box) {
        const toggle = () => { box.style.display = select.value === 'rejected' ? '' : 'none'; };
        select.addEventListener('change', toggle);
        toggle();
    }

    // Photo modal opener
    document.querySelectorAll('.open-photo-modal').forEach(function(img) {
        img.addEventListener('click', function() {
            const url = this.getAttribute('data-img-url');
            const caption = this.getAttribute('data-img-caption');
            const modalImg = document.getElementById('surveyPhotoModalImg');
            const modalCaption = document.getElementById('surveyPhotoModalCaption');
            const modalDl = document.getElementById('surveyPhotoModalDownload');
            if (modalImg) modalImg.src = url;
            if (modalCaption) modalCaption.textContent = caption || '';
            if (modalDl) modalDl.href = url;
            const modalEl = document.getElementById('surveyPhotoModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        });
    });

    // GPS Points and Photo Upload Handler for Field Officer Form
    (function() {
        const hiddenGpsInput = document.getElementById('gps_points_json');
        if (!hiddenGpsInput) return; // not in surveyor form mode

        let currentPoints = [];
        try {
            const raw = hiddenGpsInput.value;
            if (raw) {
                currentPoints = JSON.parse(raw);
                if (!Array.isArray(currentPoints)) currentPoints = [];
            }
        } catch(e) {
            currentPoints = [];
        }

        const tbody = document.getElementById('gps-points-tbody');
        const emptyRow = document.getElementById('gps-empty-row');
        const statusBox = document.getElementById('gps-status-box');
        const statusIcon = document.getElementById('gps-status-icon');
        const counterText = document.getElementById('gps-counter-text');
        const statusBadge = document.getElementById('gps-status-badge');

        function renderPoints() {
            if (!tbody) return;

            // clear existing point rows
            const rows = tbody.querySelectorAll('.gps-point-row');
            rows.forEach(r => r.remove());

            if (currentPoints.length === 0) {
                if (emptyRow) emptyRow.style.display = '';
            } else {
                if (emptyRow) emptyRow.style.display = 'none';
                currentPoints.forEach(function(pt, idx) {
                    const tr = document.createElement('tr');
                    tr.className = 'gps-point-row';
                    tr.innerHTML = `
                        <td class="text-center"><span class="badge bg-secondary">Point ${idx + 1}</span></td>
                        <td><strong>${escapeHtml(pt.label || 'Point ' + (idx + 1))}</strong></td>
                        <td><code>${Number(pt.lat).toFixed(7)}</code></td>
                        <td><code>${Number(pt.lng).toFixed(7)}</code></td>
                        <td>${pt.accuracy ? `<span class="badge bg-info-subtle text-info">± ${pt.accuracy} m</span>` : '<span class="text-muted">—</span>'}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-outline-danger py-0 px-1 remove-point-btn" data-index="${idx}" title="Remove Point">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            // update status box
            const count = currentPoints.length;
            if (counterText) {
                counterText.textContent = `${count} / 3 GPS points recorded`;
            }
            if (count >= 3) {
                if (statusBox) {
                    statusBox.className = 'alert alert-success py-2 px-3 mb-2 d-flex justify-content-between align-items-center';
                }
                if (statusIcon) {
                    statusIcon.className = 'fa-solid fa-circle-check me-1';
                }
                if (statusBadge) {
                    statusBadge.className = 'badge bg-success';
                    statusBadge.textContent = 'Perimeter Valid (3+ Points)';
                }
            } else {
                if (statusBox) {
                    statusBox.className = 'alert alert-warning py-2 px-3 mb-2 d-flex justify-content-between align-items-center';
                }
                if (statusIcon) {
                    statusIcon.className = 'fa-solid fa-triangle-exclamation me-1';
                }
                if (statusBadge) {
                    statusBadge.className = 'badge bg-warning text-dark';
                    statusBadge.textContent = 'Minimum 3 Required';
                }
            }

            hiddenGpsInput.value = JSON.stringify(currentPoints);

            // attach remove listeners
            tbody.querySelectorAll('.remove-point-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.getAttribute('data-index'), 10);
                    currentPoints.splice(idx, 1);
                    renderPoints();
                });
            });
        }

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function(m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
            });
        }

        function addPoint(pt) {
            currentPoints.push(pt);
            renderPoints();
        }

        // Capture from mobile device GPS
        const btnMobile = document.getElementById('btn-capture-mobile-gps');
        if (btnMobile) {
            btnMobile.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported by your browser. Please enter coordinates manually.');
                    return;
                }

                const origHtml = btnMobile.innerHTML;
                btnMobile.disabled = true;
                btnMobile.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Getting GPS fix...';

                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        btnMobile.disabled = false;
                        btnMobile.innerHTML = origHtml;

                        const ptNum = currentPoints.length + 1;
                        const lat = parseFloat(pos.coords.latitude.toFixed(7));
                        const lng = parseFloat(pos.coords.longitude.toFixed(7));
                        const acc = pos.coords.accuracy ? parseFloat(pos.coords.accuracy.toFixed(1)) : null;
                        const label = 'Corner ' + ptNum + (acc ? ' (±' + acc + 'm)' : '');

                        addPoint({
                            point_number: ptNum,
                            lat: lat,
                            lng: lng,
                            accuracy: acc,
                            label: label,
                            timestamp: new Date().toISOString()
                        });
                    },
                    function(err) {
                        btnMobile.disabled = false;
                        btnMobile.innerHTML = origHtml;
                        let msg = 'Could not acquire GPS position.';
                        if (err.code === err.PERMISSION_DENIED) {
                            msg = 'Location access was denied. Please enable location permissions in your browser or device settings.';
                        } else if (err.code === err.TIMEOUT) {
                            msg = 'GPS request timed out. Please try again outside under open sky or enter coordinates manually.';
                        } else if (err.code === err.POSITION_UNAVAILABLE) {
                            msg = 'Location information is unavailable on this device. Please enter coordinates manually.';
                        }
                        alert(msg);
                    },
                    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
                );
            });
        }

        // Manual point entry
        const btnManual = document.getElementById('btn-add-manual-gps');
        if (btnManual) {
            btnManual.addEventListener('click', function() {
                const latInput = document.getElementById('manual-lat');
                const lngInput = document.getElementById('manual-lng');
                const accInput = document.getElementById('manual-acc');
                const labelInput = document.getElementById('manual-label');

                const lat = parseFloat(latInput.value);
                const lng = parseFloat(lngInput.value);
                const acc = accInput.value ? parseFloat(accInput.value) : null;
                const ptNum = currentPoints.length + 1;
                const label = labelInput.value.trim() || ('Boundary Corner ' + ptNum);

                if (isNaN(lat) || lat < -90 || lat > 90) {
                    alert('Please enter a valid Latitude between -90 and 90.');
                    latInput.focus();
                    return;
                }
                if (isNaN(lng) || lng < -180 || lng > 180) {
                    alert('Please enter a valid Longitude between -180 and 180.');
                    lngInput.focus();
                    return;
                }

                addPoint({
                    point_number: ptNum,
                    lat: parseFloat(lat.toFixed(7)),
                    lng: parseFloat(lng.toFixed(7)),
                    accuracy: acc ? parseFloat(acc.toFixed(1)) : null,
                    label: label,
                    timestamp: new Date().toISOString()
                });

                latInput.value = '';
                lngInput.value = '';
                accInput.value = '';
                labelInput.value = '';
            });
        }

        // Photo inputs validation & preview (Max 5 MB each)
        const MAX_PHOTO_BYTES = 5 * 1024 * 1024; // 5 MB
        document.querySelectorAll('.survey-photo-input').forEach(function(input) {
            input.addEventListener('change', function() {
                const previewId = this.getAttribute('data-preview');
                const infoId = this.getAttribute('data-info');
                const previewEl = document.getElementById(previewId);
                const infoEl = document.getElementById(infoId);

                if (!this.files || !this.files[0]) {
                    if (previewEl) previewEl.classList.add('d-none');
                    if (infoEl) infoEl.textContent = '';
                    return;
                }

                const file = this.files[0];
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);

                if (file.size > MAX_PHOTO_BYTES) {
                    alert(`File "${file.name}" is ${sizeMb} MB, which exceeds the 5 MB limit. Please select or capture a photo under 5 MB.`);
                    this.value = '';
                    if (previewEl) previewEl.classList.add('d-none');
                    if (infoEl) {
                        infoEl.innerHTML = `<span class="text-danger fw-bold"><i class="fa-solid fa-circle-xmark me-1"></i> Exceeds 5 MB limit (${sizeMb} MB)</span>`;
                    }
                    return;
                }

                if (infoEl) {
                    infoEl.innerHTML = `<span class="text-success"><i class="fa-solid fa-check me-1"></i> ${escapeHtml(file.name)} (${sizeMb} MB)</span>`;
                }

                if (previewEl) {
                    const img = previewEl.querySelector('img');
                    if (img) {
                        img.src = URL.createObjectURL(file);
                    }
                    previewEl.classList.remove('d-none');
                }
            });
        });

        // Form submit validation
        const form = document.getElementById('survey-findings-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (currentPoints.length < 3) {
                    e.preventDefault();
                    alert(`A minimum of 3 GPS points of the land boundary are required. Currently recorded: ${currentPoints.length}.\n\nPlease capture at least 3 points before submitting.`);
                    const sec = document.getElementById('gps-status-box');
                    if (sec) sec.scrollIntoView({ behavior: 'smooth' });
                    return false;
                }
                hiddenGpsInput.value = JSON.stringify(currentPoints);
            });
        }

        renderPoints();
    })();
});
</script>
@endpush
