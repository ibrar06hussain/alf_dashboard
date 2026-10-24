<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Applications Register — Agriculture Department Gilgit-Baltistan</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.35;
            margin: 12px;
        }
        .header {
            border-bottom: 2.5px solid #0f766e;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            font-size: 15px;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header h2 {
            margin: 3px 0 0;
            font-size: 11.5px;
            color: #334155;
            font-weight: 600;
        }
        .meta-bar {
            margin-top: 6px;
            padding: 5px 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 8.5px;
            color: #475569;
        }
        .meta-bar table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .meta-bar td {
            border: none;
            padding: 2px 4px;
        }
        .filter-tags {
            margin-top: 6px;
            font-size: 8px;
            color: #64748b;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 8px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
            vertical-align: middle;
        }
        table.data-table th {
            background: #0f766e;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            text-transform: uppercase;
            font-size: 7.5px;
            letter-spacing: 0.3px;
        }
        table.data-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .text-success { color: #166534; }
        .text-muted { color: #64748b; }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: bold;
            text-align: center;
        }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-verified { background: #e0f2fe; color: #075985; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-disbursed { background: #ccfbf1; color: #115e59; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .footer {
            margin-top: 15px;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            font-size: 7.5px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Agriculture Department Gilgit Baltistan — Online Portal</h1>
        <h2>Applications & Beneficiaries Register Report</h2>
        <div class="meta-bar">
            <table>
                <tr>
                    <td width="35%"><strong>Date of Report Generation:</strong> {{ $generatedAt }}</td>
                    <td width="35%"><strong>Officer:</strong> {{ $generatedBy }}</td>
                    <td width="30%" class="text-end"><strong>Total Records:</strong> {{ number_format($applications->count()) }}</td>
                </tr>
            </table>
        </div>
        @if(!empty(array_filter($filters ?? [])))
            <div class="filter-tags">
                <strong>Active Filters:</strong>
                @if(!empty($filters['q'])) Search: "{{ $filters['q'] }}" | @endif
                @if(!empty($filters['status'])) Status: {{ ucfirst($filters['status']) }} | @endif
                @if(!empty($filters['min_land'])) Min Land: {{ $filters['min_land'] }} Kanals | @endif
                @if(!empty($filters['max_land'])) Max Land: {{ $filters['max_land'] }} Kanals | @endif
                @if(!empty($filters['min_bags'])) Min Bags: {{ $filters['min_bags'] }} | @endif
                @if(!empty($filters['max_bags'])) Max Bags: {{ $filters['max_bags'] }} | @endif
            </div>
        @endif
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="3%" class="text-center">#</th>
                <th width="12%">App No</th>
                <th width="14%">Applicant Details</th>
                <th width="11%">CNIC / Phone</th>
                <th width="12%">District / Location</th>
                <th width="16%">Scheme</th>
                <th width="9%" class="text-end">Available Land</th>
                <th width="8%" class="text-center">Bags Req</th>
                <th width="8%" class="text-center">Status</th>
                <th width="7%" class="text-center">Submitted</th>
            </tr>
        </thead>
        <tbody>
            @forelse($applications as $index => $app)
                @php
                    $applicant = $app->applicant;
                    $cnic = $applicant?->cnic;
                    $phone = $applicant?->phone;
                    if ($masked) {
                        $cnic = \App\Support\Cnic::mask($cnic);
                        $phone = \App\Support\Cnic::maskPhone($phone);
                    } else {
                        $cnic = \App\Support\Cnic::dashed($cnic);
                    }
                    $land = $app->getAvailableLandKanals();
                    $bags = $app->getBagsRequired();
                    $statusClass = match($app->status) {
                        'directorate_approved' => 'badge-approved',
                        'district_verified' => 'badge-verified',
                        'disbursed' => 'badge-disbursed',
                        'rejected' => 'badge-rejected',
                        default => 'badge-pending',
                    };
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="fw-bold">{{ $app->application_no }}</td>
                    <td>
                        <div class="fw-bold">{{ $applicant?->full_name ?? '—' }}</div>
                        @if($applicant?->father_name)
                            <div class="text-muted">S/O {{ $applicant->father_name }}</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $cnic ?? '—' }}</div>
                        <div class="text-muted">{{ $phone ?? '—' }}</div>
                    </td>
                    <td>
                        <div class="fw-bold">{{ $app->district?->name ?? '—' }}</div>
                        <div class="text-muted">
                            {{ $applicant?->tehsil ?: '' }}@if($applicant?->tehsil && $applicant?->village), @endif{{ $applicant?->village ?: '' }}
                        </div>
                    </td>
                    <td>
                        <div>{{ $app->scheme?->title ?? '—' }}</div>
                        <div class="text-muted" style="font-size: 7px;">{{ $app->scheme?->department?->name ?? '' }}</div>
                    </td>
                    <td class="text-end">
                        @if($land !== null)
                            <span class="fw-bold">{{ number_format($land, 1) }}</span> <span class="text-muted">Kanals</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($bags !== null)
                            <span class="fw-bold text-success">{{ $bags }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $statusClass }}">
                            {{ $app->statusLabel() }}
                        </span>
                    </td>
                    <td class="text-center text-muted">
                        {{ optional($app->submitted_at ?? $app->created_at)->format('d M Y') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 20px; color: #64748b;">
                        No applications found matching the selected criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Agriculture Department Gilgit Baltistan — Online Portal | Confidential Executive Report | Generated on: {{ $generatedAt }}
    </div>
</body>
</html>
