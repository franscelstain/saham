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

            // momentum / volatility
            $table->decimal('rsi14', 6, 2)->nullable();
            $table->decimal('atr14', 18, 4)->nullable();

            // support & resistance (rolling)
            $table->decimal('support_20d', 18, 4)->nullable();
            $table->decimal('resistance_20d', 18, 4)->nullable();

            // hasil klasifikasi (yang kamu mau)
            $table->unsignedTinyInteger('decision_code')->default(1); // 1..5
            $table->unsignedTinyInteger('signal_code')->nullable(); // 1..10
            $table->unsignedTinyInteger('volume_label_code')->nullable(); // 1..8
            $table->boolean('is_valid')->default(true);
            $table->string('invalid_reason', 64)->nullable();

            // Tanggal pertama kali sinyal ini muncul (streak start)
            $table->date('signal_first_seen_date')->nullable();
            $table->smallInteger('signal_age_days')->default(0);

            // scoring
            $table->smallInteger('score_total')->default(0);
            $table->smallInteger('score_trend')->default(0);
            $table->smallInteger('score_momentum')->default(0);
            $table->smallInteger('score_volume')->default(0);
            $table->smallInteger('score_breakout')->default(0);
            $table->smallInteger('score_risk')->default(0);

            $table->string('source', 30)->nullable();

            $table->boolean('is_deleted')->default(false);

            // match DDL: timestamp NULL DEFAULT NULL
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['ticker_id', 'trade_date'], 'uq_ind_daily_ticker_date');

            $table->index(['trade_date', 'vol_ratio'], 'idx_ind_date_volratio');
            $table->index(['trade_date', 'signal_code', 'volume_label_code', 'score_total'], 'idx_ind_candidates');
            $table->index(['trade_date', 'rsi14', 'ma20', 'ma50', 'ma200'], 'idx_ind_trend_filter');
            $table->index(['trade_date', 'signal_code', 'signal_age_days'], 'idx_ti_trade_signal_age');
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
