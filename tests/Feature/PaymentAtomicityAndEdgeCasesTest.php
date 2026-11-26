<?php

namespace Tests\Feature;

use App\Exceptions\GbpayPaymentException;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatusLog;
use App\Models\Bill;
use App\Models\ChallanTemplate;
use App\Models\CollectionCenter;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormField;
use App\Models\GbpayApiCredential;
use App\Models\PsidConfig;
use App\Models\Scheme;
use App\Models\SchemeStockItem;
use App\Models\StockDisbursement;
use App\Models\User;
use App\Models\VoucherSerial;
use App\Services\ApplicationLifecycleService;
use App\Services\GbpayService;
use App\Services\StockManagementService;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PaymentAtomicityAndEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private Department $agriDept;
    private Department $livestockDept;
    private Department $fisheriesDept;
    private District $gilgit;
    private User $ddGilgit;
    private User $superAdmin;
    private User $distOfficer;
    private PsidConfig $globalPsidConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacHierarchySeeder::class);

        $this->agriDept = Department::where('slug', 'agriculture')->firstOrFail();
        $this->livestockDept = Department::where('slug', 'livestock')->firstOrFail();
        $this->fisheriesDept = Department::where('slug', 'fisheries')->firstOrFail();
        $this->gilgit = District::where('name', 'Gilgit')->firstOrFail();

        $this->ddGilgit = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();

        $this->superAdmin = User::create([
            'name' => 'Super Admin QA',
            'email' => 'superadmin.qa@alf.gb.gov.pk',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->distOfficer = User::create([
            'name' => 'Gilgit Distribution Officer QA',
            'email' => 'dist.gilgit.qa@alf.gb.gov.pk',
            'password' => Hash::make('password'),
            'district_id' => $this->gilgit->id,
            'department_id' => $this->agriDept->id,
            'is_active' => true,
        ]);
        $this->distOfficer->assignRole('Distribution Officer');

        $this->globalPsidConfig = PsidConfig::whereNull('scheme_id')->where('is_active', true)->first()
            ?? PsidConfig::create([
                'scheme_id' => null,
                'one_link_prefix' => '101530',
                'department_code' => '09',
                'district_code' => '01',
                'department_service_no' => '01',
                'rco' => 'GL-1618',
                'head' => 'C03870',
                'is_active' => true,
                'last_voucher_serial' => 0,
            ]);

        $this->globalPsidConfig->update([
            'one_link_prefix' => '101530',
            'department_code' => '09',
            'district_code' => '01',
            'department_service_no' => '01',
            'rco' => 'GL-1618',
            'head' => 'C03870',
            'is_active' => true,
            'last_voucher_serial' => 0,
        ]);

        GbpayApiCredential::create([
            'username' => 'gbpay_qa_client',
            'password' => Hash::make('gbpay_qa_secret'),
            'is_active' => true,
        ]);
    }

    /**
     * Helper: build a mixed paid/free tree scheme with itemized options.
     *
     * @return array{scheme: Scheme, form: Form, field: FormField, template: ChallanTemplate}
     */
    private function createMixedItemizedScheme(?Department $dept = null, bool $requiresChallan = true): array
    {
        $dept ??= $this->agriDept;

        $template = ChallanTemplate::create([
            'name' => 'Challan Template ' . uniqid(),
            'department_name' => $dept->name,
            'challan_title' => 'FEE DEPOSIT CHALLAN',
            'bank_name' => 'KCBL / Bank of Punjab',
            'account_title' => 'Directorate Fee Account',
            'account_no' => '0011223344',
            'rco_code' => 'GL-1618',
            'head_code' => 'C03870',
            'validity_days' => 14,
            'fee_mode' => 'fixed_per_unit',
            'govt_share_pct' => 0,
            'farmer_share_pct' => 100,
            'show_breakdown' => true,
            'is_active' => true,
        ]);

        $scheme = Scheme::create([
            'department_id' => $dept->id,
            'title' => 'Mixed Fruit Orchards Scheme ' . uniqid(),
            'slug' => 'mixed-orchards-' . uniqid(),
            'status' => 'active',
            'type' => 'running',
            'scope' => 'district',
            'requires_challan' => $requiresChallan,
            'challan_fee_per_unit' => 0,
            'challan_template_id' => $template->id,
            'has_auto_dashboard' => true,
        ]);

        $form = Form::create([
            'scheme_id' => $scheme->id,
            'title' => 'Tree Selection Form',
            'is_published' => true,
            'version' => 1,
        ]);

        $field = FormField::create([
            'form_id' => $form->id,
            'field_key' => 'requested_trees',
            'field_label' => 'Select Tree Types & Quantities',
            'field_type' => 'checkbox_with_text',
            'is_mandatory' => true,
            'is_billable_items_field' => true,
            'display_order' => 1,
            'option_configs_json' => [
                [
                    'key' => 'apple_grafted',
                    'label' => 'Apple Grafted Plant',
                    'pricing_mode' => 'paid',
                    'unit_price' => 500,
                    'govt_share_pct' => 40, // Farmer pays 60% = Rs. 300/plant
                    'unit_label' => 'plants',
                ],
                [
                    'key' => 'cherry_fractional',
                    'label' => 'Cherry Plant Fractional',
                    'pricing_mode' => 'paid',
                    'unit_price' => 333.33,
                    'govt_share_pct' => 40, // 3 * 333.33 * 60% = 599.994 -> ceil = 600
                    'unit_label' => 'plants',
                ],
                [
                    'key' => 'apricot_free',
                    'label' => 'Apricot Local Sapling',
                    'pricing_mode' => 'free',
                    'unit_price' => 0,
                    'govt_share_pct' => 100,
                    'unit_label' => 'plants',
                ],
                [
                    'key' => 'walnut_fully_subsidized',
                    'label' => 'Walnut 100% Subsidized',
                    'pricing_mode' => 'paid',
                    'unit_price' => 450,
                    'govt_share_pct' => 100,
                    'unit_label' => 'plants',
                ],
            ],
        ]);

        return compact('scheme', 'form', 'field', 'template');
    }

    /**
     * Helper: create an application with attached itemized selections ready for DD approval.
     *
     * @param  array<int, array{item_key: string, item_label: string, is_free: bool, unit_price: float, govt_share_pct: float, demanded_qty: float, scheme_stock_item_id?: int|null}>  $items
     */
    private function createItemizedApplication(Scheme $scheme, Form $form, FormField $field, array $items, ?string $cnic = null): Application
    {
        $applicant = Applicant::create([
            'full_name' => 'Beneficiary ' . uniqid(),
            'father_name' => 'Father Name',
            'cnic' => $cnic ?: ('71501-' . random_int(1000000, 9999999) . '-1'),
            'phone' => '0345' . random_int(1000000, 9999999),
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'union_council' => 'Jutial',
            'village' => 'Jutial Bala',
        ]);

        $demandedSum = array_sum(array_column($items, 'demanded_qty'));

        $app = Application::create([
            'application_no' => 'ALF-QA-' . strtoupper(uniqid()),
            'scheme_id' => $scheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => $scheme->department_id,
            'form_id' => $form->id,
            'status' => 'pending',
            'current_stage' => Application::STAGE_UNDER_REVIEW,
            'demanded_quantity' => $demandedSum,
            'assigned_to' => $this->ddGilgit->id,
            'submitted_at' => now(),
            'lock_version' => 1,
        ]);

        foreach ($items as $itemData) {
            $isFree = (bool) $itemData['is_free'];
            $unitPrice = $isFree ? 0.0 : (float) $itemData['unit_price'];
            $govtPct = $isFree ? 100.0 : (float) $itemData['govt_share_pct'];
            $farmerPct = max(0.0, 100.0 - $govtPct);

            $appItem = new ApplicationItem([
                'application_id' => $app->id,
                'form_field_id' => $field->id,
                'item_key' => $itemData['item_key'],
                'item_label' => $itemData['item_label'],
                'unit_label' => 'plants',
                'is_free' => $isFree,
                'unit_price' => $unitPrice,
                'govt_share_pct' => $govtPct,
                'farmer_share_pct' => $farmerPct,
                'demanded_qty' => (float) $itemData['demanded_qty'],
                'approved_qty' => null,
                'disbursed_qty' => 0,
                'scheme_stock_item_id' => $itemData['scheme_stock_item_id'] ?? null,
            ]);
            $appItem->recalculateFinancials()->save();
        }

        return $app->fresh(['items', 'scheme', 'applicant', 'district']);
    }

    public function test_repeated_bill_generation_reuses_single_pending_psid_and_leaves_zero_serial_gaps(): void
    {
        ['scheme' => $scheme, 'form' => $form, 'field' => $field] = $this->createMixedItemizedScheme();

        // 5 Apple @ Rs.500 with 40% govt subsidy => Rs.300 * 5 = Rs.1,500 net payable + 10 free Apricots
        $app = $this->createItemizedApplication($scheme, $form, $field, [
            [
                'item_key' => 'apple_grafted',
                'item_label' => 'Apple Grafted Plant',
                'is_free' => false,
                'unit_price' => 500,
                'govt_share_pct' => 40,
                'demanded_qty' => 5,
            ],
            [
                'item_key' => 'apricot_free',
                'item_label' => 'Apricot Local Sapling',
                'is_free' => true,
                'unit_price' => 0,
                'govt_share_pct' => 100,
                'demanded_qty' => 10,
            ],
        ]);

        app(ApplicationLifecycleService::class)->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [
            'note' => 'Approved 5 Apple + 10 Free Apricot',
        ], $app->lock_version);

        $app->refresh();
        $this->assertSame(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
        $this->assertSame(1500.0, (float) $app->challan_amount);

        $gbpay = app(GbpayService::class);
        $firstPsid = $app->psid;
        $firstBill = Bill::where('application_id', $app->id)->firstOrFail();

        // Re-invoke bill creation/sync 5 consecutive times (e.g. concurrent browser reloads / PDF downloads)
        for ($i = 0; $i < 5; $i++) {
            $syncedBill = $gbpay->createOrUpdateBillForApplication($app->fresh());
            $this->assertSame($firstBill->bill_id, $syncedBill->bill_id);
            $this->assertSame($firstPsid, $syncedBill->psid);
            $this->assertSame(1500, $syncedBill->bill_amount);
        }

        // Strictly 1 Bill, 1 VoucherSerial, serial = 1, zero wasted gaps
        $this->assertSame(1, Bill::where('application_id', $app->id)->count());
        $this->assertSame(1, VoucherSerial::count());
        $this->assertSame(1, (int) $this->globalPsidConfig->fresh()->last_voucher_serial);
        $this->assertSame(1, (int) $firstBill->voucher_serial);
    }

    public function test_all_free_or_100_percent_subsidized_apps_never_mint_psids_and_return_404_on_gbpay_api(): void
    {
        ['scheme' => $scheme, 'form' => $form, 'field' => $field] = $this->createMixedItemizedScheme();

        // Application selects only free Apricots AND 100%-subsidized Walnuts => Rs. 0 payable
        $app = $this->createItemizedApplication($scheme, $form, $field, [
            [
                'item_key' => 'apricot_free',
                'item_label' => 'Apricot Local Sapling',
                'is_free' => true,
                'unit_price' => 0,
                'govt_share_pct' => 100,
                'demanded_qty' => 25,
            ],
            [
                'item_key' => 'walnut_fully_subsidized',
                'item_label' => 'Walnut 100% Subsidized',
                'is_free' => false,
                'unit_price' => 450,
                'govt_share_pct' => 100,
                'demanded_qty' => 10,
            ],
        ]);

        app(ApplicationLifecycleService::class)->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [], $app->lock_version);
        $app->refresh();

        $this->assertSame(Application::STAGE_ELIGIBLE_BENEFICIARY, $app->current_stage);
        $this->assertNull($app->challan_no);
        $this->assertNull($app->challan_amount);
        $this->assertNull($app->psid);
        $this->assertSame(0, Bill::where('application_id', $app->id)->count());
        $this->assertSame(0, VoucherSerial::count());

        // Direct service invocation refuses to create a Rs. 0 Bill/PSID
        try {
            app(GbpayService::class)->createOrUpdateBillForApplication($app);
            $this->fail('Expected RuntimeException for Rs. 0 payable bill generation.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Rs. 0', $e->getMessage());
        }

        // GBPay search-psid by application_no gracefully returns 404 ('01') instead of 500
        $search = $this->postJson('/api/gb-pay/search-psid', [
            'username' => 'gbpay_qa_client',
            'password' => 'gbpay_qa_secret',
            'search_by' => 'reference_number',
            'search_value' => $app->application_no,
        ]);
        $search->assertStatus(404);
        $search->assertJsonPath('response_code', '01');

        // Challan PDF download blocks cleanly with redirect
        $download = $this->get(route('applications.challan.download', ['application_no' => $app->application_no]));
        $download->assertRedirect(route('applications.track', ['application_no' => $app->application_no]));
        $download->assertSessionHas('error');
        $this->assertSame(0, (int) $this->globalPsidConfig->fresh()->last_voucher_serial);
    }

    public function test_unpaid_challan_qty_edit_syncs_pending_psid_amount_and_zero_payable_cancels_psid_for_free_clearance(): void
    {
        ['scheme' => $scheme, 'form' => $form, 'field' => $field] = $this->createMixedItemizedScheme();

        $app = $this->createItemizedApplication($scheme, $form, $field, [
            [
                'item_key' => 'apple_grafted',
                'item_label' => 'Apple Grafted Plant',
                'is_free' => false,
                'unit_price' => 500,
                'govt_share_pct' => 40, // Rs.300/plant
                'demanded_qty' => 5,
            ],
            [
                'item_key' => 'apricot_free',
                'item_label' => 'Apricot Local Sapling',
                'is_free' => true,
                'unit_price' => 0,
                'govt_share_pct' => 100,
                'demanded_qty' => 8,
            ],
        ]);

        // 1. DD approves full qty => Rs. 1,500 pending PSID issued
        app(ApplicationLifecycleService::class)->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [], $app->lock_version);
        $app->refresh();

        $appleItem = $app->items()->where('item_key', 'apple_grafted')->firstOrFail();
        $apricotItem = $app->items()->where('item_key', 'apricot_free')->firstOrFail();
        $originalBill = Bill::where('application_id', $app->id)->firstOrFail();
        $originalPsid = $originalBill->psid;

        $this->assertSame(1500, $originalBill->bill_amount);

        // 2. While unpaid, DD reduces Apple quantity 5 -> 2 (payable Rs. 1,500 -> Rs. 600)
        $reapprovePaidReduced = $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_APPROVED,
                'approved_items' => [
                    $appleItem->id => 2,
                    $apricotItem->id => 8,
                ],
                'note' => 'Reduced paid apple allocation to 2 plants.',
                'lock_version' => $app->lock_version,
            ]);
        $reapprovePaidReduced->assertRedirect();
        $reapprovePaidReduced->assertSessionHas('success');

        $app->refresh();
        $originalBill->refresh();

        $this->assertSame(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
        $this->assertSame(600.0, (float) $app->challan_amount);
        $this->assertSame($originalPsid, $app->psid);
        $this->assertSame($originalPsid, $originalBill->psid);
        $this->assertSame(600, $originalBill->bill_amount);
        $this->assertSame('pending', $originalBill->status);
        $this->assertSame(1, VoucherSerial::count(), 'PSID serial must be reused without wasting a new voucher.');

        // Stale amount Rs.1500 is rejected after sync; only synced amount Rs.600 works on inquiry
        $inquiry = $this->postJson('/api/gb-pay/bill-inquiry', [
            'username' => 'gbpay_qa_client',
            'password' => 'gbpay_qa_secret',
            'voucher_number' => $originalPsid,
        ]);
        $inquiry->assertOk()->assertJsonPath('payable_amount', 600);

        // 3. While still unpaid, DD sets Paid Apple qty -> 0 while keeping 8 Free Apricots => Rs. 0 payable
        $reapproveToFreeOnly = $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_APPROVED,
                'approved_items' => [
                    $appleItem->id => 0,
                    $apricotItem->id => 8,
                ],
                'note' => 'Only free apricot saplings approved; zero fee applies.',
                'lock_version' => $app->lock_version,
            ]);
        $reapproveToFreeOnly->assertRedirect();
        $reapproveToFreeOnly->assertSessionHas('success');

        $app->refresh();
        $originalBill->refresh();

        $this->assertSame(Application::STAGE_ELIGIBLE_BENEFICIARY, $app->current_stage);
        $this->assertNull($app->challan_no);
        $this->assertNull($app->challan_amount);
        $this->assertNull($app->psid);
        $this->assertSame('cancelled', $originalBill->status);

        // Payment against the cancelled PSID MUST be blocked ('02', 403)
        $blockedPay = $this->postJson('/api/gb-pay/bill-payment', [
            'username' => 'gbpay_qa_client',
            'password' => 'gbpay_qa_secret',
            'voucher_number' => $originalPsid,
            'transaction_id' => 'TXN-CANCELLED-FREE-1',
            'transaction_amount' => 600,
            'bank_mnemonic' => 'HBL',
        ]);
        $blockedPay->assertStatus(403)->assertJsonPath('response_code', '02');
    }

    public function test_paid_application_locks_item_quantity_fee_rejection_and_force_new_psid_while_allowing_contact_edits(): void
    {
        ['scheme' => $scheme, 'form' => $form, 'field' => $field] = $this->createMixedItemizedScheme();

        $app = $this->createItemizedApplication($scheme, $form, $field, [
            [
                'item_key' => 'apple_grafted',
                'item_label' => 'Apple Grafted Plant',
                'is_free' => false,
                'unit_price' => 500,
                'govt_share_pct' => 40,
                'demanded_qty' => 4, // 4 * 300 = Rs. 1,200
            ],
        ]);

        app(ApplicationLifecycleService::class)->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [], $app->lock_version);
        $app->refresh();

        $bill = Bill::where('application_id', $app->id)->firstOrFail();

        // Pay via GBPay API
        $this->postJson('/api/gb-pay/bill-payment', [
            'username' => 'gbpay_qa_client',
            'password' => 'gbpay_qa_secret',
            'voucher_number' => $bill->psid,
            'transaction_id' => 'TXN-LOCK-001',
            'transaction_amount' => 1200,
            'bank_mnemonic' => 'NBP',
        ])->assertOk()->assertJsonPath('response_code', '00');

        $app->refresh();
        $appleItem = $app->items()->firstOrFail();

        // 1. Force-new PSID MUST throw and preserve paid PSID
        try {
            app(GbpayService::class)->createOrUpdateBillForApplication($app, 1200, true);
            $this->fail('Expected RuntimeException when forcing new PSID on already paid application.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('PAID bill', $e->getMessage());
        }

        // 2. Super Admin attempting to modify challan_amount or approved_items on paid app MUST be blocked
        $tamperFee = $this->actingAs($this->superAdmin)
            ->put(route('admin.applications.update', $app), [
                'full_name' => $app->applicant->full_name,
                'phone' => $app->applicant->phone,
                'district_id' => $app->district_id,
                'challan_amount' => 5000,
                'modification_reason' => 'Attempt fee edit on paid application.',
            ]);
        $tamperFee->assertRedirect()->assertSessionHas('error');

        $tamperItem = $this->actingAs($this->superAdmin)
            ->put(route('admin.applications.update', $app), [
                'full_name' => $app->applicant->full_name,
                'phone' => $app->applicant->phone,
                'district_id' => $app->district_id,
                'approved_items' => [$appleItem->id => 10],
                'modification_reason' => 'Attempt item qty edit on paid application.',
            ]);
        $tamperItem->assertRedirect()->assertSessionHas('error');

        $this->assertSame(1200.0, (float) $app->fresh()->challan_amount);
        $this->assertSame(4.0, (float) $appleItem->fresh()->approved_qty);

        // 3. Rejecting paid application MUST be blocked
        $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_REJECTED,
                'rejection_reason' => 'Invalid rejection of paid app.',
                'lock_version' => $app->lock_version,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // 4. Non-financial contact phone update on paid app STILL succeeds cleanly
        $contactFix = $this->actingAs($this->superAdmin)
            ->put(route('admin.applications.update', $app), [
                'full_name' => $app->applicant->full_name,
                'phone' => '03459998887',
                'district_id' => $app->district_id,
                'verification_note' => 'Updated applicant phone only.',
                'modification_reason' => 'Corrected applicant contact number after payment.',
            ]);
        $contactFix->assertRedirect()->assertSessionHas('success');
        $this->assertSame('03459998887', $app->applicant->fresh()->phone);
    }

    public function test_portable_10_and_14_digit_suffix_lookup_fractional_ceil_rounding_and_strict_amount_validation(): void
    {
        ['scheme' => $scheme, 'form' => $form, 'field' => $field] = $this->createMixedItemizedScheme();

        // 3 * 333.33 = 999.99 gross; 40% govt => 599.994 farmer payable => upper integer ceil = Rs. 600
        $app = $this->createItemizedApplication($scheme, $form, $field, [
            [
                'item_key' => 'cherry_fractional',
                'item_label' => 'Cherry Plant Fractional',
                'is_free' => false,
                'unit_price' => 333.33,
                'govt_share_pct' => 40,
                'demanded_qty' => 3,
            ],
        ]);

        app(ApplicationLifecycleService::class)->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [], $app->lock_version);
        $app->refresh();
        $bill = Bill::where('application_id', $app->id)->firstOrFail();

        $this->assertSame(600, $bill->bill_amount);
        $this->assertMatchesRegularExpression('/^\d{20}$/', $bill->psid);

        $psid14 = substr($bill->psid, -14);
        $psid10 = substr($bill->psid, -10);

        // Inquiry via 20-digit, 14-digit consumer suffix, and 10-digit voucher suffix on SQLite
        foreach ([$bill->psid, $psid14, $psid10] as $voucherInput) {
            $inq = $this->postJson('/api/gb-pay/bill-inquiry', [
                'username' => 'gbpay_qa_client',
                'password' => 'gbpay_qa_secret',
                'voucher_number' => $voucherInput,
            ]);
            $inq->assertOk()
                ->assertJsonPath('response_code', '00')
                ->assertJsonPath('payable_amount', 600);
        }

        // Strict amount validation on /api/gb-pay/bill-payment (underpay, overpay, zero)
        foreach ([599, 601, 0, -100] as $badAmount) {
            $badPay = $this->postJson('/api/gb-pay/bill-payment', [
                'username' => 'gbpay_qa_client',
                'password' => 'gbpay_qa_secret',
                'voucher_number' => $psid10,
                'transaction_id' => 'TXN-BAD-' . abs((int) $badAmount),
                'transaction_amount' => $badAmount,
                'bank_mnemonic' => 'KCBL',
            ]);
            $badPay->assertStatus(400)->assertJsonPath('response_code', '01');
            $this->assertSame('pending', $bill->fresh()->status);
        }

        // Strict amount validation on /gbpay/webhook (mismatch & missing amount)
        $whMismatch = $this->postJson('/gbpay/webhook', [
            'psid' => $bill->psid,
            'status' => 'paid',
            'amount' => 599,
            'transaction_id' => 'TXN-WH-BAD-1',
        ]);
        $whMismatch->assertStatus(400)->assertJsonPath('response_code', '01');

        $whMissingAmount = $this->postJson('/gbpay/webhook', [
            'psid' => $bill->psid,
            'status' => 'paid',
            'transaction_id' => 'TXN-WH-NOAMT',
        ]);
        $whMissingAmount->assertStatus(400)->assertJsonPath('response_code', '01');

        // Exact payment via 14-digit suffix clears the bill & application
        $goodPay = $this->postJson('/api/gb-pay/bill-payment', [
            'username' => 'gbpay_qa_client',
            'password' => 'gbpay_qa_secret',
            'voucher_number' => $psid14,
            'transaction_id' => 'TXN-EXACT-600',
            'transaction_amount' => 600,
            'bank_mnemonic' => 'KCBL',
        ]);
        $goodPay->assertOk()->assertJsonPath('response_code', '00');

        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertSame(Application::STAGE_ELIGIBLE_BENEFICIARY, $app->fresh()->current_stage);
    }

    public function test_atomic_duplicate_already_paid_expiry_and_webhook_signature_enforcement(): void
    {
        ['scheme' => $scheme, 'form' => $form, 'field' => $field] = $this->createMixedItemizedScheme();

        // App A: test idempotent payment + replays + HMAC webhook secret
        $appA = $this->createItemizedApplication($scheme, $form, $field, [
            [
                'item_key' => 'apple_grafted',
                'item_label' => 'Apple Grafted Plant',
                'is_free' => false,
                'unit_price' => 500,
                'govt_share_pct' => 40,
                'demanded_qty' => 2, // Rs. 600
            ],
        ]);
        app(ApplicationLifecycleService::class)->transition($appA, Application::STAGE_APPROVED, $this->ddGilgit, [], $appA->lock_version);
        $billA = Bill::where('application_id', $appA->id)->firstOrFail();

        $this->postJson('/api/gb-pay/bill-payment', [
            'username' => 'gbpay_qa_client',
            'password' => 'gbpay_qa_secret',
            'voucher_number' => $billA->psid,
            'transaction_id' => 'TXN-IDEMP-01',
            'transaction_amount' => 600,
            'bank_mnemonic' => 'HBL',
        ])->assertOk();

        $paidBillSnapshot = $billA->fresh();
        $logCountAfterPay = ApplicationStatusLog::where('application_id', $appA->id)->count();

        // Replay exact same transaction_id -> 03 Duplicate Transaction
        $this->postJson('/api/gb-pay/bill-payment', [
            'username' => 'gbpay_qa_client',
            'password' => 'gbpay_qa_secret',
            'voucher_number' => $billA->psid,
            'transaction_id' => 'TXN-IDEMP-01',
            'transaction_amount' => 600,
            'bank_mnemonic' => 'HBL',
        ])->assertStatus(400)->assertJsonPath('response_code', '03');

        // Competing different transaction_id -> 06 Already Paid
        $this->postJson('/api/gb-pay/bill-payment', [
            'username' => 'gbpay_qa_client',
            'password' => 'gbpay_qa_secret',
            'voucher_number' => $billA->psid,
            'transaction_id' => 'TXN-COMPETE-02',
            'transaction_amount' => 600,
            'bank_mnemonic' => 'ABL',
        ])->assertStatus(400)->assertJsonPath('response_code', '06');

        // Same replays through /gbpay/webhook -> atomic 03 / 06 without overwriting bill
        $this->postJson('/gbpay/webhook', [
            'psid' => $billA->psid,
            'status' => 'paid',
            'amount' => 600,
            'transaction_id' => 'TXN-IDEMP-01',
        ])->assertStatus(400)->assertJsonPath('response_code', '03');

        $this->postJson('/gbpay/webhook', [
            'psid' => $billA->psid,
            'status' => 'paid',
            'amount' => 600,
            'transaction_id' => 'TXN-COMPETE-03',
        ])->assertStatus(400)->assertJsonPath('response_code', '06');

        $billA->refresh();
        $this->assertSame('TXN-IDEMP-01', $billA->transaction_id);
        $this->assertSame('HBL', $billA->bank_mnemonic);
        $this->assertSame($paidBillSnapshot->paid_at?->toIso8601String(), $billA->paid_at?->toIso8601String());
        $this->assertSame($logCountAfterPay, ApplicationStatusLog::where('application_id', $appA->id)->count());

        // App B: Expired past-due challan on webhook auto-expires to DORMANT and rejects payment ('02', 403)
        $appB = $this->createItemizedApplication($scheme, $form, $field, [
            [
                'item_key' => 'apple_grafted',
                'item_label' => 'Apple Grafted Plant',
                'is_free' => false,
                'unit_price' => 500,
                'govt_share_pct' => 40,
                'demanded_qty' => 2,
            ],
        ]);
        app(ApplicationLifecycleService::class)->transition($appB, Application::STAGE_APPROVED, $this->ddGilgit, [], $appB->lock_version);
        $appB->refresh();
        $billB = Bill::where('application_id', $appB->id)->firstOrFail();

        $appB->update(['challan_due_date' => now()->subDay()->toDateString()]);
        $billB->update(['due_date' => now()->subDay()->toDateString()]);

        $whExpired = $this->postJson('/gbpay/webhook', [
            'psid' => $billB->psid,
            'status' => 'paid',
            'amount' => 600,
            'transaction_id' => 'TXN-EXPIRED-1',
        ]);
        $whExpired->assertStatus(403)->assertJsonPath('response_code', '02');
        $this->assertSame(Application::STAGE_DORMANT, $appB->fresh()->current_stage);
        $this->assertSame('cancelled', $billB->fresh()->status);

        // App C: Webhook signature check rejects spoofed signature when secret is configured
        config()->set('gbpay.webhook_secret', 'super-secret-hmac-key');
        $this->postJson('/gbpay/webhook', [
            'psid' => $billA->psid,
            'status' => 'paid',
            'amount' => 600,
        ], ['X-GBPay-Signature' => 'invalid-signature'])->assertStatus(401);
        config()->set('gbpay.webhook_secret', null);
    }

    public function test_unique_gap_free_sequential_20_digit_psids_across_interleaved_paid_and_free_schemes(): void
    {
        $agri = $this->createMixedItemizedScheme($this->agriDept, true);
        $livestock = $this->createMixedItemizedScheme($this->livestockDept, false); // Fee-free livestock
        $fisheries = $this->createMixedItemizedScheme($this->fisheriesDept, true);

        // Interleave paid & free applications across departments
        $paid1 = $this->createItemizedApplication($agri['scheme'], $agri['form'], $agri['field'], [
            ['item_key' => 'apple_grafted', 'item_label' => 'Apple', 'is_free' => false, 'unit_price' => 500, 'govt_share_pct' => 40, 'demanded_qty' => 2],
        ]);
        $free1 = $this->createItemizedApplication($livestock['scheme'], $livestock['form'], $livestock['field'], [
            ['item_key' => 'apricot_free', 'item_label' => 'Apricot Free', 'is_free' => true, 'unit_price' => 0, 'govt_share_pct' => 100, 'demanded_qty' => 4],
        ]);
        $paid2 = $this->createItemizedApplication($fisheries['scheme'], $fisheries['form'], $fisheries['field'], [
            ['item_key' => 'apple_grafted', 'item_label' => 'Apple', 'is_free' => false, 'unit_price' => 500, 'govt_share_pct' => 40, 'demanded_qty' => 3],
        ]);
        $free2 = $this->createItemizedApplication($agri['scheme'], $agri['form'], $agri['field'], [
            ['item_key' => 'walnut_fully_subsidized', 'item_label' => 'Walnut Free', 'is_free' => false, 'unit_price' => 450, 'govt_share_pct' => 100, 'demanded_qty' => 5],
        ]);
        $paid3 = $this->createItemizedApplication($agri['scheme'], $agri['form'], $agri['field'], [
            ['item_key' => 'apple_grafted', 'item_label' => 'Apple', 'is_free' => false, 'unit_price' => 500, 'govt_share_pct' => 40, 'demanded_qty' => 1],
        ]);

        // Also assign Fisheries DD authority (Gilgit) via DD roles: notice DD has Gilgit jurisdiction; check if DD is restricted by department!
        // Make DD Gilgit also authorized for Fisheries if needed by clearing department_id or testing with role check
        $this->ddGilgit->update(['department_id' => null]);

        $lifecycle = app(ApplicationLifecycleService::class);
        foreach ([$paid1, $free1, $paid2, $free2, $paid3] as $app) {
            $lifecycle->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [], $app->lock_version);
        }

        // Only the 3 paid applications generated bills/PSIDs; serial numbers strictly 1, 2, 3 (no skipped gaps from free1 / free2!)
        $bills = Bill::orderBy('voucher_serial')->get();
        $this->assertCount(3, $bills);
        $this->assertSame([1, 2, 3], $bills->pluck('voucher_serial')->all());
        $this->assertSame(3, $bills->pluck('psid')->unique()->count());
        $this->assertSame(3, (int) $this->globalPsidConfig->fresh()->last_voucher_serial);

        foreach ($bills as $bill) {
            $this->assertMatchesRegularExpression('/^\d{20}$/', $bill->psid);
        }

        // Verify department service code '01' for Agriculture & '03' for Fisheries embedded in prefix positions [8..9]
        $this->assertSame('01', substr($bills[0]->psid, 8, 2)); // Agriculture
        $this->assertSame('03', substr($bills[1]->psid, 8, 2)); // Fisheries
        $this->assertSame('01', substr($bills[2]->psid, 8, 2)); // Agriculture
    }

    public function test_transaction_rollback_atomicity_on_approval_bill_and_multi_item_disbursement_failures(): void
    {
        // Part A: Failed transaction inside approval/PSID minting rolls back VoucherSerial, last_voucher_serial, and Bill
        $this->globalPsidConfig->update(['last_voucher_serial' => 5]);
        ['scheme' => $scheme, 'form' => $form, 'field' => $field] = $this->createMixedItemizedScheme();

        $app = $this->createItemizedApplication($scheme, $form, $field, [
            ['item_key' => 'apple_grafted', 'item_label' => 'Apple', 'is_free' => false, 'unit_price' => 500, 'govt_share_pct' => 40, 'demanded_qty' => 2],
        ]);

        try {
            DB::transaction(function () use ($app) {
                app(ApplicationLifecycleService::class)->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [], $app->lock_version);
                throw new RuntimeException('Simulated post-bill failure forcing rollback.');
            });
            $this->fail('Expected simulated rollback exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated post-bill failure', $e->getMessage());
        }

        $this->assertSame(0, Bill::count());
        $this->assertSame(0, VoucherSerial::count());
        $this->assertSame(5, (int) $this->globalPsidConfig->fresh()->last_voucher_serial);
        $this->assertSame(Application::STAGE_UNDER_REVIEW, $app->fresh()->current_stage);

        // Part B: Multi-item disbursement failure (second item short by 1 plant) rolls back ENTIRE batch
        $appleStock = SchemeStockItem::create([
            'scheme_id' => $scheme->id,
            'item_name' => 'Apple Grafted Plant',
            'item_code' => 'STK-APPLE',
            'unit' => 'plants',
            'is_active' => true,
        ]);
        $apricotStock = SchemeStockItem::create([
            'scheme_id' => $scheme->id,
            'item_name' => 'Apricot Local Sapling',
            'item_code' => 'STK-APRI',
            'unit' => 'plants',
            'is_active' => true,
        ]);

        $center = CollectionCenter::create([
            'name' => 'Gilgit Nursery Center',
            'code' => 'NUR-GLT-01',
            'level' => 'district',
            'district_id' => $this->gilgit->id,
            'is_active' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $stockService = app(StockManagementService::class);
        $stockService->recordStockReceipt([
            'scheme_stock_item_id' => $appleStock->id,
            'collection_center_id' => $center->id,
            'quantity_received' => 50,
            'supplier_source' => 'NURSERY-A',
        ], $this->distOfficer);
        $stockService->recordStockReceipt([
            'scheme_stock_item_id' => $apricotStock->id,
            'collection_center_id' => $center->id,
            'quantity_received' => 4, // Eligible app needs 5 => 1 short!
            'supplier_source' => 'NURSERY-A',
        ], $this->distOfficer);

        $eligibleApp = $this->createItemizedApplication($scheme, $form, $field, [
            [
                'item_key' => 'apple_grafted',
                'item_label' => 'Apple Grafted Plant',
                'is_free' => false,
                'unit_price' => 500,
                'govt_share_pct' => 40,
                'demanded_qty' => 3,
                'scheme_stock_item_id' => $appleStock->id,
            ],
            [
                'item_key' => 'apricot_free',
                'item_label' => 'Apricot Local Sapling',
                'is_free' => true,
                'unit_price' => 0,
                'govt_share_pct' => 100,
                'demanded_qty' => 5,
                'scheme_stock_item_id' => $apricotStock->id,
            ],
        ]);
        app(ApplicationLifecycleService::class)->transition($eligibleApp, Application::STAGE_APPROVED, $this->ddGilgit, [], $eligibleApp->lock_version);
        $eligibleBill = Bill::where('application_id', $eligibleApp->id)->firstOrFail();
        app(GbpayService::class)->markBillAsPaid($eligibleBill, 'TXN-BATCH-01', 900, 'KCBL');
        $eligibleApp->refresh();

        $appleItem = $eligibleApp->items()->where('item_key', 'apple_grafted')->firstOrFail();
        $apricotItem = $eligibleApp->items()->where('item_key', 'apricot_free')->firstOrFail();

        $batchPayload = [
            'application_id' => $eligibleApp->id,
            'collection_center_id' => $center->id,
            'batch_items' => [
                ['application_item_id' => $appleItem->id, 'scheme_stock_item_id' => $appleStock->id],
                ['application_item_id' => $apricotItem->id, 'scheme_stock_item_id' => $apricotStock->id],
            ],
        ];

        try {
            $stockService->disburseStock($batchPayload, $this->distOfficer);
            $this->fail('Expected ValidationException when Apricot inventory is 1 plant short.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('batch_items', $e->errors());
        }

        // Neither Apple nor Apricot stock balance was decremented!
        $this->assertSame(50.0, (float) $center->getInventoryForItem($appleStock->id)->current_balance);
        $this->assertSame(4.0, (float) $center->getInventoryForItem($apricotStock->id)->current_balance);
        $this->assertSame(0, StockDisbursement::where('application_id', $eligibleApp->id)->count());

        // Receive 1 more Apricot -> Batch disbursement now succeeds in one unified voucher, then blocks repeat disbursement!
        $stockService->recordStockReceipt([
            'scheme_stock_item_id' => $apricotStock->id,
            'collection_center_id' => $center->id,
            'quantity_received' => 1,
            'supplier_source' => 'NURSERY-TOPUP',
        ], $this->distOfficer);

        $firstDisb = $stockService->disburseStock($batchPayload, $this->distOfficer);
        $this->assertNotNull($firstDisb->batch_voucher_no);
        $this->assertSame(47.0, (float) $center->getInventoryForItem($appleStock->id)->current_balance);
        $this->assertSame(0.0, (float) $center->getInventoryForItem($apricotStock->id)->current_balance);
        $this->assertSame(Application::STAGE_FULLY_DISTRIBUTED, $eligibleApp->fresh()->current_stage);

        // Repeat disbursement attempt for already-disbursed beneficiary MUST fail cleanly
        try {
            $stockService->disburseStock($batchPayload, $this->distOfficer);
            $this->fail('Expected ValidationException on repeat disbursement.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('application_id', $e->errors());
        }
    }

    public function test_optimistic_lock_version_prevents_stale_concurrent_dd_approval_and_zero_item_denial(): void
    {
        ['scheme' => $scheme, 'form' => $form, 'field' => $field] = $this->createMixedItemizedScheme();

        $app = $this->createItemizedApplication($scheme, $form, $field, [
            ['item_key' => 'apple_grafted', 'item_label' => 'Apple', 'is_free' => false, 'unit_price' => 500, 'govt_share_pct' => 40, 'demanded_qty' => 5],
            ['item_key' => 'apricot_free', 'item_label' => 'Apricot', 'is_free' => true, 'unit_price' => 0, 'govt_share_pct' => 100, 'demanded_qty' => 5],
        ]);

        $appleItem = $app->items()->where('item_key', 'apple_grafted')->firstOrFail();
        $apricotItem = $app->items()->where('item_key', 'apricot_free')->firstOrFail();

        // Approving with ALL item quantities zeroed out must fail cleanly without creating a bill
        try {
            app(ApplicationLifecycleService::class)->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [
                'approved_items' => [
                    $appleItem->id => 0,
                    $apricotItem->id => 0,
                ],
            ], $app->lock_version);
            $this->fail('Expected ValidationException when approving 0 total items.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('approved_items', $e->errors());
        }
        $this->assertSame(0, Bill::count());

        $staleLockVersion = $app->lock_version;

        // First DD session approves successfully (bumps lock_version)
        app(ApplicationLifecycleService::class)->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [
            'approved_items' => [
                $appleItem->id => 3,
                $apricotItem->id => 5,
            ],
        ], $staleLockVersion);

        $app->refresh();
        $this->assertGreaterThan($staleLockVersion, $app->lock_version);
        $this->assertSame(1, Bill::count());

        // Second concurrent DD session using stale lock_version fails without creating duplicate bill/serial
        try {
            app(ApplicationLifecycleService::class)->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [
                'approved_items' => [
                    $appleItem->id => 4,
                    $apricotItem->id => 5,
                ],
            ], $staleLockVersion);
            $this->fail('Expected RuntimeException on stale lock_version conflict.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Concurrent update detected', $e->getMessage());
        }

        $this->assertSame(1, Bill::count());
        $this->assertSame(900.0, (float) $app->fresh()->challan_amount);
    }

    public function test_unpaid_challan_edit_via_application_update_syncs_pending_psid_and_cancels_when_reduced_to_zero(): void
    {
        ['scheme' => $scheme, 'form' => $form, 'field' => $field] = $this->createMixedItemizedScheme();

        $app = $this->createItemizedApplication($scheme, $form, $field, [
            ['item_key' => 'apple_grafted', 'item_label' => 'Apple', 'is_free' => false, 'unit_price' => 500, 'govt_share_pct' => 40, 'demanded_qty' => 5],
            ['item_key' => 'apricot_free', 'item_label' => 'Apricot', 'is_free' => true, 'unit_price' => 0, 'govt_share_pct' => 100, 'demanded_qty' => 5],
        ]);

        app(ApplicationLifecycleService::class)->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [], $app->lock_version);
        $app->refresh();

        $appleItem = $app->items()->where('item_key', 'apple_grafted')->firstOrFail();
        $apricotItem = $app->items()->where('item_key', 'apricot_free')->firstOrFail();
        $bill = Bill::where('application_id', $app->id)->firstOrFail();
        $psid = $bill->psid;

        // 1. Reduce paid apple qty via PUT /admin/applications/{id} -> keeps same PSID, syncs bill_amount 1500 -> 900
        $this->actingAs($this->ddGilgit)
            ->put(route('admin.applications.update', $app), [
                'full_name' => $app->applicant->full_name,
                'phone' => $app->applicant->phone,
                'district_id' => $app->district_id,
                'approved_items' => [
                    $appleItem->id => 3,
                    $apricotItem->id => 5,
                ],
                'modification_reason' => 'Reduced paid trees from 5 to 3 before deposit.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($psid, $app->fresh()->psid);
        $this->assertSame(900, $bill->fresh()->bill_amount);
        $this->assertSame('pending', $bill->fresh()->status);

        // 2. Super Admin waives/reduces paid qty to 0 via PUT update -> cancels PSID & promotes to eligible_beneficiary
        $this->actingAs($this->superAdmin)
            ->put(route('admin.applications.update', $app), [
                'full_name' => $app->applicant->full_name,
                'phone' => $app->applicant->phone,
                'district_id' => $app->district_id,
                'approved_items' => [
                    $appleItem->id => 0,
                    $apricotItem->id => 5,
                ],
                'modification_reason' => 'Removed paid tree item; only free trees retained.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $app->refresh();
        $this->assertSame(Application::STAGE_ELIGIBLE_BENEFICIARY, $app->current_stage);
        $this->assertNull($app->psid);
        $this->assertNull($app->challan_no);
        $this->assertSame('cancelled', $bill->fresh()->status);
    }
}
