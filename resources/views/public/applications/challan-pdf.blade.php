<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bank Challan - {{ $application->challan_no }}</title>
    <style>
        @page {
            margin: 12mm 10mm;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1a202c;
            font-size: 11px;
            line-height: 1.35;
        }
        .challan-container {
            width: 100%;
            display: table;
            table-layout: fixed;
        }
        .challan-copy {
            display: table-cell;
            width: 33.33%;
            padding: 8px;
            vertical-align: top;
            border-right: 1px dashed #718096;
            box-sizing: border-box;
        }
        .challan-copy:last-child {
            border-right: none;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2d3748;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .header h3 {
            margin: 0;
            font-size: 12px;
            text-transform: uppercase;
            color: #1a365d;
        }
        .header h4 {
            margin: 2px 0;
            font-size: 10px;
            color: #2b6cb0;
        }
        .copy-badge {
            display: inline-block;
            background: #2b6cb0;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 3px;
            margin-top: 4px;
            text-transform: uppercase;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 8px;
            font-size: 10px;
        }
        .meta-table td {
            padding: 2px 0;
        }
        .meta-table td.label {
            color: #4a5568;
            width: 45%;
        }
        .meta-table td.val {
            font-weight: bold;
            color: #1a202c;
        }
        .amount-box {
            background: #edf2f7;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            padding: 6px;
            text-align: center;
            margin: 8px 0;
        }
        .amount-box .title {
            font-size: 9px;
            color: #4a5568;
            text-transform: uppercase;
        }
        .amount-box .amount {
            font-size: 14px;
            font-weight: bold;
            color: #276749;
            margin-top: 2px;
        }
        .bank-info {
            background: #ebf8ff;
            border: 1px solid #bee3f8;
            padding: 5px;
            font-size: 9px;
            margin-bottom: 8px;
            border-radius: 3px;
        }
        .signature-section {
            margin-top: 25px;
            display: table;
            width: 100%;
        }
        .sig-box {
            display: table-cell;
            width: 50%;
            text-align: center;
            font-size: 8px;
            color: #718096;
            border-top: 1px solid #a0aec0;
            padding-top: 3px;
        }
        .notice {
            font-size: 8px;
            color: #e53e3e;
            text-align: center;
            margin-top: 6px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="challan-container">
    @foreach(['BANK COPY', 'DEPARTMENT COPY', 'APPLICANT COPY'] as $copyTitle)
    <div class="challan-copy">
        <div class="header">
            <h3>Govt of Gilgit-Baltistan</h3>
            <h4>Agriculture, Livestock & Fisheries</h4>
            <div class="copy-badge">{{ $copyTitle }}</div>
        </div>

        <div class="bank-info">
            <strong>Deposit In:</strong> National Bank of Pakistan (NBP) / Bank of GB<br>
            <strong>Account:</strong> G-B Agriculture Revolving Fund<br>
            <strong>Head:</strong> C03820 - Agri Subsidies & Cost Share
        </div>

        <table class="meta-table">
            <tr>
                <td class="label">Challan No:</td>
                <td class="val">{{ $application->challan_no }}</td>
            </tr>
            <tr>
                <td class="label">Application No:</td>
                <td class="val">{{ $application->application_no }}</td>
            </tr>
            <tr>
                <td class="label">Issue Date:</td>
                <td class="val">{{ $application->challan_issued_at ? $application->challan_issued_at->format('d-M-Y') : date('d-M-Y') }}</td>
            </tr>
            <tr>
                <td class="label">Due Date (14 Days):</td>
                <td class="val" style="color: #c53030;">{{ $application->challan_due_date ? \Carbon\Carbon::parse($application->challan_due_date)->format('d-M-Y') : '14 Days' }}</td>
            </tr>
            <tr>
                <td class="label">Applicant Name:</td>
                <td class="val">{{ $applicant?->full_name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Father Name:</td>
                <td class="val">{{ $applicant?->father_name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">CNIC:</td>
                <td class="val">{{ \App\Support\Cnic::dashed($applicant?->cnic) }}</td>
            </tr>
            <tr>
                <td class="label">District:</td>
                <td class="val">{{ $district?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Scheme:</td>
                <td class="val">{{ \Illuminate\Support\Str::limit($scheme?->title ?? 'ALF Scheme', 30) }}</td>
            </tr>
            <tr>
                <td class="label">Bags Allocated:</td>
                <td class="val">{{ $application->allocated_quantity ?: ($application->getBagsRequired() ?: 1) }} (50kg Bags)</td>
            </tr>
        </table>

        <div class="amount-box">
            <div class="title">Total Payable Amount (PKR)</div>
            <div class="amount">PKR {{ number_format($application->challan_amount ?: 0, 2) }}</div>
        </div>

        <div class="notice">
            * Unpaid challan automatically expires after 14 calendar days.
        </div>

        <div class="signature-section">
            <div class="sig-box" style="margin-right: 5px;">
                Applicant Signature
            </div>
            <div class="sig-box">
                Bank Officer & Cashier
            </div>
        </div>
    </div>
    @endforeach
</div>

</body>
</html>
