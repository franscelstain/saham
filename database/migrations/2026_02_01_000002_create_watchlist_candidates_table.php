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

            $table->unsignedBigInteger('watchlist_daily_id');
            $table->string('policy', 32);
            $table->date('trade_date');      // execution date
            $table->date('asof_eod_date');   // plan date

            $table->string('group_code', 16); // TOP_PICKS|SECONDARY|WATCH_ONLY|AVOID|NO_TRADE
            $table->string('ticker', 16);

            $table->integer('rank')->nullable();
            $table->decimal('score_total', 10, 6)->nullable();

            // PLAN (denormalized for filtering) + full JSON for audit
            $table->string('setup_type', 16)->nullable(); // PULLBACK|BREAKOUT
            $table->integer('plan_entry')->nullable();
            $table->integer('plan_stop')->nullable();
            $table->integer('plan_tp1')->nullable();
            $table->decimal('rr_est', 10, 6)->nullable();

            $table->json('reasons_json')->nullable();
            $table->json('eod_bar_json')->nullable();
            $table->json('ticker_plan_json')->nullable();

            $table->timestamps();

            $table->foreign('watchlist_daily_id')
                ->references('watchlist_daily_id')
                ->on('watchlist_daily')
                ->onDelete('cascade');

            $table->unique(['watchlist_daily_id','group_code','ticker'], 'uq_watchlist_candidates_daily_group_ticker');
            $table->index(['policy','trade_date','group_code','rank'], 'idx_watchlist_candidates_policy_trade_group_rank');
            $table->index(['ticker','asof_eod_date'], 'idx_watchlist_candidates_ticker_eod');
        });
    }

    public function down()
    {
        Schema::dropIfExists('watchlist_candidates');
    }
}
