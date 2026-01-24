<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTickerStatusDailyTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ticker_status_daily')) return;

        Schema::create('ticker_status_daily', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ticker_id');
            $table->date('trade_date')->index();
            // stored as CSV ("E,X") or JSON ("["E","X"]")
            $table->text('special_notations')->nullable();
            $table->boolean('is_suspended')->default(false);
            $table->string('status_quality', 16)->default('UNKNOWN'); // OK|STALE|UNKNOWN
            $table->string('trading_mechanism', 32)->default('REGULAR'); // REGULAR|FULL_CALL_AUCTION
            $table->string('source', 32)->nullable();
            $table->timestamps();

            $table->unique(['ticker_id', 'trade_date'], 'tsd_ticker_date_uq');
            $table->index(['trade_date', 'status_quality'], 'tsd_date_quality_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticker_status_daily');
    }
}
