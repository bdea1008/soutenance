<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transaction (§12). Écriture de suivi (journal) rattachée à un paiement,
 * pour l'historique des transactions (§7.4) et la traçabilité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('direction'); // credit | debit
            $table->unsignedBigInteger('amount'); // FCFA
            $table->string('currency', 3)->default('XOF');
            $table->string('label');
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
