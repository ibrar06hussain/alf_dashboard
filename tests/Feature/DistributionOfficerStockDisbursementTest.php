<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\CollectionCenter;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SchemeStockItem;
use App\Models\StockDisbursement;
use App\Models\User;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DistributionOfficerStockDisbursementTest extends TestCase
{
    use RefreshDatabase;

    protected User $distOfficerDiamer;
    protected User $distOfficerGilgit;
    protected District $diamer;
    protected District $gilgit;
    protected Scheme $scheme;
    protected SchemeStockItem $item;
    protected CollectionCenter $diamerCenter;
    protected CollectionCenter $gilgitCenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->diamer = District::firstOrCreate(['id' => 9], ['name' => 'Diamer', 'code' => 'DMR']);
        $this->gilgit = District::firstOrCreate(['id' => 1], ['name' => 'Gilgit', 'code' => 'GLT']);

        $this->scheme = Scheme::firstOrCreate(
            ['id' => 1],
            [
                'title' => 'Wheat Seed Distribution Scheme',
                'slug' => 'wheat-seed-test',
                'status' => 'active',
                'department_id' => 1,
            ]
        );

        $this->item = SchemeStockItem::create([
            'scheme_id' => $this->scheme->id,
            'item_name' => 'Certified Wheat Seed (50kg Bag)',
            'unit' => 'Bag (50kg)',
            'is_active' => true,
        ]);

        // Create Distribution Officer for Diamer
        $this->distOfficerDiamer = User::firstOrCreate(
            ['email' => 'dist.diamer.test@alf.gb.gov.pk'],
            [
                'name' => 'Distribution Officer Diamer Test',
                'password' => bcrypt('password123'),
                'district_id' => $this->diamer->id,
                'department_id' => 1,
                'is_active' => true,
            ]
        );
        $this->distOfficerDiamer->syncRoles(['Distribution Officer']);

        // Create Distribution Officer for Gilgit
        $this->distOfficerGilgit = User::firstOrCreate(
            ['email' => 'dist.gilgit.test@alf.gb.gov.pk'],
            [
                'name' => 'Distribution Officer Gilgit Test',
                'password' => bcrypt('password123'),
                'district_id' => $this->gilgit->id,
                'department_id' => 1,
                'is_active' => true,
            ]
        );
        $this->distOfficerGilgit->syncRoles(['Distribution Officer']);

        // Create Collection Centers
        $this->diamerCenter = CollectionCenter::create([
            'name' => 'Chilas Main Seed Depot',
            'code' => 'DMR-CHILAS-01',
            'level' => 'district',
            'district_id' => $this->diamer->id,
            'is_active' => true,
            'created_by' => $this->distOfficerDiamer->id,
        ]);

        $this->gilgitCenter = CollectionCenter::create([
            'name' => 'Gilgit Central Seed Depot',
            'code' => 'GLT-MAIN-01',
            'level' => 'district',
            'district_id' => $this->gilgit->id,
            'is_active' => true,
            'created_by' => $this->distOfficerGilgit->id,
        ]);
    }

    /**
     * Helper to create an eligible application with verified challan.
     */
    protected function createEligibleApplication(District $district, int $bags = 2): Application
    {
        $applicant = Applicant::create([
            'full_name' => 'Farmer ' . uniqid(),
            'cnic' => '71501-' . rand(1000000, 9999999) . '-1',
            'phone' => '0345' . rand(1000000, 9999999),
            'district_id' => $district->id,
        ]);

        return Application::create([
            'application_no' => Application::generateApplicationNo(),
            'scheme_id' => $this->scheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $district->id,
            'department_id' => 1,
            'status' => Application::STATUS_DIRECTORATE_APPROVED,
            'current_stage' => Application::STAGE_PAYMENT_VERIFIED,
            'challan_no' => 'CHL-' . rand(10000, 99999),
            'challan_amount' => 5000,
            'challan_paid_at' => now(),
            'payment_verified_at' => now(),
            'allocated_quantity' => $bags,
            'distributed_quantity' => 0,
            'lock_version' => 1,
        ]);
    }

    public function test_distribution_officer_can_view_eligible_applicants_in_their_district(): void
    {
        $diamerApp = $this->createEligibleApplication($this->diamer, 3);
        $gilgitApp = $this->createEligibleApplication($this->gilgit, 5);

        $response = $this->actingAs($this->distOfficerDiamer)
            ->get(route('admin.stock.disbursements', ['tab' => 'eligible']));

        $response->assertStatus(200);
        $response->assertSee($diamerApp->application_no);
        $response->assertSee($diamerApp->applicant->name);
        // Cross-district isolation: should NOT see Gilgit application
        $response->assertDontSee($gilgitApp->application_no);
    }

    public function test_distribution_officer_can_disburse_stock_and_inventory_is_atomically_debited(): void
    {
        Storage::fake('public');

        // Fund Diamer Center with 50 bags
        $inventory = $this->diamerCenter->getInventoryForItem($this->item->id);
        $inventory->quantity_received = 50;
        $inventory->recalculate();
        $this->assertEquals(50, $inventory->current_balance);

        $app = $this->createEligibleApplication($this->diamer, 2);

        $proof = UploadedFile::fake()->create('receiving_proof.jpg', 300, 'image/jpeg');

        $response = $this->actingAs($this->distOfficerDiamer)
            ->post(route('admin.stock.disbursements.store'), [
                'application_id' => $app->id,
                'collection_center_id' => $this->diamerCenter->id,
                'scheme_stock_item_id' => $this->item->id,
                'quantity_disbursed' => 2,
                'recipient_name' => $app->applicant->name,
                'recipient_cnic' => $app->applicant->cnic,
                'recipient_phone' => $app->applicant->phone,
                'is_original_applicant' => 1,
                'challan_no' => $app->challan_no,
                'disbursement_date' => now()->toDateString(),
                'proof_file' => $proof,
                'notes' => 'Handed over 2 bags in good condition.',
            ]);

        $response->assertRedirect(route('admin.stock.disbursements', ['tab' => 'history']));
        $response->assertSessionHas('success');

        // Check StockDisbursement record created
        $this->assertDatabaseHas('stock_disbursements', [
            'application_id' => $app->id,
            'collection_center_id' => $this->diamerCenter->id,
            'scheme_stock_item_id' => $this->item->id,
            'quantity_disbursed' => 2,
            'disbursed_by' => $this->distOfficerDiamer->id,
        ]);

        // Check Inventory atomically debited
        $inventory->refresh();
        $this->assertEquals(2, $inventory->quantity_distributed);
        $this->assertEquals(48, $inventory->current_balance);

        // Check Application stage updated to fully_distributed and status to disbursed
        $app->refresh();
        $this->assertEquals(Application::STAGE_FULLY_DISTRIBUTED, $app->current_stage);
        $this->assertEquals('disbursed', $app->status);
        $this->assertEquals(2, $app->distributed_quantity);
        $this->assertNotNull($app->distributed_at);
    }

    public function test_partial_disbursement_sets_stage_to_partially_distributed(): void
    {
        // Fund Center with 50 bags
        $inventory = $this->diamerCenter->getInventoryForItem($this->item->id);
        $inventory->quantity_received = 50;
        $inventory->recalculate();

        // Application requires 4 bags
        $app = $this->createEligibleApplication($this->diamer, 4);

        // Officer disburses only 2 bags
        $response = $this->actingAs($this->distOfficerDiamer)
            ->post(route('admin.stock.disbursements.store'), [
                'application_id' => $app->id,
                'collection_center_id' => $this->diamerCenter->id,
                'scheme_stock_item_id' => $this->item->id,
                'quantity_disbursed' => 2,
                'recipient_name' => $app->applicant->name,
                'recipient_cnic' => $app->applicant->cnic,
                'disbursement_date' => now()->toDateString(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $app->refresh();
        $this->assertEquals(Application::STAGE_PARTIALLY_DISTRIBUTED, $app->current_stage);
        $this->assertEquals(2, $app->distributed_quantity);
        $this->assertEquals(2, $app->getRemainingDisbursementQuantity());
        $this->assertTrue($app->isEligibleForDisbursement());
    }

    public function test_distribution_officer_cannot_disburse_stock_to_applicant_of_another_district(): void
    {
        $gilgitApp = $this->createEligibleApplication($this->gilgit, 2);

        // Diamer officer attempts to disburse to Gilgit applicant
        $response = $this->actingAs($this->distOfficerDiamer)
            ->post(route('admin.stock.disbursements.store'), [
                'application_id' => $gilgitApp->id,
                'collection_center_id' => $this->diamerCenter->id,
                'scheme_stock_item_id' => $this->item->id,
                'quantity_disbursed' => 2,
                'recipient_name' => $gilgitApp->applicant->name,
                'recipient_cnic' => $gilgitApp->applicant->cnic,
                'disbursement_date' => now()->toDateString(),
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('stock_disbursements', [
            'application_id' => $gilgitApp->id,
        ]);
    }

    public function test_disbursement_fails_if_collection_center_has_insufficient_stock(): void
    {
        // Center only has 1 bag
        $inventory = $this->diamerCenter->getInventoryForItem($this->item->id);
        $inventory->quantity_received = 1;
        $inventory->recalculate();

        $app = $this->createEligibleApplication($this->diamer, 3);

        $response = $this->actingAs($this->distOfficerDiamer)
            ->post(route('admin.stock.disbursements.store'), [
                'application_id' => $app->id,
                'collection_center_id' => $this->diamerCenter->id,
                'scheme_stock_item_id' => $this->item->id,
                'quantity_disbursed' => 3,
                'recipient_name' => $app->applicant->name,
                'recipient_cnic' => $app->applicant->cnic,
                'disbursement_date' => now()->toDateString(),
            ]);

        $response->assertSessionHasErrors(['quantity_disbursed']);
        $this->assertDatabaseMissing('stock_disbursements', [
            'application_id' => $app->id,
        ]);
    }

    public function test_disbursement_gate_pass_voucher_renders_successfully(): void
    {
        $app = $this->createEligibleApplication($this->diamer, 2);

        $disbursement = StockDisbursement::create([
            'disbursement_no' => 'DSB-' . date('Ymd') . '-TEST1',
            'application_id' => $app->id,
            'collection_center_id' => $this->diamerCenter->id,
            'scheme_stock_item_id' => $this->item->id,
            'district_id' => $this->diamer->id,
            'quantity_disbursed' => 2,
            'recipient_name' => $app->applicant->name,
            'recipient_cnic' => $app->applicant->cnic,
            'recipient_phone' => $app->applicant->phone,
            'is_original_applicant' => true,
            'challan_no' => $app->challan_no,
            'challan_verified' => true,
            'disbursement_date' => now()->toDateString(),
            'disbursed_by' => $this->distOfficerDiamer->id,
        ]);

        $response = $this->actingAs($this->distOfficerDiamer)
            ->get(route('admin.stock.disbursements.voucher', $disbursement));

        $response->assertStatus(200);
        $response->assertSee($disbursement->disbursement_no);
        $response->assertSee($app->applicant->name);
        $response->assertSee('OFFICIAL STOCK HANDOVER SLIP & GATE PASS', false);
    }
}
