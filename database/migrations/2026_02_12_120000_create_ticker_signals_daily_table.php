<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTickerSignalsDailyTable extends Migration
{
    public function up(): void
    {
        Schema::create('ticker_signals_daily', function (Blueprint $table) {
            $table->bigIncrements('signal_daily_id');
            $table->unsignedBigInteger('ticker_id');
            $table->date('trade_date');

            // classifier outputs
            $table->integer('decision_code')->nullable();
            $table->integer('signal_code')->nullable();
            $table->integer('volume_label_code')->nullable();

            // signal-age tracking (deterministic)
            $table->date('signal_first_seen_date')->nullable();
            $table->integer('signal_age_days')->nullable();

            // validity mirrors indicator validity (so downstream can gate quickly)
            $table->tinyInteger('is_valid')->default(1);
            $table->string('invalid_reason', 64)->nullable();

            // provenance
            $table->string('source', 32)->default('compute-eod');

            $table->tinyInteger('is_deleted')->default(0);
            $table->timestamps();

            $table->unique(['ticker_id', 'trade_date'], 'uq_signal_ticker_trade_date');
            $table->index(['trade_date', 'signal_code'], 'idx_signal_trade_signal');
            $table->index(['trade_date', 'decision_code'], 'idx_signal_trade_decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticker_signals_daily');
    }
};
