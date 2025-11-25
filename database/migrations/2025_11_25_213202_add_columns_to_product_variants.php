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
        Schema::connection($this->connection)->table('product_variants', function (Blueprint $table) {
            $table->decimal('oryginal_price', 18, 4)->nullable()->after('price');
            $table->string('ean', 191)->nullable()->after('oryginal_price');
            $table->string('wee', 191)->nullable()->after('ean');
            $table->decimal('weight', 10, 3)->nullable()->after('wee');
            $table->text('oryginal_url')->nullable()->after('wee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'oryginal_price',
                'ean',
                'wee',
                'weight',
                'oryginal_url',
            ]);
        });
    }
};
