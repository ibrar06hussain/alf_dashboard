<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsTemplate extends Model
{
    public const EVENT_SUBMITTED = 'submitted';
    public const EVENT_APPROVED = 'approved';
    public const EVENT_REJECTED = 'rejected';
    public const EVENT_GBPAY_PAYMENT_SUCCESS = 'gbpay_payment_success';
    public const EVENT_PAYMENT_VERIFIED = 'payment_verified';
    public const EVENT_UNDER_REVIEW = 'under_review';
    public const EVENT_CORRECTION_REQUIRED = 'correction_required';
    public const EVENT_FIELD_SURVEY_ASSIGNED = 'field_survey_assigned';
    public const EVENT_FULLY_DISTRIBUTED = 'fully_distributed';
    public const EVENT_DORMANT = 'dormant';

    protected $fillable = [
        'name',
        'event_key',
        'scheme_id',
        'send_language',
        'message_en',
        'message_ur',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'scheme_id' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SmsLog::class, 'sms_template_id');
    }

    /**
     * All supported application status & event triggers.
     */
    public static function eventLabels(): array
    {
        return [
            'submitted' => '1. Scheme Registration / Application Submitted (with Application ID & CNIC Password)',
            'approved' => '2. Application Approved / Challan Issued (Approved Bags & Fee)',
            'rejected' => '3. Application Rejected (with Rejection Reason)',
            'gbpay_payment_success' => '4. Bill Paid / GBPay Payment Success (with Bill Paid Amount & PSID)',
            'payment_verified' => '5. Payment Verified by Accounts Officer',
            'under_review' => '6. Under Review (Deputy Director Review)',
            'correction_required' => '7. Correction Required from Applicant',
            'field_survey_assigned' => '8. Field Survey Assigned to Officer',
            'visit_scheduled' => '9. Field Survey Visit Scheduled',
            'visit_completed' => '10. Field Survey Visit Completed',
            'findings_submitted' => '11. Survey Findings Submitted',
            'challan_issued' => '12. Bank Challan Issued (Specific Override)',
            'payment_proof_submitted' => '13. Manual Payment Proof Uploaded by Applicant',
            'eligible_beneficiary' => '14. Confirmed Eligible Beneficiary',
            'stock_allocated' => '15. Stock / Commodity Allocated',
            'partially_distributed' => '16. Stock Partially Disbursed',
            'fully_distributed' => '17. Stock Fully Disbursed / Handed Over',
            'completed' => '18. Application Lifecycle Completed',
            'dormant' => '19. Unpaid Challan Expired (Dormant)',
            'reactivated' => '20. Dormant Application Reactivated',
        ];
    }

    public function eventLabel(): string
    {
        return self::eventLabels()[$this->event_key] ?? ucwords(str_replace('_', ' ', (string) $this->event_key));
    }

    /**
     * Available dynamic placeholder variables that Admin can insert into English & Urdu templates.
     */
    public static function availablePlaceholders(): array
    {
        return [
            '{name}' => [
                'label' => 'Applicant Name',
                'description' => 'Full name of the applicant',
                'sample_en' => 'Muhammad Ali',
                'sample_ur' => 'محمد علی',
            ],
            '{father_name}' => [
                'label' => 'Father/Husband Name',
                'description' => 'Father or husband name of the applicant',
                'sample_en' => 'Ghulam Rasool',
                'sample_ur' => 'غلام رسول',
            ],
            '{application_id}' => [
                'label' => 'Application ID',
                'description' => 'Unique application tracking number (e.g. ALF-20261028-00089)',
                'sample_en' => 'ALF-20261028-00089',
                'sample_ur' => 'ALF-20261028-00089',
            ],
            '{password}' => [
                'label' => 'CNIC as Password',
                'description' => 'Applicant 13-digit CNIC used as password for tracking/login',
                'sample_en' => '7150181611355',
                'sample_ur' => '7150181611355',
            ],
            '{cnic}' => [
                'label' => 'CNIC (Dashed)',
                'description' => 'Applicant CNIC formatted with dashes',
                'sample_en' => '71501-8161135-5',
                'sample_ur' => '71501-8161135-5',
            ],
            '{phone}' => [
                'label' => 'Mobile Number',
                'description' => 'Applicant registered phone number',
                'sample_en' => '03458161135',
                'sample_ur' => '03458161135',
            ],
            '{scheme}' => [
                'label' => 'Scheme Name',
                'description' => 'Title of the scheme (uses Urdu title in Urdu SMS if available)',
                'sample_en' => 'Distribution of Wheat Seed (Cost Sharing 50:50)',
                'sample_ur' => 'گندم کے بیج کی تقسیم (50:50)',
            ],
            '{department}' => [
                'label' => 'Department Name',
                'description' => 'Department name',
                'sample_en' => 'Agriculture Department GB',
                'sample_ur' => 'محکمہ زراعت گلگت بلتستان',
            ],
            '{district}' => [
                'label' => 'District',
                'description' => 'Applicant district name',
                'sample_en' => 'Gilgit',
                'sample_ur' => 'گلگت',
            ],
            '{tehsil}' => [
                'label' => 'Tehsil',
                'description' => 'Applicant tehsil name',
                'sample_en' => 'Gilgit',
                'sample_ur' => 'گلگت',
            ],
            '{village}' => [
                'label' => 'Village',
                'description' => 'Applicant village name',
                'sample_en' => 'Danyore',
                'sample_ur' => 'دنیور',
            ],
            '{status}' => [
                'label' => 'Application Status',
                'description' => 'Current status label of the application',
                'sample_en' => 'Approved',
                'sample_ur' => 'منظور شدہ',
            ],
            '{demanded_bags}' => [
                'label' => 'Demanded Bags / Qty',
                'description' => 'Number of bags/units requested by applicant',
                'sample_en' => '5',
                'sample_ur' => '5',
            ],
            '{approved_bags}' => [
                'label' => 'Approved Bags / Qty',
                'description' => 'Number of bags/units approved by officer',
                'sample_en' => '4',
                'sample_ur' => '4',
            ],
            '{disbursed_bags}' => [
                'label' => 'Disbursed Bags / Qty',
                'description' => 'Number of bags/units physically handed over',
                'sample_en' => '4',
                'sample_ur' => '4',
            ],
            '{challan_no}' => [
                'label' => 'Challan No',
                'description' => 'Generated bank deposit challan number',
                'sample_en' => 'CHL-2026-000089',
                'sample_ur' => 'CHL-2026-000089',
            ],
            '{psid}' => [
                'label' => '20-Digit PSID',
                'description' => 'GBPay 20-digit PSID voucher number',
                'sample_en' => '11240180261028000089',
                'sample_ur' => '11240180261028000089',
            ],
            '{challan_amount}' => [
                'label' => 'Challan / Bill Fee',
                'description' => 'Payable challan fee amount in PKR',
                'sample_en' => '13,000',
                'sample_ur' => '13,000',
            ],
            '{amount_paid}' => [
                'label' => 'Bill Paid Amount',
                'description' => 'Confirmed paid bill amount in PKR',
                'sample_en' => '13,000',
                'sample_ur' => '13,000',
            ],
            '{transaction_id}' => [
                'label' => 'Transaction ID',
                'description' => 'GBPay / Bank payment transaction reference ID',
                'sample_en' => 'TXN-9845120',
                'sample_ur' => 'TXN-9845120',
            ],
            '{due_date}' => [
                'label' => 'Challan Due Date',
                'description' => 'Expiry / due date of the challan',
                'sample_en' => '07-Nov-2026',
                'sample_ur' => '07-Nov-2026',
            ],
            '{rejection_reason}' => [
                'label' => 'Rejection Reason',
                'description' => 'Reason entered by officer when rejecting application',
                'sample_en' => 'Ineligible landholding documentation',
                'sample_ur' => 'کاغذات نامکمل ہیں',
            ],
            '{correction_remarks}' => [
                'label' => 'Correction Remarks',
                'description' => 'Correction instructions from reviewing officer',
                'sample_en' => 'Please upload a clear copy of CNIC',
                'sample_ur' => 'براہ کرم شناختی کارڈ کی واضح کاپی اپ لوڈ کریں',
            ],
            '{tracking_url}' => [
                'label' => 'Tracking Portal Link',
                'description' => 'Public URL to track application status',
                'sample_en' => url('/track'),
                'sample_ur' => url('/track'),
            ],
        ];
    }
}
