<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnement promoteur (§12, §16.2.a). Un abonnement actif est requis pour
 * publier effectivement un projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoter_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('tier');       // basic | premium | enterprise
            $table->unsignedBigInteger('price'); // FCFA / mois
            $table->string('currency', 3)->default('XOF');
            $table->string('status')->default('pending_payment')->index();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promoter_subscriptions');
    }
};
