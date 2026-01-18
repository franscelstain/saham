<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMdCanonicalEodTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('md_canonical_eod', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('canonical_id');

            $table->unsignedBigInteger('run_id');
            $table->unsignedBigInteger('ticker_id');
            $table->date('trade_date');

            $table->string('chosen_source', 30);
            $table->string('reason', 60); // PRIORITY_WIN | FALLBACK_USED | ONLY_SOURCE | etc
            $table->text('flags')->nullable();

            $table->decimal('open', 18, 4);
            $table->decimal('high', 18, 4);
            $table->decimal('low', 18, 4);
            $table->decimal('close', 18, 4);
            $table->decimal('adj_close', 18, 4)->nullable();
            $table->unsignedBigInteger('volume');

            $table->timestamp('built_at')->useCurrent();

            $table->unique(['run_id', 'ticker_id', 'trade_date'], 'uq_md_can_run_ticker_date');
            $table->index(['trade_date','chosen_source'], 'idx_md_can_date_source');
            $table->index(['ticker_id', 'trade_date'], 'idx_md_can_ticker_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('md_canonical_eod');
    }
}
