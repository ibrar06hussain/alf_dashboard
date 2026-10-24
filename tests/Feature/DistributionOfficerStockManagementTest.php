<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CenterInventory;
use App\Models\CollectionCenter;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SchemeStockItem;
use App\Models\StockDamage;
use App\Models\StockReceipt;
use App\Models\StockTransfer;
use App\Models\Tehsil;
use App\Models\User;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DistributionOfficerStockManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $distOfficerDiamer;
    protected User $superAdmin;
    protected District $diamer;
    protected District $gilgit;
    protected Scheme $scheme;
    protected SchemeStockItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->diamer = District::firstOrCreate(['id' => 9], ['name' => 'Diamer', 'code' => 'DMR']);
        $this->gilgit = District::firstOrCreate(['id' => 1], ['name' => 'Gilgit', 'code' => 'GLT']);

        // Create Scheme and SchemeStockItem
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
        $this->distOfficerDiamer = User::factory()->create([
            'name' => 'Distribution Officer Diamer',
            'email' => 'distribution.diamer@alf.gb.gov.pk',
            'district_id' => $this->diamer->id,
            'department_id' => 1,
            'is_active' => true,
        ]);
        $this->distOfficerDiamer->assignRole('Distribution Officer');

        // Create Super Admin
        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'admin.test@alf.gb.gov.pk',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('Super Admin');
    }

    public function test_distribution_officer_can_view_stock_dashboard()
    {
        $response = $this->actingAs($this->distOfficerDiamer)->get(route('admin.stock.index'));

        $response->assertStatus(200);
        $response->assertSee('Scheme Stock & Distribution Management', false);
        $response->assertSee('Total Stock Received');
        $response->assertSee('Available in Centers');
    }

    public function test_distribution_officer_can_create_collection_centers_at_various_levels()
    {
        // 1. Create Tehsil center in Diamer
        $tehsil = Tehsil::create([
            'name' => 'Chilas',
            'district_id' => $this->diamer->id,
        ]);

        $response = $this->actingAs($this->distOfficerDiamer)->post(route('admin.stock.centers.store'), [
            'name' => 'Chilas Main Tehsil Depot',
            'code' => 'CC-DMR-CHL-01',
            'level' => 'tehsil',
            'district_id' => $this->diamer->id,
            'tehsil_id' => $tehsil->id,
            'village_name' => 'Chilas Bazar',
            'focal_person_name' => 'Muhammad Ali',
            'focal_person_phone' => '0300-1234567',
            'capacity_units' => 5000,
        ]);

        $response->assertRedirect(route('admin.stock.centers'));
        $this->assertDatabaseHas('collection_centers', [
            'code' => 'CC-DMR-CHL-01',
            'level' => 'tehsil',
            'district_id' => $this->diamer->id,
        ]);
    }

    public function test_distribution_officer_cannot_create_center_in_different_district()
    {
        $response = $this->actingAs($this->distOfficerDiamer)->post(route('admin.stock.centers.store'), [
            'name' => 'Unauthorized Gilgit Center',
            'code' => 'CC-GLT-ILLEGAL',
            'level' => 'district',
            'district_id' => $this->gilgit->id, // Gilgit instead of Diamer
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('collection_centers', [
            'code' => 'CC-GLT-ILLEGAL',
        ]);
    }

    public function test_record_stock_receipt_and_updates_inventory_balance()
    {
        $center = CollectionCenter::create([
            'name' => 'Diamer Central Depot',
            'code' => 'CC-DMR-MAIN',
            'level' => 'district',
            'district_id' => $this->diamer->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->distOfficerDiamer)->post(route('admin.stock.receipts.store'), [
            'collection_center_id' => $center->id,
            'scheme_stock_item_id' => $this->item->id,
            'quantity_received' => 1200,
            'received_date' => now()->toDateString(),
            'supplier_source' => 'Punjab Seed Corporation',
            'batch_no' => 'LOT-2026-X',
        ]);

        $response->assertRedirect(route('admin.stock.receipts'));

        // Check receipt created
        $this->assertDatabaseHas('stock_receipts', [
            'collection_center_id' => $center->id,
            'quantity_received' => 1200,
        ]);

        // Check inventory balance updated
        $inv = CenterInventory::where('collection_center_id', $center->id)
            ->where('scheme_stock_item_id', $this->item->id)
            ->first();

        $this->assertNotNull($inv);
        $this->assertEquals(1200, (float) $inv->current_balance);
        $this->assertEquals(1200, (float) $inv->quantity_received);
    }

    public function test_stock_transfer_requires_demand_justification_and_updates_both_centers()
    {
        // 1. Setup two centers
        $sourceCenter = CollectionCenter::create([
            'name' => 'Diamer Main Depot',
            'code' => 'CC-DMR-SRC',
            'level' => 'district',
            'district_id' => $this->diamer->id,
            'is_active' => true,
        ]);

        $destCenter = CollectionCenter::create([
            'name' => 'Tangir Tehsil Depot',
            'code' => 'CC-DMR-TNG',
            'level' => 'tehsil',
            'district_id' => $this->diamer->id,
            'is_active' => true,
        ]);

        // Seed 1000 units in source
        $invSource = $sourceCenter->getInventoryForItem($this->item->id);
        $invSource->quantity_received = 1000;
        $invSource->recalculate();

        // 2. Attempt transfer without demand justification - should fail
        $failResponse = $this->actingAs($this->distOfficerDiamer)->post(route('admin.stock.transfers.store'), [
            'from_center_id' => $sourceCenter->id,
            'to_center_id' => $destCenter->id,
            'scheme_stock_item_id' => $this->item->id,
            'quantity' => 300,
            'demand_justification' => '', // Empty justification
            'transfer_date' => now()->toDateString(),
        ]);

        $failResponse->assertSessionHasErrors(['demand_justification']);

        // 3. Perform transfer with valid demand justification
        $successResponse = $this->actingAs($this->distOfficerDiamer)->post(route('admin.stock.transfers.store'), [
            'from_center_id' => $sourceCenter->id,
            'to_center_id' => $destCenter->id,
            'scheme_stock_item_id' => $this->item->id,
            'quantity' => 300,
            'demand_justification' => 'Urgent demand from 60 approved wheat farmers in Tangir valley awaiting seed distribution.',
            'transfer_date' => now()->toDateString(),
        ]);

        $successResponse->assertRedirect(route('admin.stock.transfers'));

        // Verify transfer record
        $this->assertDatabaseHas('stock_transfers', [
            'from_center_id' => $sourceCenter->id,
            'to_center_id' => $destCenter->id,
            'quantity' => 300,
            'status' => 'completed',
        ]);

        // Verify Source balance decreased: 1000 - 300 = 700
        $invSource->refresh();
        $this->assertEquals(700, (float) $invSource->current_balance);
        $this->assertEquals(300, (float) $invSource->quantity_transferred_out);

        // Verify Destination balance increased: 0 + 300 = 300
        $invDest = $destCenter->getInventoryForItem($this->item->id);
        $this->assertEquals(300, (float) $invDest->current_balance);
        $this->assertEquals(300, (float) $invDest->quantity_transferred_in);
    }

    public function test_record_stock_damage_with_proof_and_deducts_inventory()
    {
        Storage::fake('public');

        $center = CollectionCenter::create([
            'name' => 'Diamer Main Depot',
            'code' => 'CC-DMR-DMG',
            'level' => 'district',
            'district_id' => $this->diamer->id,
            'is_active' => true,
        ]);

        // Seed 500 units
        $inv = $center->getInventoryForItem($this->item->id);
        $inv->quantity_received = 500;
        $inv->recalculate();

        $proofFile = UploadedFile::fake()->create('damage_inspection.jpg', 500, 'image/jpeg');

        $response = $this->actingAs($this->distOfficerDiamer)->post(route('admin.stock.damages.store'), [
            'collection_center_id' => $center->id,
            'scheme_stock_item_id' => $this->item->id,
            'quantity' => 20,
            'damage_type' => 'water_moisture',
            'reason' => 'Rainwater seepage through storage ceiling damaged 20 seed bags on pallet 4.',
            'proof_file' => $proofFile,
            'action_taken' => 'Bags quarantined and sent for laboratory germination check.',
            'incident_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('admin.stock.damages'));

        $this->assertDatabaseHas('stock_damages', [
            'collection_center_id' => $center->id,
            'quantity' => 20,
            'damage_type' => 'water_moisture',
        ]);

        // Inventory should be 500 - 20 = 480
        $inv->refresh();
        $this->assertEquals(480, (float) $inv->current_balance);
        $this->assertEquals(20, (float) $inv->quantity_damaged);
    }

    public function test_demand_vs_available_stock_view_calculates_approved_demand_and_variance()
    {
        // 1. Create collection center with 400 available bags
        $center = CollectionCenter::create([
            'name' => 'Diamer Central Depot',
            'code' => 'CC-DMR-DEMAND',
            'level' => 'district',
            'district_id' => $this->diamer->id,
            'is_active' => true,
        ]);
        $inv = $center->getInventoryForItem($this->item->id);
        $inv->quantity_received = 400;
        $inv->recalculate();

        // 2. Create 3 approved applications in Diamer (each with allocated_quantity = 100, total demand = 300)
        for ($i = 0; $i < 3; $i++) {
            $applicant = \App\Models\Applicant::create([
                'cnic' => '715011234567' . ($i + 1),
                'full_name' => 'Ghulam Nabi ' . ($i + 1),
                'phone' => '0345123456' . $i,
                'district_id' => $this->diamer->id,
            ]);

            Application::create([
                'application_no' => 'APP-TEST-DEMAND-' . ($i + 1),
                'scheme_id' => $this->scheme->id,
                'applicant_id' => $applicant->id,
                'district_id' => $this->diamer->id,
                'current_stage' => Application::STAGE_ELIGIBLE_BENEFICIARY,
                'status' => 'approved',
                'allocated_quantity' => 100,
            ]);
        }

        // Available: 400, Demand: 300 -> Surplus: +100
        $response = $this->actingAs($this->distOfficerDiamer)->get(route('admin.stock.demand'));

        $response->assertStatus(200);
        $response->assertSee('Demand vs Available Stock Analysis', false);
        $response->assertSee('Approved Cases');
        $response->assertSee('Available in Depots');
        $response->assertSee('+100'); // Surplus of 100
    }
}
