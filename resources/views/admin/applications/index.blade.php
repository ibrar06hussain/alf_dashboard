@extends('layouts.admin')

@section('title', 'Applications')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Applications',
    'subtitle' => 'Review and update application status across schemes.',
    'icon' => 'fa-solid fa-folder',
    'actions' => [
        ['label' => 'Export Excel', 'url' => route('admin.applications.export', array_merge(request()->query(), ['format' => 'xlsx'])), 'class' => 'btn btn-success'],
        ['label' => 'Export CSV', 'url' => route('admin.applications.export', array_merge(request()->query(), ['format' => 'csv'])), 'class' => 'btn btn-info'],
    ],
])

@include('admin.partials.stats', ['stats' => $pageStats])

<div class="card alf-panel mb-3">
    <div class="card-body">
        <form method="GET" class="alf-filters row g-2">
            <div class="col-md-3">
                <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="App no / name / CNIC / phone">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-control">
                    <option value="">All statuses</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="department_id" class="form-control">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="district_id" class="form-control">
                    <option value="">All districts</option>
                    @foreach($districts as $district)
                        <option value="{{ $district->id }}" @selected(request('district_id') == $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="scheme_id" class="form-control">
                    <option value="">All schemes</option>
                    @foreach($schemes as $scheme)
                        <option value="{{ $scheme->id }}" @selected(request('scheme_id') == $scheme->id)>{{ $scheme->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100">Go</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table alf-table">
                <thead>
                <tr>
                    <th>App No</th>
                    <th>Applicant</th>
                    <th>Scheme</th>
                    <th>Department</th>
                    <th>District</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($applications as $application)
                    <tr>
                        <td><strong>{{ $application->application_no }}</strong></td>
                        <td>
                            {{ $application->applicant?->full_name }}
                            <div class="text-muted small">{{ $application->applicant?->cnic }}</div>
                        </td>
                        <td>{{ $application->scheme?->title }}</td>
                        <td>{{ $application->scheme?->department?->name }}</td>
                        <td>{{ $application->district?->name }}</td>
                        <td><span class="alf-badge alf-badge--slate">{{ $statuses[$application->status] ?? $application->status }}</span></td>
                        <td>{{ $application->submitted_at?->format('d M Y') ?: $application->created_at?->format('d M Y') }}</td>
                        <td><a href="{{ route('admin.applications.show', $application) }}" class="btn btn-sm btn-primary">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="alf-empty">No applications found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $applications->links() }}</div>
    </div>
</div>
@endsection
