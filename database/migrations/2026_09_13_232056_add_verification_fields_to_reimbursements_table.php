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
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->string('receipt_number')->nullable()->after('merchant_name');
            $table->text('verification_notes')->nullable()->after('receipt_number');
        });
    }

    public function down()
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropColumn(['receipt_number', 'verification_notes']);
        });
    }
};
