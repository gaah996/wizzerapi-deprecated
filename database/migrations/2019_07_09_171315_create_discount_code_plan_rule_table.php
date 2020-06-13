<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDiscountCodePlanRuleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discount_code_plan_rule', function (Blueprint $table) {
            $table->integer('discount_code_id')->unsigned();
            $table->foreign('discount_code_id')->references('id')->on('discount_codes')->onDelete('cascade');
            $table->integer('plan_rule_id')->unsigned();
            $table->foreign('plan_rule_id')->references('plan_rule_id')->on('plan_rules')->onDelete('cascade');
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
        Schema::dropIfExists('discount_code_plan_rule');
    }
}
