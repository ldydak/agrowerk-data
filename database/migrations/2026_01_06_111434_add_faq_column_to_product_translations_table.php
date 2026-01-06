<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql-sklep';
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('product_translations', function (Blueprint $table) {
            $table->text('faq')->nullable()->after('short_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('product_translations', function (Blueprint $table) {
            $table->dropColumn([
                'faq'
            ]);
        });
    }
};
