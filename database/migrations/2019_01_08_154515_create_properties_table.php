<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePropertiesTable extends Migration
{
    /**
     * Run the migrations.
     * Create and struture database fetures home
     * @return void
     */
    public function up()
    {
        Schema::create('properties', function (Blueprint $table) {

            // Key Primary(properties_id) and Foreign Key(user_id)
            $table->increments('property_id');
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('user_id')->
            on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('development_id')->unsigned()->nullable();
            $table->foreign('development_id')->references('id')->
                on('development_ads')->onDelete('cascade')->onUpdate('cascade');

            //Information adds
            $table->text('property_type');
            $table->text('title')->nullable();
            $table->text('description');

            // Information locale home
            $table->string('complement')->nullable();
            $table->string('cep',8)->nullable();
            $table->string('number', 10)->nullable();
            $table->string('street')->nullable();
            $table->string('neighborhood',100);
            $table->string('city',45);
            $table->string('state',2);
            $table->double('lat');
            $table->double('lng');

            // Features home
            $table->integer('rooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->integer('parking_spaces')->nullable();
            $table->float('area');
            $table->integer('quantity')->nullable();
            $table->double('price', 16, 2)->nullable();

            // Pictures show home
            $table->text('picture')->nullable();
            $table->text('video')->nullable();
            $table->text('blueprint')->nullable();
            $table->string('tour', 200)->nullable();

            // Used from Alexandres properties
            $table->text('external_register_id', 20)->nullable();

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
        Schema::dropIfExists('properties');
    }
}
