<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTickerIndicatorsDailyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticker_indicators_daily', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->bigIncrements('indicator_daily_id');
            $table->unsignedBigInteger('ticker_id');
            $table->date('trade_date');

            // snapshot EOD (optional tapi membantu debug)
            $table->decimal('open', 18, 4)->nullable();
            $table->decimal('high', 18, 4)->nullable();
            $table->decimal('low', 18, 4)->nullable();
            $table->decimal('close', 18, 4)->nullable();
            $table->decimal('adj_close', 18, 4)->nullable();
            $table->string('ca_hint', 32)->nullable();
            $table->string('ca_event', 32)->nullable();
            $table->enum('basis_used', ['close', 'adj_close'])->nullable();
            $table->decimal('price_used', 18, 4)->nullable();
            $table->unsignedBigInteger('volume')->nullable();

            // moving averages
            $table->decimal('ma20', 18, 4)->nullable();
            $table->decimal('ma50', 18, 4)->nullable();
            $table->decimal('ma200', 18, 4)->nullable();

            // volume metrics
            $table->decimal('vol_sma20', 20, 4)->nullable();
            $table->decimal('vol_ratio', 12, 4)->nullable(); // volume / vol_sma20

            $table->decimal('dv20_idr', 26, 2)->nullable();
            $table->decimal('atr14_pct', 10, 6)->nullable();
            $table->decimal('hh20', 18, 4)->nullable();
            $table->decimal('ll5', 18, 4)->nullable();
            $table->decimal('roc20', 10, 6)->nullable();

            // momentum / volatility
            $table->decimal('rsi14', 6, 2)->nullable();
            $table->decimal('atr14', 18, 4)->nullable();

            // support & resistance (rolling)
            $table->decimal('support_20d', 18, 4)->nullable();
            $table->decimal('resistance_20d', 18, 4)->nullable();

            $table->boolean('is_valid')->default(true);
            $table->string('invalid_reason', 64)->nullable();

            $table->string('source', 30)->nullable();

            $table->boolean('is_deleted')->default(false);

            // match DDL: timestamp NULL DEFAULT NULL
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['ticker_id', 'trade_date'], 'uq_ind_daily_ticker_date');

            $table->index(['trade_date', 'vol_ratio'], 'idx_ind_date_volratio');
            $table->index(['trade_date', 'rsi14', 'ma20', 'ma50', 'ma200'], 'idx_ind_trend_filter');
            $table->index(['trade_date', 'basis_used'], 'idx_tid_basis_date');

            $table->foreign('ticker_id', 'fk_ind_daily_ticker')
                ->references('ticker_id')->on('tickers')
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
        Schema::dropIfExists('ticker_indicators_daily');
    }
}
