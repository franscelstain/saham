<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateTickersTable extends Migration
{
    public function up()
    {
        Schema::create('tickers', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->bigIncrements('ticker_id');
            $table->string('ticker_code', 10);
            $table->string('company_name', 255);
            $table->string('company_logo', 255)->nullable();
            $table->boolean('is_deleted')->default(false);

            // match MySQL: created_at default current_timestamp(), updated_at default current_timestamp()
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique('ticker_code', 'ticker_code');
        });

        // Optional tapi lebih "mirip" DDL kamu:
        // kalau kamu mau updated_at auto-update saat row diupdate (MySQL ON UPDATE CURRENT_TIMESTAMP)
        // Laravel tidak set ini otomatis di schema builder lama, jadi pakai raw SQL.
        DB::statement("ALTER TABLE `tickers` MODIFY `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tickers');
    }
}
