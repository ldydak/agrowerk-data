<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql-sklep';

   public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('short_description')->nullable()->after('is_active');
            $table->longText('section_seo_texts')->nullable()->after('short_description');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'short_description',
                'section_seo_texts',
            ]);
        });
    }
};
