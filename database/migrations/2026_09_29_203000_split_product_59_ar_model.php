<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('id', 59)
            ->update(['model_glb' => 'products/models/dish_059.glb']);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('id', 59)
            ->update(['model_glb' => 'products/models/dish_010.glb']);
    }
};
