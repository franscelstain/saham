<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMdRawEodTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('md_raw_eod', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('raw_id');

            $table->unsignedBigInteger('run_id');
            $table->unsignedBigInteger('ticker_id');
            $table->date('trade_date');

            $table->string('source', 30);
            $table->string('source_symbol', 40)->nullable();
            $table->dateTime('source_ts')->nullable();

            $table->decimal('open', 18, 4)->nullable();
            $table->decimal('high', 18, 4)->nullable();
            $table->decimal('low', 18, 4)->nullable();
            $table->decimal('close', 18, 4)->nullable();
            $table->decimal('adj_close', 18, 4)->nullable();
            $table->unsignedBigInteger('volume')->nullable();

            $table->boolean('hard_valid')->default(0);
            $table->text('flags')->nullable();      // "OUTLIER,STALE,DISAGREE_MAJOR"
            $table->string('error_code', 50)->nullable();
            $table->text('error_msg')->nullable();

            $table->timestamp('imported_at')->useCurrent();

            $table->unique(['run_id','ticker_id','trade_date','source'], 'uq_md_raw_run_ticker_date_source');
            $table->index(['run_id', 'ticker_id','trade_date'], 'idx_md_raw_run_ticker_date');
            $table->index(['source','trade_date'], 'idx_md_raw_source_date');

            $table->foreign('run_id', 'fk_md_raw_run')
                ->references('run_id')->on('md_runs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('md_raw_eod');
    }
}
