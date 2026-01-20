<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWatchlistCandidatesTable extends Migration
{
    public function up()
    {
        Schema::create('watchlist_candidates', function (Blueprint $table) {
            $table->bigIncrements('watchlist_candidate_id');

            // link to daily snapshot
            $table->unsignedBigInteger('watchlist_daily_id')->nullable();

            $table->date('trade_date');
            $table->unsignedBigInteger('ticker_id');
            $table->string('ticker', 16);

            // bucket group: TOP_PICKS / WATCH / AVOID
            $table->string('bucket', 16)->index();
            $table->integer('rank')->nullable();

            $table->decimal('watchlist_score', 8, 2)->default(0);
            $table->string('confidence', 12)->nullable();

            // codes / labels
            $table->unsignedSmallInteger('decision_code')->default(0);
            $table->unsignedSmallInteger('signal_code')->default(0);
            $table->unsignedSmallInteger('volume_label_code')->default(0);
            $table->string('decision_label', 64)->nullable();
            $table->string('signal_label', 64)->nullable();
            $table->string('volume_label', 64)->nullable();

            // liquidity
            $table->decimal('dv20', 22, 2)->nullable();
            $table->char('liq_bucket', 1)->nullable();

            // OHLC
            $table->decimal('open', 18, 4)->nullable();
            $table->decimal('high', 18, 4)->nullable();
            $table->decimal('low', 18, 4)->nullable();
            $table->decimal('close', 18, 4)->nullable();
            $table->unsignedBigInteger('volume')->nullable();

            // prev candle
            $table->decimal('prev_open', 18, 4)->nullable();
            $table->decimal('prev_high', 18, 4)->nullable();
            $table->decimal('prev_low', 18, 4)->nullable();
            $table->decimal('prev_close', 18, 4)->nullable();

            // candle flags (ratios 0..1)
            $table->decimal('candle_body_pct', 10, 6)->nullable();
            $table->decimal('candle_upper_wick_pct', 10, 6)->nullable();
            $table->decimal('candle_lower_wick_pct', 10, 6)->nullable();
            $table->boolean('is_inside_day')->nullable();
            $table->string('engulfing_type', 8)->nullable();
            $table->boolean('is_long_upper_wick')->nullable();
            $table->boolean('is_long_lower_wick')->nullable();

            // plan + reasons
            $table->json('plan')->nullable();
            $table->json('rank_reason_codes')->nullable();
            $table->json('rank_breakdown')->nullable();

            $table->timestamps();

            $table->index(['trade_date', 'ticker_id']);
            $table->foreign('watchlist_daily_id')->references('watchlist_daily_id')->on('watchlist_daily')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('watchlist_candidates');
    }
}
