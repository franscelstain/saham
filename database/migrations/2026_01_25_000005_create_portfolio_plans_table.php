<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePortfolioPlansTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('portfolio_plans')) return;

        Schema::create('portfolio_plans', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('ticker_id')->index();
            $table->string('strategy_code', 32)->index();
            $table->date('as_of_trade_date')->index();

            $table->string('intent', 24)->index();
            $table->double('alloc_pct', 8, 4)->nullable();

            // Plan snapshot is immutable once OPENED; updates must go via events.
            $table->json('plan_snapshot_json');
            $table->json('entry_json')->nullable();
            $table->json('risk_json')->nullable();
            $table->json('take_profit_json')->nullable();
            $table->json('timebox_json')->nullable();
            $table->json('reason_codes_json')->nullable();

            $table->string('plan_version', 16)->index();
            $table->string('status', 16)->default('PLANNED')->index(); // PLANNED|OPENED|EXPIRED|CANCELLED

            $table->date('entry_expiry_date')->nullable()->index();
            $table->integer('max_holding_days')->nullable();

            $table->timestamps();

            $table->unique(['account_id','ticker_id','strategy_code','as_of_trade_date','plan_version'], 'pp_account_ticker_strategy_date_ver_uq');
        });
    }

    public function down()
    {
        Schema::dropIfExists('portfolio_plans');
    }
}
