<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDevelopmentAdsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('development_ads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type')->nullable();
            $table->text('logo')->nullable();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->string('number', 10)->nullable();
            $table->string('street', 100)->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->string('city', 100);
            $table->string('state', 2);
            $table->integer('cep')->nullable();
            $table->double('lat');
            $table->double('lng');
            $table->text('datasheet')->nullable();
            $table->integer('work_stage')->nullable();
            $table->date('due_date')->nullable();
            $table->text('picture')->nullable();
            $table->text('video')->nullable();
            $table->string('background', 200)->nullable();

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
        Schema::dropIfExists('development_ads');
    }
}
