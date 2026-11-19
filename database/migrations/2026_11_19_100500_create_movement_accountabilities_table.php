<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('movement_accountabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->constrained('movements')->cascadeOnDelete();
            $table->date('rendered_at');
            $table->decimal('amount', 15, 2)->nullable();
            $table->text('description');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('movement_id');
            $table->index('rendered_at');
        });

        Schema::create('accountability_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accountability_id')->constrained('movement_accountabilities')->cascadeOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->index('accountability_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accountability_attachments');
        Schema::dropIfExists('movement_accountabilities');
    }
};
