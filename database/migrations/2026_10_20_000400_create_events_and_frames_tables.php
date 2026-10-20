<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('frames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('size_w');
            $table->unsignedSmallInteger('size_h');
            $table->string('background_path', 500);
            $table->string('overlay_path', 500)->nullable();
            $table->string('thumbnail_path', 500)->nullable();
            $table->json('config');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('frame_generations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('frame_id')->constrained()->cascadeOnDelete();
            $table->foreignId('local_guide_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('output_path', 500);
            $table->json('stats_snapshot');
            $table->json('source_photo_paths')->nullable();
            $table->unsignedInteger('downloads')->default(0);
            $table->boolean('is_featured')->default(false)->index();
            $table->dateTime('sources_purged_at')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('stat_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_guide_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('changes');
            $table->string('note', 500);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stat_overrides');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('frame_generations');
        Schema::dropIfExists('frames');
        Schema::dropIfExists('events');
    }
};
