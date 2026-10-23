@extends('layouts.admin')

@section('title', 'Access & Action Audit Logs')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Access & Action Audit Logs',
    'subtitle' => 'Immutable audit trail of privileged transitions, operational assignments, and PII exports.',
    'icon' => 'fa-solid fa-clock-rotate-left',
    'actions' => [
        ['label' => 'Back to Users', 'url' => route('admin.users.index'), 'class' => 'btn btn-outline-secondary'],
    ],
])

<!-- Filters -->
<div class="card alf-panel mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">Filter by Action</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Filter by User</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All Users</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card alf-panel">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table alf-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="small text-muted" style="white-space:nowrap;">
                            {{ $log->created_at?->format('d M Y, H:i:s') }}
                        </td>
                        <td>
                            @if($log->user)
                                <div class="fw-bold text-dark">{{ $log->user->name }}</div>
                                <div class="small text-muted">{{ $log->user->email }}</div>
                            @else
                                <span class="text-muted">System / Anonymous</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-secondary font-monospace">{{ $log->action }}</span>
                        </td>
                        <td class="small">
                            @if($log->target_type)
                                <span class="text-muted">{{ class_basename($log->target_type) }}</span>
                                @if($log->target_id)
                                    #{{ $log->target_id }}
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="small font-monospace">{{ $log->ip_address ?: '—' }}</td>
                        <td class="small">
                            @if(!empty($log->payload))
                                <pre class="bg-light p-1 mb-0 rounded" style="font-size: 11px; max-width: 320px; overflow-x: auto;">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="alf-empty py-4">No audit logs recorded matching criteria.</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
