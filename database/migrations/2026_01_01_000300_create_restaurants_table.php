<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Location
            $table->string('address_line');
            $table->foreignId('suburb_id')->constrained()->restrictOnDelete();
            $table->string('postcode', 4);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            // Contact
            $table->string('phone')->nullable();
            $table->string('website')->nullable();

            // External identity (Google Places is a source, never the database)
            $table->string('google_place_id')->nullable()->unique();
            $table->decimal('google_rating', 2, 1)->nullable();
            $table->unsignedInteger('google_review_count')->nullable();
            $table->timestamp('google_data_updated_at')->nullable();

            // Trading
            $table->json('opening_hours')->nullable();
            $table->unsignedTinyInteger('price_level')->nullable(); // 1 (cheap) - 4
            $table->string('status')->default('published');

            // Society verdict
            $table->unsignedTinyInteger('kebab_score')->nullable();
            $table->json('score_breakdown')->nullable();
            $table->smallInteger('editorial_adjustment')->default(0);
            $table->text('editorial_note')->nullable();
            $table->decimal('society_rating', 2, 1)->nullable();
            $table->unsignedInteger('society_review_count')->default(0);
            $table->unsignedInteger('check_in_count')->default(0);
            $table->string('verification_status')->default('unverified');
            $table->timestamp('society_approved_at')->nullable();

            // Provenance
            $table->string('data_source')->default('seed_data');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['latitude', 'longitude']);
            $table->index(['status', 'kebab_score']);
            $table->index('suburb_id');
            $table->index('society_approved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
