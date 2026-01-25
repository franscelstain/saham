<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watchlist_scorecards', function (Blueprint $table) {
            $table->bigIncrements('scorecard_id');

            $table->unsignedBigInteger('run_id');

            // 0..1
            $table->double('feasible_rate')->nullable();
            $table->double('fill_rate')->nullable();
            $table->double('outcome_rate')->nullable();

            // Optional: extra breakdowns / debugging.
            $table->longText('payload_json')->nullable();

            $table->timestamps();

            $table->foreign('run_id')->references('run_id')->on('watchlist_strategy_runs')->onDelete('cascade');
            $table->unique(['run_id'], 'wlss_unique_run');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_scorecards');
    }
};
