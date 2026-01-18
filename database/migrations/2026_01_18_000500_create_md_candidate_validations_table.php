<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMdCandidateValidationsTable extends Migration
{
    public function up()
    {
        Schema::create('md_candidate_validations', function (Blueprint $table) {
            $table->bigIncrements('validation_id');

            $table->date('trade_date');
            $table->unsignedBigInteger('ticker_id');

            // Validator provider name (ex: EODHD)
            $table->string('provider', 16)->default('EODHD');

            // OK | WARN | DISAGREE_MAJOR | PRIMARY_NO_DATA | VALIDATOR_NO_DATA | VALIDATOR_ERROR | INVALID_TICKER
            $table->string('status', 32);

            $table->unsignedBigInteger('canonical_run_id')->nullable();

            $table->decimal('primary_close', 18, 4)->nullable();
            $table->decimal('validator_close', 18, 4)->nullable();
            $table->decimal('diff_pct', 8, 4)->nullable();

            $table->string('error_code', 64)->nullable();
            $table->string('error_msg', 255)->nullable();

            $table->timestamps();

            $table->unique(['trade_date', 'ticker_id', 'provider'], 'u_md_val_date_ticker_provider');
            $table->index(['trade_date', 'provider'], 'idx_md_val_date_provider');
        });
    }

    public function down()
    {
        Schema::dropIfExists('md_candidate_validations');
    }
}
