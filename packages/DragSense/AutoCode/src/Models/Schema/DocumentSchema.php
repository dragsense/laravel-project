<?php

namespace DragSense\AutoCode\Models\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DocumentSchema {
    
    public static function up($tableName)
    {
        Schema::create($tableName, function (Blueprint $table) {
            $table->string('_id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('url')->nullable();
            $table->json('coll');
            $table->json('relationships');
            $table->json('states');
            $table->json('_states');
            $table->boolean('published')->default(true);
            $table->json('setting');
            $table->json('updater');
            $table->boolean('duplicated')->default(false);
            $table->json('creator');
            $table->timestamps();
        });
    }

};
