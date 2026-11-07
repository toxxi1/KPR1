<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nomenclatures', function (Blueprint $table) {
            $table->id();
            $table->integer('nomenclature_id_sbis')->nullable();
            $table->integer('nomenclature_id')->nullable();
            $table->integer('folder_sbis')->nullable();
            $table->integer('folder')->nullable();
            $table->boolean('is_folder')->default(false);
            $table->text('name')->nullable();
            $table->string('nomenclature_type')->nullable();
            $table->integer('cost')->nullable();
            $table->boolean('is_deleted')->default(false);
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
        Schema::dropIfExists('nomenclatures');
    }
};
