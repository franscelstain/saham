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
            $table->engine = 'InnoDB';
            $table->bigIncrements('ohlc_daily_id');
            $table->unsignedBigInteger('ticker_id');
            $table->unsignedBigInteger('run_id')->nullable();
            $table->date('trade_date');

            $table->decimal('open', 18, 4)->nullable();
            $table->decimal('high', 18, 4)->nullable();
            $table->decimal('low', 18, 4)->nullable();
            $table->decimal('close', 18, 4)->nullable();

            $table->decimal('adj_close', 18, 4)->nullable();
            $table->enum('price_basis', ['close', 'adj_close'])->nullable();

            $table->unsignedBigInteger('volume')->nullable();

            $table->string('ca_hint', 32)->nullable();
            $table->string('ca_event', 32)->nullable();
            $table->string('source', 30)->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['ticker_id', 'trade_date'], 'uq_ohlc_daily_ticker_date');
            $table->index('trade_date', 'idx_ohlc_daily_date');
            $table->index(['trade_date', 'ca_event'], 'idx_ohlc_ca_event_date');

            $table->foreign('ticker_id', 'ticker_ohlc_daily_ticker_id_foreign')
                ->references('ticker_id')->on('tickers')
                ->onUpdate('cascade');

            $table->foreign('run_id', 'ticker_ohlc_daily_run_id_foreign')
                ->references('run_id')->on('md_runs')
                ->onUpdate('cascade');
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
