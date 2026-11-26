<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\ApplicationItem;
use App\Models\ChallanTemplate;
use App\Models\FormField;
use App\Models\Scheme;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchemeItemSyncService
{
    public function __construct(private GbpayService $gbpay)
    {
    }

    /**
     * Parse legacy text values such as:
     * - "Apple: 20; Apricot: 5; Cherry: 150 chery"
     * - "Cherry: ۲۰; Walnut: ۱۰"
     * - "Almond: , 50; Lemon: , 5"
     *
     * @return array<int, array{label: string, qty: float, explicit_zero: bool}>
     */
    public function parseLegacyFieldValue(string $rawValue): array
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '') {
            return [];
        }

        // Preserve commas inside segments like "Almond: , 50" when semicolons exist;
        // otherwise split on commas only when followed by a label letter.
        $segments = str_contains($rawValue, ';')
            ? explode(';', $rawValue)
            : preg_split('/,(?=\s*[A-Za-z\p{L}])/u', $rawValue);

        $aggregated = [];

        foreach ($segments ?: [] as $part) {
            $part = trim((string) $part);
            if ($part === '' || ! str_contains($part, ':')) {
                continue;
            }

            [$rawLabel, $rawQtyText] = array_map('trim', explode(':', $part, 2));
            if ($rawLabel === '') {
                continue;
            }

            $qtyText = $this->normalizeNumerals($rawQtyText);
            $qtyTextLower = strtolower(trim($qtyText));

            if ($qtyTextLower === '' || preg_match('/^(nil|none|null|n\/a|-+)$/', $qtyTextLower)) {
                continue;
            }

            $explicitZero = false;
            $qty = 0.0;

            if (preg_match('/(\d+(?:\.\d+)?)/', $qtyText, $m)) {
                $qty = (float) $m[1];
                if ($qty <= 0) {
                    $explicitZero = true;
                }
            }

            if ($explicitZero) {
                continue;
            }

            $normKey = ApplicationItem::normalizeKey($rawLabel);
            if ($normKey === '') {
                continue;
            }

            if (! isset($aggregated[$normKey])) {
                $aggregated[$normKey] = [
                    'label' => $rawLabel,
                    'qty' => $qty,
                    'explicit_zero' => false,
                ];
            } else {
                $aggregated[$normKey]['qty'] += $qty;
            }
        }

        return array_values($aggregated);
    }

    /**
     * Backfill missing application_items from legacy application_field_values,
     * synchronize unpaid application_items pricing from current Form Builder options,
     * and regenerate/cancel unpaid challans & pending PSID bills for approved apps.
     *
     * Paid applications & paid bills are never modified.
     *
     * @return array<string, int>
     */
    public function syncScheme(Scheme $scheme, bool $dryRun = false, ?array $onlyAppIds = null): array
    {
        $scheme->loadMissing(['challanTemplate', 'publishedForm.fields']);

        $stats = [
            'apps_scanned' => 0,
            'apps_skipped_paid_or_terminal' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'demanded_qty_updated' => 0,
            'approved_apps_synced' => 0,
            'challans_updated_or_generated' => 0,
            'challans_cancelled_to_eligible' => 0,
        ];

        $form = $scheme->publishedForm ?: $scheme->forms()->with('fields')->latest('version')->first();
        if (! $form) {
            return $stats;
        }

        $form->loadMissing('fields');
        $billableFields = $form->fields
            ->filter(fn (FormField $f) => $f->field_type === 'checkbox_with_text' || $f->is_billable_items_field)
            ->values();

        if ($billableFields->isEmpty()) {
            return $stats;
        }

        $primaryField = $billableFields->first();
        $fallbackKeyFieldIds = $form->fields
            ->whereIn('field_key', $billableFields->pluck('field_key')->filter()->unique()->all())
            ->pluck('id')
            ->all();

        $template = $scheme->getChallanTemplate();
        $schemeUnitLabel = $scheme->resolveUnitLabel();

        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            $appQuery = Application::query()
                ->where('scheme_id', $scheme->id)
                ->when(! empty($onlyAppIds), fn ($q) => $q->whereIn('id', $onlyAppIds))
                ->orderBy('id');

            $appQuery->chunkById(200, function (Collection $apps) use (
                &$stats,
                $billableFields,
                $primaryField,
                $fallbackKeyFieldIds,
                $template,
                $schemeUnitLabel
            ) {
                $appIds = $apps->pluck('id')->all();

                $preSync = function () use (
                    $appIds,
                    &$stats,
                    $billableFields,
                    $primaryField,
                    $fallbackKeyFieldIds,
                    $template,
                    $schemeUnitLabel
                ) {
                    $lockedApps = Application::query()
                        ->whereIn('id', $appIds)
                        ->with(['items', 'bills', 'fieldValues'])
                        ->lockForUpdate()
                        ->get();

                    $batchInsertRows = [];
                    $now = now();

                    foreach ($lockedApps as $lockedApp) {
                        $stats['apps_scanned']++;

                        if ($this->isProtectedFromMutation($lockedApp)) {
                            $stats['apps_skipped_paid_or_terminal']++;
                            continue;
                        }

                        $stage = $lockedApp->current_stage ?: $lockedApp->status;
                        $isApprovedStage = in_array($stage, [
                            Application::STAGE_APPROVED,
                            Application::STAGE_CHALLAN_ISSUED,
                            Application::STAGE_ELIGIBLE_BENEFICIARY,
                            'directorate_approved',
                        ], true);

                        if ($lockedApp->items->isEmpty()) {
                            $newRows = $this->backfillLegacyItemsForApplication(
                                $lockedApp,
                                $billableFields,
                                $primaryField,
                                $fallbackKeyFieldIds,
                                $template,
                                $schemeUnitLabel,
                                $isApprovedStage,
                                $stats,
                                $now
                            );
                            array_push($batchInsertRows, ...$newRows);
                        } else {
                            $this->syncExistingItemsForApplication(
                                $lockedApp,
                                $billableFields,
                                $template,
                                $schemeUnitLabel,
                                $stats
                            );
                        }
                    }

                    foreach (array_chunk($batchInsertRows, 500) as $insertChunk) {
                        ApplicationItem::insert($insertChunk);
                    }
                };

                if (DB::transactionLevel() > 0) {
                    $preSync();
                } else {
                    DB::transaction($preSync);
                }
            });

            // Snapshot approved unpaid challan states before financial/PSID sync
            $beforeStates = Application::query()
                ->where('scheme_id', $scheme->id)
                ->when(! empty($onlyAppIds), fn ($q) => $q->whereIn('id', $onlyAppIds))
                ->get(['id', 'current_stage', 'challan_amount', 'psid'])
                ->keyBy('id');

            $syncedCount = $this->gbpay->syncUnpaidApplicationsForScheme($scheme, true);
            $stats['approved_apps_synced'] = $syncedCount;

            $afterStates = Application::query()
                ->whereIn('id', $beforeStates->keys())
                ->get(['id', 'current_stage', 'challan_amount', 'psid'])
                ->keyBy('id');

            foreach ($afterStates as $id => $after) {
                $before = $beforeStates->get($id);
                if (! $before) {
                    continue;
                }

                if ($before->current_stage !== Application::STAGE_ELIGIBLE_BENEFICIARY
                    && $after->current_stage === Application::STAGE_ELIGIBLE_BENEFICIARY) {
                    $stats['challans_cancelled_to_eligible']++;
                } elseif ((string) ($before->challan_amount ?? '') !== (string) ($after->challan_amount ?? '')
                    || (string) ($before->psid ?? '') !== (string) ($after->psid ?? '')
                    || ($before->current_stage !== $after->current_stage && $after->current_stage === Application::STAGE_CHALLAN_ISSUED)) {
                    $stats['challans_updated_or_generated']++;
                }
            }
        } finally {
            if ($dryRun && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        return $stats;
    }

    private function isProtectedFromMutation(Application $app): bool
    {
        if ($app->isRejected() || $app->isChallanPaid()) {
            return true;
        }

        if ($app->bills->where('status', 'paid')->isNotEmpty()) {
            return true;
        }

        if ((float) ($app->distributed_quantity ?? 0) > 0) {
            return true;
        }

        $stage = $app->current_stage ?: $app->status;

        return in_array($stage, [
            Application::STAGE_DORMANT,
            Application::STAGE_REJECTED,
            Application::STAGE_PAYMENT_VERIFIED,
            Application::STAGE_STOCK_ALLOCATED,
            Application::STAGE_PARTIALLY_DISTRIBUTED,
            Application::STAGE_FULLY_DISTRIBUTED,
            Application::STAGE_COMPLETED,
            Application::STAGE_DISBURSED,
            Application::STATUS_REJECTED,
            Application::STATUS_DISBURSED,
        ], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function backfillLegacyItemsForApplication(
        Application $app,
        Collection $billableFields,
        FormField $primaryField,
        array $fallbackKeyFieldIds,
        ?ChallanTemplate $template,
        string $schemeUnitLabel,
        bool $isApprovedStage,
        array &$stats,
        \DateTimeInterface $timestamp
    ): array {
        $fieldIdsOrder = array_values(array_unique(array_merge(
            $billableFields->pluck('id')->all(),
            $fallbackKeyFieldIds
        )));

        $fvByFieldId = $app->fieldValues->keyBy('form_field_id');
        $rawText = '';
        $matchedField = $primaryField;

        foreach ($fieldIdsOrder as $fid) {
            $candidate = trim((string) ($fvByFieldId->get($fid)?->value ?? ''));
            if ($candidate !== '') {
                $rawText = $candidate;
                $foundBillable = $billableFields->firstWhere('id', $fid);
                if ($foundBillable) {
                    $matchedField = $foundBillable;
                }
                break;
            }
        }

        if ($rawText === '') {
            return [];
        }

        $parsedItems = $this->parseLegacyFieldValue($rawText);
        if (empty($parsedItems)) {
            return [];
        }

        $usedKeys = [];
        $totalDemanded = 0.0;
        $totalApproved = 0.0;
        $rowsToInsert = [];

        foreach ($parsedItems as $entry) {
            $cfg = $this->resolveConfigByLabel($matchedField, $entry['label'])
                ?? $this->resolveConfigAcrossFields($billableFields, $entry['label']);

            $itemLabel = $cfg['label'] ?? $entry['label'];
            $baseKey = ApplicationItem::normalizeKey($itemLabel);
            if ($baseKey === '') {
                $baseKey = trim((string) ($cfg['key'] ?? 'item'));
            }

            $itemKey = $baseKey;
            $suffix = 2;
            while (in_array($itemKey, $usedKeys, true)) {
                $itemKey = $baseKey . '_' . $suffix++;
            }
            $usedKeys[] = $itemKey;

            $pricing = $this->extractPricingFromConfig($cfg, $template, $schemeUnitLabel);
            $qty = max(0.0, (float) $entry['qty']);

            $appItem = new ApplicationItem([
                'application_id' => $app->id,
                'form_field_id' => $matchedField->id,
                'item_key' => $itemKey,
                'item_label' => $itemLabel,
                'item_label_ur' => $cfg['label_ur'] ?? null,
                'unit_label' => $pricing['unit_label'],
                'is_free' => $pricing['is_free'],
                'unit_price' => $pricing['unit_price'],
                'govt_share_pct' => $pricing['govt_share_pct'],
                'inherits_template_share' => $pricing['inherits_template_share'],
                'farmer_share_pct' => $pricing['farmer_share_pct'],
                'demanded_qty' => $qty,
                'approved_qty' => $isApprovedStage ? $qty : null,
                'disbursed_qty' => 0,
            ]);
            $appItem->recalculateFinancials($template, false);

            $rowsToInsert[] = [
                'application_id' => $appItem->application_id,
                'form_field_id' => $appItem->form_field_id,
                'item_key' => $appItem->item_key,
                'item_label' => $appItem->item_label,
                'item_label_ur' => $appItem->item_label_ur,
                'unit_label' => $appItem->unit_label,
                'is_free' => (bool) $appItem->is_free,
                'unit_price' => $appItem->unit_price,
                'govt_share_pct' => $appItem->govt_share_pct,
                'inherits_template_share' => (bool) $appItem->inherits_template_share,
                'farmer_share_pct' => $appItem->farmer_share_pct,
                'demanded_qty' => $appItem->demanded_qty,
                'approved_qty' => $appItem->approved_qty,
                'disbursed_qty' => 0,
                'gross_amount' => $appItem->gross_amount,
                'govt_share_amount' => $appItem->govt_share_amount,
                'farmer_payable_amount' => $appItem->farmer_payable_amount,
                'scheme_stock_item_id' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            $totalDemanded += $qty;
            if ($isApprovedStage) {
                $totalApproved += $qty;
            }
            $stats['items_created']++;
        }

        $dirty = false;
        if ($totalDemanded > 0 && abs((float) ($app->demanded_quantity ?? 0) - $totalDemanded) > 0.001) {
            $app->demanded_quantity = $totalDemanded;
            $dirty = true;
            $stats['demanded_qty_updated']++;
        }

        if ($isApprovedStage && abs((float) ($app->approved_quantity ?? 0) - $totalApproved) > 0.001) {
            $app->approved_quantity = $totalApproved;
            $dirty = true;
        }

        if ($dirty) {
            $app->saveQuietly();
        }

        return $rowsToInsert;
    }

    private function syncExistingItemsForApplication(
        Application $app,
        Collection $billableFields,
        ?ChallanTemplate $template,
        string $schemeUnitLabel,
        array &$stats
    ): void {
        foreach ($app->items as $item) {
            $cfg = $this->resolveConfigAcrossFields($billableFields, (string) $item->item_label);
            if (! $cfg) {
                continue;
            }

            $pricing = $this->extractPricingFromConfig($cfg, $template, $schemeUnitLabel);

            $needsUpdate =
                ((bool) $item->is_free !== $pricing['is_free'])
                || abs((float) $item->unit_price - $pricing['unit_price']) > 0.001
                || ((bool) $item->inherits_template_share !== $pricing['inherits_template_share'])
                || abs((float) $item->govt_share_pct - $pricing['govt_share_pct']) > 0.01
                || ((string) ($item->unit_label ?? '') !== $pricing['unit_label'])
                || (! empty($cfg['label_ur']) && $item->item_label_ur !== $cfg['label_ur']);

            if (! $needsUpdate) {
                continue;
            }

            $item->unit_label = $pricing['unit_label'];
            if (! empty($cfg['label_ur'])) {
                $item->item_label_ur = $cfg['label_ur'];
            }
            $item->is_free = $pricing['is_free'];
            $item->unit_price = $pricing['unit_price'];
            $item->inherits_template_share = $pricing['inherits_template_share'];
            $item->govt_share_pct = $pricing['govt_share_pct'];
            $item->farmer_share_pct = $pricing['farmer_share_pct'];
            $item->recalculateFinancials($template, false)->save();

            $stats['items_updated']++;
        }
    }

    private function resolveConfigAcrossFields(Collection $billableFields, string $itemLabel): ?array
    {
        foreach ($billableFields as $field) {
            $cfg = $this->resolveConfigByLabel($field, $itemLabel);
            if ($cfg) {
                return $cfg;
            }
        }

        return null;
    }

    /**
     * Match Form Builder option strictly by display label (case-insensitive or normalized key of label),
     * preventing stale legacy option keys (e.g., key="cherry" on label="Apple") from shadowing another item.
     */
    private function resolveConfigByLabel(FormField $field, string $label): ?array
    {
        $needle = strtolower(trim($label));
        $normalizedNeedle = ApplicationItem::normalizeKey($label);
        if ($needle === '') {
            return null;
        }

        $configs = $field->normalizedOptionConfigs();

        foreach ($configs as $cfg) {
            if (strtolower(trim((string) $cfg['label'])) === $needle) {
                return $cfg;
            }
        }

        foreach ($configs as $cfg) {
            if (ApplicationItem::normalizeKey((string) $cfg['label']) === $normalizedNeedle) {
                return $cfg;
            }
        }

        return null;
    }

    /**
     * @return array{unit_label: string, is_free: bool, unit_price: float, inherits_template_share: bool, govt_share_pct: float, farmer_share_pct: float}
     */
    private function extractPricingFromConfig(?array $cfg, ?ChallanTemplate $template, string $schemeUnitLabel): array
    {
        if (! $cfg) {
            return [
                'unit_label' => $schemeUnitLabel,
                'is_free' => true,
                'unit_price' => 0.0,
                'inherits_template_share' => false,
                'govt_share_pct' => 100.0,
                'farmer_share_pct' => 0.0,
            ];
        }

        $pricingMode = $cfg['pricing_mode'] ?? 'free';
        $unitPrice = $pricingMode === 'paid' ? max(0.0, (float) ($cfg['unit_price'] ?? 0)) : 0.0;
        $isFree = ($pricingMode !== 'paid' || $unitPrice <= 0);

        $rawUnit = trim((string) ($cfg['unit_label'] ?? ''));
        $unitLabel = ($rawUnit !== '' && strcasecmp($rawUnit, 'Units') !== 0) ? $rawUnit : $schemeUnitLabel;

        $hasExplicitGovt = isset($cfg['govt_share_pct']) && $cfg['govt_share_pct'] !== '' && $cfg['govt_share_pct'] !== null;
        $inheritsTemplateShare = ! $isFree && ! $hasExplicitGovt;

        if ($isFree) {
            $govtSharePct = 100.0;
            $farmerSharePct = 0.0;
        } elseif ($hasExplicitGovt) {
            $govtSharePct = max(0.0, min(100.0, (float) $cfg['govt_share_pct']));
            $farmerSharePct = max(0.0, min(100.0, round(100.0 - $govtSharePct, 2)));
        } else {
            $govtSharePct = max(0.0, min(100.0, (float) ($template?->govt_share_pct ?? 0)));
            $farmerSharePct = max(0.0, min(100.0, round(100.0 - $govtSharePct, 2)));
        }

        return [
            'unit_label' => $unitLabel,
            'is_free' => $isFree,
            'unit_price' => $unitPrice,
            'inherits_template_share' => $inheritsTemplateShare,
            'govt_share_pct' => $govtSharePct,
            'farmer_share_pct' => $farmerSharePct,
        ];
    }

    private function normalizeNumerals(string $input): string
    {
        return strtr($input, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '⁰' => '0', '¹' => '1', '²' => '2', '³' => '3', '⁴' => '4',
            '⁵' => '5', '⁶' => '6', '⁷' => '7', '⁸' => '8', '⁹' => '9',
        ]);
    }
}
