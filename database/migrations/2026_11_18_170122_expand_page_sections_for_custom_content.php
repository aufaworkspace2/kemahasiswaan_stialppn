<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_sections', function (Blueprint $table): void {
            $table->dropUnique(['type']);
            $table->string('name')->nullable();
            $table->string('anchor', 100)->nullable()->unique();
            $table->string('location', 20)->default('home');
            $table->string('body_format', 10)->default('plain');
            $table->json('settings')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('mobile_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->index(['is_visible', 'location', 'position']);
        });
        foreach (DB::table('page_sections')->get() as $section) {
            DB::table('page_sections')->where('id', $section->id)->update(['name' => $section->title, 'anchor' => $section->type]);
        }
    }

    public function down(): void
    {
        Schema::table('page_sections', function (Blueprint $table): void {
            $table->dropForeign(['media_id']);
            $table->dropForeign(['mobile_media_id']);
            $table->dropIndex(['is_visible', 'location', 'position']);
            $table->dropUnique(['anchor']);
            $table->dropColumn(['name', 'anchor', 'location', 'body_format', 'settings', 'media_id', 'mobile_media_id', 'starts_at', 'ends_at']);
        });
        // Repeated section types remain valid after rollback to avoid dropping content.
    }
};
