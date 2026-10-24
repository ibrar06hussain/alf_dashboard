<?php

namespace Database\Seeders;

use App\Models\CollectionCenter;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SchemeStockItem;
use App\Models\StockReceipt;
use App\Models\Tehsil;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StockManagementSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure permissions exist
        $permissions = [
            'stock.manage',
            'collection_centers.manage',
            'stock.transfer',
            'stock.record_damage',
            'stock.view_demand',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Grant to roles
        $rolesToGrant = ['Super Admin', 'Distribution Officer', 'District Deputy Director', 'District Officer'];
        foreach ($rolesToGrant as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        // 2. Ensure Scheme 1 has a SchemeStockItem
        $scheme = Scheme::find(1);
        $stockItem = null;
        if ($scheme) {
            $stockItem = SchemeStockItem::firstOrCreate(
                [
                    'scheme_id' => $scheme->id,
                    'item_name' => 'Certified Wheat Seed (Fakhr-e-Bhakkar / Zincol)',
                ],
                [
                    'item_code' => 'WHT-SEED-50KG',
                    'unit' => 'Bag (50kg)',
                    'description' => 'Certified high-yield wheat seed 50kg bag under 50:50 cost-sharing subsidy scheme.',
                    'is_active' => true,
                ]
            );
        }

        // 3. Create Collection Centers across districts
        $districts = District::all();
        $adminUser = User::whereHas('roles', fn($q) => $q->where('name', 'Super Admin'))->first();

        foreach ($districts as $district) {
            // District Central Depot
            $distCenter = CollectionCenter::firstOrCreate(
                [
                    'code' => 'CC-' . strtoupper($district->code ?? substr($district->name, 0, 3)) . '-MAIN-01',
                ],
                [
                    'name' => "{$district->name} Central Agriculture Depot",
                    'level' => 'district',
                    'district_id' => $district->id,
                    'tehsil_id' => null,
                    'village_name' => 'District Headquarters',
                    'focal_person_name' => "Deputy Director Agri {$district->name}",
                    'focal_person_phone' => '05812-920000',
                    'address' => "Main Agriculture Complex, {$district->name}",
                    'capacity_units' => 10000,
                    'is_active' => true,
                    'created_by' => $adminUser?->id,
                ]
            );

            // Tehsil Centers for each tehsil
            $tehsils = Tehsil::where('district_id', $district->id)->get();
            foreach ($tehsils as $idx => $tehsil) {
                CollectionCenter::firstOrCreate(
                    [
                        'code' => 'CC-' . strtoupper($district->code ?? substr($district->name, 0, 3)) . '-THS-' . str_pad($idx + 1, 2, '0', STR_PAD_LEFT),
                    ],
                    [
                        'name' => "{$tehsil->name} Tehsil Seed Distribution Center",
                        'level' => 'tehsil',
                        'district_id' => $district->id,
                        'tehsil_id' => $tehsil->id,
                        'village_name' => $tehsil->name . ' Bazar',
                        'focal_person_name' => "Assistant Director / Field Officer ({$tehsil->name})",
                        'focal_person_phone' => '0300-1234567',
                        'address' => "Agriculture Extension Office, {$tehsil->name}",
                        'capacity_units' => 3000,
                        'is_active' => true,
                        'created_by' => $adminUser?->id,
                    ]
                );
            }
        }

        // 4. Seed initial stock receipt for Diamer (District 9) and Gilgit (District 1) if stockItem exists
        if ($stockItem) {
            $diamerMainCenter = CollectionCenter::where('district_id', 9)->where('level', 'district')->first();
            if ($diamerMainCenter) {
                $inv = $diamerMainCenter->getInventoryForItem($stockItem->id);
                if ($inv->quantity_received == 0) {
                    $receipt = StockReceipt::firstOrCreate(
                        ['receipt_no' => 'RCV-20261025-DMR01'],
                        [
                            'scheme_stock_item_id' => $stockItem->id,
                            'collection_center_id' => $diamerMainCenter->id,
                            'district_id' => 9,
                            'quantity_received' => 2500,
                            'received_date' => now()->toDateString(),
                            'supplier_source' => 'Punjab Seed Corporation (Consignment #ALF-GB-2026)',
                            'batch_no' => 'LOT-WHT-2026-9B',
                            'received_by' => $adminUser?->id,
                            'notes' => 'Central shipment of 2,500 Certified Wheat Seed 50kg Bags received at Chilas Main Depot.',
                        ]
                    );
                    $inv->quantity_received = 2500;
                    $inv->recalculate();
                }
            }

            $gilgitMainCenter = CollectionCenter::where('district_id', 1)->where('level', 'district')->first();
            if ($gilgitMainCenter) {
                $invG = $gilgitMainCenter->getInventoryForItem($stockItem->id);
                if ($invG->quantity_received == 0) {
                    StockReceipt::firstOrCreate(
                        ['receipt_no' => 'RCV-20261025-GLT01'],
                        [
                            'scheme_stock_item_id' => $stockItem->id,
                            'collection_center_id' => $gilgitMainCenter->id,
                            'district_id' => 1,
                            'quantity_received' => 1500,
                            'received_date' => now()->toDateString(),
                            'supplier_source' => 'Federal Seed Certification & Registration Dept',
                            'batch_no' => 'LOT-WHT-2026-1A',
                            'received_by' => $adminUser?->id,
                            'notes' => 'Initial batch of 1,500 bags for Gilgit district distribution.',
                        ]
                    );
                    $invG->quantity_received = 1500;
                    $invG->recalculate();
                }
            }
        }
    }
}
