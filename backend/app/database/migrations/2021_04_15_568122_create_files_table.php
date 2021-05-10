<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilesTable extends Migration {

    public function up() {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('mimetype', 5)->index();
            $table->timestamps();

            $table->unsignedBigInteger('response_id')->index();
            $table->foreign('response_id')->references('id')->on('responses')->onDelete('cascade');
        });
    }


    public function down() {
        Schema::dropIfExists('files');
    }
}
