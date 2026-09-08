<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes et commentaires d'investisseurs (diagramme de cas d'utilisation :
 * « Noter / commenter un projet », extension d'« Investir dans un projet »).
 *
 * Réservé à qui a réellement investi dans le projet (vérifié en contrôleur,
 * pas ici) — un avis « vérifié acheteur », pas un forum ouvert. Une seule
 * note par investisseur et par projet : republier revient à la modifier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('rating'); // 1 à 5
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'investor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_reviews');
    }
};
