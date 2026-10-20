<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_guide_snapshots', function (Blueprint $table) {
            $table->foreignId('local_guide_id')->constrained()->cascadeOnDelete();
            $table->date('snap_date');
            $table->unsignedTinyInteger('level')->nullable();
            $table->unsignedInteger('points')->nullable();
            $table->unsignedInteger('reviews')->nullable();
            $table->unsignedInteger('photos')->nullable();
            $table->unsignedInteger('videos')->nullable();
            $table->unsignedBigInteger('photo_views')->nullable();

            $table->primary(['local_guide_id', 'snap_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_guide_snapshots');
    }
};
