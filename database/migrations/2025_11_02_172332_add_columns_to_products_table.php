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
            $table->decimal('oryginal_price', 18, 4)->nullable()->after('price');
            $table->string('ean', 191)->nullable()->after('oryginal_price');
            $table->string('wee', 191)->nullable()->after('ean');
            $table->decimal('weight', 10, 3)->nullable()->after('wee');
            $table->text('product_sheet_url')->nullable()->after('weight');
            $table->text('safety_sheet_url')->nullable()->after('product_sheet_url');
            $table->text('manual_url')->nullable()->after('safety_sheet_url');
            $table->text('chemical_info')->nullable()->after('manual_url');
            $table->text('oryginal_url')->nullable()->after('chemical_info');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('products', function (Blueprint $table) {
            $table->dropColumn([
                'oryginal_price',
                'ean',
                'wee',
                'weight',
                'product_sheet_url',
                'safety_sheet_url',
                'manual_url',
                'chemical_info',
                'oryginal_url',
            ]);
        });
    }
};
