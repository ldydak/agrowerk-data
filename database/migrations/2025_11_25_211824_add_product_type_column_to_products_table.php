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
        Schema::connection($this->connection)->table('products', function (Blueprint $table) {
            $table->text('product_type')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('products', function (Blueprint $table) {
            $table->dropColumn([
                'product_type'
            ]);
        });
    }
};
