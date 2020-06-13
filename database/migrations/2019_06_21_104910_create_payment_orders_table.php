<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type')->default('plan');
            $table->string('code', 50)->unique()->nullable();
            $table->integer('status');
            $table->double('amount', 16, 2);
            $table->date('last_event_date');
            $table->date('scheduling_date');
            $table->text('transactions')->nullable();
            $table->integer('plan_id')->unsigned();
            $table->foreign('plan_id')->references('plan_id')->on('plans')->onDelete('cascade');
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
        Schema::dropIfExists('payment_orders');
    }
}
