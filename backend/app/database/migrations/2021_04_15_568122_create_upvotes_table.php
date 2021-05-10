<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUpvotesTable extends Migration {

    public function up() {
        Schema::create('request_upvotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->index();
            $table->foreign('request_id')->references('id')->on('requests')->onDelete('cascade');

            $table->unsignedBigInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('response_upvotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('response_id')->index();
            $table->foreign('response_id')->references('id')->on('responses')->onDelete('cascade');

            $table->unsignedBigInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }


    public function down() {
        Schema::dropIfExists('request_upvotes');
        Schema::dropIfExists('response_upvotes');
    }
}
