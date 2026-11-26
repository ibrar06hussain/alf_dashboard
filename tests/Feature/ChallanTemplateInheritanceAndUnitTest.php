<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Bill;
use App\Models\ChallanTemplate;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormDocument;
use App\Models\FormField;
use App\Models\Office;
use App\Models\PsidConfig;
use App\Models\Scheme;
use App\Models\SchemeDistrict;
use App\Models\User;
use App\Models\UserOfficeAssignment;
use App\Models\VoucherSerial;
use App\Services\GbpayService;
use App\Support\CoreFormFields;
use App\Support\QrCodeGenerator;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChallanTemplateInheritanceAndUnitTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $ddGilgit;
    private Department $agriDept;
    private District $gilgit;
    private ChallanTemplate $templateZeroGovt;
    private ChallanTemplate $templateFiftyFifty;
    private Scheme $treeScheme;
    private Form $treeForm;
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

        $this->gilgit = District::firstOrCreate(
            ['code' => 'GLT'],
            ['name' => 'Gilgit', 'code' => 'GLT', 'is_active' => true]
        );

        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole('Super Admin');

        $this->ddGilgit = User::factory()->create([
            'department_id' => $this->agriDept->id,
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);
        $this->ddGilgit->assignRole('District Deputy Director');

        $ddOffice = Office::create([
            'name' => 'DD Agri Gilgit Office',
            'office_type' => Office::TYPE_DISTRICT_DEPUTY_DIRECTORATE,
            'department_id' => $this->agriDept->id,
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);

        UserOfficeAssignment::create([
            'user_id' => $this->ddGilgit->id,
            'office_id' => $ddOffice->id,
            'department_id' => $this->agriDept->id,
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);

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

        $this->templateFiftyFifty = ChallanTemplate::create([
            'name' => 'Fifty Fifty Template',
            'department_name' => 'Agriculture Department GB',
            'sub_title' => 'Government of Gilgit-Baltistan',
            'challan_title' => 'FEE DEPOSIT CHALLAN',
            'bank_name' => 'National Bank of Pakistan',
            'account_title' => 'ALF GB',
            'account_no' => '4256260305',
            'account_head' => 'C03117 - Plants',
            'validity_days' => 30,
            'directions' => 'Valid for 30 days.',
            'fee_mode' => 'fixed_unit',
            'govt_share_pct' => 50.00,
            'farmer_share_pct' => 50.00,
            'show_breakdown' => true,
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->templateZeroGovt = ChallanTemplate::create([
            'name' => 'Full Farmer Share Template',
            'department_name' => 'Agriculture Department GB',
            'sub_title' => 'Government of Gilgit-Baltistan',
            'challan_title' => 'FEE DEPOSIT CHALLAN',
            'bank_name' => 'National Bank of Pakistan',
            'account_title' => 'ALF GB',
            'account_no' => '4256260305',
            'account_head' => 'C03117 - Plants',
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
            'title' => 'Green Pakistan Orchard Scheme',
            'slug' => 'green-pakistan-orchard',
            'type' => 'running',
            'scope' => 'district',
            'status' => 'active',
            'requires_challan' => true,
            'challan_fee_per_unit' => 0,
            'challan_template_id' => $this->templateZeroGovt->id,
            'unit_label' => null,
            'has_auto_dashboard' => true,
            'start_date' => now()->subDay(),
            'last_date' => now()->addMonth(),
        ]);

        SchemeDistrict::create([
            'scheme_id' => $this->treeScheme->id,
            'district_id' => $this->gilgit->id,
            'quota' => 500,
        ]);

        $this->treeForm = Form::create([
            'scheme_id' => $this->treeScheme->id,
            'title' => 'Green Pakistan Application Form',
            'version' => 1,
            'is_published' => true,
        ]);

        foreach (CoreFormFields::definitions() as $def) {
            FormField::create(array_merge($def, ['form_id' => $this->treeForm->id]));
        }

        $this->treeField = FormField::create([
            'form_id' => $this->treeForm->id,
            'field_key' => 'requested_trees',
            'field_label' => 'Select Plants & Quantities',
            'field_label_ur' => 'پودوں کی اقسام',
            'field_type' => 'checkbox_with_text',
            'is_mandatory' => true,
            'is_billable_items_field' => true,
            'options_json' => ['Cherry', 'Walnut', 'Apple'],
            'option_configs_json' => [
                [
                    'key' => 'cherry',
                    'label' => 'Cherry',
                    'label_ur' => 'چیری',
                    'pricing_mode' => 'paid',
                    'unit_price' => 300,
                    'govt_share_pct' => null, // Blank -> dynamically inherits template
                    'input_mode' => 'numeric_qty',
                    'unit_label' => 'Trees',
                ],
                [
                    'key' => 'walnut',
                    'label' => 'Walnut',
                    'label_ur' => 'اخروٹ',
                    'pricing_mode' => 'paid',
                    'unit_price' => 500,
                    'govt_share_pct' => 40, // Explicit item-level override
                    'input_mode' => 'numeric_qty',
                    'unit_label' => 'Trees',
                ],
                [
                    'key' => 'apple',
                    'label' => 'Apple',
                    'label_ur' => 'سیب',
                    'pricing_mode' => 'free',
                    'unit_price' => 0,
                    'govt_share_pct' => null,
                    'input_mode' => 'numeric_qty',
                    'unit_label' => 'Trees',
                ],
            ],
            'display_order' => 20,
        ]);

        $this->cnicDoc = FormDocument::create([
            'form_id' => $this->treeForm->id,
            'document_name' => 'CNIC Copy',
            'is_mandatory' => true,
            'allowed_extensions' => 'pdf,jpg,png',
            'max_size_mb' => 5,
            'display_order' => 1,
        ]);
    }

    public function test_blank_form_builder_share_inherits_challan_template_and_explicit_share_overrides_it(): void
    {
        Storage::fake('public');

        $this->post(route('schemes.apply.store', $this->treeScheme->slug), [
            'full_name' => 'Ibrar Hussain',
            'father_name' => 'Ali',
            'cnic' => '7150112690591',
            'phone' => '03235402052',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'land_location' => 'Danyore',
            'requested_trees' => [
                0 => ['label' => 'Cherry', 'checked' => '1', 'text' => '25'],
                1 => ['label' => 'Walnut', 'checked' => '1', 'text' => '10'],
                2 => ['label' => 'Apple', 'checked' => '1', 'text' => '50'],
            ],
            'documents' => [
                $this->cnicDoc->id => UploadedFile::fake()->create('cnic.pdf', 50, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        $app = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7150112690591'))->firstOrFail();

        $cherry = $app->items()->where('item_key', 'cherry')->firstOrFail();
        $walnut = $app->items()->where('item_key', 'walnut')->firstOrFail();
        $apple = $app->items()->where('item_key', 'apple')->firstOrFail();

        $this->assertTrue($cherry->inherits_template_share);
        $this->assertFalse($walnut->inherits_template_share);
        $this->assertFalse($apple->inherits_template_share);

        $this->actingAs($this->ddGilgit)->post(route('admin.applications.status', $app), [
            'status' => Application::STAGE_APPROVED,
            'approved_items' => [
                $cherry->id => 25,
                $walnut->id => 10,
                $apple->id => 50,
            ],
        ])->assertSessionHasNoErrors();

        $app->refresh();
        $cherry->refresh();
        $walnut->refresh();

        // Cherry inherited 0% Govt / 100% Farmer => 25 * 300 = Rs. 7,500
        $this->assertEquals(0.00, (float) $cherry->govt_share_pct);
        $this->assertEquals(100.00, (float) $cherry->farmer_share_pct);
        $this->assertEquals(7500.00, (float) $cherry->farmer_payable_amount);

        // Walnut explicit override 40% Govt / 60% Farmer => 10 * 500 * 60% = Rs. 3,000
        $this->assertEquals(40.00, (float) $walnut->govt_share_pct);
        $this->assertEquals(60.00, (float) $walnut->farmer_share_pct);
        $this->assertEquals(3000.00, (float) $walnut->farmer_payable_amount);

        // Total bill = 7,500 + 3,000 = 10,500
        $this->assertSame(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
        $this->assertEquals(10500.00, (float) $app->challan_amount);

        $bill = Bill::where('application_id', $app->id)->firstOrFail();
        $this->assertEquals(10500.00, (float) $bill->bill_amount);
    }

    public function test_updating_challan_template_auto_syncs_unpaid_bill_preserving_psid_and_leaves_paid_frozen(): void
    {
        Storage::fake('public');

        // Assign 50/50 template before submissions
        $this->treeScheme->update(['challan_template_id' => $this->templateFiftyFifty->id]);

        // Unpaid Applicant (#1)
        $this->post(route('schemes.apply.store', $this->treeScheme->slug), [
            'full_name' => 'Unpaid Applicant',
            'father_name' => 'Khan',
            'cnic' => '7150111111111',
            'phone' => '03451111111',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'land_location' => 'Basin',
            'requested_trees' => [
                0 => ['label' => 'Cherry', 'checked' => '1', 'text' => '25'],
            ],
            'documents' => [
                $this->cnicDoc->id => UploadedFile::fake()->create('cnic.pdf', 50, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        // Paid Applicant (#2)
        $this->post(route('schemes.apply.store', $this->treeScheme->slug), [
            'full_name' => 'Paid Applicant',
            'father_name' => 'Baig',
            'cnic' => '7150122222222',
            'phone' => '03452222222',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'land_location' => 'Jutial',
            'requested_trees' => [
                0 => ['label' => 'Cherry', 'checked' => '1', 'text' => '25'],
            ],
            'documents' => [
                $this->cnicDoc->id => UploadedFile::fake()->create('cnic.pdf', 50, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        $unpaidApp = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7150111111111'))->firstOrFail();
        $paidApp = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7150122222222'))->firstOrFail();

        foreach ([$unpaidApp, $paidApp] as $targetApp) {
            $cherry = $targetApp->items()->where('item_key', 'cherry')->firstOrFail();
            $this->actingAs($this->ddGilgit)->post(route('admin.applications.status', $targetApp), [
                'status' => Application::STAGE_APPROVED,
                'approved_items' => [$cherry->id => 25],
            ])->assertSessionHasNoErrors();
        }

        $unpaidApp->refresh();
        $paidApp->refresh();
        $this->assertEquals(3750.00, (float) $unpaidApp->challan_amount);
        $this->assertEquals(3750.00, (float) $paidApp->challan_amount);

        $originalUnpaidBill = Bill::where('application_id', $unpaidApp->id)->firstOrFail();
        $originalPsid = $originalUnpaidBill->psid;
        $this->assertSame(20, strlen($originalPsid));

        // Mark second application as PAID
        $paidBill = Bill::where('application_id', $paidApp->id)->firstOrFail();
        $paidBill->update(['status' => 'paid', 'paid_at' => now()]);
        $paidApp->update([
            'current_stage' => Application::STAGE_PAYMENT_VERIFIED,
            'challan_paid_at' => now(),
            'payment_verified_at' => now(),
        ]);

        // Super Admin updates templateFiftyFifty from 50/50 to 0% Govt / 100% Farmer
        $this->actingAs($this->superAdmin)
            ->put(route('admin.challan-templates.update', $this->templateFiftyFifty), [
                'name' => $this->templateFiftyFifty->name,
                'department_name' => $this->templateFiftyFifty->department_name,
                'sub_title' => $this->templateFiftyFifty->sub_title,
                'challan_title' => $this->templateFiftyFifty->challan_title,
                'bank_name' => $this->templateFiftyFifty->bank_name,
                'account_title' => $this->templateFiftyFifty->account_title,
                'account_no' => $this->templateFiftyFifty->account_no,
                'account_head' => $this->templateFiftyFifty->account_head,
                'validity_days' => 30,
                'directions' => $this->templateFiftyFifty->directions,
                'fee_mode' => 'fixed_unit',
                'govt_share_pct' => 0,
                'farmer_share_pct' => 100,
                'show_breakdown' => '1',
                'is_active' => '1',
                'is_default' => '1',
            ])
            ->assertRedirect(route('admin.challan-templates.index'));

        // Unpaid application synced to Rs. 7,500 keeping EXACT same Bill ID and PSID
        $unpaidApp->refresh();
        $originalUnpaidBill->refresh();
        $unpaidCherry = $unpaidApp->items()->where('item_key', 'cherry')->firstOrFail();

        $this->assertEquals(7500.00, (float) $unpaidApp->challan_amount);
        $this->assertEquals(0.00, (float) $unpaidCherry->govt_share_pct);
        $this->assertEquals(100.00, (float) $unpaidCherry->farmer_share_pct);
        $this->assertEquals(7500.00, (float) $unpaidCherry->farmer_payable_amount);
        $this->assertSame($originalPsid, $originalUnpaidBill->psid);
        $this->assertSame('pending', $originalUnpaidBill->status);
        $this->assertEquals(7500.00, (float) $originalUnpaidBill->bill_amount);

        // Paid application remains frozen at Rs. 3,750 (50% Govt / 50% Farmer)
        $paidApp->refresh();
        $paidBill->refresh();
        $paidCherry = $paidApp->items()->where('item_key', 'cherry')->firstOrFail();
        $paidBreakdown = $paidApp->getChallanBreakdown();

        $this->assertEquals(3750.00, (float) $paidApp->challan_amount);
        $this->assertEquals(3750.00, (float) $paidBill->bill_amount);
        $this->assertEquals(50.00, (float) $paidCherry->govt_share_pct);
        $this->assertEquals(50.00, (float) $paidCherry->farmer_share_pct);
        $this->assertEquals(3750.00, (float) $paidBreakdown['final_bill']);
    }

    public function test_updating_challan_template_to_100_pct_govt_cancels_unpaid_psid_and_promotes_to_eligible(): void
    {
        Storage::fake('public');

        $this->post(route('schemes.apply.store', $this->treeScheme->slug), [
            'full_name' => 'Free After Edit Applicant',
            'father_name' => 'Raza',
            'cnic' => '7150133333333',
            'phone' => '03453333333',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'land_location' => 'Sonikot',
            'requested_trees' => [
                0 => ['label' => 'Cherry', 'checked' => '1', 'text' => '25'],
            ],
            'documents' => [
                $this->cnicDoc->id => UploadedFile::fake()->create('cnic.pdf', 50, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        $app = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7150133333333'))->firstOrFail();
        $cherry = $app->items()->where('item_key', 'cherry')->firstOrFail();

        $this->actingAs($this->ddGilgit)->post(route('admin.applications.status', $app), [
            'status' => Application::STAGE_APPROVED,
            'approved_items' => [$cherry->id => 25],
        ])->assertSessionHasNoErrors();

        $app->refresh();
        $this->assertSame(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
        $this->assertEquals(7500.00, (float) $app->challan_amount);
        $bill = Bill::where('application_id', $app->id)->firstOrFail();
        $this->assertSame('pending', $bill->status);

        // Super Admin changes templateZeroGovt to 100% Govt share / 0% Farmer share
        $this->actingAs($this->superAdmin)
            ->put(route('admin.challan-templates.update', $this->templateZeroGovt), [
                'name' => $this->templateZeroGovt->name,
                'department_name' => $this->templateZeroGovt->department_name,
                'sub_title' => $this->templateZeroGovt->sub_title,
                'challan_title' => $this->templateZeroGovt->challan_title,
                'bank_name' => $this->templateZeroGovt->bank_name,
                'account_title' => $this->templateZeroGovt->account_title,
                'account_no' => $this->templateZeroGovt->account_no,
                'account_head' => $this->templateZeroGovt->account_head,
                'validity_days' => 30,
                'directions' => $this->templateZeroGovt->directions,
                'fee_mode' => 'fixed_unit',
                'govt_share_pct' => 100,
                'farmer_share_pct' => 0,
                'show_breakdown' => '1',
                'is_active' => '1',
                'schemes_sync' => '1',
                'schemes' => [$this->treeScheme->id],
            ])
            ->assertRedirect(route('admin.challan-templates.index'));

        $app->refresh();
        $bill->refresh();
        $cherry->refresh();

        $this->assertSame(Application::STAGE_ELIGIBLE_BENEFICIARY, $app->current_stage);
        $this->assertNull($app->challan_no);
        $this->assertNull($app->psid);
        $this->assertNull($app->challan_amount);
        $this->assertSame('cancelled', $bill->status);
        $this->assertEquals(0.00, (float) $cherry->farmer_payable_amount);
    }

    public function test_reassigning_scheme_template_and_unit_label_syncs_unpaid_app_and_renders_dynamic_units(): void
    {
        Storage::fake('public');

        // Start on 50/50 template and nullify Cherry item unit_label so Scheme.unit_label drives the UI
        $this->treeScheme->update([
            'challan_template_id' => $this->templateFiftyFifty->id,
            'unit_label' => 'Trees',
        ]);
        $configs = $this->treeField->option_configs_json;
        $configs[0]['unit_label'] = null;
        $this->treeField->update(['option_configs_json' => $configs]);

        $this->post(route('schemes.apply.store', $this->treeScheme->slug), [
            'full_name' => 'Reassign Test Farmer',
            'father_name' => 'Mir',
            'cnic' => '7150144444444',
            'phone' => '03454444444',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'land_location' => 'Nomal',
            'requested_trees' => [
                0 => ['label' => 'Cherry', 'checked' => '1', 'text' => '25'],
            ],
            'documents' => [
                $this->cnicDoc->id => UploadedFile::fake()->create('cnic.pdf', 50, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        $app = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7150144444444'))->firstOrFail();
        $cherry = $app->items()->where('item_key', 'cherry')->firstOrFail();

        // Exceeding district quota uses scheme unit label ("Trees") instead of hardcoded "Bags"
        $quotaErr = $this->actingAs($this->ddGilgit)->post(route('admin.applications.status', $app), [
            'status' => Application::STAGE_APPROVED,
            'approved_items' => [$cherry->id => 600],
        ]);
        $quotaErr->assertSessionHas('error');
        $errMessage = (string) $quotaErr->getSession()->get('error');
        $this->assertStringContainsStringIgnoringCase('trees', $errMessage);
        $this->assertStringNotContainsStringIgnoringCase('Bags', $errMessage);

        // Approve 25 Cherry on 50/50 template => Rs. 3,750
        $this->actingAs($this->ddGilgit)->post(route('admin.applications.status', $app), [
            'status' => Application::STAGE_APPROVED,
            'approved_items' => [$cherry->id => 25],
        ])->assertSessionHasNoErrors();

        $app->refresh();
        $this->assertEquals(3750.00, (float) $app->challan_amount);
        $originalPsid = $app->psid;

        // Reassign scheme challan_template_id to 0% Govt / 100% Farmer and set unit_label = 'Trees'
        $this->actingAs($this->superAdmin)->put(route('admin.schemes.update', $this->treeScheme), [
            'department_id' => $this->agriDept->id,
            'title' => $this->treeScheme->title,
            'type' => 'running',
            'scope' => 'district',
            'status' => 'active',
            'requires_challan' => '1',
            'challan_template_id' => $this->templateZeroGovt->id,
            'unit_label' => 'Trees',
            'district_quotas' => [$this->gilgit->id => 500],
        ])->assertRedirect(route('admin.schemes.index'));

        $app->refresh();
        $bill = Bill::where('application_id', $app->id)->firstOrFail();
        $this->assertSame($originalPsid, $bill->psid);
        $this->assertEquals(7500.00, (float) $app->challan_amount);
        $this->assertEquals(7500.00, (float) $bill->bill_amount);
        $this->assertSame('Trees', $app->resolveUnitLabel());

        // Check rendered Admin Show page
        $adminShow = $this->actingAs($this->superAdmin)->get(route('admin.applications.show', $app));
        $adminShow->assertStatus(200);
        $adminShow->assertSee('7,500');
        $adminShow->assertSee('Trees');
        $adminShow->assertSee('0%');
        $adminShow->assertSee('100%');
        $this->assertStringNotContainsStringIgnoringCase('Bags', strip_tags($adminShow->getContent()));

        // Check rendered Admin Scheme Show page (District Quota unit)
        $schemeShow = $this->actingAs($this->superAdmin)->get(route('admin.schemes.show', $this->treeScheme));
        $schemeShow->assertStatus(200);
        $schemeShow->assertSee('Trees');
        $this->assertStringNotContainsStringIgnoringCase('Bags', strip_tags($schemeShow->getContent()));

        // Check Public Track page
        $trackResp = $this->post(route('applications.track'), [
            'application_no' => $app->application_no,
            'cnic' => '7150144444444',
        ]);
        $trackResp->assertStatus(200);
        $trackResp->assertSee('7,500');
        $trackResp->assertSee('Trees');
        $trackResp->assertSee('0%');
        $trackResp->assertSee('100%');
        $this->assertStringNotContainsStringIgnoringCase('Bags', strip_tags($trackResp->getContent()));

        // Check Challan PDF HTTP download & rendered PDF HTML
        $dlResp = $this->get(route('applications.challan.download', $app->application_no));
        $dlResp->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $dlResp->headers->get('Content-Type'));

        $app->load(['scheme.challanTemplate', 'scheme.stockItems', 'applicant', 'district', 'items']);
        $breakdown = $app->getChallanBreakdown();
        $pdfHtml = view('public.applications.challan-pdf', [
            'application' => $app,
            'bill' => $bill,
            'psidConfig' => PsidConfig::getActive($app->scheme_id),
            'qrCodeBase64' => QrCodeGenerator::toBase64Png($bill->psid, 3, 2),
            'scheme' => $app->scheme,
            'applicant' => $app->applicant,
            'district' => $app->district,
            'template' => $breakdown['template'],
            'breakdown' => $breakdown,
        ])->render();

        $this->assertStringContainsString('7,500', $pdfHtml);
        $this->assertStringContainsString($originalPsid, $pdfHtml);
        $this->assertStringContainsString('Trees', $pdfHtml);
        $this->assertStringContainsString('0%', $pdfHtml);
        $this->assertStringContainsString('100%', $pdfHtml);
        $this->assertStringNotContainsStringIgnoringCase('Bags', strip_tags($pdfHtml));
    }

    public function test_scheme_unit_label_animals_renders_across_views_and_updating_template_without_schemes_flag_preserves_links(): void
    {
        $livestockTpl = ChallanTemplate::create([
            'name' => 'Livestock Unit Template',
            'department_name' => 'Livestock Department GB',
            'challan_title' => 'FEE DEPOSIT CHALLAN',
            'bank_name' => 'National Bank of Pakistan',
            'account_title' => 'Livestock GB',
            'account_no' => '9988776655',
            'account_head' => 'C03223',
            'validity_days' => 15,
            'directions' => 'Valid for 15 days.',
            'fee_mode' => 'fixed_unit',
            'govt_share_pct' => 20.00,
            'farmer_share_pct' => 80.00,
            'show_breakdown' => true,
            'is_active' => true,
            'is_default' => false,
        ]);

        $animalScheme = Scheme::create([
            'department_id' => $this->agriDept->id,
            'title' => 'Yak & Cattle Distribution',
            'slug' => 'yak-cattle-distribution',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => true,
            'challan_fee_per_unit' => 5000,
            'challan_template_id' => $livestockTpl->id,
            'unit_label' => 'Animals',
            'start_date' => now()->subDay(),
            'last_date' => now()->addMonth(),
        ]);

        // Updating template WITHOUT schemes_sync or schemes preserves animalScheme link
        $this->actingAs($this->superAdmin)
            ->put(route('admin.challan-templates.update', $livestockTpl), [
                'name' => $livestockTpl->name,
                'department_name' => $livestockTpl->department_name,
                'challan_title' => $livestockTpl->challan_title,
                'bank_name' => $livestockTpl->bank_name,
                'account_title' => $livestockTpl->account_title,
                'account_no' => $livestockTpl->account_no,
                'account_head' => $livestockTpl->account_head,
                'validity_days' => 15,
                'directions' => $livestockTpl->directions,
                'fee_mode' => 'fixed_unit',
                'govt_share_pct' => 25,
                'farmer_share_pct' => 75,
                'show_breakdown' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.challan-templates.index'));

        $animalScheme->refresh();
        $this->assertSame($livestockTpl->id, $animalScheme->challan_template_id);
        $this->assertSame('Animals', $animalScheme->resolveUnitLabel());

        $applicant = \App\Models\Applicant::create([
            'full_name' => 'Livestock Applicant',
            'father_name' => 'Ullah',
            'cnic' => '7150155555555',
            'phone' => '03455555555',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'land_location' => 'Minawar',
        ]);

        $app = Application::create([
            'application_no' => 'ALF-ANIMALS-001',
            'scheme_id' => $animalScheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => $this->agriDept->id,
            'status' => Application::STATUS_DIRECTORATE_APPROVED,
            'current_stage' => Application::STAGE_CHALLAN_ISSUED,
            'demanded_quantity' => 2,
            'approved_quantity' => 2,
            'challan_no' => 'CHL-2026-99001',
            'challan_amount' => 7500,
            'challan_issued_at' => now(),
            'challan_due_date' => now()->addDays(15)->toDateString(),
            'submitted_at' => now(),
        ]);

        $bill = app(GbpayService::class)->createOrUpdateBillForApplication($app, 7500);
        $app->refresh()->load(['scheme.challanTemplate', 'scheme.stockItems', 'applicant', 'district', 'items']);

        $showResp = $this->actingAs($this->superAdmin)->get(route('admin.applications.show', $app));
        $showResp->assertStatus(200);
        $showResp->assertSee('Animals');
        $showResp->assertSee('25%');
        $showResp->assertSee('75%');
        $showResp->assertSee('7,500');
        $this->assertStringNotContainsStringIgnoringCase('Bags', strip_tags($showResp->getContent()));

        $breakdown = $app->getChallanBreakdown();
        $pdfHtml = view('public.applications.challan-pdf', [
            'application' => $app,
            'bill' => $bill,
            'psidConfig' => PsidConfig::getActive($app->scheme_id),
            'qrCodeBase64' => QrCodeGenerator::toBase64Png($bill->psid, 3, 2),
            'scheme' => $app->scheme,
            'applicant' => $app->applicant,
            'district' => $app->district,
            'template' => $breakdown['template'],
            'breakdown' => $breakdown,
        ])->render();

        $this->assertStringContainsString('Animals', $pdfHtml);
        $this->assertStringContainsString('25%', $pdfHtml);
        $this->assertStringContainsString('75%', $pdfHtml);
        $this->assertStringContainsString('7,500', $pdfHtml);
        $this->assertStringNotContainsStringIgnoringCase('Bags', strip_tags($pdfHtml));
    }
}
