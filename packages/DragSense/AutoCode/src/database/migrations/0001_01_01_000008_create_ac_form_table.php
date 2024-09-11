<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ac_forms', function (Blueprint $table) {
            $table->string('_id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('form');
            $table->boolean('record')->default(false);
            $table->boolean('recordOnly')->default(false);
            $table->json('emailbody'); 
            $table->json('elements'); 
            $table->json('components'); 
            $table->json('_components'); 
            $table->json('_elements'); 
            $table->json('_styles'); 
            $table->boolean('published')->default(true);
            $table->json('styles'); 
            $table->json('states'); 
            $table->json('_states'); 
            $table->json('setting'); 
            $table->json('updater'); 
            $table->boolean('duplicated')->default(false);
            $table->json('creator'); 
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ac_forms');
    }
};
