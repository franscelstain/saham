<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTickerIntradayTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticker_intraday', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->bigIncrements('snapshot_id');
            $table->unsignedBigInteger('ticker_id');
            $table->date('trade_date');                 // tanggal bursa lokal
            $table->dateTime('snapshot_at');            // waktu ambil data
            $table->dateTime('data_at')->nullable();
            $table->decimal('last_price', 18, 4)->nullable();
            $table->bigInteger('volume_so_far')->nullable();

            // opsional kalau kamu bisa ambil:
            $table->decimal('open_price', 18, 4)->nullable();
            $table->decimal('high_price', 18, 4)->nullable();
            $table->decimal('low_price', 18, 4)->nullable();

            $table->string('source', 30)->nullable();

            $table->tinyInteger('is_deleted')->default(0);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['ticker_id', 'trade_date'], 'ticker_intraday_ticker_date_unique');
            $table->index('trade_date', 'ticker_intraday_trade_date_idx');

            $table->foreign('ticker_id', 'ticker_intraday_ticker_id_foreign')
                ->references('ticker_id')->on('tickers')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ticker_intraday');
    }
}
