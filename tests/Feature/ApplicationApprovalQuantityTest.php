<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\CollectionCenter;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SchemeStockItem;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ApplicationLifecycleService;
use App\Services\StockManagementService;
use App\Services\WheatSeedMisService;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationApprovalQuantityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ddGilgit;
    protected User $distOfficer;
    protected District $gilgit;
    protected Scheme $scheme;
    protected SchemeStockItem $stockItem;
    protected CollectionCenter $center;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->gilgit = District::firstOrCreate(['id' => 1], ['name' => 'Gilgit', 'code' => 'GLT']);

        $this->scheme = Scheme::firstOrCreate(
            ['id' => 1],
            [
                'title' => 'Wheat Seed Distribution Scheme',
                'slug' => 'wheat-seed-test',
                'status' => 'active',
                'department_id' => 1,
                'requires_challan' => true,
                'challan_fee_per_unit' => 3000.00,
            ]
        );

        $this->stockItem = SchemeStockItem::create([
            'scheme_id' => $this->scheme->id,
            'item_name' => 'Certified Wheat Seed Bag (50kg)',
            'unit' => 'Bags',
            'is_active' => true,
        ]);

        $this->ddGilgit = User::firstOrCreate(
            ['email' => 'dd.gilgit.qty.test@alf.gb.gov.pk'],
            [
                'name' => 'Deputy Director Gilgit',
                'password' => bcrypt('password123'),
                'district_id' => $this->gilgit->id,
                'department_id' => 1,
                'is_active' => true,
            ]
        );
        $this->ddGilgit->syncRoles(['District Deputy Director']);

        $this->distOfficer = User::firstOrCreate(
            ['email' => 'dist.gilgit.qty.test@alf.gb.gov.pk'],
            [
                'name' => 'Distribution Officer Gilgit',
                'password' => bcrypt('password123'),
                'district_id' => $this->gilgit->id,
                'department_id' => 1,
                'is_active' => true,
            ]
        );
        $this->distOfficer->syncRoles(['Distribution Officer']);

        $this->center = CollectionCenter::create([
            'district_id' => $this->gilgit->id,
            'name' => 'Gilgit Central Agriculture Depot',
            'code' => 'GLT-DEP-01',
            'level' => 'district',
            'is_active' => true,
        ]);

        // Seed 100 bags stock
        $inventory = $this->center->getInventoryForItem($this->stockItem->id);
        $inventory->quantity_received = 100;
        $inventory->recalculate();
    }

    protected function createTestApplication(float $demandedBags = 5.0, string $stage = Application::STAGE_UNDER_REVIEW): Application
    {
        $applicant = Applicant::create([
            'full_name' => 'Karim Shah',
            'father_name' => 'Shah Karim',
            'cnic' => '71501-1111111-1',
            'phone' => '03450000001',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'village' => 'Danyore',
        ]);

        return Application::create([
            'application_no' => 'APP-QTY-' . uniqid(),
            'applicant_id' => $applicant->id,
            'scheme_id' => $this->scheme->id,
            'district_id' => $this->gilgit->id,
            'department_id' => 1,
            'current_stage' => $stage,
            'status' => 'pending',
            'demanded_quantity' => $demandedBags,
            'assigned_to' => $this->ddGilgit->id,
            'submitted_at' => now(),
            'lock_version' => 1,
        ]);
    }

    public function test_submission_initializes_demanded_quantity(): void
    {
        $app = $this->createTestApplication(4.0, Application::STAGE_SUBMITTED);
        $lifecycle = app(ApplicationLifecycleService::class);
        $app = $lifecycle->onSubmission($app);

        $this->assertEquals(4.0, (float) $app->demanded_quantity);
        $this->assertEquals(4.0, $app->getDemandedQuantity());
    }

    public function test_officer_approves_application_with_adjusted_approved_quantity(): void
    {
        $app = $this->createTestApplication(5.0, Application::STAGE_UNDER_REVIEW);

        $response = $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_APPROVED,
                'approved_quantity' => 3.0,
                'verification_note' => 'Approved 3 bags after land survey verification (reduced from demanded 5 bags).',
                'lock_version' => $app->lock_version,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $app->refresh();

        // Check that both demanded and approved quantities are preserved
        $this->assertEquals(5.0, (float) $app->demanded_quantity);
        $this->assertEquals(3.0, (float) $app->approved_quantity);
        $this->assertEquals(3, $app->allocated_quantity);
        $this->assertEquals(3.0, $app->getApprovedQuantity());

        // Challan fee per unit is 3,000 PKR; 3 bags approved = 9,000 PKR
        $this->assertEquals(9000.00, (float) $app->challan_amount);
        $this->assertEquals(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
    }

    public function test_officer_approving_without_explicit_approved_quantity_defaults_to_demanded_quantity(): void
    {
        $app = $this->createTestApplication(5.0, Application::STAGE_UNDER_REVIEW);

        $response = $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_APPROVED,
                'verification_note' => 'Approved as demanded.',
                'lock_version' => $app->lock_version,
            ]);

        $response->assertRedirect();
        $app->refresh();

        $this->assertEquals(5.0, (float) $app->demanded_quantity);
        $this->assertEquals(5.0, (float) $app->approved_quantity);
        $this->assertEquals(5, $app->allocated_quantity);
        $this->assertEquals(15000.00, (float) $app->challan_amount);
    }

    public function test_stock_disbursement_quota_is_strictly_restricted_to_approved_quantity(): void
    {
        // Citizen asked for 5 bags, officer approved 2 bags
        $app = $this->createTestApplication(5.0, Application::STAGE_UNDER_REVIEW);

        $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_APPROVED,
                'approved_quantity' => 2.0,
                'verification_note' => 'Approved 2 bags only.',
                'lock_version' => $app->lock_version,
            ]);

        $app->refresh();
        $this->assertEquals(2.0, (float) $app->approved_quantity);

        // Mark challan as paid and verified
        $app->update([
            'current_stage' => Application::STAGE_PAYMENT_VERIFIED,
            'challan_paid_at' => now(),
            'payment_verified_at' => now(),
            'payment_verified_by' => $this->ddGilgit->id,
        ]);

        $this->assertTrue($app->isEligibleForDisbursement());
        $this->assertEquals(2.0, $app->getRemainingDisbursementQuantity());

        // Distribution Officer attempts to disburse 3 bags (more than approved quota 2)
        $stockService = app(StockManagementService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $stockService->disburseStock([
            'application_id' => $app->id,
            'collection_center_id' => $this->center->id,
            'scheme_stock_item_id' => $this->stockItem->id,
            'quantity_disbursed' => 3.0,
            'recipient_name' => 'Karim Shah',
            'recipient_cnic' => '71501-1111111-1',
            'is_original_applicant' => true,
        ], $this->distOfficer);
    }

    public function test_disbursement_completes_when_approved_quantity_is_handed_over(): void
    {
        $app = $this->createTestApplication(5.0, Application::STAGE_UNDER_REVIEW);
        $app->update([
            'approved_quantity' => 2.0,
            'allocated_quantity' => 2,
            'current_stage' => Application::STAGE_PAYMENT_VERIFIED,
            'challan_paid_at' => now(),
            'payment_verified_at' => now(),
            'payment_verified_by' => $this->ddGilgit->id,
        ]);

        $stockService = app(StockManagementService::class);

        // Hand over exact 2 approved bags
        $disb = $stockService->disburseStock([
            'application_id' => $app->id,
            'collection_center_id' => $this->center->id,
            'scheme_stock_item_id' => $this->stockItem->id,
            'quantity_disbursed' => 2.0,
            'recipient_name' => 'Karim Shah',
            'recipient_cnic' => '71501-1111111-1',
            'is_original_applicant' => true,
        ], $this->distOfficer);

        $this->assertNotNull($disb);
        $app->refresh();

        $this->assertEquals(2.0, (float) $app->distributed_quantity);
        $this->assertEquals(0.0, $app->getRemainingDisbursementQuantity());
        $this->assertEquals(Application::STAGE_FULLY_DISTRIBUTED, $app->current_stage);
        $this->assertEquals('disbursed', $app->status);
    }

    public function test_application_dossier_displays_demanded_and_approved_quantities_and_variance(): void
    {
        $app = $this->createTestApplication(5.0, Application::STAGE_UNDER_REVIEW);
        $app->update([
            'approved_quantity' => 3.0,
            'allocated_quantity' => 3,
        ]);

        $response = $this->actingAs($this->ddGilgit)
            ->get(route('admin.applications.show', $app));

        $response->assertOk();
        $response->assertSee('Quota & Demanded Stock', false);
        $response->assertSee('Demanded');
        $response->assertSee('5');
        $response->assertSee('Approved');
        $response->assertSee('3');
        $response->assertSee('Adjusted by Officer');
        $response->assertSee('-2 Bags');
    }

    public function test_challan_fee_calculated_from_configured_district_seed_rate_and_approved_bags(): void
    {
        // 1. Configure seed data: base rate 6000, farmer share 50%
        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_bag_rate'],
            ['value' => '6000', 'label' => 'Approved Bag Rate (PKR)', 'group' => 'wheat_seed']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_farmer_share_pct'],
            ['value' => '50', 'label' => 'Farmer Share (%)', 'group' => 'wheat_seed']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_govt_share_pct'],
            ['value' => '50', 'label' => 'Govt Share (%)', 'group' => 'wheat_seed']
        );

        // Gilgit transport carriage = PKR 500/bag -> Delivered bag rate = 6,500 PKR
        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_district_rates'],
            [
                'value' => json_encode([
                    $this->gilgit->id => [
                        'transport_cost' => 500,
                        'rate' => 6500,
                    ],
                ]),
                'label' => 'District Delivered Rates & Transport Carriage',
                'group' => 'wheat_seed',
            ]
        );

        $misService = app(WheatSeedMisService::class);
        $this->assertEquals(6500.0, $misService->getRateForDistrict($this->gilgit->id));
        // Subsidized farmer share (50% discount) = 3,250 PKR / bag
        $this->assertEquals(3250.0, $misService->getPayableRateForDistrict($this->gilgit->id));

        // 2. Applicant demanded 5 bags
        $app = $this->createTestApplication(5.0, Application::STAGE_UNDER_REVIEW);

        // 3. Officer approves 4 bags
        $response = $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_APPROVED,
                'approved_quantity' => 4.0,
                'verification_note' => 'Approved 4 bags at district delivered rate with 50% discount.',
                'lock_version' => $app->lock_version,
            ]);

        $response->assertRedirect();
        $app->refresh();

        // 4. Verify challan fee: 4 approved bags * 3,250 PKR = 13,000.00 PKR
        $this->assertEquals(4.0, (float) $app->approved_quantity);
        $this->assertEquals(3250.0, (float) $app->getEffectiveUnitRate());
        $this->assertEquals(13000.00, (float) $app->challan_amount);
        $this->assertEquals(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
    }

    public function test_challan_fee_respects_varying_district_delivered_rates(): void
    {
        $diamer = District::firstOrCreate(['id' => 2], ['name' => 'Diamer', 'code' => 'DMR']);

        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_bag_rate'],
            ['value' => '6000', 'label' => 'Approved Bag Rate (PKR)', 'group' => 'wheat_seed']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_farmer_share_pct'],
            ['value' => '50', 'label' => 'Farmer Share (%)', 'group' => 'wheat_seed']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_district_rates'],
            [
                'value' => json_encode([
                    $diamer->id => [
                        'transport_cost' => 1000,
                        'rate' => 7000, // Diamer delivered rate: 7000 PKR -> 50% discount = 3500 PKR
                    ],
                ]),
                'label' => 'District Delivered Rates & Transport Carriage',
                'group' => 'wheat_seed',
            ]
        );

        $ddDiamer = User::firstOrCreate(
            ['email' => 'dd.diamer.qty.test@alf.gb.gov.pk'],
            [
                'name' => 'Deputy Director Diamer',
                'password' => bcrypt('password123'),
                'district_id' => $diamer->id,
                'department_id' => 1,
                'is_active' => true,
            ]
        );
        $ddDiamer->syncRoles(['District Deputy Director']);

        $applicantDiamer = Applicant::create([
            'full_name' => 'Sher Khan',
            'father_name' => 'Khan Sher',
            'cnic' => '71201-2222222-1',
            'phone' => '03450000002',
            'district_id' => $diamer->id,
            'tehsil' => 'Chilas',
            'village' => 'Gonar Farm',
        ]);

        $appDiamer = Application::create([
            'application_no' => 'APP-QTY-DMR-' . uniqid(),
            'applicant_id' => $applicantDiamer->id,
            'scheme_id' => $this->scheme->id,
            'district_id' => $diamer->id,
            'department_id' => 1,
            'current_stage' => Application::STAGE_UNDER_REVIEW,
            'status' => 'pending',
            'demanded_quantity' => 4.0,
            'assigned_to' => $ddDiamer->id,
            'submitted_at' => now(),
            'lock_version' => 1,
        ]);

        // Officer approves 2 bags for Diamer applicant
        $response = $this->actingAs($ddDiamer)
            ->post(route('admin.applications.status', $appDiamer), [
                'status' => Application::STAGE_APPROVED,
                'approved_quantity' => 2.0,
                'verification_note' => 'Approved 2 bags for Diamer.',
                'lock_version' => $appDiamer->lock_version,
            ]);

        $response->assertRedirect();
        $appDiamer->refresh();

        // 2 bags * (7000 * 50%) = 2 * 3500 = 7,000.00 PKR
        $this->assertEquals(2.0, (float) $appDiamer->approved_quantity);
        $this->assertEquals(3500.0, (float) $appDiamer->getEffectiveUnitRate());
        $this->assertEquals(7000.00, (float) $appDiamer->challan_amount);
    }

    public function test_challan_breakdown_rounds_to_nearest_upper_integer_and_generates_digits_and_words(): void
    {
        // Configure odd delivered rate (e.g. 6501 PKR) so 50% is 3250.5 -> rounded up to 3251 PKR
        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_bag_rate'],
            ['value' => '6000', 'label' => 'Approved Bag Rate (PKR)', 'group' => 'wheat_seed']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_district_rates'],
            [
                'value' => json_encode([
                    $this->gilgit->id => [
                        'transport_cost' => 501,
                        'rate' => 6501,
                    ],
                ]),
                'label' => 'District Delivered Rates & Transport Carriage',
                'group' => 'wheat_seed',
            ]
        );

        $app = $this->createTestApplication(3.0, Application::STAGE_UNDER_REVIEW);
        $app->update(['approved_quantity' => 1.0]);

        $breakdown = $app->getChallanBreakdown();

        $this->assertEquals(6501.0, $breakdown['cost_of_1_bag']);
        $this->assertEquals(1.0, $breakdown['approved_bags']);
        $this->assertEquals(6501, $breakdown['total_bill']);
        // 50% of 6501 = 3250.5 -> ceil = 3251
        $this->assertEquals(3251, $breakdown['govt_share']);
        $this->assertEquals(3251, $breakdown['final_bill']);
        $this->assertEquals('Rs. 3,251', $breakdown['amount_digits']);
        $this->assertStringContainsString('Three Thousand Two Hundred Fifty One Rupees Only', $breakdown['amount_words']);
    }

    public function test_admin_can_create_dynamic_challan_template_assign_to_scheme_and_print_challan(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin.challan.test@alf.gb.gov.pk'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password123'),
                'is_active' => true,
            ]
        );
        $superAdmin->syncRoles(['Super Admin']);

        // Configure district seed rate for Gilgit: 6500 PKR per bag
        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_bag_rate'],
            ['value' => '6000', 'label' => 'Approved Bag Rate (PKR)', 'group' => 'wheat_seed']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'wheat_seed_district_rates'],
            [
                'value' => json_encode([
                    $this->gilgit->id => [
                        'transport_cost' => 500,
                        'rate' => 6500,
                    ],
                ]),
                'label' => 'District Delivered Rates & Transport Carriage',
                'group' => 'wheat_seed',
            ]
        );

        // 1. Admin creates a new dynamic challan template and assigns it to the scheme
        $storeResponse = $this->actingAs($superAdmin)->post(route('admin.challan-templates.store'), [
            'name' => 'Custom Wheat Seed Challan 2026',
            'department_name' => 'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
            'sub_title' => 'Government of Gilgit-Baltistan',
            'challan_title' => 'FEE DEPOSIT CHALLAN',
            'bank_name' => 'National Bank Of Pakistan',
            'account_title' => 'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
            'account_no' => '4256260305',
            'account_head' => 'Application Processing Fee(C03870)',
            'validity_days' => 10,
            'directions' => 'This challan is valid for 10 days after the challan is generated. This fee doesnot include transport charges from district to village.',
            'fee_mode' => 'seed_configured',
            'govt_share_pct' => 50,
            'farmer_share_pct' => 50,
            'show_breakdown' => 1,
            'is_active' => 1,
            'schemes' => [$this->scheme->id],
        ]);

        $storeResponse->assertRedirect(route('admin.challan-templates.index'));

        $this->scheme->refresh();
        $this->assertNotNull($this->scheme->challan_template_id);
        $template = $this->scheme->getChallanTemplate();
        $this->assertEquals('Custom Wheat Seed Challan 2026', $template->name);
        $this->assertEquals(10, $template->validity_days);

        // 2. Officer approves application for 4 bags
        $app = $this->createTestApplication(5.0, Application::STAGE_UNDER_REVIEW);
        $this->actingAs($this->ddGilgit)->post(route('admin.applications.status', $app), [
            'status' => Application::STAGE_APPROVED,
            'approved_quantity' => 4.0,
            'verification_note' => 'Approved 4 bags.',
            'lock_version' => $app->lock_version,
        ]);

        $app->refresh();
        // 4 bags * 6500 = 26,000 total bill; 50% = 13,000 final bill
        $this->assertEquals(13000.0, (float) $app->challan_amount);
        $this->assertEquals(now()->addDays(10)->toDateString(), $app->challan_due_date->toDateString());

        // 3. Render Challan Blade view and verify all required fields & text
        $breakdown = $app->getChallanBreakdown();
        $html = view('public.applications.challan-pdf', [
            'application' => $app,
            'scheme' => $this->scheme,
            'applicant' => $app->applicant,
            'district' => $this->gilgit,
            'template' => $template,
            'breakdown' => $breakdown,
        ])->render();

        $this->assertStringContainsString('Agriculture LiveStock and Fishries Department Gilgit Baltistan', $html);
        $this->assertStringContainsString('This challan is valid for 10 days after the challan is generated. This fee doesnot include transport charges from district to village.', $html);
        $this->assertStringContainsString('Challan Generated Date:', $html);
        $this->assertStringContainsString($app->application_no, $html);
        $this->assertStringContainsString('Cost of 1 Bag ( total cost of bag including transport charges )', $html);
        $this->assertStringContainsString('Rs. 6,500', $html);
        $this->assertStringContainsString('No of Wheat bags approved', $html);
        $this->assertStringContainsString('Total Bill : ( cost of 1 bag * no of bags)', $html);
        $this->assertStringContainsString('Rs. 26,000', $html);
        $this->assertStringContainsString('Government Share: 50% of total bill rounded', $html);
        $this->assertStringContainsString('Final Bill : 50% of total bill rounded', $html);
        $this->assertStringContainsString('Rs. 13,000', $html);
        $this->assertStringContainsString('Thirteen Thousand Rupees Only', $html);
        $this->assertStringContainsString('National Bank Of Pakistan', $html);
        $this->assertStringContainsString('4256260305', $html);
        $this->assertStringContainsString('Application Processing Fee(C03870)', $html);

        // 4. Download Challan PDF endpoint returns 200 PDF response
        $pdfResponse = $this->get(route('applications.challan.download', $app->application_no));
        $pdfResponse->assertOk();
    }

    public function test_deputy_director_cannot_change_challan_fee_when_editing_application(): void
    {
        $app = $this->createTestApplication(5.0, Application::STAGE_UNDER_REVIEW);

        // Approve for 3 bags -> Challan fee generated as 3 * 3000 = 9000 PKR
        $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_APPROVED,
                'approved_quantity' => 3.0,
                'verification_note' => 'Approved 3 bags.',
                'lock_version' => $app->lock_version,
            ]);

        $app->refresh();
        $this->assertEquals(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
        $this->assertEquals(9000.00, (float) $app->challan_amount);

        // Edit page renders challan_amount input as readonly disabled for DD
        $editResponse = $this->actingAs($this->ddGilgit)
            ->get(route('admin.applications.edit', $app));
        $editResponse->assertOk();
        $editResponse->assertSee('name="challan_amount"', false);
        $editResponse->assertSee('readonly disabled', false);

        // Crafted PUT request attempting to change challan_amount by DD is ignored
        $updateResponse = $this->actingAs($this->ddGilgit)
            ->put(route('admin.applications.update', $app), [
                'full_name' => $app->applicant->full_name,
                'father_name' => $app->applicant->father_name,
                'phone' => $app->applicant->phone,
                'district_id' => $app->district_id,
                'verification_note' => 'Updated verification remark by DD.',
                'challan_amount' => 100,
                'modification_reason' => 'Attempted fee edit along with remark update.',
            ]);

        $updateResponse->assertRedirect(route('admin.applications.show', $app));
        $updateResponse->assertSessionHas('success');

        $app->refresh();
        $this->assertEquals(9000.00, (float) $app->challan_amount);
        $this->assertEquals('Updated verification remark by DD.', $app->verification_note);
    }

    public function test_dd_can_reject_unpaid_challan_blocks_psid_shows_in_tracking_and_hides_download(): void
    {
        $app = $this->createTestApplication(5.0, Application::STAGE_UNDER_REVIEW);

        // 1. DD approves for 3 bags -> challan issued & pending PSID bill created
        $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_APPROVED,
                'approved_quantity' => 3.0,
                'verification_note' => 'Approved 3 bags.',
                'lock_version' => $app->lock_version,
            ]);

        $app->refresh();
        $bill = \App\Models\Bill::where('application_id', $app->id)->firstOrFail();
        $psid = $bill->psid;
        $this->assertEquals(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
        $this->assertSame('pending', $bill->status);
        $this->assertSame(3, app(\App\Services\QuotaService::class)->usedSlots($this->scheme->id, $this->gilgit->id));

        // 2. DD rejects unpaid challan application
        $rejectResponse = $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $app), [
                'status' => Application::STAGE_REJECTED,
                'rejection_reason' => 'Challan fee was not deposited within acceptable timeline.',
                'lock_version' => $app->lock_version,
            ]);
        $rejectResponse->assertRedirect();
        $rejectResponse->assertSessionHas('success');

        $app->refresh();
        $bill->refresh();
        $this->assertEquals(Application::STAGE_REJECTED, $app->current_stage);
        $this->assertEquals('rejected', $app->status);
        $this->assertNull($app->approved_quantity);
        $this->assertNull($app->allocated_quantity);
        $this->assertSame('cancelled', $bill->status);
        $this->assertSame(0, app(\App\Services\QuotaService::class)->usedSlots($this->scheme->id, $this->gilgit->id));

        // 3. Admin dossier hides Download Challan PDF & shows cancelled status
        $showRes = $this->actingAs($this->ddGilgit)->get(route('admin.applications.show', $app));
        $showRes->assertOk();
        $showRes->assertSee('REJECTED / CHALLAN CANCELLED');
        $showRes->assertDontSee('Download Challan PDF');

        // 4. Direct challan PDF download is blocked for rejected application
        $dlRes = $this->get(route('applications.challan.download', $app->application_no));
        $dlRes->assertRedirect(route('applications.track', ['application_no' => $app->application_no]));

        // 5. Public tracking shows rejection and hides previous challan download
        $trackRes = $this->post(route('applications.track'), [
            'application_no' => $app->application_no,
            'cnic' => $app->applicant->cnic,
        ]);
        $trackRes->assertOk();
        $trackRes->assertSee('Rejected');
        $trackRes->assertSee('Application Rejected');
        $trackRes->assertSee('Challan fee was not deposited within acceptable timeline.');
        $trackRes->assertDontSee('Download Fee Deposit Challan');

        // 6. PSID payment & inquiry APIs reject payment for cancelled/rejected PSID
        \App\Models\GbpayApiCredential::create([
            'username' => 'gbpay_reject_test',
            'password' => \Illuminate\Support\Facades\Hash::make('secret1234'),
            'is_active' => true,
        ]);

        $inquiry = $this->postJson('/api/gb-pay/bill-inquiry', [
            'username' => 'gbpay_reject_test',
            'password' => 'secret1234',
            'voucher_number' => $psid,
        ]);
        $inquiry->assertStatus(403);
        $inquiry->assertJsonPath('response_code', '02');

        $payment = $this->postJson('/api/gb-pay/bill-payment', [
            'username' => 'gbpay_reject_test',
            'password' => 'secret1234',
            'voucher_number' => $psid,
            'transaction_id' => 'TXN-BLOCKED-1',
            'transaction_amount' => 9000,
            'bank_mnemonic' => 'HBL',
        ]);
        $payment->assertStatus(403);
        $payment->assertJsonPath('response_code', '02');

        $search = $this->postJson('/api/gb-pay/search-psid', [
            'username' => 'gbpay_reject_test',
            'password' => 'secret1234',
            'search_by' => 'reference_number',
            'search_value' => $psid,
        ]);
        $search->assertOk();
        $search->assertJsonPath('bill_status', 'C');

        $this->expectException(\RuntimeException::class);
        app(\App\Services\GbpayService::class)->handleWebhook([
            'psid' => $psid,
            'status' => 'paid',
            'transaction_id' => 'TXN-WH-BLOCKED',
            'amount' => 9000,
        ]);
    }

    public function test_dd_can_reject_previously_approved_unpaid_app_but_cannot_reject_paid_app(): void
    {
        // Case A: Previously approved legacy application (approved stage, unpaid)
        $legacyApp = $this->createTestApplication(4.0, Application::STAGE_APPROVED);
        $legacyApp->update([
            'status' => 'directorate_approved',
            'approved_quantity' => 4.0,
            'allocated_quantity' => 4,
            'challan_no' => 'CHL-2026-009999',
            'challan_amount' => 12000,
        ]);

        $showRes = $this->actingAs($this->ddGilgit)->get(route('admin.applications.show', $legacyApp));
        $showRes->assertOk();
        $showRes->assertSee('Reject Unpaid Application');

        $rejectLegacy = $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $legacyApp), [
                'status' => Application::STAGE_REJECTED,
                'rejection_reason' => 'Previously approved applicant did not pay challan.',
                'lock_version' => $legacyApp->lock_version,
            ]);
        $rejectLegacy->assertRedirect();
        $rejectLegacy->assertSessionHas('success');
        $legacyApp->refresh();
        $this->assertEquals(Application::STAGE_REJECTED, $legacyApp->current_stage);
        $this->assertEquals('rejected', $legacyApp->status);

        // Case B: Paid challan application cannot be rejected by DD
        $paidApplicant = Applicant::create([
            'full_name' => 'Ali Khan Paid',
            'father_name' => 'Khan',
            'cnic' => '7150122222222',
            'phone' => '03450000002',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'village' => 'Danyore',
        ]);
        $paidApp = Application::create([
            'application_no' => 'APP-QTY-' . uniqid(),
            'applicant_id' => $paidApplicant->id,
            'scheme_id' => $this->scheme->id,
            'district_id' => $this->gilgit->id,
            'department_id' => 1,
            'current_stage' => Application::STAGE_CHALLAN_ISSUED,
            'status' => 'directorate_approved',
            'demanded_quantity' => 2.0,
            'approved_quantity' => 2.0,
            'allocated_quantity' => 2,
            'challan_no' => 'CHL-2026-008888',
            'challan_amount' => 6000,
            'payment_verified_at' => now(),
            'gbpay_transaction_id' => 'TXN-PAID-8888',
            'assigned_to' => $this->ddGilgit->id,
            'submitted_at' => now(),
            'lock_version' => 1,
        ]);

        $rejectPaid = $this->actingAs($this->ddGilgit)
            ->post(route('admin.applications.status', $paidApp), [
                'status' => Application::STAGE_REJECTED,
                'rejection_reason' => 'Attempt to reject already paid application.',
                'lock_version' => $paidApp->lock_version,
            ]);
        $rejectPaid->assertRedirect();
        $rejectPaid->assertSessionHas('error');

        $paidApp->refresh();
        $this->assertEquals(Application::STAGE_CHALLAN_ISSUED, $paidApp->current_stage);
    }
}

