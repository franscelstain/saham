<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConfirmRetryFieldsToWatchlistIntradaySnapshots extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('watchlist_intraday_snapshots')) {
            return;
        }

        Schema::table('watchlist_intraday_snapshots', function (Blueprint $table) {
            // Retry budget state for strict CONFIRM (docs/watchlist/scorecard.md)
            if (!Schema::hasColumn('watchlist_intraday_snapshots', 'confirm_retry_count')) {
                $table->integer('confirm_retry_count')->default(0)->after('spread_pct');
            }
            if (!Schema::hasColumn('watchlist_intraday_snapshots', 'confirm_last_checked_at')) {
                $table->dateTime('confirm_last_checked_at')->nullable()->after('confirm_retry_count');
            }
            if (!Schema::hasColumn('watchlist_intraday_snapshots', 'confirm_next_check_at')) {
                $table->dateTime('confirm_next_check_at')->nullable()->after('confirm_last_checked_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('watchlist_intraday_snapshots')) {
            return;
        }

        Schema::table('watchlist_intraday_snapshots', function (Blueprint $table) {
            // SQLite doesn't support dropColumn with multiple columns in one call on older versions.
            if (Schema::hasColumn('watchlist_intraday_snapshots', 'confirm_next_check_at')) {
                $table->dropColumn('confirm_next_check_at');
            }
            if (Schema::hasColumn('watchlist_intraday_snapshots', 'confirm_last_checked_at')) {
                $table->dropColumn('confirm_last_checked_at');
            }
            if (Schema::hasColumn('watchlist_intraday_snapshots', 'confirm_retry_count')) {
                $table->dropColumn('confirm_retry_count');
            }
        });
    }
}
