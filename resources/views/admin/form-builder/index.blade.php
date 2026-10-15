@extends('layouts.admin')

@section('title', 'Application Forms')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Application Forms',
    'subtitle' => 'Design, customize, translate, and publish application forms for all department schemes.',
    'icon' => 'fa-solid fa-file-pen',
    'actions' => [
        ['label' => 'View Schemes', 'url' => route('admin.schemes.index'), 'class' => 'btn btn-light'],
    ],
])

@include('admin.partials.stats', ['stats' => [
    ['label' => 'Total Schemes', 'value' => $stats['total_schemes'], 'icon' => 'fa-solid fa-file-lines', 'tone' => 'slate'],
    ['label' => 'Published Forms', 'value' => $stats['published_forms'], 'icon' => 'fa-solid fa-circle-check', 'tone' => 'green'],
    ['label' => 'Draft Forms', 'value' => $stats['draft_forms'], 'icon' => 'fa-solid fa-file-lines', 'tone' => 'amber'],
]])

<div class="card alf-panel mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.form-builder.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted mb-1">Search Scheme</label>
                <input type="text" name="q" class="form-control" placeholder="Search by scheme title or slug..." value="{{ request('q') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small text-muted mb-1">Department</label>
                <select name="department_id" class="form-select">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" @selected((string) request('department_id') === (string) $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small text-muted mb-1">Form Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="published" @selected(request('status') === 'published')>Published</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="none" @selected(request('status') === 'none')>No Form Configured</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                @if(request()->hasAny(['q', 'department_id', 'status']))
                    <a href="{{ route('admin.form-builder.index') }}" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card alf-panel">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table alf-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="min-width: 260px;">Scheme</th>
                        <th>Department</th>
                        <th>Form Title & Version</th>
                        <th class="text-center">Fields</th>
                        <th class="text-center">Documents</th>
                        <th>Status</th>
                        <th class="text-end" style="min-width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($schemes as $scheme)
                    @php
                        $latestForm = $scheme->forms->first();
                        $publishedForm = $scheme->forms->firstWhere('is_published', true);
                        $form = $latestForm;
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $scheme->title }}</div>
                            <div class="small text-muted">{{ $scheme->slug }}</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $scheme->department?->name ?? '—' }}</span>
                        </td>
                        <td>
                            @if($form)
                                <div>{{ $form->title }}</div>
                                <span class="badge bg-secondary">v{{ $form->version }}</span>
                            @else
                                <span class="text-muted fst-italic">Default template will be created</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($form)
                                <span class="badge bg-info text-dark" title="{{ $form->fields_count }} fields configured">
                                    <i class="fa-solid fa-list me-1"></i> {{ $form->fields_count }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($form)
                                <span class="badge bg-light text-dark border" title="{{ $form->documents_count }} documents required">
                                    <i class="fa-solid fa-paperclip me-1"></i> {{ $form->documents_count }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($form && $form->is_published)
                                <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Published</span>
                            @elseif($form)
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> Draft</span>
                            @else
                                <span class="badge bg-secondary">Not Created</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('admin.form-builder.edit', $scheme) }}" class="btn btn-sm btn-primary" title="Edit Form">
                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Form
                                </a>
                                @if($scheme->type === 'running' || ($form && $form->is_published))
                                    <a href="{{ route('schemes.apply', $scheme->slug) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Preview Public Form">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="alf-empty text-center py-4">No schemes or forms found matching your criteria.</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($schemes->hasPages())
            <div class="p-3 border-top">
                {{ $schemes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
