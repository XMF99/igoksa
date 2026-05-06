<?php

namespace App\Console\Commands\Sahab;

use App\Models\Sahab\Asset;
use App\Models\Sahab\AssetDepreciation;
use App\Models\Sahab\JournalEntry;
use App\Models\Sahab\JournalLine;
use App\Models\Sahab\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Command: sahab:depreciate-assets
 *  يحسب الإهلاك الشهري للأصول الثابتة
 *  ويسجّل قيود محاسبيّة آليّة
 *
 *  جدولة: أوّل كل شهر 9 صباحاً
 */
class DepreciateAssetsCommand extends Command
{
    protected $signature = 'sahab:depreciate-assets
                          {--month= : الشهر (1-12)}
                          {--year= : السنة}';
    protected $description = 'حساب وتسجيل إهلاك الأصول الثابتة شهرياً';

    public function handle(): int
    {
        $month = (int) ($this->option('month') ?? now()->subMonth()->month);
        $year  = (int) ($this->option('year') ?? now()->subMonth()->year);

        $this->info("💰 حساب الإهلاك للشهر {$month}/{$year}");
        $count = 0;
        $totalAmount = 0;

        $assets = Asset::where('status', 'active')
            ->where('depreciation_method', 'straight_line')
            ->where('purchase_date', '<=', now()->endOfMonth())
            ->cursor();

        foreach ($assets as $asset) {
            try {
                // تجنّب تكرار التسجيل
                $exists = AssetDepreciation::where('asset_id', $asset->id)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->exists();
                if ($exists) continue;

                $monthlyDep = $asset->monthlyDepreciation();
                if ($monthlyDep <= 0) continue;

                // لا تتجاوز قيمة المُتبقي
                $remaining = $asset->purchase_cost - $asset->accumulated_depreciation - $asset->salvage_value;
                $monthlyDep = min($monthlyDep, $remaining);
                if ($monthlyDep <= 0) continue;

                DB::transaction(function () use ($asset, $month, $year, $monthlyDep) {
                    // 1) تسجيل الإهلاك
                    $newAccumulated = $asset->accumulated_depreciation + $monthlyDep;
                    $newBookValue = $asset->purchase_cost - $newAccumulated;

                    // 2) قيد محاسبي
                    $journalEntry = $this->createDepreciationJournalEntry($asset, $monthlyDep, $month, $year);

                    AssetDepreciation::create([
                        'tenant_id' => $asset->tenant_id,
                        'asset_id'  => $asset->id,
                        'year'      => $year,
                        'month'     => $month,
                        'depreciation_amount' => $monthlyDep,
                        'book_value_after'    => $newBookValue,
                        'journal_entry_id'    => $journalEntry?->id,
                    ]);

                    $asset->update([
                        'accumulated_depreciation' => $newAccumulated,
                        'current_value' => $newBookValue,
                    ]);
                });

                $count++;
                $totalAmount += $monthlyDep;
                $this->line("  ✓ {$asset->name} — " . round($monthlyDep, 2) . " ر.س");
            } catch (\Exception $e) {
                $this->error("  ✗ خطأ: {$e->getMessage()}");
            }
        }

        $this->info("");
        $this->info("📊 المجموع: {$count} أصل | " . round($totalAmount, 2) . " ر.س");

        return self::SUCCESS;
    }

    protected function createDepreciationJournalEntry($asset, $amount, $month, $year): ?JournalEntry
    {
        // البحث عن حسابات الإهلاك (يجب أن تكون موجودة في دليل الحسابات)
        $depExpense = Account::where('tenant_id', $asset->tenant_id)
            ->where('code', 'like', '5%')
            ->where('name_ar', 'like', '%إهلاك%')
            ->first();

        $accumulatedDep = Account::where('tenant_id', $asset->tenant_id)
            ->where('code', 'like', '12%')
            ->where('name_ar', 'like', '%مجمع إهلاك%')
            ->first();

        if (!$depExpense || !$accumulatedDep) {
            return null; // المنشأة لا تستخدم نظام محاسبي كامل
        }

        $entry = JournalEntry::create([
            'tenant_id'    => $asset->tenant_id,
            'entry_number' => 'DEP-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . $asset->id,
            'entry_date'   => now()->setYear($year)->setMonth($month)->endOfMonth(),
            'type'         => 'depreciation',
            'reference_type' => 'asset',
            'reference_id' => $asset->id,
            'description'  => "إهلاك أصل: {$asset->name} لشهر {$month}/{$year}",
            'total_debit'  => $amount,
            'total_credit' => $amount,
            'status'       => 'posted',
            'posted_at'    => now(),
        ]);

        // مدين: مصروف الإهلاك
        JournalLine::create([
            'tenant_id'  => $asset->tenant_id,
            'entry_id'   => $entry->id,
            'account_id' => $depExpense->id,
            'cost_center_id' => $asset->cost_center_id,
            'debit'      => $amount,
            'credit'     => 0,
            'description'=> "إهلاك {$asset->name}",
            'line_order' => 1,
        ]);

        // دائن: مجمع الإهلاك
        JournalLine::create([
            'tenant_id'  => $asset->tenant_id,
            'entry_id'   => $entry->id,
            'account_id' => $accumulatedDep->id,
            'debit'      => 0,
            'credit'     => $amount,
            'description'=> "مجمع إهلاك {$asset->name}",
            'line_order' => 2,
        ]);

        return $entry;
    }
}
