<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projet immobilier (§12). Publié par un promoteur, ouvert au co-investissement.
 * Les montants financiers sont exprimés en FCFA (XOF), sans décimales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promoter_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();      // Accroche courte (aperçu public)
            $table->longText('description')->nullable();
            $table->string('category')->nullable();    // résidentiel, commercial, terrain...

            // Localisation géographique (§7.2, cartographie Mapbox/OSM).
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Objectifs financiers (§7.2) en FCFA.
            $table->unsignedBigInteger('funding_goal');
            $table->unsignedBigInteger('amount_raised')->default(0);
            $table->unsignedBigInteger('min_investment')->default(0);
            $table->decimal('expected_return_rate', 5, 2)->nullable(); // rendement estimé %
            $table->unsignedSmallInteger('duration_months')->nullable();

            // Calendrier du projet (§7.2).
            $table->date('funding_deadline')->nullable();
            $table->date('start_date')->nullable();
            $table->date('expected_end_date')->nullable();

            // Médias (§7.2).
            $table->string('cover_image')->nullable();
            $table->json('images')->nullable();

            // Cycle de vie.
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
