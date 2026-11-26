<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\Bill;
use App\Models\ChallanTemplate;
use App\Models\CollectionCenter;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormDocument;
use App\Models\FormField;
use App\Models\Office;
use App\Models\PsidConfig;
use App\Models\Scheme;
use App\Models\SchemeDistrict;
use App\Models\SchemeStockItem;
use App\Models\StockDisbursement;
use App\Models\User;
use App\Models\UserOfficeAssignment;
use App\Models\VoucherSerial;
use App\Services\QuotaService;
use App\Services\StockManagementService;
use App\Support\CoreFormFields;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ItemizedMultiTreeSchemeTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $secretary;
    private User $directorAgri;
    private User $directorLivestock;
    private User $ddGilgit;
    private User $ddSkardu;
    private User $fieldOfficerGilgit;
    private User $accountsOfficerGilgit;
    private User $distOfficerGilgit;

    private Department $agriDept;
    private Department $livestockDept;
    private District $gilgit;
    private District $skardu;
    private Scheme $treeScheme;
    private Scheme $livestockScheme;
    private Form $form;
    private FormField $treeField;
    private FormDocument $cnicDoc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->agriDept = Department::firstOrCreate(
            ['slug' => 'agriculture'],
            ['name' => 'Agriculture Department', 'is_active' => true, 'display_order' => 1]
        );
        $this->livestockDept = Department::firstOrCreate(
            ['slug' => 'livestock'],
            ['name' => 'Livestock Department', 'is_active' => true, 'display_order' => 2]
        );

        $this->gilgit = District::firstOrCreate(['code' => 'GLT'], ['name' => 'Gilgit', 'code' => 'GLT', 'is_active' => true]);
        $this->skardu = District::firstOrCreate(['code' => 'SKD'], ['name' => 'Skardu', 'code' => 'SKD', 'is_active' => true]);

        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole('Super Admin');

        $this->secretary = User::factory()->create(['is_active' => true]);
        $this->secretary->assignRole('Secretary');

        $this->directorAgri = User::factory()->create(['department_id' => $this->agriDept->id, 'is_active' => true]);
        $this->directorAgri->assignRole('Director Agriculture');

        $this->directorLivestock = User::factory()->create(['department_id' => $this->livestockDept->id, 'is_active' => true]);
        $this->directorLivestock->assignRole('Director Livestock');

        $this->ddGilgit = User::factory()->create([
            'department_id' => $this->agriDept->id,
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);
        $this->ddGilgit->assignRole('District Deputy Director');

        $ddGilgitOffice = Office::create([
            'name' => 'DD Agri Gilgit Office',
            'office_type' => Office::TYPE_DISTRICT_DEPUTY_DIRECTORATE,
            'department_id' => $this->agriDept->id,
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);
        UserOfficeAssignment::create([
            'user_id' => $this->ddGilgit->id,
            'office_id' => $ddGilgitOffice->id,
            'department_id' => $this->agriDept->id,
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);

        $this->ddSkardu = User::factory()->create([
            'department_id' => $this->agriDept->id,
            'district_id' => $this->skardu->id,
            'is_active' => true,
        ]);
        $this->ddSkardu->assignRole('District Deputy Director');

        $this->fieldOfficerGilgit = User::factory()->create(['district_id' => $this->gilgit->id, 'is_active' => true]);
        $this->fieldOfficerGilgit->assignRole('Field Officer');

        $this->accountsOfficerGilgit = User::factory()->create(['district_id' => $this->gilgit->id, 'is_active' => true]);
        $this->accountsOfficerGilgit->assignRole('Accounts Officer');

        $this->distOfficerGilgit = User::factory()->create(['district_id' => $this->gilgit->id, 'is_active' => true]);
        $this->distOfficerGilgit->assignRole('Distribution Officer');

        PsidConfig::firstOrCreate(
            ['is_active' => true, 'scheme_id' => null],
            [
                'one_link_prefix' => '101530',
                'department_code' => '09',
                'department_service_no' => '01',
                'district_code' => '01',
                'rco' => 'GL-1618',
                'head' => 'C03870',
                'is_active' => true,
            ]
        );

        $itemizedTemplate = ChallanTemplate::create([
            'name' => 'Multi-Tree Subsidy Template',
            'department_name' => 'Agriculture Department GB',
            'challan_title' => 'FEE DEPOSIT CHALLAN',
            'bank_name' => 'National Bank of Pakistan',
            'account_title' => 'ALF GB',
            'account_no' => '1234567890',
            'account_head' => 'C03870',
            'validity_days' => 10,
            'directions' => 'Valid for 10 days.',
            'fee_mode' => 'itemized',
            'govt_share_pct' => 50.00,
            'farmer_share_pct' => 50.00,
            'show_breakdown' => true,
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->treeScheme = Scheme::create([
            'department_id' => $this->agriDept->id,
            'title' => 'Fruit & Forest Plantation Scheme',
            'slug' => 'fruit-forest-plantation',
            'type' => 'running',
            'scope' => 'district',
            'status' => 'active',
            'requires_challan' => true,
            'challan_fee_per_unit' => 0,
            'challan_template_id' => $itemizedTemplate->id,
            'has_auto_dashboard' => true,
            'start_date' => now()->subDay(),
            'last_date' => now()->addMonth(),
        ]);

        SchemeDistrict::create([
            'scheme_id' => $this->treeScheme->id,
            'district_id' => $this->gilgit->id,
            'quota' => 500,
        ]);

        $this->livestockScheme = Scheme::create([
            'department_id' => $this->livestockDept->id,
            'title' => 'Poultry Unit Support',
            'slug' => 'poultry-unit-support',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'has_auto_dashboard' => true,
        ]);

        $this->form = Form::create([
            'scheme_id' => $this->treeScheme->id,
            'title' => 'Fruit & Forest Tree Application',
            'version' => 1,
            'is_published' => true,
        ]);

        foreach (CoreFormFields::definitions() as $def) {
            FormField::create(array_merge($def, ['form_id' => $this->form->id]));
        }

        $this->treeField = FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'requested_trees',
            'field_label' => 'Select Tree Types & Quantities',
            'field_label_ur' => 'درختوں کی اقسام اور تعداد',
            'field_type' => 'checkbox_with_text',
            'is_mandatory' => true,
            'is_billable_items_field' => true,
            'options_json' => ['Olive', 'Apple', 'Wild Pine', 'Walnut 100% Subsidized'],
            'option_configs_json' => [
                [
                    'key' => 'olive',
                    'label' => 'Olive',
                    'label_ur' => 'زیتون',
                    'pricing_mode' => 'paid',
                    'unit_price' => 500,
                    'govt_share_pct' => 40, // Farmer pays 60% = Rs. 300/tree
                    'input_mode' => 'numeric_qty',
                    'unit_label' => 'Trees',
                ],
                [
                    'key' => 'apple',
                    'label' => 'Apple',
                    'label_ur' => 'سیب',
                    'pricing_mode' => 'paid',
                    'unit_price' => 200,
                    'govt_share_pct' => null, // Falls back to ChallanTemplate 50% -> Rs. 100/tree
                    'input_mode' => 'numeric_qty',
                    'unit_label' => 'Trees',
                ],
                [
                    'key' => 'wild_pine',
                    'label' => 'Wild Pine',
                    'label_ur' => 'چیل',
                    'pricing_mode' => 'free',
                    'unit_price' => 0,
                    'govt_share_pct' => null,
                    'input_mode' => 'numeric_qty',
                    'unit_label' => 'Trees',
                ],
                [
                    'key' => 'walnut_100_subsidized',
                    'label' => 'Walnut 100% Subsidized',
                    'label_ur' => 'اخروٹ',
                    'pricing_mode' => 'paid',
                    'unit_price' => 300,
                    'govt_share_pct' => 100, // 100% govt share -> Rs. 0 payable
                    'input_mode' => 'numeric_qty',
                    'unit_label' => 'Trees',
                ],
            ],
            'display_order' => 20,
        ]);

        $this->cnicDoc = FormDocument::create([
            'form_id' => $this->form->id,
            'document_name' => 'CNIC Copy',
            'is_mandatory' => true,
            'allowed_extensions' => 'pdf,jpg,png',
            'max_size_mb' => 5,
            'display_order' => 1,
        ]);
    }

    public function test_form_builder_saves_mixed_paid_free_item_matrix(): void
    {
        $corePayload = [];
        foreach ($this->form->fields()->where('is_core', true)->get() as $i => $f) {
            $corePayload[] = [
                'id' => $f->id,
                'field_key' => $f->field_key,
                'field_label' => $f->field_label,
                'field_type' => $f->field_type,
                'is_mandatory' => $f->is_mandatory ? '1' : '0',
                'display_order' => $i + 1,
            ];
        }

        $corePayload[] = [
            'id' => $this->treeField->id,
            'field_key' => 'requested_trees',
            'field_label' => 'Select Tree Types & Quantities',
            'field_type' => 'checkbox_with_text',
            'is_mandatory' => '1',
            'is_billable_items_field' => '1',
            'display_order' => 20,
            'option_configs' => [
                [
                    'label' => 'Cherry Tree',
                    'label_ur' => 'چیری',
                    'pricing_mode' => 'paid',
                    'unit_price' => 400,
                    'govt_share_pct' => 25,
                    'input_mode' => 'numeric_qty',
                    'unit_label' => 'Trees',
                ],
                [
                    'label' => 'Willow',
                    'label_ur' => 'بید',
                    'pricing_mode' => 'free',
                    'unit_price' => 0,
                    'input_mode' => 'numeric_qty',
                    'unit_label' => 'Trees',
                ],
            ],
        ];

        $this->actingAs($this->superAdmin)
            ->put(route('admin.form-builder.update', $this->treeScheme), [
                'title' => $this->form->title,
                'fields' => $corePayload,
                'publish' => '1',
            ])
            ->assertRedirect();

        $this->treeField->refresh();
        $this->assertTrue($this->treeField->is_billable_items_field);
        $this->assertSame(['Cherry Tree', 'Willow'], $this->treeField->options_json);
        $this->assertSame('paid', $this->treeField->option_configs_json[0]['pricing_mode']);
        $this->assertEquals(400, $this->treeField->option_configs_json[0]['unit_price']);
        $this->assertEquals(25, $this->treeField->option_configs_json[0]['govt_share_pct']);
        $this->assertSame('free', $this->treeField->option_configs_json[1]['pricing_mode']);
    }

    public function test_quantity_validation_enforces_positive_integers_for_billable_items(): void
    {
        Storage::fake('public');

        $response = $this->post(route('schemes.apply.store', $this->treeScheme->slug), [
            'full_name' => 'Karim Trees',
            'father_name' => 'Ali',
            'cnic' => '7110199990001',
            'phone' => '03451112233',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit City',
            'land_location' => 'Danyore',
            'requested_trees' => [
                0 => ['label' => 'Olive', 'checked' => '1', 'text' => 'abc'],
            ],
            'documents' => [
                $this->cnicDoc->id => UploadedFile::fake()->create('cnic.pdf', 50, 'application/pdf'),
            ],
        ]);

        $response->assertSessionHasErrors('requested_trees.0.text');
    }

    public function test_all_free_selection_skips_challan_and_psid_and_advances_to_eligible(): void
    {
        Storage::fake('public');

        $resp = $this->post(route('schemes.apply.store', $this->treeScheme->slug), [
            'full_name' => 'Free Pine Applicant',
            'father_name' => 'Hassan',
            'cnic' => '7110199990002',
            'phone' => '03451112234',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit City',
            'land_location' => 'Jutial',
            'requested_trees' => [
                2 => ['label' => 'Wild Pine', 'checked' => '1', 'text' => '25'],
                3 => ['label' => 'Walnut 100% Subsidized', 'checked' => '1', 'text' => '10'],
            ],
            'documents' => [
                $this->cnicDoc->id => UploadedFile::fake()->create('cnic.pdf', 50, 'application/pdf'),
            ],
        ]);
        $resp->assertSessionHasNoErrors();

        $app = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7110199990002'))->firstOrFail();
        $this->assertEquals(35, $app->demanded_quantity);
        $this->assertCount(2, $app->items);

        // DD approves application
        $approveResp = $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_APPROVED,
                'approved_items' => [
                    $app->items[0]->id => 20,
                    $app->items[1]->id => 10,
                ],
                'note' => 'Approved free + 100% subsidized trees',
            ]);
        $approveResp->assertSessionHasNoErrors();

        $app->refresh();
        $this->assertSame(Application::STAGE_ELIGIBLE_BENEFICIARY, $app->current_stage);
        $this->assertNull($app->challan_no);
        $this->assertNull($app->psid);
        $this->assertNull($app->challan_amount);
        $this->assertEquals(30, (float) $app->approved_quantity);
        $this->assertSame(0, Bill::where('application_id', $app->id)->count());
        $this->assertSame(0, VoucherSerial::where('application_id', $app->id)->count());

        // Direct challan download attempt must NOT generate challan or PSID
        $dlResp = $this->get(route('applications.challan.download', $app->application_no));
        $dlResp->assertRedirect();
        $app->refresh();
        $this->assertNull($app->challan_no);
        $this->assertNull($app->psid);
        $this->assertSame(0, Bill::where('application_id', $app->id)->count());

        // Super Admin manual PSID generation must also block cleanly
        $this->actingAs($this->superAdmin)
            ->post(route('admin.applications.generate-gbpay-bill', $app))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(0, Bill::where('application_id', $app->id)->count());
    }

    public function test_mixed_paid_and_free_selection_with_dd_reduction_to_free_suppresses_challan(): void
    {
        Storage::fake('public');

        $this->post(route('schemes.apply.store', $this->treeScheme->slug), [
            'full_name' => 'Mixed Reduced Applicant',
            'father_name' => 'Karim',
            'cnic' => '7110199990003',
            'phone' => '03451112235',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit City',
            'land_location' => 'Konodass',
            'requested_trees' => [
                0 => ['label' => 'Olive', 'checked' => '1', 'text' => '10'],
                2 => ['label' => 'Wild Pine', 'checked' => '1', 'text' => '20'],
            ],
            'documents' => [
                $this->cnicDoc->id => UploadedFile::fake()->create('cnic.pdf', 50, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        $app = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7110199990003'))->firstOrFail();
        $oliveItem = $app->items()->where('item_key', 'olive')->firstOrFail();
        $pineItem = $app->items()->where('item_key', 'wild_pine')->firstOrFail();

        // DD zeros out paid Olive item and approves 15 free Wild Pine
        $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_APPROVED,
                'approved_items' => [
                    $oliveItem->id => 0,
                    $pineItem->id => 15,
                ],
            ]);

        $app->refresh();
        $this->assertSame(Application::STAGE_ELIGIBLE_BENEFICIARY, $app->current_stage);
        $this->assertNull($app->challan_no);
        $this->assertNull($app->psid);
        $this->assertEquals(15, (float) $app->approved_quantity);
        $this->assertSame(0, Bill::where('application_id', $app->id)->count());
    }

    public function test_mixed_paid_and_free_calculates_govt_subsidy_bills_only_paid_and_disburses_single_voucher(): void
    {
        Storage::fake('public');

        $this->post(route('schemes.apply.store', $this->treeScheme->slug), [
            'full_name' => 'Mixed Full Flow Farmer',
            'father_name' => 'Babar',
            'cnic' => '7110199990004',
            'phone' => '03451112236',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit City',
            'land_location' => 'Baseen',
            'requested_trees' => [
                0 => ['label' => 'Olive', 'checked' => '1', 'text' => '10'],
                1 => ['label' => 'Apple', 'checked' => '1', 'text' => '5'],
                2 => ['label' => 'Wild Pine', 'checked' => '1', 'text' => '20'],
            ],
            'documents' => [
                $this->cnicDoc->id => UploadedFile::fake()->create('cnic.pdf', 50, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        $app = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7110199990004'))->firstOrFail();
        $olive = $app->items()->where('item_key', 'olive')->firstOrFail();
        $apple = $app->items()->where('item_key', 'apple')->firstOrFail();
        $pine = $app->items()->where('item_key', 'wild_pine')->firstOrFail();

        // DD reduces Olive from 10 to 5, keeps Apple 5, reduces Wild Pine from 20 to 15
        // Expected:
        // Olive: 5 * 500 = 2500 gross, 40% govt (1000), 60% farmer (1500)
        // Apple: 5 * 200 = 1000 gross, 50% template fallback (500), 50% farmer (500)
        // Pine : 15 * 0 = 0
        // Total payable = 2000, Approved total = 25 trees
        $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_APPROVED,
                'approved_items' => [
                    $olive->id => 5,
                    $apple->id => 5,
                    $pine->id => 15,
                ],
            ]);

        $app->refresh();
        $this->assertSame(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
        $this->assertNotNull($app->challan_no);
        $this->assertNotNull($app->psid);
        $this->assertEquals(20, strlen($app->psid));
        $this->assertEquals(2000, (float) $app->challan_amount);
        $this->assertEquals(25, (float) $app->approved_quantity);

        $bill = Bill::where('application_id', $app->id)->firstOrFail();
        $this->assertEquals(2000, (float) $bill->bill_amount);

        // QuotaService counts all 25 approved trees
        $this->assertSame(25, app(QuotaService::class)->usedSlots($this->treeScheme->id, $this->gilgit->id));

        // Simulate Accounts Officer payment verification
        $bill->update(['status' => 'paid', 'paid_at' => now()]);
        $app->update([
            'current_stage' => Application::STAGE_PAYMENT_VERIFIED,
            'payment_verified_at' => now(),
            'challan_paid_at' => now(),
        ]);

        // Set up stock depot & stock items matching the tree types
        $center = CollectionCenter::create([
            'name' => 'Gilgit Nursery Center',
            'code' => 'NUR-GLT-01',
            'level' => 'district',
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);

        $oliveStock = SchemeStockItem::create([
            'scheme_id' => $this->treeScheme->id,
            'item_name' => 'Olive',
            'item_code' => 'olive',
            'unit' => 'Trees',
            'is_active' => true,
        ]);
        $appleStock = SchemeStockItem::create([
            'scheme_id' => $this->treeScheme->id,
            'item_name' => 'Apple',
            'item_code' => 'apple',
            'unit' => 'Trees',
            'is_active' => true,
        ]);
        $pineStock = SchemeStockItem::create([
            'scheme_id' => $this->treeScheme->id,
            'item_name' => 'Wild Pine',
            'item_code' => 'wild_pine',
            'unit' => 'Trees',
            'is_active' => true,
        ]);

        $stockService = app(StockManagementService::class);
        // Stock receipts: intentionally keep Apple short (2 < 5 needed) to verify atomic block
        $stockService->recordStockReceipt(['collection_center_id' => $center->id, 'scheme_stock_item_id' => $oliveStock->id, 'quantity_received' => 50], $this->distOfficerGilgit);
        $stockService->recordStockReceipt(['collection_center_id' => $center->id, 'scheme_stock_item_id' => $appleStock->id, 'quantity_received' => 2], $this->distOfficerGilgit);
        $stockService->recordStockReceipt(['collection_center_id' => $center->id, 'scheme_stock_item_id' => $pineStock->id, 'quantity_received' => 50], $this->distOfficerGilgit);

        $disbPayload = [
            'application_id' => $app->id,
            'collection_center_id' => $center->id,
            'recipient_name' => 'Mixed Full Flow Farmer',
            'recipient_cnic' => '7110199990004',
            'disbursement_date' => now()->toDateString(),
            'batch_items' => [
                ['application_item_id' => $olive->id, 'scheme_stock_item_id' => $oliveStock->id],
                ['application_item_id' => $apple->id, 'scheme_stock_item_id' => $appleStock->id],
                ['application_item_id' => $pine->id, 'scheme_stock_item_id' => $pineStock->id],
            ],
        ];

        // Expect failure because Apple only has 2 vs 5 needed (must NOT partially debit Olive!)
        try {
            $stockService->disburseStock($disbPayload, $this->distOfficerGilgit);
            $this->fail('Expected ValidationException due to short Apple stock.');
        } catch (ValidationException $e) {
            $this->assertEquals(50, (float) $center->getInventoryForItem($oliveStock->id)->current_balance);
        }

        // Top up Apple receipt, then disburse all approved species in single unified voucher
        $stockService->recordStockReceipt(['collection_center_id' => $center->id, 'scheme_stock_item_id' => $appleStock->id, 'quantity_received' => 20], $this->distOfficerGilgit);

        $this->actingAs($this->distOfficerGilgit)
            ->post(route('admin.stock.disbursements.store'), $disbPayload)
            ->assertRedirect();

        $app->refresh();
        $this->assertSame(Application::STAGE_FULLY_DISTRIBUTED, $app->current_stage);
        $this->assertEquals(25, (float) $app->distributed_quantity);

        // Verify per-item inventories and recalculateCenterInventory consistency
        $this->assertEquals(45, (float) $stockService->recalculateCenterInventory($center->id, $oliveStock->id)->current_balance);
        $this->assertEquals(17, (float) $stockService->recalculateCenterInventory($center->id, $appleStock->id)->current_balance);
        $this->assertEquals(35, (float) $stockService->recalculateCenterInventory($center->id, $pineStock->id)->current_balance);

        $firstVoucher = StockDisbursement::where('application_id', $app->id)->firstOrFail();
        $this->assertNotNull($firstVoucher->batch_voucher_no);
        $this->actingAs($this->distOfficerGilgit)
            ->get(route('admin.stock.disbursements.voucher', $firstVoucher))
            ->assertOk()
            ->assertSee('Olive')
            ->assertSee('Apple')
            ->assertSee('Wild Pine');
    }

    public function test_auto_generated_scheme_dashboard_scopes_by_officer_role_and_district(): void
    {
        // Seed one Gilgit application in treeScheme
        Storage::fake('public');
        $this->post(route('schemes.apply.store', $this->treeScheme->slug), [
            'full_name' => 'Dashboard Farmer',
            'father_name' => 'Rahim',
            'cnic' => '7110199990005',
            'phone' => '03451112237',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit City',
            'land_location' => 'Sonikot',
            'requested_trees' => [
                0 => ['label' => 'Olive', 'checked' => '1', 'text' => '12'],
                2 => ['label' => 'Wild Pine', 'checked' => '1', 'text' => '8'],
            ],
            'documents' => [
                $this->cnicDoc->id => UploadedFile::fake()->create('cnic.pdf', 50, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        // Gilgit DD sees treeScheme automatically on /admin home AND can open its auto-generated dashboard
        $this->actingAs($this->ddGilgit)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Fruit & Forest Plantation Scheme')
            ->assertSee('Open Scheme MIS Dashboard');

        $this->actingAs($this->ddGilgit)
            ->get(route('admin.scheme-dashboards.show', $this->treeScheme))
            ->assertOk()
            ->assertSee('Olive')
            ->assertSee('Wild Pine')
            ->assertSee('12 Paid / 8 Free');

        // AJAX pagination header returns clean HTML without breaking
        $this->actingAs($this->ddGilgit)
            ->withHeader('X-Alf-Ajax-Pagination', '1')
            ->get(route('admin.scheme-dashboards.show', $this->treeScheme))
            ->assertOk();

        // JSON analytics endpoint works
        $this->actingAs($this->ddGilgit)
            ->get(route('admin.scheme-dashboards.analytics', $this->treeScheme))
            ->assertOk()
            ->assertJsonPath('ok', true);

        // Regression test: GIS markers query valid columns (gps_points / gps_coordinates / evidence_data), never gps_points_json
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('application_surveys', 'gps_points_json'));
        $seededApp = Application::where('scheme_id', $this->treeScheme->id)->firstOrFail();
        \App\Models\ApplicationSurvey::create([
            'application_id' => $seededApp->id,
            'assigned_to' => $this->fieldOfficerGilgit->id,
            'assigned_by' => $this->ddGilgit->id,
            'status' => \App\Models\ApplicationSurvey::STATUS_FINDINGS_SUBMITTED,
            'gps_points' => [['point_number' => 1, 'lat' => 35.9208, 'lng' => 74.3089, 'label' => 'Orchard Center']],
        ]);

        $this->actingAs($this->ddGilgit)
            ->get(route('admin.scheme-dashboards.analytics', $this->treeScheme))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data.gis_markers')
            ->assertJsonPath('data.gis_markers.0.application_no', $seededApp->application_no);

        // Skardu DD is not assigned to Gilgit-only district scheme -> 403
        $this->actingAs($this->ddSkardu)
            ->get(route('admin.scheme-dashboards.show', $this->treeScheme))
            ->assertForbidden();

        // Director Agriculture can see Agriculture scheme, forbidden from Livestock scheme
        $this->actingAs($this->directorAgri)
            ->get(route('admin.scheme-dashboards.show', $this->treeScheme))
            ->assertOk();
        $this->actingAs($this->directorAgri)
            ->get(route('admin.scheme-dashboards.show', $this->livestockScheme))
            ->assertForbidden();

        // Super Admin and Secretary see both
        $this->actingAs($this->superAdmin)
            ->get(route('admin.scheme-dashboards.show', $this->livestockScheme))
            ->assertOk();
        $this->actingAs($this->secretary)
            ->get(route('admin.scheme-dashboards.show', $this->treeScheme))
            ->assertOk();

        // Operational queues (Field Officer, Accounts Officer, Distribution Officer) do NOT see auto dashboards
        $this->actingAs($this->fieldOfficerGilgit)
            ->get(route('admin.scheme-dashboards.show', $this->treeScheme))
            ->assertForbidden();
        $this->actingAs($this->accountsOfficerGilgit)
            ->get(route('admin.scheme-dashboards.show', $this->treeScheme))
            ->assertForbidden();
        $this->actingAs($this->distOfficerGilgit)
            ->get(route('admin.scheme-dashboards.show', $this->treeScheme))
            ->assertForbidden();
    }
}
