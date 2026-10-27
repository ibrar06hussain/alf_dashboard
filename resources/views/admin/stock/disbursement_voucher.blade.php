<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Handover Slip / Gate Pass #{{ $disbursement->disbursement_no }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .voucher-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e1e4e8;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #28a745;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .gov-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e7e34;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .dept-title {
            font-size: 15px;
            font-weight: 600;
            color: #495057;
            margin: 4px 0;
        }
        .doc-title {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 6px 18px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 14px;
            margin-top: 10px;
            letter-spacing: 0.5px;
        }
        .voucher-meta {
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px 18px;
            margin-bottom: 25px;
            font-size: 13px;
        }
        .meta-item strong {
            color: #212529;
        }
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #2e7d32;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 6px;
            margin-top: 20px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .info-table th, .info-table td {
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            text-align: left;
        }
        .info-table th {
            background-color: #f8f9fa;
            color: #495057;
            width: 25%;
        }
        .highlight-box {
            background-color: #f1f8e9;
            border: 1px solid #c8e6c9;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .highlight-label {
            font-size: 13px;
            color: #388e3c;
            font-weight: 600;
        }
        .highlight-value {
            font-size: 20px;
            font-weight: 800;
            color: #1b5e20;
        }
        .acknowledgment {
            font-size: 12px;
            color: #6c757d;
            background: #fafafa;
            border-left: 3px solid #28a745;
            padding: 12px;
            margin: 25px 0 35px 0;
            line-height: 1.5;
        }
        .signature-grid {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 20px;
        }
        .sig-box {
            width: 40%;
            text-align: center;
            border-top: 1px dashed #6c757d;
            padding-top: 10px;
            font-size: 12px;
        }
        .actions-bar {
            max-width: 800px;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-block;
            font-weight: 600;
            text-align: center;
            padding: 8px 18px;
            font-size: 13px;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-print {
            background: #28a745;
            color: #fff;
        }
        .btn-print:hover {
            background: #218838;
        }
        .btn-back {
            background: #6c757d;
            color: #fff;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .voucher-container {
                box-shadow: none;
                border: none;
                padding: 10px;
            }
            .actions-bar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="actions-bar">
    <a href="{{ route('admin.stock.disbursements', ['tab' => 'history']) }}" class="btn btn-back">
        <i class="fa-solid fa-arrow-left"></i> Back to Disbursements
    </a>
    <button onclick="window.print()" class="btn btn-print">
        <i class="fa-solid fa-print"></i> Print Gate Pass / Slip
    </button>
</div>

<div class="voucher-container">
    <div class="header">
        <h1 class="gov-title">Government of Gilgit-Baltistan</h1>
        <div class="dept-title">Agriculture, Livestock and Fisheries Department</div>
        <div class="doc-title">
            <i class="fa-solid fa-receipt"></i> OFFICIAL STOCK HANDOVER SLIP & GATE PASS
        </div>
    </div>

    <div class="voucher-meta">
        <div class="meta-item">
            <span>Voucher No:</span> <strong>#{{ $disbursement->disbursement_no }}</strong>
        </div>
        <div class="meta-item">
            <span>Application No:</span> <strong>#{{ $disbursement->application->application_no ?? $disbursement->application_id }}</strong>
        </div>
        <div class="meta-item">
            <span>Issue Date:</span> <strong>{{ $disbursement->disbursement_date->format('d M Y') }}</strong>
        </div>
    </div>

    <div class="section-title"><i class="fa-solid fa-user-check"></i> Beneficiary & Recipient Details</div>
    <table class="info-table">
        <tr>
            <th>Beneficiary Name</th>
            <td><strong>{{ $disbursement->application->applicant->name ?? 'N/A' }}</strong></td>
            <th>Beneficiary CNIC</th>
            <td><strong>{{ $disbursement->application->applicant->cnic ?? 'N/A' }}</strong></td>
        </tr>
        <tr>
            <th>District & Tehsil</th>
            <td>{{ $disbursement->district->name ?? 'N/A' }}</td>
            <th>Contact Phone</th>
            <td>{{ $disbursement->application->applicant->phone ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Handover Recipient</th>
            <td>
                <strong>{{ $disbursement->recipient_name }}</strong>
                @if(!$disbursement->is_original_applicant)
                    <span style="color: #d9534f;">(Representative: {{ $disbursement->representative_relationship ?? 'Authorized' }})</span>
                @else
                    <span style="color: #28a745;">(Original Applicant)</span>
                @endif
            </td>
            <th>Recipient CNIC</th>
            <td><strong>{{ $disbursement->recipient_cnic }}</strong></td>
        </tr>
    </table>

    <div class="section-title"><i class="fa-solid fa-boxes-stacked"></i> Scheme & Commodity Issued</div>
    <table class="info-table">
        <tr>
            <th>Scheme Title</th>
            <td colspan="3"><strong>{{ $disbursement->item->scheme->title ?? 'Government Subsidy Scheme' }}</strong></td>
        </tr>
        <tr>
            <th>Commodity Item</th>
            <td><strong>{{ $disbursement->item->item_name ?? 'Scheme Commodity' }}</strong></td>
            <th>Packaging Unit</th>
            <td>{{ $disbursement->item->unit ?? 'Units' }}</td>
        </tr>
        <tr>
            <th>Collection Depot</th>
            <td><strong>{{ $disbursement->center->name ?? 'Collection Depot' }}</strong> ({{ ucfirst($disbursement->center->level ?? 'Center') }})</td>
            <th>Paid Challan Reference</th>
            <td>
                @if($disbursement->challan_no)
                    <strong style="color: #2e7d32;">#{{ $disbursement->challan_no }} (Verified)</strong>
                @else
                    <span style="color: #6c757d;">Direct Subsidy Clearance</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="highlight-box">
        <div>
            <div class="highlight-label">QUANTITY DISBURSED / RELEASED FROM DEPOT</div>
            <small style="color: #555;">Official allocation deducted from active warehouse ledger.</small>
        </div>
        <div class="highlight-value">
            {{ number_format($disbursement->quantity_disbursed) }} {{ $disbursement->item->unit ?? 'Bags' }}
        </div>
    </div>

    @if($disbursement->notes)
    <div style="font-size: 12px; margin-bottom: 15px;">
        <strong>Officer Remarks:</strong> {{ $disbursement->notes }}
    </div>
    @endif

    <div class="acknowledgment">
        <strong>Beneficiary Acknowledgment & Declaration:</strong><br>
        I, the undersigned beneficiary / authorized representative, hereby confirm that I have physically received the commodity stated above in intact packaging, free from moisture or damage, and under the approved Government of Gilgit-Baltistan subsidy quota.
    </div>

    <div class="signature-grid">
        <div class="sig-box">
            <strong>Beneficiary / Recipient</strong><br>
            Signature / Thumb Impression
        </div>
        <div class="sig-box">
            <strong>{{ $disbursement->disbursedBy->name ?? 'Distribution Officer' }}</strong><br>
            Distribution Officer / In-Charge Depot
        </div>
    </div>

    <div style="text-align: center; margin-top: 40px; font-size: 11px; color: #999;">
        System generated electronically via ALFGB Citizen Schemes MIS on {{ now()->format('d M Y, h:i A') }}.
    </div>
</div>

</body>
</html>
