<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notification (§12, §7.7). Entité applicative multi-canal (in-app, SMS,
 * email, WhatsApp). Table nommée `app_notifications` pour ne pas entrer en
 * conflit avec la table `notifications` par défaut de Laravel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('channel')->default('in_app'); // in_app | sms | email | whatsapp
            $table->string('type');   // ex: project.funded, kyc.approved...
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
