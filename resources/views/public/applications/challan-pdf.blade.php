<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bank Challan - {{ $application->challan_no }}</title>
    <style>
        @page {
            margin: 6mm 5mm;
            size: A4 landscape;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #111827;
            font-size: 8.5px;
            line-height: 1.25;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        .challan-container {
            width: 100%;
            display: table;
            table-layout: fixed;
        }
        .challan-copy {
            display: table-cell;
            width: 33.333%;
            padding: 4px 8px;
            vertical-align: top;
            border-right: 1px dashed #6b7280;
        }
        .challan-copy:last-child {
            border-right: none;
        }
        .copy-tag {
            text-align: right;
            font-size: 7.5px;
            font-weight: bold;
            color: #4b5563;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .header {
            text-align: center;
            margin-bottom: 5px;
        }
        .header .dept-name {
            font-size: 9.5px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            line-height: 1.2;
            margin: 0;
        }
        .header .govt-sub {
            font-size: 8px;
            color: #374151;
            margin: 1px 0;
        }
        .header .challan-title {
            font-size: 9px;
            font-weight: bold;
            text-decoration: underline;
            color: #111827;
            letter-spacing: 0.5px;
            margin-top: 1px;
        }
        .bank-box {
            border: 1.2px solid #111827;
            padding: 4px 6px;
            margin-bottom: 6px;
            font-size: 8px;
            line-height: 1.35;
            background: #fafafa;
        }
        .bank-box .bank-row {
            margin-bottom: 1px;
        }
        .bank-box strong {
            color: #111827;
            font-weight: bold;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 8px;
        }
        .meta-table td {
            padding: 1.5px 0;
            vertical-align: top;
        }
        .meta-table td.lbl {
            width: 38%;
            font-weight: bold;
            color: #1f2937;
        }
        .meta-table td.val {
            width: 62%;
            color: #111827;
        }
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            font-size: 8px;
            border: 1px solid #111827;
        }
        .fee-table th {
            border: 1px solid #111827;
            padding: 3px 4px;
            background: #f3f4f6;
            font-weight: bold;
            color: #111827;
        }
        .fee-table th.desc-th {
            text-align: left;
            width: 68%;
        }
        .fee-table th.amt-th {
            text-align: right;
            width: 32%;
        }
        .fee-table td {
            border: 1px solid #111827;
            padding: 2.5px 4px;
        }
        .fee-table td.amt-td {
            text-align: right;
            font-weight: bold;
            white-space: nowrap;
        }
        .fee-table tr.total-row td {
            font-weight: bold;
            background: #f9fafb;
            border-top: 1.5px solid #111827;
        }
        .fee-table td.total-label {
            text-align: left;
            font-size: 8.5px;
        }
        .fee-table td.total-amount {
            text-align: right;
            font-size: 8.5px;
            color: #111827;
        }
        .amount-words {
            font-size: 8px;
            color: #1f2937;
            margin-top: 3px;
            margin-bottom: 8px;
            line-height: 1.25;
        }
        .amount-words strong {
            color: #111827;
        }
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            margin-bottom: 8px;
        }
        .sig-table td.sig-box {
            width: 45%;
            text-align: center;
            vertical-align: top;
            font-size: 7.5px;
            color: #374151;
            font-weight: bold;
        }
        .sig-table td.sig-space {
            width: 10%;
        }
        .sig-line {
            border-top: 1px solid #111827;
            margin-bottom: 2px;
        }
        .directions-box {
            font-size: 7px;
            line-height: 1.25;
            color: #374151;
            text-align: justify;
            margin-top: 4px;
            border-top: 0.5px solid #d1d5db;
            padding-top: 3px;
        }
        .directions-box strong {
            color: #111827;
        }
        .stamp-box {
            margin-top: 5px;
            text-align: center;
        }
        .stamp-inner {
            display: inline-block;
            border: 1px dashed #9ca3af;
            border-radius: 4px;
            padding: 3px 8px;
            font-size: 6.5px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

@php
    $copies = [
        'BANK COPY',
        'DEPARTMENT COPY',
        'CANDIDATE COPY'
    ];
    $template = $template ?? ($application->scheme?->getChallanTemplate() ?? \App\Models\ChallanTemplate::getDefault());
    $breakdown = $breakdown ?? $application->getChallanBreakdown();
    $applicant = $applicant ?? $application->applicant;
    $district = $district ?? $application->district;
    $scheme = $scheme ?? $application->scheme;
@endphp

<div class="challan-container">
    @foreach($copies as $copyTitle)
    <div class="challan-copy">
        <div class="copy-tag">{{ $copyTitle }}</div>

        <div class="header">
            <div class="dept-name">{{ $template->department_name ?: 'Agriculture LiveStock and Fishries Department Gilgit Baltistan' }}</div>
            <div class="govt-sub">{{ $template->sub_title ?: 'Government of Gilgit-Baltistan' }}</div>
            <div class="challan-title">{{ $template->challan_title ?: 'FEE DEPOSIT CHALLAN' }}</div>
        </div>

        <div class="bank-box">
            <div class="bank-row"><strong>Bank Name:</strong> {{ $template->bank_name ?: 'National Bank Of Pakistan' }}</div>
            <div class="bank-row"><strong>Account Title:</strong> {{ $template->account_title ?: 'Agriculture LiveStock and Fishries Department Gilgit Baltistan' }}</div>
            <div class="bank-row"><strong>Account No:</strong> {{ $template->account_no ?: '4256260305' }}</div>
            <div class="bank-row"><strong>Account Head:</strong> {{ $template->account_head ?: 'Application Processing Fee(C03870)' }}</div>
        </div>

        <table class="meta-table">
            <tr>
                <td class="lbl">Challan No:</td>
                <td class="val"><strong>#{{ $application->challan_no ?: 'PENDING' }}</strong></td>
            </tr>
            <tr>
                <td class="lbl">Challan Generated Date:</td>
                <td class="val">{{ $application->challan_issued_at ? \Carbon\Carbon::parse($application->challan_issued_at)->format('d-M-Y') : date('d-M-Y') }}</td>
            </tr>
            <tr>
                <td class="lbl">Application No:</td>
                <td class="val"><strong>{{ $application->application_no }}</strong></td>
            </tr>
            <tr>
                <td class="lbl">Applicant Name:</td>
                <td class="val"><strong>{{ $applicant?->full_name ?? '—' }}</strong></td>
            </tr>
            <tr>
                <td class="lbl">Registration Type:</td>
                <td class="val">{{ \Illuminate\Support\Str::limit($scheme?->title ?? 'Farmer / Beneficiary Registration', 30) }}</td>
            </tr>
            <tr>
                <td class="lbl">CNIC:</td>
                <td class="val">{{ \App\Support\Cnic::dashed($applicant?->cnic) }}</td>
            </tr>
            @if($district)
            <tr>
                <td class="lbl">District:</td>
                <td class="val">{{ $district->name }}</td>
            </tr>
            @endif
        </table>

        <table class="fee-table">
            <thead>
                <tr>
                    <th class="desc-th">Description</th>
                    <th class="amt-th">Amount</th>
                </tr>
            </thead>
            <tbody>
                @if($template->show_breakdown ?? true)
                <tr>
                    <td>Cost of 1 Bag ( total cost of bag including transport charges )</td>
                    <td class="amt-td">Rs. {{ number_format((int) ceil($breakdown['cost_of_1_bag'])) }}</td>
                </tr>
                <tr>
                    <td>No of Wheat bags approved</td>
                    <td class="amt-td">{{ (float) $breakdown['approved_bags'] == (int) $breakdown['approved_bags'] ? (int) $breakdown['approved_bags'] : $breakdown['approved_bags'] }}</td>
                </tr>
                <tr>
                    <td>Total Bill : ( cost of 1 bag * no of bags)</td>
                    <td class="amt-td">Rs. {{ number_format($breakdown['total_bill']) }}</td>
                </tr>
                <tr>
                    <td>Government Share: {{ (int)$breakdown['govt_share_pct'] }}% of total bill rounded</td>
                    <td class="amt-td">Rs. {{ number_format($breakdown['govt_share']) }}</td>
                </tr>
                @endif
                <tr>
                    <td>Final Bill : {{ (int)$breakdown['farmer_share_pct'] }}% of total bill rounded</td>
                    <td class="amt-td">Rs. {{ number_format($breakdown['final_bill']) }}</td>
                </tr>
                <tr class="total-row">
                    <td class="total-label">TOTAL (In Figures / Digits)</td>
                    <td class="total-amount">Rs. {{ number_format($breakdown['final_bill']) }}/-</td>
                </tr>
            </tbody>
        </table>

        <div class="amount-words">
            <strong>Challan Fee in Words:</strong> <em>{{ $breakdown['amount_words'] }}</em>
        </div>

        <table class="sig-table">
            <tr>
                <td class="sig-box">
                    <div class="sig-line"></div>
                    <span>Applicant Signature</span>
                </td>
                <td class="sig-space"></td>
                <td class="sig-box">
                    <div class="sig-line"></div>
                    <span>Bank Cashier</span>
                </td>
            </tr>
        </table>

        <div class="directions-box">
            <strong>Directions:</strong> {{ $template->directions ?: 'This challan is valid for 10 days after the challan is generated. This fee doesnot include transport charges from district to village.' }}
        </div>

        <div class="stamp-box">
            <div class="stamp-inner">
                BANK CASH RECEIVED STAMP & DATE
            </div>
        </div>
    </div>
    @endforeach
</div>

</body>
</html>
