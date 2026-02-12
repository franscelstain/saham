<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticker_indicators_daily', function (Blueprint $table) {
            // NOTE:
            // - These are policy-facing rollups that are computed deterministically in compute-eod.
            // - Semantics:
            //   dv20_idr, hh20, ll5 use exclude-today window (pre-trade guard for next session).
            //   roc20 uses include-today (needs 21 price_used values).
            if (!Schema::hasColumn('ticker_indicators_daily', 'dv20_idr')) {
                $table->decimal('dv20_idr', 26, 2)->nullable()->after('vol_ratio');
            }
            if (!Schema::hasColumn('ticker_indicators_daily', 'atr14_pct')) {
                $table->decimal('atr14_pct', 10, 6)->nullable()->after('dv20_idr');
            }
            if (!Schema::hasColumn('ticker_indicators_daily', 'hh20')) {
                $table->decimal('hh20', 18, 4)->nullable()->after('atr14_pct');
            }
            if (!Schema::hasColumn('ticker_indicators_daily', 'll5')) {
                $table->decimal('ll5', 18, 4)->nullable()->after('hh20');
            }
            if (!Schema::hasColumn('ticker_indicators_daily', 'roc20')) {
                $table->decimal('roc20', 10, 6)->nullable()->after('ll5');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticker_indicators_daily', function (Blueprint $table) {
            foreach (['roc20', 'll5', 'hh20', 'atr14_pct', 'dv20_idr'] as $col) {
                if (Schema::hasColumn('ticker_indicators_daily', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
