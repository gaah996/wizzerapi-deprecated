<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlansTable extends Migration
{
    /**
     * Run the migrations.
     * Create and struture database fetures plan user for use system in time determined
     */
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {

            //Primary key (plan_id) and Foreign keys (user_id and plan_rule_id)
            $table->increments('plan_id');
            $table->integer('user_id')->unsigned();
            $table->integer('plan_rule_id')->unsigned();
            $table->foreign('plan_rule_id')->references('plan_rule_id')->on('plan_rules');

            //Information about this specific plan
            $table->dateTimeTz('signature_date');

            //Plan payment information
            $table->string('discount_code', 100)->nullable();
            $table->string('payment_id', 100)->nullable();
            $table->text('payment_link')->nullable();
            $table->integer('payment_status');
            $table->string('pagseguro_plan_id', 100)->nullable();

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
        Schema::dropIfExists('plans');
    }
}
