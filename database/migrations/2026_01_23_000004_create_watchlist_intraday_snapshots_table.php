<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWatchlistIntradaySnapshotsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('watchlist_intraday_snapshots')) return;

        Schema::create('watchlist_intraday_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('trade_date')->index();
            $table->unsignedBigInteger('ticker_id')->index();
            // preopen/open or latest executable price proxy
            $table->double('open_or_last_exec')->nullable();
            // optional spread proxy (0..1)
            $table->double('spread_pct')->nullable();
            $table->string('source', 32)->nullable();
            $table->timestamps();

            $table->unique(['trade_date', 'ticker_id'], 'wis_date_ticker_uq');
        });
    }

    public function down()
    {
        Schema::dropIfExists('watchlist_intraday_snapshots');
    }
}
