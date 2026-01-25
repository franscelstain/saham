<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watchlist_strategy_checks', function (Blueprint $table) {
            $table->bigIncrements('check_id');

            $table->unsignedBigInteger('run_id');

            // When the check was performed (WIB local time recommended).
            $table->timestamp('checked_at');

            // User/job-provided market snapshot.
            $table->longText('snapshot_json');

            // Result of evaluation: per-ticker eligibility + reasons + recommended action.
            $table->longText('result_json');

            $table->timestamps();

            $table->foreign('run_id')->references('run_id')->on('watchlist_strategy_runs')->onDelete('cascade');
            $table->index(['run_id', 'checked_at'], 'wlsc_run_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_strategy_checks');
    }
};
