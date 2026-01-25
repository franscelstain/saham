<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePortfolioPositionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('portfolio_positions')) return;

        Schema::create('portfolio_positions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id')->default(1)->index();
            $table->unsignedBigInteger('ticker_id')->index();
            $table->string('strategy_code', 32)->nullable()->index();
            $table->string('state', 16)->default('OPEN')->index();
            $table->integer('qty')->default(0);
            $table->double('avg_price');
            $table->double('realized_pnl', 18, 4)->default(0);
            $table->double('unrealized_pnl', 18, 4)->nullable();
            $table->double('market_value', 18, 4)->nullable();
            $table->date('last_valued_date')->nullable()->index();
            $table->integer('position_lots');
            $table->date('entry_date')->nullable()->index();
            $table->boolean('is_open')->default(true)->index();
            $table->string('policy_code', 32)->nullable();
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->json('plan_snapshot_json')->nullable();
            $table->date('as_of_trade_date')->nullable()->index();
            $table->string('plan_version', 16)->nullable();
            $table->timestamps();

            $table->index(['is_open', 'ticker_id'], 'pp_open_ticker_idx');
            $table->index(['account_id', 'ticker_id', 'is_open'], 'pp_account_ticker_open_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('portfolio_positions');
    }
}
