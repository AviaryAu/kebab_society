<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('research_category')->nullable()->after('location_precision');
            $table->string('research_status')->nullable()->after('research_category');
            $table->string('research_source')->nullable()->after('research_status');
            $table->date('research_last_verified')->nullable()->after('research_source');
            $table->text('research_verification_notes')->nullable()->after('research_last_verified');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn([
                'research_category',
                'research_status',
                'research_source',
                'research_last_verified',
                'research_verification_notes',
            ]);
        });
    }
};
