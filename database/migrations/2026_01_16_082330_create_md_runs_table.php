<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMdRunsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('md_runs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('run_id');

            $table->string('job', 50)->default('import_eod');
            $table->string('run_mode', 16)->default('FETCH');
            $table->unsignedBigInteger('parent_run_id')->nullable();
            $table->unsignedBigInteger('raw_source_run_id')->nullable();
            $table->string('timezone', 40);
            $table->string('cutoff', 10); // "16:30"
            $table->date('effective_start_date');
            $table->date('effective_end_date');
            $table->date('last_good_trade_date')->nullable();

            $table->unsignedInteger('target_tickers')->default(0);
            $table->unsignedInteger('target_days')->default(0);
            $table->unsignedBigInteger('expected_points')->default(0);
            $table->unsignedBigInteger('canonical_points')->default(0);

            $table->string('status', 30)->default('RUNNING'); 
            // RUNNING | SUCCESS | CANONICAL_HELD | FAILED

            // ringkasan metrik (telemetry minimal)
            $table->decimal('coverage_pct', 6, 2)->nullable();
            $table->decimal('fallback_pct', 6, 2)->nullable();
            $table->unsignedInteger('hard_rejects')->default(0);
            $table->unsignedInteger('soft_flags')->default(0);
            $table->unsignedInteger('disagree_major')->default(0);
            $table->unsignedInteger('missing_trading_day')->default(0);

            $table->text('notes')->nullable(); // human readable summary / critical issues

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('md_runs');
    }
}
