<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ALF Applications Export — CM Dashboard</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1e293b; margin: 12px; }
        h2 { margin: 0 0 4px; color: #0f766e; font-size: 14px; text-transform: uppercase; }
        .meta { margin-bottom: 8px; font-size: 8px; color: #475569; }
        .notice-banner {
            margin-bottom: 8px;
            padding: 4px 7px;
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
            font-size: 7.5px;
            border-radius: 3px;
        }
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 7.5px; }
        th, td { border-right: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; padding: 4px 5px; text-align: left; }
        th:first-child, td:first-child { border-left: 1px solid #cbd5e1; }
        tr:first-child th { border-top: 1px solid #cbd5e1; }
        th { background: #0f766e; color: #fff; text-transform: uppercase; font-size: 7px; letter-spacing: 0.3px; }
        tr:nth-child(even) { background: #f8fafc; }
        .badge { display: inline-block; padding: 2px 4px; border-radius: 3px; font-size: 6.5px; font-weight: bold; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-verified { background: #e0f2fe; color: #075985; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-disbursed { background: #ccfbf1; color: #115e59; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <h2>GB ALF Applications Export — CM Dashboard</h2>
    <div class="meta">
        <strong>Generated:</strong> {{ now()->format('d M Y, h:i A') }} |
        <strong>Total Matching Records:</strong> {{ number_format($totalCount ?? $applications->count()) }}
        @if(($totalCount ?? $applications->count()) > $applications->count())
            <span style="color: #64748b;">(Showing top {{ $applications->count() }})</span>
        @endif
    </div>

    @if(($totalCount ?? $applications->count()) > $applications->count())
        <div class="notice-banner">
            <strong>Note:</strong> Showing top {{ $applications->count() }} of {{ number_format($totalCount) }} matching records for optimal PDF rendering. For complete unfiltered datasets, please use the <strong>Export Excel</strong> or <strong>Export CSV</strong> option.
        </div>
    @endif

    <table>
        <thead>
        <tr>
            <th style="width: 20px;">#</th>
            <th style="width: 80px;">App No</th>
            <th>Applicant</th>
            <th style="width: 80px;">CNIC</th>
            <th>District</th>
            <th>Department</th>
            <th>Scheme</th>
            <th style="width: 70px;">Status</th>
            <th style="width: 65px;">Submitted</th>
        </tr>
        </thead>
        <tbody>
        @forelse($applications as $index => $application)
            @php
                $statusClass = match($application->status) {
                    'directorate_approved' => 'badge-approved',
                    'district_verified' => 'badge-verified',
                    'disbursed' => 'badge-disbursed',
                    'rejected' => 'badge-rejected',
                    default => 'badge-pending',
                };
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td><strong>{{ $application->application_no }}</strong></td>
                <td>{{ $application->applicant?->full_name ?? '—' }}</td>
                <td>{{ \App\Support\Cnic::dashed($application->applicant?->cnic) }}</td>
                <td>{{ $application->district?->name ?? '—' }}</td>
                <td>{{ $application->scheme?->department?->name ?? '—' }}</td>
                <td>{{ $application->scheme?->title ?? '—' }}</td>
                <td>
                    <span class="badge {{ $statusClass }}">
                        {{ $application->statusLabel() }}
                    </span>
                </td>
                <td>{{ optional($application->submitted_at ?? $application->created_at)?->format('d M Y') ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" style="text-align: center; color: #64748b; padding: 15px;">
                    No applications found matching the selected filters.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
