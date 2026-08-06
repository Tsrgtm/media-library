<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('disk', 60)->default('public');
            $table->string('path')->nullable();
            $table->string('original_name');
            $table->string('file_name')->nullable();
            $table->string('mime_type', 160)->nullable();
            $table->string('extension', 30)->nullable();
            $table->string('kind', 30)->default('file');
            $table->string('status', 30)->default('uploading');

            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();

            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->json('responsive_images')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['folder_id', 'status']);
            $table->index(['kind', 'status']);
            $table->index('original_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
