<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWatchlistStrategyRunsTable extends Migration
{
    public function up()
    {
        Schema::create('watchlist_strategy_runs', function (Blueprint $table) {
            $table->bigIncrements('run_id');

            $table->date('trade_date');         // EOD date used for plan (legacy naming; see docs/watchlist/scorecard.md)
            $table->date('exec_trade_date');    // execution date
            $table->string('policy', 32);
            $table->string('source', 64)->default('watchlist');

            $table->dateTime('generated_at')->nullable();
            $table->json('payload_json');

            $table->timestamps();

            $table->unique(['trade_date','exec_trade_date','policy','source'], 'uq_watchlist_runs_trade_exec_policy_source');
            $table->index(['exec_trade_date','policy'], 'idx_watchlist_runs_exec_policy');
        });
    }

    public function down()
    {
        Schema::dropIfExists('watchlist_strategy_runs');
    }
}
