<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestsResponsesTables extends Migration {
    public function up() {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('description', 200);
            $table->string('validation', 100);
            $table->timestamps();

            $table->unsignedBigInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->string('description', 200);
            $table->timestamps();

            $table->unsignedBigInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('request_id')->index();
            $table->foreign('request_id')->references('id')->on('requests');
        });
    }

    public function down() {
        Schema::dropIfExists('responses');
        Schema::dropIfExists('requests');
    }
}
