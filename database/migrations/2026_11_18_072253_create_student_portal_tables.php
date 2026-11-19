<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('role_user', function (Blueprint $t): void {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'user_id']);
        });
        Schema::create('permission_user', function (Blueprint $t): void {
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['permission_id', 'user_id']);
        });
        Schema::create('services', function (Blueprint $t): void {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('summary');
            $t->text('description');
            $t->string('icon');
            $t->unsignedInteger('position')->default(0);
            $t->boolean('is_sensitive')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('service_assignments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->unique(['user_id', 'service_id']);
        });
        Schema::create('study_programs', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('cohorts', function (Blueprint $t): void {
            $t->id();
            $t->unsignedSmallInteger('year')->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('service_categories', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('service_id')->constrained();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('forms', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('service_id')->constrained();
            $t->foreignId('service_category_id')->constrained();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('status')->default('draft');
            $t->unsignedBigInteger('active_version_id')->nullable();
            $t->timestamp('opens_at')->nullable();
            $t->timestamp('closes_at')->nullable();
            $t->timestamps();
            $t->index(['service_id', 'status']);
        });
        Schema::create('form_versions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('form_id')->constrained();
            $t->unsignedInteger('version');
            $t->string('status')->default('draft');
            $t->json('fields');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['form_id', 'version']);
        });
        Schema::create('applications', function (Blueprint $t): void {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('form_id')->constrained();
            $t->foreignId('form_version_id')->constrained();
            $t->foreignId('service_id')->constrained();
            $t->foreignId('service_category_id')->constrained();
            $t->foreignId('study_program_id')->constrained();
            $t->foreignId('cohort_id')->constrained();
            $t->string('student_number', 30)->index();
            $t->string('full_name', 150);
            $t->string('reply_channel');
            $t->string('email')->nullable();
            $t->string('whatsapp', 25)->nullable();
            $t->json('answers');
            $t->string('status')->default('received');
            $t->uuid('idempotency_key')->unique();
            $t->string('receipt_path')->nullable();
            $t->timestamp('submitted_at');
            $t->timestamps();
            $t->index(['service_id', 'status', 'created_at']);
            $t->index(['study_program_id', 'cohort_id']);
        });
        Schema::create('application_revisions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->json('answers');
            $t->json('identity');
            $t->foreignId('submitted_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('application_attachments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->foreignId('application_revision_id')->constrained();
            $t->string('field_key');
            $t->string('original_name');
            $t->string('path');
            $t->string('mime_type');
            $t->unsignedBigInteger('size');
            $t->timestamps();
        });
        Schema::create('application_status_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->text('message')->nullable();
            $t->timestamps();
        });
        Schema::create('application_notes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('notification_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->string('channel');
            $t->string('kind');
            $t->string('destination');
            $t->text('message');
            $t->text('access_url')->nullable();
            $t->string('status')->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('sent_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
        Schema::create('application_access_tokens', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->string('purpose');
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('pages', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->longText('body');
            $t->string('status')->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('page_sections', function (Blueprint $t): void {
            $t->id();
            $t->string('type')->unique();
            $t->string('title');
            $t->text('body')->nullable();
            $t->unsignedInteger('position');
            $t->boolean('is_visible')->default(true);
            $t->timestamps();
        });
        Schema::create('menu_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $t->string('label');
            $t->string('target_type')->default('url');
            $t->unsignedBigInteger('target_id')->nullable();
            $t->string('url')->nullable();
            $t->unsignedInteger('position')->default(0);
            $t->boolean('is_visible')->default(true);
            $t->timestamps();
        });
        Schema::create('posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('excerpt', 500);
            $t->longText('body');
            $t->string('kind')->default('news');
            $t->string('status')->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'published_at']);
        });
        Schema::create('media', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('path');
            $t->string('mime_type');
            $t->unsignedBigInteger('size');
            $t->foreignId('user_id')->constrained();
            $t->timestamps();
        });
        Schema::create('site_settings', function (Blueprint $t): void {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('letter_templates', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('service_id')->constrained();
            $t->string('name');
            $t->unsignedBigInteger('active_version_id')->nullable();
            $t->timestamps();
        });
        Schema::create('letter_template_versions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('letter_template_id')->constrained();
            $t->unsignedInteger('version');
            $t->text('body');
            $t->string('signatory');
            $t->string('title');
            $t->timestamps();
            $t->unique(['letter_template_id', 'version']);
        });
        Schema::create('letter_sequences', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('service_id')->constrained();
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('next_number')->default(1);
            $t->timestamps();
            $t->unique(['service_id', 'year']);
        });
        Schema::create('letters', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('application_id')->constrained();
            $t->foreignId('letter_template_version_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('number')->unique();
            $t->json('snapshot');
            $t->string('path')->nullable();
            $t->string('status')->default('pending');
            $t->foreignId('replaces_id')->nullable()->constrained('letters');
            $t->timestamps();
            $t->unique(['application_id', 'letter_template_version_id']);
        });
        Schema::create('audit_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'letters', 'letter_sequences', 'letter_template_versions', 'letter_templates', 'site_settings', 'media', 'posts', 'menu_items', 'page_sections', 'pages', 'application_access_tokens', 'notification_deliveries', 'application_notes', 'application_status_logs', 'application_attachments', 'application_revisions', 'applications', 'form_versions', 'forms', 'service_categories', 'cohorts', 'study_programs', 'service_assignments', 'services', 'permission_user', 'role_user', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
