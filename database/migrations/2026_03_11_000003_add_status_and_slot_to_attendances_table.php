<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('status')->nullable()->after('scanned_at');
            $table->unsignedTinyInteger('slot_index')->nullable()->after('status');
            $table->time('slot_start')->nullable()->after('slot_index');
            $table->time('slot_end')->nullable()->after('slot_start');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['status', 'slot_index', 'slot_start', 'slot_end']);
        });
    }
};

