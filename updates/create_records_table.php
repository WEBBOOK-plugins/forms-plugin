<?php

declare(strict_types=1);

namespace WebBook\Forms\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

/*
 * CreateRecordsTable Migration
 */
return new class() extends Migration {
    /**
     * up builds the migration.
     */
    public function up(): void
    {
        Schema::create('webbook_forms_records', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('None');
            $table->text('form_data')->nullable();
            $table->string('ip')->nullable();
            $table->boolean('unread')->default(1);
            $table->timestamps();
        });
    }

    /**
     * down reverses the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('webbook_forms_records');
    }
};
