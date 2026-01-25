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
            $table->unsignedBigInteger('account_id')->default(1)->after('id')->index();
            $table->unsignedBigInteger('ticker_id')->index();
            $table->string('strategy_code', 32)->nullable()->after('ticker_id')->index();
            $table->string('state', 16)->default('OPEN')->after('strategy_code')->index();
            $table->integer('qty')->default(0)->after('state');
            $table->double('avg_price');
            $table->double('realized_pnl', 18, 4)->default(0)->after('avg_price');
            $table->double('unrealized_pnl', 18, 4)->nullable()->after('realized_pnl');
            $table->double('market_value', 18, 4)->nullable()->after('unrealized_pnl');
            $table->date('last_valued_date')->nullable()->after('market_value')->index();
            $table->integer('position_lots');
            $table->date('entry_date')->nullable()->index();
            $table->boolean('is_open')->default(true)->index();
            $table->string('policy_code', 32)->nullable();
            $table->unsignedBigInteger('plan_id')->nullable()->after('policy_code')->index();
            $table->json('plan_snapshot_json')->nullable()->after('plan_id');
            $table->date('as_of_trade_date')->nullable()->after('plan_snapshot_json')->index();
            $table->string('plan_version', 16)->nullable()->after('as_of_trade_date');
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
