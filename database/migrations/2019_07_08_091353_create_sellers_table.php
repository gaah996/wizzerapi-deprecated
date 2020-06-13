<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSellersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->increments('id');
            $table->text('avatar')->nullable();
            $table->string('name', 50);
            $table->text('phones');
            $table->text('emails');
            $table->string('site', 100)->nullable();
            $table->string('creci', 50)->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('development_ad_id');
            $table->foreign('development_ad_id')->references('id')->on('development_ads')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sellers');
    }
}
