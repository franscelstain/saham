<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTickerDividendEventsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ticker_dividend_events')) return;

        Schema::create('ticker_dividend_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ticker_id');
            $table->date('cum_date')->nullable()->index();
            $table->date('ex_date')->nullable()->index();
            $table->double('cash_dividend')->nullable();
            $table->double('dividend_yield_est')->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('source', 32)->nullable();
            $table->timestamps();

            $table->index(['ticker_id', 'cum_date'], 'tde_ticker_cum_idx');
            $table->index(['ticker_id', 'ex_date'], 'tde_ticker_ex_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticker_dividend_events');
    }
}
