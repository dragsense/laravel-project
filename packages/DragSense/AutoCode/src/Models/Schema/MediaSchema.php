<?php

namespace DragSense\AutoCode\Models\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MediaSchema
{
    public static function up($tableName)
    {
        Schema::create($tableName, function (Blueprint $table) {
            $table->string('_id')->primary();
            $table->string('src')->nullable();
            $table->string('name')->nullable(false);
            $table->string('fileName')->nullable();
            $table->string('type')->nullable();
            $table->string('alt')->default('')->nullable(false);
            $table->unsignedBigInteger('size')->default(0);
            $table->json('dimensions')->nullable();
            $table->string('mimetype')->default('');
            $table->timestamps();
        });
    }

};
