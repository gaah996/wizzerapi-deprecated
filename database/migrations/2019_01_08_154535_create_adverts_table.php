<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdvertsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('adverts', function (Blueprint $table) {
            $table->increments('advert_id');
            $table->integer('plan_id')->unsigned();
            $table->foreign('plan_id')->references('plan_id')->on('plans')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->integer('property_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('user_id')
                ->on('users')->onDelete('cascade')->onUpdate('cascade');

            $table->float('price', 16, 2)->nullable();
            $table->float('price_max', 16, 2)->nullable();
            $table->float('condo')->nullable();
            $table->string('transaction', 15);
            $table->string('status', 15);

            //Advertiser contact info
            $table->boolean('user_picture')->nullable();
            $table->string('phone', 200)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('site', 100)->nullable();
            $table->string('facebook', 100)->nullable();
            $table->string('instagram', 100)->nullable();
            $table->string('youtube')->nullable();
            $table->string('advert_type', 30)->nullable();

            //Control info
            $table->integer('view_count');
            $table->integer('message_count');
            $table->integer('call_count');
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
        Schema::dropIfExists('adverts');
    }
}
