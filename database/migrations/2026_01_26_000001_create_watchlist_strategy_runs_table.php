<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watchlist_strategy_runs', function (Blueprint $table) {
            $table->bigIncrements('run_id');

            $table->date('trade_date');
            $table->date('exec_trade_date');

            // Policy selected in the run (WEEKLY_SWING, DIVIDEND_SWING, INTRADAY_LIGHT, POSITION_TRADE, NO_TRADE)
            $table->string('policy', 32);

            // Source of run payload (ex: preopen_contract_WEEKLY_SWING)
            $table->string('source', 64)->default('watchlist');

            // JSON output payload as stored, exactly as emitted by watchlist contract.
            $table->longText('payload_json');

            // Optional: generated_at inside payload (RFC3339).
            $table->timestamp('generated_at')->nullable();

            $table->timestamps();

            $table->unique(['trade_date', 'exec_trade_date', 'policy', 'source'], 'wlsr_unique_run');
            $table->index(['trade_date', 'exec_trade_date'], 'wlsr_trade_exec_idx');
            $table->index(['policy'], 'wlsr_policy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_strategy_runs');
    }
};
