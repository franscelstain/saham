<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePortfolioLotMatchesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('portfolio_lot_matches')) return;

        Schema::create('portfolio_lot_matches', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('ticker_id')->index();

            $table->unsignedBigInteger('sell_trade_id')->index();
            $table->unsignedBigInteger('buy_lot_id')->index();

            $table->integer('matched_qty');

            $table->double('buy_unit_cost', 18, 6);
            $table->double('sell_unit_price', 18, 6); // net proceeds per share

            $table->double('buy_fee_alloc', 18, 6)->nullable();
            $table->double('sell_fee_alloc', 18, 6)->nullable();

            $table->double('realized_pnl', 18, 4);

            $table->timestamps();

            $table->unique(['sell_trade_id', 'buy_lot_id'], 'plm_sell_buy_uq');
            $table->index(['account_id', 'ticker_id', 'sell_trade_id'], 'plm_account_ticker_sell_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('portfolio_lot_matches');
    }
}
