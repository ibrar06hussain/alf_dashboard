<?php

namespace App\Services;

use App\Models\Application;
use App\Models\CenterInventory;
use App\Models\CollectionCenter;
use App\Models\Scheme;
use App\Models\SchemeStockItem;
use App\Models\StockDamage;
use App\Models\StockReceipt;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockManagementService
{
    /**
     * Record a new stock receipt from a supplier into a collection center.
     */
    public function recordStockReceipt(array $data, User $actor, ?UploadedFile $proofFile = null): StockReceipt
    {
        return DB::transaction(function () use ($data, $actor, $proofFile) {
            $center = CollectionCenter::findOrFail($data['collection_center_id']);

            // Validate that the user is authorized for this center's district
            $this->validateUserCenterAccess($actor, $center);

            $item = SchemeStockItem::findOrFail($data['scheme_stock_item_id']);

            $proofPath = null;
            if ($proofFile) {
                $proofPath = $proofFile->store('stock/receipts', 'public');
            }

            $receiptNo = 'RCV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $receipt = StockReceipt::create([
                'receipt_no' => $receiptNo,
                'scheme_stock_item_id' => $item->id,
                'collection_center_id' => $center->id,
                'district_id' => $center->district_id,
                'quantity_received' => $data['quantity_received'],
                'received_date' => $data['received_date'] ?? now()->toDateString(),
                'supplier_source' => $data['supplier_source'] ?? null,
                'batch_no' => $data['batch_no'] ?? null,
                'received_by' => $actor->id,
                'proof_attachment_path' => $proofPath,
                'notes' => $data['notes'] ?? null,
            ]);

            // Update Center Inventory
            $inventory = $center->getInventoryForItem($item->id);
            $inventory->quantity_received = (float) $inventory->quantity_received + (float) $data['quantity_received'];
            $inventory->recalculate();

            return $receipt;
        });
    }

    /**
     * Transfer stock from one collection center to another with mandatory demand justification.
     */
    public function transferStock(array $data, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($data, $actor) {
            $fromCenter = CollectionCenter::findOrFail($data['from_center_id']);
            $toCenter = CollectionCenter::findOrFail($data['to_center_id']);

            if ($fromCenter->id === $toCenter->id) {
                throw ValidationException::withMessages([
                    'to_center_id' => 'Destination center must be different from source center.',
                ]);
            }

            // User must be authorized for source center
            $this->validateUserCenterAccess($actor, $fromCenter);

            $item = SchemeStockItem::findOrFail($data['scheme_stock_item_id']);
            $quantity = (float) $data['quantity'];

            // Check current stock balance at source center
            $fromInventory = $fromCenter->getInventoryForItem($item->id);
            if ((float) $fromInventory->current_balance < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Insufficient stock in {$fromCenter->name}. Available: {$fromInventory->current_balance} {$item->unit}, Requested: {$quantity} {$item->unit}.",
                ]);
            }

            // Ensure demand justification is provided
            if (empty(trim($data['demand_justification'] ?? ''))) {
                throw ValidationException::withMessages([
                    'demand_justification' => 'A valid justification explaining the demand requirement is mandatory for stock transfers.',
                ]);
            }

            $transferNo = 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $transfer = StockTransfer::create([
                'transfer_no' => $transferNo,
                'scheme_stock_item_id' => $item->id,
                'from_center_id' => $fromCenter->id,
                'to_center_id' => $toCenter->id,
                'quantity' => $quantity,
                'demand_justification' => trim($data['demand_justification']),
                'status' => 'completed',
                'transferred_by' => $actor->id,
                'received_by' => $actor->id,
                'transfer_date' => $data['transfer_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Debit from source center
            $fromInventory->quantity_transferred_out = (float) $fromInventory->quantity_transferred_out + $quantity;
            $fromInventory->recalculate();

            // Credit to destination center
            $toInventory = $toCenter->getInventoryForItem($item->id);
            $toInventory->quantity_transferred_in = (float) $toInventory->quantity_transferred_in + $quantity;
            $toInventory->recalculate();

            return $transfer;
        });
    }

    /**
     * Record wasted or damaged stock with mandatory proof photo or report.
     */
    public function recordStockDamage(array $data, User $actor, ?UploadedFile $proofFile = null): StockDamage
    {
        return DB::transaction(function () use ($data, $actor, $proofFile) {
            $center = CollectionCenter::findOrFail($data['collection_center_id']);

            // Validate center authorization
            $this->validateUserCenterAccess($actor, $center);

            $item = SchemeStockItem::findOrFail($data['scheme_stock_item_id']);
            $quantity = (float) $data['quantity'];

            // Check current stock balance
            $inventory = $center->getInventoryForItem($item->id);
            if ((float) $inventory->current_balance < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Damaged quantity cannot exceed current available stock ({$inventory->current_balance} {$item->unit}).",
                ]);
            }

            $proofPath = null;
            if ($proofFile) {
                $proofPath = $proofFile->store('stock/damages', 'public');
            }

            $refNo = 'DMG-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $damage = StockDamage::create([
                'reference_no' => $refNo,
                'collection_center_id' => $center->id,
                'scheme_stock_item_id' => $item->id,
                'quantity' => $quantity,
                'damage_type' => $data['damage_type'] ?? 'packaging_tear',
                'reason' => $data['reason'],
                'proof_file_path' => $proofPath,
                'recorded_by' => $actor->id,
                'action_taken' => $data['action_taken'] ?? null,
                'incident_date' => $data['incident_date'] ?? now()->toDateString(),
            ]);

            // Deduct from center inventory
            $inventory->quantity_damaged = (float) $inventory->quantity_damaged + $quantity;
            $inventory->recalculate();

            return $damage;
        });
    }

    /**
     * Compute Demand vs Available Stock metrics comparing approved cases with physical inventory.
     */
    public function getDemandVsStockMetrics(?int $districtId = null, ?int $schemeId = null): array
    {
        // 1. Available physical stock across centers
        $inventoryQuery = CenterInventory::query()
            ->join('collection_centers', 'center_inventories.collection_center_id', '=', 'collection_centers.id')
            ->join('scheme_stock_items', 'center_inventories.scheme_stock_item_id', '=', 'scheme_stock_items.id')
            ->join('schemes', 'scheme_stock_items.scheme_id', '=', 'schemes.id');

        if ($districtId) {
            $inventoryQuery->where('collection_centers.district_id', $districtId);
        }

        if ($schemeId) {
            $inventoryQuery->where('schemes.id', $schemeId);
        }

        $stockSummary = $inventoryQuery->selectRaw('
            SUM(center_inventories.quantity_received) as total_received,
            SUM(center_inventories.quantity_transferred_in) as total_transferred_in,
            SUM(center_inventories.quantity_transferred_out) as total_transferred_out,
            SUM(center_inventories.quantity_distributed) as total_distributed,
            SUM(center_inventories.quantity_damaged) as total_damaged,
            SUM(center_inventories.current_balance) as available_stock
        ')->first();

        $availableStock = (float) ($stockSummary->available_stock ?? 0);
        $totalDistributed = (float) ($stockSummary->total_distributed ?? 0);
        $totalDamaged = (float) ($stockSummary->total_damaged ?? 0);
        $totalReceived = (float) ($stockSummary->total_received ?? 0);

        // 2. Approved Applications Demand
        // Stages representing approved/eligible beneficiaries needing or having received stock
        $approvedStages = [
            Application::STAGE_ELIGIBLE_BENEFICIARY,
            Application::STAGE_PAYMENT_VERIFIED,
            Application::STAGE_STOCK_ALLOCATED,
            Application::STAGE_PARTIALLY_DISTRIBUTED,
            Application::STAGE_FULLY_DISTRIBUTED,
            'approved',
            'disbursed',
        ];

        $appQuery = Application::query()
            ->whereIn('current_stage', $approvedStages);

        if ($districtId) {
            $appQuery->where('district_id', $districtId);
        }

        if ($schemeId) {
            $appQuery->where('scheme_id', $schemeId);
        }

        $approvedCasesCount = (clone $appQuery)->count();

        // Calculate demanded units: use allocated_quantity, or fallback to 1 bag/unit per approved beneficiary
        $demandedUnits = (float) (clone $appQuery)->selectRaw('COALESCE(SUM(NULLIF(allocated_quantity, 0)), COUNT(*)) as demand')->value('demand');

        // Net Remaining Demand (Approved cases not yet fully disbursed)
        $pendingDistributionQuery = (clone $appQuery)->whereNotIn('current_stage', [
            Application::STAGE_FULLY_DISTRIBUTED,
            'disbursed'
        ]);

        $pendingCasesCount = (clone $pendingDistributionQuery)->count();
        $pendingDemandedUnits = (float) (clone $pendingDistributionQuery)
            ->selectRaw('COALESCE(SUM(NULLIF(allocated_quantity, 0)), COUNT(*)) as demand')
            ->value('demand');

        $variance = $availableStock - $pendingDemandedUnits;
        $sufficiencyRate = $pendingDemandedUnits > 0 ? min(100, round(($availableStock / $pendingDemandedUnits) * 100, 1)) : 100;

        // 3. Center-wise breakdown
        $centerBreakdownQuery = CollectionCenter::query()
            ->with(['district', 'tehsil', 'inventories.item']);

        if ($districtId) {
            $centerBreakdownQuery->where('district_id', $districtId);
        }

        $centers = $centerBreakdownQuery->get()->map(function ($c) {
            $totalCenterStock = (float) $c->inventories->sum('current_balance');
            $c->total_available_stock = $totalCenterStock;
            return $c;
        });

        // 4. Scheme items
        $schemeItems = SchemeStockItem::with('scheme')->get();

        return [
            'total_received' => $totalReceived,
            'available_stock' => $availableStock,
            'total_distributed' => $totalDistributed,
            'total_damaged' => $totalDamaged,
            'approved_cases_count' => $approvedCasesCount,
            'total_demanded_units' => $demandedUnits,
            'pending_cases_count' => $pendingCasesCount,
            'pending_demand_units' => $pendingDemandedUnits,
            'variance' => $variance, // Positive = surplus, Negative = deficit
            'is_deficit' => $variance < 0,
            'sufficiency_rate' => $sufficiencyRate,
            'centers' => $centers,
            'scheme_items' => $schemeItems,
        ];
    }

    /**
     * Validate that the user is authorized for the given collection center's district.
     */
    protected function validateUserCenterAccess(User $user, CollectionCenter $center): void
    {
        if ($user->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            return;
        }

        $authorizedDistricts = $user->authorizedDistrictIds();
        if (! empty($authorizedDistricts) && ! in_array($center->district_id, $authorizedDistricts, true)) {
            throw ValidationException::withMessages([
                'collection_center_id' => 'You do not have administrative authority over collection centers in this district.',
            ]);
        }
    }
}
