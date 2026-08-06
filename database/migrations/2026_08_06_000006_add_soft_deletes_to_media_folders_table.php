<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('media_folders')
            && ! Schema::hasColumn('media_folders', 'deleted_at')
        ) {
            Schema::table(
                'media_folders',
                function (Blueprint $table): void {
                    $table->softDeletes();
                },
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('media_folders')
            && Schema::hasColumn('media_folders', 'deleted_at')
        ) {
            Schema::table(
                'media_folders',
                function (Blueprint $table): void {
                    $table->dropSoftDeletes();
                },
            );
        }
    }
};
