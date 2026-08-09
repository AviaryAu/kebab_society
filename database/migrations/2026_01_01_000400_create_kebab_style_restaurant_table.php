<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kebab_style_restaurant', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kebab_style_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_signature')->default(false);

            $table->primary(['restaurant_id', 'kebab_style_id']);
            $table->index('kebab_style_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kebab_style_restaurant');
    }
};
