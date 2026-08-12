<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('adapter', 60)->index();
            $table->string('tier', 20)->index();
            $table->string('trust', 20)->index();

            $table->string('endpoint')->nullable();
            $table->string('sitemap_url')->nullable();
            $table->string('website')->nullable();

            // Paths we are permitted to crawl. Sydney Opera House, for one,
            // disallows everything then allows a named list, so a source needs
            // to carry its own allowlist rather than rely on a global rule.
            $table->json('path_allowlist')->nullable();

            // API keys and basic-auth pairs. Encrypted at rest by the model.
            $table->text('credentials')->nullable();

            // Adapter-specific knobs, and the source taxonomy mapped onto the
            // eight slugs in config('kslive.categories').
            $table->json('options')->nullable();
            $table->json('category_map')->nullable();
            $table->string('default_category_slug', 60)->nullable();

            $table->unsignedSmallInteger('frequency_minutes')->default(360);
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(30);

            $table->boolean('auto_publish')->default(false);
            $table->boolean('allow_image_import')->default(false);
            $table->boolean('is_enabled')->default(true)->index();

            // Kept for the record so an audit can retrace our permission.
            $table->string('licence')->nullable();
            $table->string('terms_url')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('last_run_at')->nullable()->index();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->text('last_message')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_sources');
    }
};
