<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    
    public function up()
    {
        Schema::create('ac_components', function (Blueprint $table) {
            $table->string('_id')->primary();
            $table->string('name');
            $table->string('type')->default('component');
            $table->json('elements');
            $table->json('components');
            $table->json('forms');
            $table->json('_collStates');
            $table->json('collStates');
            $table->json('styles');
            $table->json('states');
            $table->json('events');
            $table->json('_events');
            $table->json('_states');
            $table->json('_components');
            $table->json('_forms');
            $table->json('_elements');
            $table->json('_styles');
            $table->boolean('published')->default(true);
            $table->json('updater');
            $table->boolean('duplicated')->default(false);
            $table->json('creator');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ac_components');
    }
};
