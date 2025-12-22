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
            $table->bigIncrements('snapshot_id');
            $table->unsignedBigInteger('ticker_id');
            $table->date('trade_date');                 // tanggal bursa lokal
            $table->dateTime('snapshot_at');            // waktu ambil data
            $table->decimal('last_price', 18, 4)->nullable();
            $table->bigInteger('volume_so_far')->nullable();

            // opsional kalau kamu bisa ambil:
            $table->decimal('open_price', 18, 4)->nullable();
            $table->decimal('high_price', 18, 4)->nullable();
            $table->decimal('low_price', 18, 4)->nullable();

            $table->tinyInteger('is_deleted')->default(0);
            $table->timestamps();

            $table->index(['trade_date']);
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
