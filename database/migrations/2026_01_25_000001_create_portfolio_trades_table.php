<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePortfolioTradesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('portfolio_trades')) return;

        Schema::create('portfolio_trades', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('ticker_id')->index();
            $table->string('symbol', 16)->nullable()->index();

            $table->date('trade_date')->index();
            $table->string('side', 8)->index(); // BUY | SELL
            $table->integer('qty'); // shares
            $table->double('price', 18, 4);

            // Stored numbers are deterministic derived values to avoid recomputing in UI.
            $table->double('gross_amount', 18, 4)->nullable();
            $table->double('fee_amount', 18, 4)->nullable();
            $table->double('tax_amount', 18, 4)->nullable();
            $table->double('net_amount', 18, 4)->nullable();

            // Idempotency keys
            $table->string('external_ref', 64)->nullable();
            $table->string('trade_hash', 64)->nullable();

            // Optional metadata
            $table->string('broker_ref', 64)->nullable();
            $table->string('source', 32)->default('manual');
            $table->string('currency', 16)->nullable();
            $table->json('meta_json')->nullable();

            $table->timestamps();

            $table->unique(['account_id', 'external_ref'], 'pt_account_external_ref_uq');
            $table->unique(['account_id', 'trade_hash'], 'pt_account_trade_hash_uq');
            $table->index(['account_id', 'ticker_id', 'trade_date'], 'pt_account_ticker_date_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('portfolio_trades');
    }
}
