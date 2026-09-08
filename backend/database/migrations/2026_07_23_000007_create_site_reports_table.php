<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rapport chantier (§12, §7.5). Suivi d'avancement d'un projet financé :
 * progression, rapports, photos et preuves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->longText('description')->nullable();
            $table->unsignedTinyInteger('progress_percentage')->default(0); // 0..100
            $table->json('photos')->nullable();

            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_reports');
    }
};
