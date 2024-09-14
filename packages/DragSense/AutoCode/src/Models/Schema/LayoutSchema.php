<?php 

namespace DragSense\AutoCode\Models\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LayoutSchema {


    public static function up($tableName)
    {
        Schema::create($tableName, function (Blueprint $table) {
            $table->string('_id')->primary();
            $table->string('name');
            $table->string('main');
            $table->json('elements');
            $table->json('components');
            $table->json('forms');
            $table->json('_components');
            $table->json('_forms');
            $table->json('_elements');
            $table->json('_styles');
            $table->boolean('published')->default(true);
            $table->json('styles');
            $table->json('updater');
            $table->boolean('duplicated')->default(false);
            $table->json('creator');
            $table->timestamps();
        });
    }

};
