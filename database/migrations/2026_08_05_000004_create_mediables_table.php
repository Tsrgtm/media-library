<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mediables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->morphs('mediable');
            $table->string('collection', 100)->default('default');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_properties')->nullable();
            $table->timestamps();

            $table->unique(
                ['media_id', 'mediable_type', 'mediable_id', 'collection'],
                'mediables_unique_attachment',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediables');
    }
};
