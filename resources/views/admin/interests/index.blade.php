@extends('layouts.admin')

@section('title', 'Scheme Interests')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Express Interest',
    'subtitle' => 'Demand collected for proposed / upcoming schemes.',
    'icon' => 'fa-solid fa-bell',
])

@include('admin.partials.stats', ['stats' => $pageStats])

<div class="card alf-panel mb-3">
    <div class="card-body">
        <form method="GET" class="alf-filters row g-2">
            <div class="col-md-3">
                <select name="department_id" class="form-control">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="scheme_id" class="form-control">
                    <option value="">All proposed schemes</option>
                    @foreach($schemes as $scheme)
                        <option value="{{ $scheme->id }}" @selected(request('scheme_id') == $scheme->id)>{{ $scheme->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, CNIC, phone">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table alf-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Scheme</th>
                    <th>Name</th>
                    <th>CNIC</th>
                    <th>Phone</th>
                    <th>District</th>
                    <th>Message</th>
                </tr>
                </thead>
                <tbody>
                @forelse($interests as $interest)
                    <tr>
                        <td>{{ $interest->created_at?->format('d M Y') }}</td>
                        <td>
                            <div><strong>{{ $interest->scheme?->title }}</strong></div>
                            <small class="text-muted">{{ $interest->scheme?->department?->name }}</small>
                        </td>
                        <td>{{ $interest->full_name }}</td>
                        <td>{{ $interest->cnic }}</td>
                        <td>{{ $interest->phone }}</td>
                        <td>{{ $interest->district?->name ?? '—' }}</td>
                        <td>{{ $interest->message ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="alf-empty">No interest submissions yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $interests->links() }}</div>
    </div>
</div>
@endsection
