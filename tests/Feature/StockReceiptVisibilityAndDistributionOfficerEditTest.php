<?php

namespace Tests\Feature;

use App\Models\CenterInventory;
use App\Models\CollectionCenter;
use App\Models\Department;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SchemeStockItem;
use App\Models\StockEditAuditLog;
use App\Models\StockReceipt;
use App\Models\User;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReceiptVisibilityAndDistributionOfficerEditTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $distOfficerGilgit;
    private User $distOfficerDiamer;
    private District $gilgit;
    private District $diamer;
    private CollectionCenter $gilgitCenter;
    private SchemeStockItem $stockItem;
    private StockReceipt $receipt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->superAdmin = User::where('email', 'admin@alf.gb.gov.pk')->firstOrFail();
        $agriDept = Department::firstOrCreate(['slug' => 'agriculture'], ['name' => 'Agriculture', 'display_order' => 1]);
        $this->gilgit = District::firstOrCreate(['code' => 'GLT'], ['name' => 'Gilgit', 'is_active' => true]);
        $this->diamer = District::firstOrCreate(['code' => 'DMR'], ['name' => 'Diamer', 'is_active' => true]);

        $this->distOfficerGilgit = User::factory()->create([
            'email' => 'do.dist.gilgit@alf.gb.gov.pk',
            'department_id' => $agriDept->id,
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);
        $this->distOfficerGilgit->syncRoles(['Distribution Officer']);

        $this->distOfficerDiamer = User::factory()->create([
            'email' => 'do.dist.diamer@alf.gb.gov.pk',
            'department_id' => $agriDept->id,
            'district_id' => $this->diamer->id,
            'is_active' => true,
        ]);
        $this->distOfficerDiamer->syncRoles(['Distribution Officer']);

        $scheme = Scheme::create([
            'department_id' => $agriDept->id,
            'title' => 'Wheat Seed Stock Receipt Test Scheme',
            'slug' => 'wheat-seed-stock-receipt-edit-test',
            'status' => 'active',
            'type' => 'running',
            'scope' => 'gb',
        ]);

        $this->stockItem = SchemeStockItem::create([
            'scheme_id' => $scheme->id,
            'item_name' => 'Certified Wheat Seed Bag',
            'unit' => 'Bags',
            'is_active' => true,
        ]);

        $this->gilgitCenter = CollectionCenter::create([
            'name' => 'Gilgit Central Depot',
            'code' => 'CC-GLT-01',
            'level' => 'district',
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);

        // Create a receipt recorded by Super Admin 2 days ago (outside 10-minute grace window)
        $this->receipt = StockReceipt::create([
            'receipt_no' => 'REC-2026-0001',
            'scheme_stock_item_id' => $this->stockItem->id,
            'collection_center_id' => $this->gilgitCenter->id,
            'district_id' => $this->gilgit->id,
            'quantity_received' => 500,
            'received_date' => now()->subDays(2)->toDateString(),
            'supplier_source' => 'Initial Depot',
            'batch_no' => 'BATCH-01',
            'received_by' => $this->superAdmin->id,
        ]);
        $this->receipt->created_at = now()->subDays(2);
        $this->receipt->save();

        CenterInventory::create([
            'collection_center_id' => $this->gilgitCenter->id,
            'scheme_stock_item_id' => $this->stockItem->id,
            'quantity_received' => 500,
            'quantity_distributed' => 0,
            'quantity_transferred_in' => 0,
            'quantity_transferred_out' => 0,
            'quantity_damaged' => 0,
            'current_balance' => 500,
        ]);
    }

    public function test_receipts_page_displays_dark_high_contrast_quantity_received_and_edit_button_for_distribution_officer(): void
    {
        $response = $this->actingAs($this->distOfficerGilgit)->get(route('admin.stock.receipts'));

        $response->assertOk();
        $response->assertSee('+500 Bags', false);
        $response->assertSee('color: #064e3b', false);
        $response->assertSee('openEditReceiptModal(', false);
        $response->assertSee('Stock Receipt Edit History Log', false);
    }

    public function test_distribution_officer_can_edit_receipt_after_10_minutes_and_history_is_preserved_for_each_edit(): void
    {
        $this->assertTrue($this->receipt->canUserEdit($this->distOfficerGilgit));

        // First edit by Distribution Officer
        $res1 = $this->actingAs($this->distOfficerGilgit)->put(route('admin.stock.receipts.update', $this->receipt), [
            'collection_center_id' => $this->gilgitCenter->id,
            'scheme_stock_item_id' => $this->stockItem->id,
            'quantity_received' => 650,
            'received_date' => $this->receipt->received_date->format('Y-m-d'),
            'supplier_source' => 'Updated Supplier Corp',
            'batch_no' => 'BATCH-01A',
            'notes' => 'Reconciled physical count upon inspection',
            'justification' => 'Adjusted received quantity after recounting trucks 1 and 2.',
        ]);

        $res1->assertRedirect(route('admin.stock.receipts'));

        $this->receipt->refresh();
        $this->assertEquals(650.0, (float) $this->receipt->quantity_received);
        $this->assertEquals('Updated Supplier Corp', $this->receipt->supplier_source);

        // Inventory balance recalculated cleanly
        $inventory = CenterInventory::where('collection_center_id', $this->gilgitCenter->id)
            ->where('scheme_stock_item_id', $this->stockItem->id)
            ->firstOrFail();
        $this->assertEquals(650.0, (float) $inventory->current_balance);

        // Second edit by Distribution Officer
        $res2 = $this->actingAs($this->distOfficerGilgit)->put(route('admin.stock.receipts.update', $this->receipt), [
            'collection_center_id' => $this->gilgitCenter->id,
            'scheme_stock_item_id' => $this->stockItem->id,
            'quantity_received' => 620,
            'received_date' => $this->receipt->received_date->format('Y-m-d'),
            'supplier_source' => 'Updated Supplier Corp',
            'batch_no' => 'BATCH-01B',
            'notes' => 'Returned 30 torn sacks',
            'justification' => 'Excluded 30 damaged sacks returned to vendor.',
        ]);

        $res2->assertRedirect(route('admin.stock.receipts'));

        // Both edits must be preserved permanently in StockEditAuditLog
        $logs = StockEditAuditLog::where('stock_type', 'receipt')
            ->where('stock_id', $this->receipt->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $logs);
        $this->assertEquals(500.0, $logs[0]->changed_fields['quantity_received']['from']);
        $this->assertEquals(650.0, $logs[0]->changed_fields['quantity_received']['to']);
        $this->assertEquals('Adjusted received quantity after recounting trucks 1 and 2.', $logs[0]->justification);
        $this->assertStringContainsString('Distribution Officer', $logs[0]->user_role);

        $this->assertEquals(650.0, $logs[1]->changed_fields['quantity_received']['from']);
        $this->assertEquals(620.0, $logs[1]->changed_fields['quantity_received']['to']);
        $this->assertEquals('Excluded 30 damaged sacks returned to vendor.', $logs[1]->justification);

        // Verify History UI renders both audit entries and count
        $view = $this->actingAs($this->distOfficerGilgit)->get(route('admin.stock.receipts'));
        $view->assertOk();
        $view->assertSee('Edited (2)', false);
        $view->assertSee('History (2)', false);
        $view->assertSee('Adjusted received quantity after recounting trucks 1 and 2.');
        $view->assertSee('Excluded 30 damaged sacks returned to vendor.');
    }

    public function test_distribution_officer_from_another_district_cannot_edit_receipt(): void
    {
        $this->assertFalse($this->receipt->canUserEdit($this->distOfficerDiamer));

        $res = $this->actingAs($this->distOfficerDiamer)->put(route('admin.stock.receipts.update', $this->receipt), [
            'collection_center_id' => $this->gilgitCenter->id,
            'scheme_stock_item_id' => $this->stockItem->id,
            'quantity_received' => 999,
            'received_date' => $this->receipt->received_date->format('Y-m-d'),
            'justification' => 'Unauthorized cross-district edit attempt.',
        ]);

        $res->assertSessionHas('error');
        $this->assertEquals(500.0, (float) $this->receipt->fresh()->quantity_received);
        $this->assertSame(0, StockEditAuditLog::count());
    }

    public function test_justification_is_mandatory_when_editing_receipt(): void
    {
        $res = $this->actingAs($this->distOfficerGilgit)->put(route('admin.stock.receipts.update', $this->receipt), [
            'collection_center_id' => $this->gilgitCenter->id,
            'scheme_stock_item_id' => $this->stockItem->id,
            'quantity_received' => 520,
            'received_date' => $this->receipt->received_date->format('Y-m-d'),
            'justification' => '',
        ]);

        $res->assertSessionHasErrors('justification');
        $this->assertEquals(500.0, (float) $this->receipt->fresh()->quantity_received);
    }
}
