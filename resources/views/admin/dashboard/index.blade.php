@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Dashboard',
    'subtitle' => 'Overview of ALF schemes and applications.',
    'icon' => 'fa-solid fa-rocket',
])

@include('admin.partials.stats', ['stats' => [
    ['label' => 'Departments', 'value' => $kpis['departments'], 'icon' => 'fa-solid fa-landmark', 'tone' => 'green'],
    ['label' => 'Schemes', 'value' => $kpis['schemes'], 'icon' => 'fa-solid fa-file-lines', 'tone' => 'blue'],
    ['label' => 'Applications', 'value' => $kpis['applications'], 'icon' => 'fa-solid fa-folder', 'tone' => 'amber'],
    ['label' => 'Users', 'value' => $kpis['users'], 'icon' => 'fa-solid fa-users', 'tone' => 'slate'],
]])

@include('admin.partials.stats', ['stats' => [
    ['label' => 'Pending', 'value' => $kpis['pending'], 'icon' => 'fa-solid fa-clock', 'tone' => 'amber'],
    ['label' => 'District verified', 'value' => $kpis['district_verified'], 'icon' => 'fa-solid fa-location-dot', 'tone' => 'blue'],
    ['label' => 'Approved', 'value' => $kpis['directorate_approved'], 'icon' => 'fa-solid fa-thumbs-up', 'tone' => 'teal'],
    ['label' => 'Disbursed', 'value' => $kpis['disbursed'], 'icon' => 'fa-solid fa-hand-holding-dollar', 'tone' => 'green'],
]])

<div class="card alf-panel mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent Applications</span>
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
                        <td>{{ $app->district?->name }}</td>
                        <td><span class="alf-badge alf-badge--slate">{{ \App\Models\Application::STATUSES[$app->status] ?? $app->status }}</span></td>
                        <td><a href="{{ route('admin.applications.show', $app) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="alf-empty">No applications yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
