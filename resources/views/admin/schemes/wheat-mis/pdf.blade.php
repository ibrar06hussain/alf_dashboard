<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Wheat Seed (50:50 Cost Sharing) Scheme MIS Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; margin: 15px; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 16px; color: #0f766e; }
        .header h2 { margin: 3px 0 0; font-size: 12px; color: #475569; font-weight: normal; }
        .meta { font-size: 9px; color: #64748b; margin-top: 5px; }
        .section-title { font-size: 11px; font-weight: bold; color: #0f766e; margin: 12px 0 6px; text-transform: uppercase; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 9px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
        th { background: #f1f5f9; color: #334155; font-weight: bold; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .kpi-table th { width: 25%; }
        .badge { display: inline-block; padding: 2px 5px; border-radius: 4px; font-size: 8px; font-weight: bold; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #e0f2fe; color: #075985; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .footer { font-size: 8px; color: #94a3b8; text-align: center; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Government of Gilgit-Baltistan — Agriculture Department</h1>
        <h2>Distribution of Wheat Seed (50:50 Cost Sharing) Scheme MIS Report</h2>
        <div class="meta">
            <strong>Generated At:</strong> {{ $generatedAt }} | 
            <strong>Officer:</strong> {{ $generatedBy }} | 
            <strong>Data Scope:</strong> Gilgit-Baltistan Wheat Seed Program
        </div>
    </div>

    {{-- EXECUTIVE KPI SUMMARY --}}
    <div class="section-title">1. Executive Summary & Agronomic KPIs</div>
    <table class="kpi-table">
        <tr>
            <th>Total Applications:</th>
            <td>{{ number_format($kpis['total_applications']) }} (Approved: {{ $kpis['status_counts']['directorate_approved'] ?? 0 }}, Disbursed: {{ $kpis['status_counts']['disbursed'] ?? 0 }})</td>
            <th>Available Wheat Land:</th>
            <td>{{ number_format($kpis['land']['available_kanals'], 1) }} Kanals ({{ number_format($kpis['land']['available_acres'], 2) }} Acres)</td>
        </tr>
        <tr>
            <th>Last Year Cultivation:</th>
            <td>
                @if($kpis['land']['has_cultivated_data'])
                    {{ number_format($kpis['land']['cultivated_kanals_last_year'], 1) }} Kanals ({{ number_format($kpis['land']['cultivated_acres_last_year'], 2) }} Acres)
                @else
                    Not yet captured
                @endif
            </td>
            <th>Seed Bags (50kg Each):</th>
            <td>Requested: {{ number_format($kpis['bags']['requested']) }} | Quota: {{ number_format($kpis['bags']['allocated']) }} | Approved: {{ number_format($kpis['bags']['approved']) }}</td>
        </tr>
        <tr>
            <th>Total Approved Weight:</th>
            <td>{{ number_format($kpis['weights']['approved_mt'], 2) }} Metric Tons ({{ number_format($kpis['weights']['approved_kg']) }} kg)</td>
            <th>Seed Rate Average:</th>
            <td>{{ $kpis['averages']['requested_bags_per_applicant'] }} bags/app ({{ $kpis['averages']['bags_per_kanal'] }} bags/kanal)</td>
        </tr>
        <tr>
            <th>50:50 Scheme Cost-Sharing:</th>
            <td colspan="3">
                @if($kpis['cost_sharing']['is_configured'])
                    @if(!empty($kpis['cost_sharing']['is_district_varying']))
                        Delivered Rates: <strong>PKR {{ number_format($kpis['cost_sharing']['rate_min'], 0) }} – {{ number_format($kpis['cost_sharing']['rate_max'], 0) }}/bag</strong> (Transport Adjusted, Base: PKR {{ number_format($kpis['cost_sharing']['rate_per_bag'], 2) }}) |
                    @else
                        Approved Rate: <strong>PKR {{ number_format($kpis['cost_sharing']['rate_per_bag'], 2) }}/bag</strong> |
                    @endif
                    Total Scheme Value: <strong>PKR {{ number_format($kpis['cost_sharing']['total_approved_value']) }}</strong> |
                    Gov Share: <strong>PKR {{ number_format($kpis['cost_sharing']['gov_share_50']) }}</strong> |
                    Farmer Share: <strong>PKR {{ number_format($kpis['cost_sharing']['farmer_share_50']) }}</strong>
                    @if(!empty($kpis['cost_sharing']['is_district_varying']))
                        | Transport Carriage: <strong>PKR {{ number_format($kpis['cost_sharing']['total_transport_cost']) }}</strong>
                    @endif
                @else
                    Approved Bag Rate: <strong>Not yet configured / Not yet captured</strong> (Prices never assumed)
                @endif
            </td>
        </tr>
    </table>

    {{-- DISTRICT DEMAND-SUPPLY GAP MATRIX --}}
    <div class="section-title">2. District-wise Quota, Demand, Supply & Gaps (50kg Bags)</div>
    <table>
        <thead>
            <tr>
                <th>Division</th>
                <th>District</th>
                <th class="text-end">Quota (Supply)</th>
                <th class="text-end">Requested (Demand)</th>
                <th class="text-end">Approved</th>
                <th class="text-end">Disbursed</th>
                <th class="text-end">Net Gap</th>
                <th class="text-end">Approved MT</th>
                <th class="text-end">Delivered Rate</th>
                <th class="text-end">Total Valuation</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($demandSupplyGap as $row)
                <tr>
                    <td>{{ $row['division'] }}</td>
                    <td><strong>{{ $row['district_name'] }}</strong></td>
                    <td class="text-end">{{ number_format($row['quota']) }}</td>
                    <td class="text-end">{{ number_format($row['requested_bags']) }}</td>
                    <td class="text-end">{{ number_format($row['approved_bags']) }}</td>
                    <td class="text-end">{{ number_format($row['disbursed_bags']) }}</td>
                    <td class="text-end">{{ $row['gap'] > 0 ? '+' : '' }}{{ number_format($row['gap']) }}</td>
                    <td class="text-end">{{ number_format($row['approved_mt'], 2) }} MT</td>
                    <td class="text-end">
                        @if($row['rate_per_bag'] !== null)
                            PKR {{ number_format($row['rate_per_bag'], 0) }}
                            @if($row['transport_cost'] > 0)
                                (+{{ number_format($row['transport_cost'], 0) }})
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-end">
                        {{ $row['approved_cost'] > 0 ? 'PKR ' . number_format($row['approved_cost'], 0) : '—' }}
                    </td>
                    <td class="text-center">{{ $row['status_badge'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- BENEFICIARY SAMPLES --}}
    <div class="section-title">3. Beneficiary & Applicant Registry (Sample / Filtered Records)</div>
    <table>
        <thead>
            <tr>
                <th>App No</th>
                <th>Applicant</th>
                <th>CNIC</th>
                <th>Phone</th>
                <th>District</th>
                <th>Tehsil</th>
                <th>Village</th>
                <th class="text-end">Land (Kanals)</th>
                <th class="text-end">Req Bags</th>
                <th class="text-end">Appr Bags</th>
                <th class="text-center">Survey Status</th>
                <th class="text-center">Decision</th>
            </tr>
        </thead>
        <tbody>
            @forelse($applications->take(30) as $app)
                @php
                    $parsed = app(\App\Services\WheatSeedMisService::class)->extractAppAttributes($app);
                    $cnic = $maskPii ? \App\Support\Cnic::mask($app->applicant?->cnic) : \App\Support\Cnic::dashed($app->applicant?->cnic);
                    $phone = $maskPii ? \App\Support\Cnic::maskPhone($app->applicant?->phone) : ($app->applicant?->phone ?? '—');
                    $apprBags = in_array($app->status, ['directorate_approved', 'disbursed'], true) ? $parsed['requested_bags'] : 0;
                    $surveySt = (in_array($app->status, ['district_verified', 'directorate_approved', 'disbursed'], true) || ! empty($app->verification_note)) ? 'Verified' : 'Pending';
                @endphp
                <tr>
                    <td>{{ $app->application_no }}</td>
                    <td>{{ $app->applicant?->full_name }}</td>
                    <td>{{ $cnic }}</td>
                    <td>{{ $phone }}</td>
                    <td>{{ $app->district?->name }}</td>
                    <td>{{ $app->applicant?->tehsil }}</td>
                    <td>{{ $app->applicant?->village }}</td>
                    <td class="text-end">{{ $parsed['land_kanals'] !== null ? number_format($parsed['land_kanals'], 1) : '—' }}</td>
                    <td class="text-end">{{ $parsed['requested_bags'] }}</td>
                    <td class="text-end">{{ $apprBags }}</td>
                    <td class="text-center">{{ $surveySt }}</td>
                    <td class="text-center">{{ $app->statusLabel() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center">No applications found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($applications->count() > 30)
        <div style="font-size: 8px; color: #64748b; margin-top: 4px;">
            * Showing first 30 records of {{ $applications->count() }} total matching applications. Use Excel or CSV export for the complete dataset.
        </div>
    @endif

    <div class="footer">
        Confidential — Government of Gilgit-Baltistan ALF Portal · Wheat Seed (50:50 Cost Sharing) Management Information System
    </div>
</body>
</html>
