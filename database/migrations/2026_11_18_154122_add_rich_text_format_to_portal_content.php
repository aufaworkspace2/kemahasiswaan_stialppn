<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pages' => 'body_format', 'posts' => 'body_format', 'services' => 'description_format', 'letter_template_versions' => 'body_format'] as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->string($column, 10)->default('plain');
            });
        }
    }

    public function down(): void
    {
        foreach (['pages' => 'body_format', 'posts' => 'body_format', 'services' => 'description_format', 'letter_template_versions' => 'body_format'] as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropColumn($column);
            });
        }
    }
};
