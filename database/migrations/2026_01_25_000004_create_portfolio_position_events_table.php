<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePortfolioPositionEventsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('portfolio_position_events')) return;

        Schema::create('portfolio_position_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('ticker_id')->index();
            $table->string('strategy_code', 32)->nullable()->index();
            $table->string('plan_version', 16)->nullable();
            $table->date('as_of_trade_date')->nullable()->index();

            $table->string('event_type', 32)->index();
            $table->integer('qty_before')->nullable();
            $table->integer('qty_after')->nullable();
            $table->double('price', 18, 4)->nullable();
            $table->string('reason_code', 32)->nullable();
            $table->string('notes', 255)->nullable();
            $table->json('payload_json')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'ticker_id', 'created_at'], 'ppe_account_ticker_time_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('portfolio_position_events');
    }
}
