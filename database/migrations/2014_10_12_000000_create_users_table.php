<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('avatar')->nullable();
            $table->string('name', 50);
            $table->string('email', 100)->unique();
            $table->string('password');

            $table->integer('profile_type');
            $table->string('cpf_cnpj', 20)->nullable()->unique();
            $table->string('creci', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('site', 100)->nullable();

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
        Schema::dropIfExists('users');
    }
}
