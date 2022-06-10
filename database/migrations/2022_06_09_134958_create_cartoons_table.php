<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCartoonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cartoons', function (Blueprint $table) {
            $table->id('cartoon_id');
            $table->string('cartoon_name');
            $table->integer('Does not contain elements of violence');
            $table->integer('Creative');
            $table->integer('Educating');
            $table->integer('Entertain');
            $table->integer('No Pornographic Elements');
            $table->string('cartoon_img');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cartoons');
    }
}
