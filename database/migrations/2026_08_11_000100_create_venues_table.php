<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('suburb')->index();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->longText('body')->nullable();
            $table->string('image')->nullable();
            $table->string('website')->nullable();
            $table->string('social_url')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('transport')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('published')->index();
            $table->boolean('featured')->default(false)->index();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
