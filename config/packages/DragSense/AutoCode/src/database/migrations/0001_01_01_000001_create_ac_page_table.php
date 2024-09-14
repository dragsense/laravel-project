<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up()
    {
        Schema::create('ac_pages', function (Blueprint $table) {
            $table->string('_id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('page');
            $table->string('layout'); 
            $table->foreign('layout')
                ->references('_id')
                ->on('ac_layouts');
            $table->json('scripts')->nullable();
            $table->string('url')->nullable();
            $table->json('elements');
            $table->json('components');
            $table->json('forms');
            $table->json('_components');
            $table->json('_forms');
            $table->json('_elements');
            $table->json('_styles');
            $table->boolean('published')->default(true);
            $table->json('styles');
            $table->json('setting');
            $table->json('updater');
            $table->boolean('duplicated')->default(false);
            $table->json('creator');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ac_pages');
    }
};
