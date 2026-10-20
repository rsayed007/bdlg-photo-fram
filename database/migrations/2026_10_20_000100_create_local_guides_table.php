<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contrib_id', 32)->unique();
            $table->string('profile_url', 500);
            $table->string('name', 200)->nullable();
            $table->string('avatar_url', 500)->nullable();
            // Every stat is nullable: null means "unknown", never store 0 for unknown.
            $table->unsignedTinyInteger('level')->nullable()->index();
            $table->unsignedInteger('points')->nullable()->index();
            $table->unsignedInteger('reviews')->nullable()->index();
            $table->unsignedInteger('photos')->nullable()->index();
            $table->unsignedInteger('videos')->nullable();
            $table->unsignedBigInteger('photo_views')->nullable()->index();
            $table->unsignedInteger('places_added')->nullable();
            $table->unsignedInteger('roads_added')->nullable();
            $table->enum('status', ['pending', 'active', 'restricted', 'failed', 'inactive'])->default('pending')->index();
            $table->unsignedInteger('fail_count')->default(0);
            $table->dateTime('last_synced_at')->nullable();
            $table->dateTime('last_attempt_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_guides');
    }
};
