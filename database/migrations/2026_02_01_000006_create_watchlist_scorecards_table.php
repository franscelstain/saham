<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWatchlistScorecardsTable extends Migration
{
    public function up()
    {
        Schema::create('watchlist_scorecards', function (Blueprint $table) {
            $table->bigIncrements('scorecard_id');

            $table->unsignedBigInteger('run_id');
            $table->decimal('feasible_rate', 18, 6)->nullable();
            $table->decimal('fill_rate', 18, 6)->nullable();
            $table->decimal('outcome_rate', 18, 6)->nullable();
            $table->json('payload_json')->nullable();

            $table->timestamps();

            $table->foreign('run_id')
                ->references('run_id')
                ->on('watchlist_strategy_runs')
                ->onDelete('cascade');

            $table->unique(['run_id'], 'uq_watchlist_scorecards_run');
        });
    }

    public function down()
    {
        Schema::dropIfExists('watchlist_scorecards');
    }
}
