<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contribution (§12). Participation d'un investisseur à un projet.
 * L'investissement est sans frais pour l'investisseur (§2). En MVP, la
 * contribution est simulée (pas de gestion réelle de fonds, §16.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            $table->unsignedBigInteger('amount'); // FCFA
            $table->decimal('share_percentage', 6, 3)->nullable(); // quote-part du projet
            $table->decimal('estimated_return', 15, 2)->nullable(); // rendement simulé

            $table->string('status')->default('pending')->index();
            $table->boolean('is_simulated')->default(true); // MVP
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['investor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
