<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document de vérification / KYC (§12). Déposé par un investisseur (avant
 * d'investir) ou par un promoteur (avant publication d'un projet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Rattachement optionnel à un projet (vérification de projet).
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();

            $table->string('context');   // investor_kyc | promoter_kyc | project_verification
            $table->string('type');      // id_card | proof_of_address | project_deed | ...
            $table->string('file_path');
            $table->string('original_name')->nullable();

            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_documents');
    }
};
