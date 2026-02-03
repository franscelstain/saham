<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWatchlistStrategyChecksTable extends Migration
{
    public function up()
    {
        Schema::create('watchlist_strategy_checks', function (Blueprint $table) {
            $table->bigIncrements('check_id');

            $table->unsignedBigInteger('run_id');
            $table->dateTime('checked_at');

            $table->json('snapshot_json');
            $table->json('result_json');

            $table->timestamps();

            $table->foreign('run_id')
                ->references('run_id')
                ->on('watchlist_strategy_runs')
                ->onDelete('cascade');

            $table->index(['run_id','checked_at'], 'idx_watchlist_checks_run_checked');
        });
    }

    public function down()
    {
        Schema::dropIfExists('watchlist_strategy_checks');
    }
}
