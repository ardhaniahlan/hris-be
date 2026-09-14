<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->string('patient_name')->nullable()->after('certificate_date');
            $table->integer('rest_duration_days')->nullable()->after('patient_name');
            $table->text('verification_notes')->nullable()->after('rest_duration_days');
        });
    }

    public function down()
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn(['patient_name', 'rest_duration_days', 'verification_notes']);
        });
    }
};
