<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWatchlistDailyTable extends Migration
{
    public function up()
    {
        Schema::create('watchlist_daily', function (Blueprint $table) {
            $table->bigIncrements('watchlist_daily_id');

            // Strict preopen contract meta
            $table->string('policy', 32);
            $table->date('trade_date');      // execution date (contract meta.trade_date)
            $table->date('asof_eod_date');   // EOD date used for PLAN (contract meta.asof_eod_date)
            $table->boolean('canonical_ready')->default(false);

            // Persistence/audit
            $table->string('source', 64)->default('preopen_contract');
            $table->dateTime('generated_at')->nullable();
            $table->json('payload_json');

            $table->timestamps();

            $table->unique(['policy','trade_date','source'], 'uq_watchlist_daily_policy_trade_source');
            $table->index(['trade_date', 'policy'], 'idx_watchlist_daily_trade_policy');
            $table->index(['asof_eod_date', 'policy'], 'idx_watchlist_daily_eod_policy');
        });
    }

    public function down()
    {
        Schema::dropIfExists('watchlist_daily');
    }
}
