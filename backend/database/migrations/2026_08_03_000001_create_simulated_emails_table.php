<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boîte d'envoi simulée : chaque message que l'application « envoie » y est
 * déposé au lieu de partir sur un serveur SMTP (voir config/mail_simulation.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulated_emails', function (Blueprint $table) {
            $table->id();

            // Identifiant public du message : c'est lui qui circule dans l'URL
            // de prévisualisation, jamais l'auto-incrément (devinable).
            $table->uuid()->unique();

            $table->string('to_email')->index();
            $table->string('to_name')->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject');

            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();

            // Nature du message (« password.reset », « password.changed »…) :
            // permet de filtrer la boîte sans analyser le sujet.
            $table->string('context')->nullable()->index();

            // Lien principal porté par le message, extrait à l'envoi pour que
            // la console d'administration puisse le montrer sans rendre le HTML.
            $table->string('action_url', 2048)->nullable();

            // Destinataire connu de la plateforme, quand il y en a un.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulated_emails');
    }
};
