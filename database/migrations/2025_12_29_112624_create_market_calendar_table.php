<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketCalendarTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('market_calendar', function (Blueprint $table) {
            $table->date('cal_date')->primary();

            // tinyint(1) default 1
            $table->boolean('is_trading_day')->default(true);

            $table->string('holiday_name', 120)->nullable();
            $table->string('session_open_time', 5)->nullable();
            $table->string('session_close_time', 5)->nullable();
            $table->text('breaks_json')->nullable();
            $table->string('source', 120)->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            // composite index (is_trading_day, cal_date)
            $table->index(['is_trading_day', 'cal_date'], 'market_calendar_trading_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('market_calendar');
    }
}
