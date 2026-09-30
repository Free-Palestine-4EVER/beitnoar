<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('model_glb')->nullable()->after('image');
            $table->string('model_usdz')->nullable()->after('model_glb');
            $table->boolean('ar_enabled')->default(false)->after('model_usdz');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['model_glb', 'model_usdz', 'ar_enabled']);
        });
    }
};
