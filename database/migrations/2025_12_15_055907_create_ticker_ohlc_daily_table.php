<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTickerOhlcDailyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticker_ohlc_daily', function (Blueprint $table) {
            $table->bigIncrements('ohlc_daily_id');
            $table->unsignedBigInteger('ticker_id');
            $table->date('trade_date');

            $table->decimal('open', 18, 4);
            $table->decimal('high', 18, 4);
            $table->decimal('low', 18, 4);
            $table->decimal('close', 18, 4);

            $table->decimal('adj_close', 18, 4)->nullable();
            $table->unsignedBigInteger('volume');

            $table->string('source', 30)->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();

            $table->unique(['ticker_id', 'trade_date'], 'uq_ohlc_daily_ticker_date');
            $table->index(['trade_date'], 'idx_ohlc_daily_date');

            $table->foreign('ticker_id', 'fk_ohlc_daily_ticker')
                ->references('ticker_id')->on('tickers')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ticker_ohlc_daily');
    }
}
