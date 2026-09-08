<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Score IA (§12, §8). Résultat du service de scoring intelligent : score de
 * confiance, estimation de rentabilité et niveau de risque d'un projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            $table->decimal('confidence_score', 5, 2); // 0..100 score de confiance
            $table->decimal('roi_estimate', 5, 2)->nullable(); // rentabilité estimée %
            $table->unsignedSmallInteger('payback_months')->nullable(); // délai de rentabilité
            $table->string('risk_level')->default('medium'); // low | medium | high

            // Facteurs explicatifs (localisation, budget, historique promoteur...).
            $table->json('factors')->nullable();
            $table->string('model_version')->nullable();
            $table->timestamp('computed_at')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'computed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_scores');
    }
};
