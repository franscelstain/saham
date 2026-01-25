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

            $table->date('trade_date')->index(); // EOD date used
            $table->string('source', 32)->default('preopen');

            // persisted JSON payload for audit/replay
            $table->longText('payload_json');

            $table->timestamp('generated_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['trade_date', 'source'], 'watchlist_daily_trade_date_source_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('watchlist_daily');
    }
}
