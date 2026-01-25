<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePortfolioLotsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('portfolio_lots')) return;

        Schema::create('portfolio_lots', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('ticker_id')->index();
            $table->unsignedBigInteger('buy_trade_id')->index();
            $table->date('buy_date')->index();

            $table->integer('qty');
            $table->integer('remaining_qty');

            // unit_cost includes buy fees (fee-aware cost basis)
            $table->double('unit_cost', 18, 6);
            $table->double('total_cost', 18, 4);

            $table->timestamps();

            $table->unique(['buy_trade_id'], 'pl_buy_trade_uq');
            $table->index(['account_id', 'ticker_id', 'buy_date', 'id'], 'pl_fifo_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('portfolio_lots');
    }
}
