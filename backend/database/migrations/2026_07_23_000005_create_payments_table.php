<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement (§12, §7.4). Principalement l'abonnement promoteur en MVP.
 * Intégrations Mobile Money (Wave, Orange Money), simulées en MVP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Rattachements optionnels selon le motif.
            $table->foreignId('subscription_id')->nullable()
                ->constrained('promoter_subscriptions')->nullOnDelete();
            $table->foreignId('project_id')->nullable()
                ->constrained('projects')->nullOnDelete();

            $table->string('purpose');   // subscription | contribution
            $table->string('provider');  // wave | orange_money
            $table->unsignedBigInteger('amount'); // FCFA
            $table->string('currency', 3)->default('XOF');
            $table->string('status')->default('pending')->index();

            $table->string('reference')->unique(); // référence interne / opérateur
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
