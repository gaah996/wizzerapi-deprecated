<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlanRulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plan_rules', function (Blueprint $table) {
            $table->increments('plan_rule_id');
            $table->text('description');
            $table->integer('profile_type');
            $table->integer('adverts_number');
            $table->integer('images_per_advert');
            $table->float('price');
            $table->integer('validity');
            $table->boolean('renewable');
            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('user_id')->
            on('users')->onDelete('cascade')->onUpdate('cascade');

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
        Schema::dropIfExists('plan_rules');
    }
}
