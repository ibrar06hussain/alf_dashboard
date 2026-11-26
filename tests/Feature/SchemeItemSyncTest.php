<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\ApplicationItem;
use App\Models\Bill;
use App\Models\ChallanTemplate;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormField;
use App\Models\PsidConfig;
use App\Models\Scheme;
use App\Models\User;
use App\Services\GbpayService;
use App\Support\CoreFormFields;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemeItemSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private Department $agriDept;
    private District $gilgit;
    private ChallanTemplate $zeroGovtTemplate;
    private Scheme $treeScheme;
    private Form $form;
    private FormField $legacyTextField;
    private FormField $treeField;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->agriDept = Department::firstOrCreate(
            ['slug' => 'agriculture'],
            ['name' => 'Agriculture Department', 'is_active' => true, 'display_order' => 1]
        );

        $this->gilgit = District::firstOrCreate(
            ['code' => 'GLT'],
            ['name' => 'Gilgit', 'code' => 'GLT', 'is_active' => true]
        );

        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole('Super Admin');

        PsidConfig::firstOrCreate(
            ['is_active' => true, 'scheme_id' => null],
            [
                'one_link_prefix' => '101530',
                'department_code' => '09',
                'department_service_no' => '01',
                'district_code' => '01',
                'rco' => 'GL-1618',
                'head' => 'C03117',
                'is_active' => true,
            ]
        );

        $this->zeroGovtTemplate = ChallanTemplate::create([
            'name' => '100% Farmer Share Tree Challan',
            'department_name' => 'Agriculture Department GB',
            'challan_title' => 'FEE DEPOSIT CHALLAN',
            'bank_name' => 'National Bank of Pakistan',
            'account_title' => 'ALF GB',
            'account_no' => '4256260305',
            'account_head' => 'C03117',
            'validity_days' => 30,
            'directions' => 'Valid for 30 days.',
            'fee_mode' => 'fixed_unit',
            'govt_share_pct' => 0.00,
            'farmer_share_pct' => 100.00,
            'show_breakdown' => true,
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->treeScheme = Scheme::create([
            'department_id' => $this->agriDept->id,
            'title' => 'Green Pakistan Trees',
            'slug' => 'green-pakistan-trees',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => true,
            'challan_fee_per_unit' => 0,
            'challan_template_id' => $this->zeroGovtTemplate->id,
            'unit_label' => 'Trees',
            'has_auto_dashboard' => true,
        ]);

        $this->form = Form::create([
            'scheme_id' => $this->treeScheme->id,
            'title' => 'Trees Application Form',
            'version' => 1,
            'is_published' => true,
        ]);

        foreach (CoreFormFields::definitions() as $def) {
            FormField::create(array_merge($def, ['form_id' => $this->form->id]));
        }

        // Mirrors live Form #31 where checkbox_with_text has stale legacy key ('cherry' on 'Apple')
        $this->treeField = FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'num_plants',
            'field_label' => 'Fruit Plants Requirement',
            'field_type' => 'checkbox_with_text',
            'is_mandatory' => true,
            'is_billable_items_field' => true,
            'options_json' => ['Apple', 'Almond', 'Cherry', 'Peach', 'Pear'],
            'option_configs_json' => [
                ['key' => 'cherry', 'label' => 'Apple', 'pricing_mode' => 'free', 'unit_price' => 0, 'govt_share_pct' => null, 'input_mode' => 'numeric_qty', 'unit_label' => 'Trees'],
                ['key' => 'almond', 'label' => 'Almond', 'pricing_mode' => 'free', 'unit_price' => 0, 'govt_share_pct' => null, 'input_mode' => 'numeric_qty', 'unit_label' => 'Trees'],
                ['key' => 'cherry_2', 'label' => 'Cherry', 'pricing_mode' => 'paid', 'unit_price' => 300, 'govt_share_pct' => null, 'input_mode' => 'numeric_qty', 'unit_label' => 'Trees'],
                ['key' => 'peach', 'label' => 'Peach', 'pricing_mode' => 'free', 'unit_price' => 0, 'govt_share_pct' => null, 'input_mode' => 'numeric_qty', 'unit_label' => 'Trees'],
                ['key' => 'pear', 'label' => 'Pear', 'pricing_mode' => 'free', 'unit_price' => 0, 'govt_share_pct' => null, 'input_mode' => 'numeric_qty', 'unit_label' => 'Trees'],
            ],
            'display_order' => 20,
        ]);

        $this->legacyTextField = FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'num_plants',
            'field_label' => 'Fruit Plants Requirement (Number)',
            'field_type' => 'number',
            'is_mandatory' => false,
            'is_billable_items_field' => false,
            'display_order' => 21,
        ]);
    }

    private function createRawApplication(string $suffix, array $overrides = []): Application
    {
        $applicant = Applicant::create([
            'full_name' => "Applicant {$suffix}",
            'father_name' => 'Father Name',
            'cnic' => '71501' . str_pad($suffix, 8, '0', STR_PAD_LEFT),
            'phone' => '0345' . str_pad($suffix, 7, '0', STR_PAD_LEFT),
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'land_location' => 'Danyore',
        ]);

        return Application::create(array_merge([
            'application_no' => "ALF-SYNC-{$suffix}",
            'scheme_id' => $this->treeScheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => $this->agriDept->id,
            'form_id' => $this->form->id,
            'status' => Application::STATUS_PENDING,
            'current_stage' => Application::STAGE_SUBMITTED,
            'demanded_quantity' => 1,
            'submitted_at' => now(),
        ], $overrides));
    }

    private function buildCoreFieldsPayload(): array
    {
        $fields = [];
        foreach ($this->form->fields()->where('is_core', true)->get() as $i => $f) {
            $fields[] = [
                'id' => $f->id,
                'field_key' => $f->field_key,
                'field_label' => $f->field_label,
                'field_type' => $f->field_type,
                'is_mandatory' => $f->is_mandatory ? '1' : '0',
                'display_order' => $i + 1,
            ];
        }

        return $fields;
    }

    public function test_form_builder_update_backfills_legacy_items_syncs_unpaid_challans_and_preserves_paid_bills(): void
    {
        $gbpay = app(GbpayService::class);

        // 1. Submitted legacy app with mixed Urdu numerals, stray commas, text suffix, non-numeric, and Nil
        $submittedApp = $this->createRawApplication('001');
        ApplicationFieldValue::create([
            'application_id' => $submittedApp->id,
            'form_field_id' => $this->treeField->id,
            'value' => 'Apple: ۲۰; Almond: , 50; Cherry: 25 chery; Peach: Plant; Pear: Nil',
        ]);

        // 2. Legacy approved challan_issued app WITH Cherry -> should update bill from 6,000 to 7,000 (20 * 350) keeping PSID
        $legacyPaidItemApp = $this->createRawApplication('002', [
            'status' => Application::STATUS_DIRECTORATE_APPROVED,
            'current_stage' => Application::STAGE_CHALLAN_ISSUED,
            'demanded_quantity' => 1,
            'approved_quantity' => 1,
            'challan_no' => 'CHL-2026-8204',
            'challan_amount' => 6000,
            'challan_issued_at' => now(),
            'challan_due_date' => now()->addDays(30)->toDateString(),
        ]);
        ApplicationFieldValue::create([
            'application_id' => $legacyPaidItemApp->id,
            'form_field_id' => $this->treeField->id,
            'value' => 'Apple: 10; Cherry: 20',
        ]);
        $legacyBillWithCherry = $gbpay->createOrUpdateBillForApplication($legacyPaidItemApp, 6000);
        $psidLegacyCherry = $legacyBillWithCherry->psid;

        // 3. Legacy approved challan_issued app WITH ONLY FREE ITEMS -> should cancel PSID and move to eligible_beneficiary
        $legacyFreeOnlyApp = $this->createRawApplication('003', [
            'status' => Application::STATUS_DIRECTORATE_APPROVED,
            'current_stage' => Application::STAGE_CHALLAN_ISSUED,
            'demanded_quantity' => 2,
            'approved_quantity' => 2,
            'challan_no' => 'CHL-2026-8571',
            'challan_amount' => 12000,
            'challan_issued_at' => now(),
            'challan_due_date' => now()->addDays(30)->toDateString(),
        ]);
        ApplicationFieldValue::create([
            'application_id' => $legacyFreeOnlyApp->id,
            'form_field_id' => $this->treeField->id,
            'value' => 'Apple: 13',
        ]);
        $legacyBillFreeOnly = $gbpay->createOrUpdateBillForApplication($legacyFreeOnlyApp, 12000);

        // 4. Paid itemized app (old Cherry @ 300) -> MUST stay completely frozen
        $paidApp = $this->createRawApplication('004', [
            'status' => Application::STATUS_DIRECTORATE_APPROVED,
            'current_stage' => Application::STAGE_PAYMENT_VERIFIED,
            'demanded_quantity' => 10,
            'approved_quantity' => 10,
            'challan_no' => 'CHL-2026-PAID4',
            'challan_amount' => 3000,
            'challan_issued_at' => now(),
            'challan_due_date' => now()->addDays(30)->toDateString(),
            'challan_paid_at' => now(),
            'payment_verified_at' => now(),
        ]);
        $paidCherry = ApplicationItem::create([
            'application_id' => $paidApp->id,
            'form_field_id' => $this->treeField->id,
            'item_key' => 'cherry',
            'item_label' => 'Cherry',
            'unit_label' => 'Trees',
            'is_free' => false,
            'unit_price' => 300,
            'govt_share_pct' => 0,
            'inherits_template_share' => true,
            'farmer_share_pct' => 100,
            'demanded_qty' => 10,
            'approved_qty' => 10,
            'gross_amount' => 3000,
            'govt_share_amount' => 0,
            'farmer_payable_amount' => 3000,
        ]);
        $paidBill = $gbpay->createOrUpdateBillForApplication($paidApp, 3000);
        $paidBill->update(['status' => 'paid', 'paid_at' => now()]);

        // 5. Unpaid itemized app (old Cherry @ 300) -> should sync price 300 -> 350 and update bill 7,500 -> 8,750
        $unpaidItemizedApp = $this->createRawApplication('005', [
            'status' => Application::STATUS_DIRECTORATE_APPROVED,
            'current_stage' => Application::STAGE_CHALLAN_ISSUED,
            'demanded_quantity' => 25,
            'approved_quantity' => 25,
            'challan_no' => 'CHL-2026-8905',
            'challan_amount' => 7500,
            'challan_issued_at' => now(),
            'challan_due_date' => now()->addDays(30)->toDateString(),
        ]);
        ApplicationItem::create([
            'application_id' => $unpaidItemizedApp->id,
            'form_field_id' => $this->treeField->id,
            'item_key' => 'cherry',
            'item_label' => 'Cherry',
            'unit_label' => 'Trees',
            'is_free' => false,
            'unit_price' => 300,
            'govt_share_pct' => 0,
            'inherits_template_share' => true,
            'farmer_share_pct' => 100,
            'demanded_qty' => 25,
            'approved_qty' => 25,
            'gross_amount' => 7500,
            'govt_share_amount' => 0,
            'farmer_payable_amount' => 7500,
        ]);
        $unpaidBill8905 = $gbpay->createOrUpdateBillForApplication($unpaidItemizedApp, 7500);
        $psid8905 = $unpaidBill8905->psid;

        // Admin saves Form Builder updating Cherry price from 300 -> 350
        $payloadFields = $this->buildCoreFieldsPayload();
        $payloadFields[] = [
            'id' => $this->treeField->id,
            'field_key' => 'num_plants',
            'field_label' => 'Fruit Plants Requirement',
            'field_type' => 'checkbox_with_text',
            'is_mandatory' => '1',
            'is_billable_items_field' => '1',
            'display_order' => 20,
            'option_configs' => [
                ['key' => 'cherry', 'label' => 'Apple', 'pricing_mode' => 'free', 'unit_price' => 0, 'input_mode' => 'numeric_qty', 'unit_label' => 'Trees'],
                ['key' => 'almond', 'label' => 'Almond', 'pricing_mode' => 'free', 'unit_price' => 0, 'input_mode' => 'numeric_qty', 'unit_label' => 'Trees'],
                ['key' => 'cherry_2', 'label' => 'Cherry', 'pricing_mode' => 'paid', 'unit_price' => 350, 'input_mode' => 'numeric_qty', 'unit_label' => 'Trees'],
                ['key' => 'peach', 'label' => 'Peach', 'pricing_mode' => 'free', 'unit_price' => 0, 'input_mode' => 'numeric_qty', 'unit_label' => 'Trees'],
                ['key' => 'pear', 'label' => 'Pear', 'pricing_mode' => 'free', 'unit_price' => 0, 'input_mode' => 'numeric_qty', 'unit_label' => 'Trees'],
            ],
        ];
        $payloadFields[] = [
            'id' => $this->legacyTextField->id,
            'field_key' => 'num_plants',
            'field_label' => 'Fruit Plants Requirement (Number)',
            'field_type' => 'number',
            'is_mandatory' => '0',
            'display_order' => 21,
        ];

        $this->actingAs($this->superAdmin)
            ->put(route('admin.form-builder.update', $this->treeScheme), [
                'title' => $this->form->title,
                'fields' => $payloadFields,
                'publish' => '1',
            ])
            ->assertRedirect(route('admin.form-builder.edit', $this->treeScheme));

        // Check resolveOptionConfig resolves 'Cherry' to the real Cherry option even when 'Apple' holds stale key 'cherry'
        $this->treeField->refresh();
        $resolvedCherry = $this->treeField->resolveOptionConfig('Cherry');
        $this->assertNotNull($resolvedCherry);
        $this->assertSame('Cherry', $resolvedCherry['label']);
        $this->assertEquals(350, $resolvedCherry['unit_price']);

        // 1. Submitted legacy app stays submitted, NO premature bill, items parsed accurately
        $submittedApp->refresh();
        $this->assertSame(Application::STAGE_SUBMITTED, $submittedApp->current_stage);
        $this->assertNull($submittedApp->challan_no);
        $this->assertNull($submittedApp->psid);
        $this->assertEquals(95, (float) $submittedApp->demanded_quantity);
        $this->assertSame(0, Bill::where('application_id', $submittedApp->id)->count());

        $subCherry = $submittedApp->items()->where('item_label', 'Cherry')->firstOrFail();
        $subApple = $submittedApp->items()->where('item_label', 'Apple')->firstOrFail();
        $subAlmond = $submittedApp->items()->where('item_label', 'Almond')->firstOrFail();
        $subPeach = $submittedApp->items()->where('item_label', 'Peach')->firstOrFail();
        $this->assertFalse($subCherry->is_free);
        $this->assertEquals(25, (float) $subCherry->demanded_qty);
        $this->assertEquals(350, (float) $subCherry->unit_price);
        $this->assertNull($subCherry->approved_qty);
        $this->assertEquals(20, (float) $subApple->demanded_qty);
        $this->assertTrue($subApple->is_free);
        $this->assertEquals(50, (float) $subAlmond->demanded_qty);
        $this->assertEquals(0, (float) $subPeach->demanded_qty);
        $this->assertNull($submittedApp->items()->where('item_label', 'Pear')->first());

        // 2. Legacy approved challan_issued app WITH Cherry -> bill regenerated to 7,000 keeping PSID
        $legacyPaidItemApp->refresh();
        $legacyBillWithCherry->refresh();
        $this->assertSame(Application::STAGE_CHALLAN_ISSUED, $legacyPaidItemApp->current_stage);
        $this->assertEquals(30, (float) $legacyPaidItemApp->approved_quantity);
        $this->assertEquals(7000, (float) $legacyPaidItemApp->challan_amount);
        $this->assertSame($psidLegacyCherry, $legacyBillWithCherry->psid);
        $this->assertSame('pending', $legacyBillWithCherry->status);
        $this->assertEquals(7000, (float) $legacyBillWithCherry->bill_amount);

        // 3. Legacy approved challan_issued app WITH free items only -> PSID cancelled, eligible_beneficiary
        $legacyFreeOnlyApp->refresh();
        $legacyBillFreeOnly->refresh();
        $this->assertSame(Application::STAGE_ELIGIBLE_BENEFICIARY, $legacyFreeOnlyApp->current_stage);
        $this->assertEquals(13, (float) $legacyFreeOnlyApp->approved_quantity);
        $this->assertNull($legacyFreeOnlyApp->challan_no);
        $this->assertNull($legacyFreeOnlyApp->psid);
        $this->assertNull($legacyFreeOnlyApp->challan_amount);
        $this->assertSame('cancelled', $legacyBillFreeOnly->status);

        // 4. Paid app stays frozen at Rs. 3,000 / 300 per tree
        $paidApp->refresh();
        $paidBill->refresh();
        $paidCherry->refresh();
        $this->assertEquals(300, (float) $paidCherry->unit_price);
        $this->assertEquals(3000, (float) $paidApp->challan_amount);
        $this->assertSame('paid', $paidBill->status);
        $this->assertEquals(3000, (float) $paidBill->bill_amount);

        // 5. Unpaid itemized app updates 7,500 -> 8,750 keeping exact PSID
        $unpaidItemizedApp->refresh();
        $unpaidBill8905->refresh();
        $unpaidCherry = $unpaidItemizedApp->items()->where('item_label', 'Cherry')->firstOrFail();
        $this->assertEquals(350, (float) $unpaidCherry->unit_price);
        $this->assertEquals(8750, (float) $unpaidCherry->farmer_payable_amount);
        $this->assertEquals(8750, (float) $unpaidItemizedApp->challan_amount);
        $this->assertSame($psid8905, $unpaidBill8905->psid);
        $this->assertEquals(8750, (float) $unpaidBill8905->bill_amount);
    }

    public function test_artisan_command_supports_dry_run_and_regenerates_challan_for_free_cleared_eligible_app(): void
    {
        // Configure Cherry as FREE initially
        $configs = $this->treeField->option_configs_json;
        $configs[2]['pricing_mode'] = 'free';
        $configs[2]['unit_price'] = 0;
        $this->treeField->update(['option_configs_json' => $configs]);

        // Approved app cleared as eligible_beneficiary (Rs. 0 payable while Cherry was free)
        $app = $this->createRawApplication('009', [
            'status' => Application::STATUS_DIRECTORATE_APPROVED,
            'current_stage' => Application::STAGE_ELIGIBLE_BENEFICIARY,
            'demanded_quantity' => 20,
            'approved_quantity' => 20,
        ]);
        ApplicationItem::create([
            'application_id' => $app->id,
            'form_field_id' => $this->treeField->id,
            'item_key' => 'cherry',
            'item_label' => 'Cherry',
            'unit_label' => 'Trees',
            'is_free' => true,
            'unit_price' => 0,
            'govt_share_pct' => 100,
            'inherits_template_share' => false,
            'farmer_share_pct' => 0,
            'demanded_qty' => 20,
            'approved_qty' => 20,
            'gross_amount' => 0,
            'govt_share_amount' => 0,
            'farmer_payable_amount' => 0,
        ]);

        // Update Cherry in Form Builder JSON directly (simulating SQL/manual update) -> paid @ 350
        $configs[2]['pricing_mode'] = 'paid';
        $configs[2]['unit_price'] = 350;
        $this->treeField->update(['option_configs_json' => $configs]);

        // Dry run must NOT modify DB
        $this->artisan('schemes:sync-items', ['scheme_id' => $this->treeScheme->id, '--dry-run' => true])
            ->assertExitCode(0);

        $app->refresh();
        $this->assertSame(Application::STAGE_ELIGIBLE_BENEFICIARY, $app->current_stage);
        $this->assertNull($app->psid);
        $this->assertSame(0, Bill::where('application_id', $app->id)->count());

        // Real run regenerates challan and mints 20-digit PSID
        $this->artisan('schemes:sync-items', ['scheme_id' => $this->treeScheme->id])
            ->assertExitCode(0);

        $app->refresh();
        $this->assertSame(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
        $this->assertNotNull($app->challan_no);
        $this->assertNotNull($app->psid);
        $this->assertSame(20, strlen($app->psid));
        $this->assertEquals(7000, (float) $app->challan_amount);

        $bill = Bill::where('application_id', $app->id)->firstOrFail();
        $this->assertSame('pending', $bill->status);
        $this->assertSame($app->psid, $bill->psid);
        $this->assertEquals(7000, (float) $bill->bill_amount);
    }
}
