<?php

namespace DragSense\AutoCode\Models\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ComponentSchema {
    
    public static function up($tableName)
    {
        Schema::create($tableName, function (Blueprint $table) {
            $table->string('_id')->primary();
            $table->string('name');
            $table->string('type')->default('component');
            $table->string('attached')->nullable(); 
            $table->foreign('attached')
            ->references('_id')
            ->on('ac_collections');
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

        Schema::table('ac_components', function (Blueprint $table) {
            $table->string('parent')->nullable(); 
            $table->foreign('parent')
                ->references('_id')
                ->on('ac_components');
        });
    }

};
