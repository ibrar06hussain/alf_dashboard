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
            border-collapse: separate;
            border-spacing: 0;
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
        .notice-banner {
            margin-top: 5px;
            padding: 4px 7px;
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
            font-size: 8px;
            border-radius: 3px;
        }
        table.data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 10px;
            font-size: 8px;
        }
        table.data-table th, table.data-table td {
            border-right: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            padding: 4px 5px;
            vertical-align: middle;
        }
        table.data-table th:first-child, table.data-table td:first-child {
            border-left: 1px solid #cbd5e1;
        }
        table.data-table tr:first-child th {
            border-top: 1px solid #cbd5e1;
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
                    <td width="30%" class="text-end">
                        <strong>Total Records:</strong> {{ number_format($totalCount ?? count($rowsData)) }}
                        @if(($totalCount ?? count($rowsData)) > count($rowsData))
                            <span class="text-muted">(Showing top {{ count($rowsData) }})</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
        @if(($totalCount ?? count($rowsData)) > count($rowsData))
            <div class="notice-banner">
                <strong>Note:</strong> Showing top {{ count($rowsData) }} of {{ number_format($totalCount) }} matching records for optimal PDF rendering. For complete unfiltered datasets, please use the <strong>Export Excel</strong> or <strong>Export CSV</strong> option.
            </div>
        @endif
        @if(!empty(array_filter($filters ?? [])))
            <div class="filter-tags">
                <strong>Active Filters:</strong>
                @if(!empty($filters['q'])) Search: "{{ $filters['q'] }}" | @endif
                @if(!empty($filters['status'])) Status: {{ ucfirst(str_replace('_', ' ', $filters['status'])) }} | @endif
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
            @forelse($rowsData as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="fw-bold">{{ $row['application_no'] }}</td>
                    <td>
                        <div class="fw-bold">{{ $row['full_name'] }}</div>
                        @if(!empty($row['father_name']))
                            <div class="text-muted">S/O {{ $row['father_name'] }}</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $row['cnic'] }}</div>
                        <div class="text-muted">{{ $row['phone'] }}</div>
                    </td>
                    <td>
                        <div class="fw-bold">{{ $row['district'] }}</div>
                        <div class="text-muted">{{ $row['location'] }}</div>
                    </td>
                    <td>
                        <div>{{ $row['scheme'] }}</div>
                        <div class="text-muted" style="font-size: 7px;">{{ $row['department'] }}</div>
                    </td>
                    <td class="text-end">
                        @if($row['land'] !== null)
                            <span class="fw-bold">{{ $row['land'] }}</span> <span class="text-muted">Kanals</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($row['bags'] !== null)
                            <span class="fw-bold text-success">{{ $row['bags'] }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $row['status_class'] }}">
                            {{ $row['status_label'] }}
                        </span>
                    </td>
                    <td class="text-center text-muted">
                        {{ $row['submitted_at'] }}
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
