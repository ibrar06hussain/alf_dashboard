@extends('layouts.admin')

@section('title', $scopedDistrict ? ($scopedDistrict->name . ' District Dashboard') : 'Dashboard')

@section('content')
@include('admin.partials.page-hero', [
    'title' => $scopedDistrict ? ($scopedDistrict->name . ' District Dashboard') : 'Dashboard',
    'subtitle' => $scopedDistrict ? ('Overview of ALF schemes, applications, and statistics for ' . $scopedDistrict->name . ' District.') : 'Overview of ALF schemes and applications.',
    'icon' => $scopedDistrict ? 'fa-solid fa-location-dot' : 'fa-solid fa-rocket',
])

@if($scopedDistrict)
    <div class="alert alert-info d-flex align-items-center mb-3 shadow-sm border-0" role="alert" style="background: linear-gradient(135deg, #e0f2fe 0%, #f0fdf4 100%); color: #0369a1; border-left: 4px solid #0284c7 !important;">
        <i class="fa-solid fa-building-user fa-lg me-3 text-primary"></i>
        <div>
            <strong>District Officer Scope Active:</strong> You are viewing applications, metrics, and statistics scoped strictly to <strong>{{ $scopedDistrict->name }} District</strong>.
        </div>
    </div>
@endif

@include('admin.partials.stats', ['stats' => [
    ['label' => $scopedDistrict ? 'District' : 'Districts', 'value' => $scopedDistrict ? $scopedDistrict->name : $kpis['districts'], 'icon' => 'fa-solid fa-location-dot', 'tone' => 'green'],
    ['label' => 'Active Schemes', 'value' => $kpis['schemes'], 'icon' => 'fa-solid fa-file-lines', 'tone' => 'blue'],
    ['label' => 'Applications', 'value' => $kpis['applications'], 'icon' => 'fa-solid fa-folder', 'tone' => 'amber'],
    ['label' => $scopedDistrict ? 'District Applicants' : 'Users', 'value' => $scopedDistrict ? $kpis['applicants'] : $kpis['users'], 'icon' => $scopedDistrict ? 'fa-solid fa-address-book' : 'fa-solid fa-users', 'tone' => 'slate'],
]])

@include('admin.partials.stats', ['stats' => [
    ['label' => 'Pending Review', 'value' => $kpis['pending'], 'icon' => 'fa-solid fa-clock', 'tone' => 'amber'],
    ['label' => 'District Verified', 'value' => $kpis['district_verified'], 'icon' => 'fa-solid fa-circle-check', 'tone' => 'blue'],
    ['label' => 'Approved', 'value' => $kpis['directorate_approved'], 'icon' => 'fa-solid fa-thumbs-up', 'tone' => 'teal'],
    ['label' => 'Disbursed', 'value' => $kpis['disbursed'], 'icon' => 'fa-solid fa-hand-holding-dollar', 'tone' => 'green'],
]])

<div class="card alf-panel mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent Applications {{ $scopedDistrict ? '(' . $scopedDistrict->name . ' District Only)' : '' }}</span>
        <a href="{{ route('admin.applications.index') }}" class="btn btn-sm btn-light">View all</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table alf-table mb-0">
                <thead>
                <tr>
                    <th>App No</th>
                    <th>Applicant</th>
                    <th>Scheme</th>
                    <th>District</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentApplications as $app)
                    <tr>
                        <td><strong>{{ $app->application_no }}</strong></td>
                        <td>{{ $app->applicant?->full_name }}</td>
                        <td>{{ $app->scheme?->title }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $app->district?->name }}</span></td>
                        <td><span class="alf-badge alf-badge--slate">{{ \App\Models\Application::STATUSES[$app->status] ?? $app->status }}</span></td>
                        <td><a href="{{ route('admin.applications.show', $app) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="alf-empty">No applications yet in this district.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
