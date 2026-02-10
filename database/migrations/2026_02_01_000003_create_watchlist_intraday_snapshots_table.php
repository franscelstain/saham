<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWatchlistIntradaySnapshotsTable extends Migration
{
    public function up()
    {
        Schema::create('watchlist_intraday_snapshots', function (Blueprint $table) {
            $table->bigIncrements('snapshot_id');

            $table->date('trade_date'); // execution date
            $table->unsignedBigInteger('ticker_id');
            $table->string('ticker_code', 16);

            // When snapshot was captured (RFC3339 string allowed in payload; stored as datetime when possible)
            $table->dateTime('checked_at')->nullable();

            // Top-of-book and last
            $table->decimal('bid1', 18, 4)->nullable();
            $table->decimal('ask1', 18, 4)->nullable();
            $table->decimal('last', 18, 4)->nullable();
            $table->decimal('open', 18, 4)->nullable();
            $table->decimal('open_or_last_exec', 18, 4)->nullable();
            $table->decimal('spread_pct', 18, 6)->nullable();
            $table->integer('confirm_retry_count')->default(0);
            $table->dateTime('confirm_last_checked_at')->nullable();
            $table->dateTime('confirm_next_check_at')->nullable();

            // Optional orderbook depth (Top-3)
            $table->decimal('bid2', 18, 4)->nullable();
            $table->decimal('bid3', 18, 4)->nullable();
            $table->decimal('ask2', 18, 4)->nullable();
            $table->decimal('ask3', 18, 4)->nullable();

            $table->integer('bid_lots1')->nullable();
            $table->integer('bid_lots2')->nullable();
            $table->integer('bid_lots3')->nullable();
            $table->integer('ask_lots1')->nullable();
            $table->integer('ask_lots2')->nullable();
            $table->integer('ask_lots3')->nullable();

            $table->timestamps();

            $table->unique(['trade_date','ticker_id'], 'uq_watchlist_intraday_trade_ticker');
            $table->index(['trade_date','ticker_code'], 'idx_watchlist_intraday_trade_code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('watchlist_intraday_snapshots');
    }
}
