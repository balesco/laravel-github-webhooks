<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('git_hub_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('delivery_id')->nullable()->index();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('git_hub_webhooks');
    }
};
