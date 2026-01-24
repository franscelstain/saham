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
            $table->unsignedBigInteger('ticker_id')->index();
            $table->double('avg_price');
            $table->integer('position_lots');
            $table->date('entry_date')->nullable()->index();
            $table->boolean('is_open')->default(true)->index();
            $table->string('policy_code', 32)->nullable();
            $table->timestamps();

            $table->index(['is_open', 'ticker_id'], 'pp_open_ticker_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('portfolio_positions');
    }
}
