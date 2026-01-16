<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->decimal('exchangeRate', 10, 2);
            $table->decimal('profit_to_50euro', 10, 0);
            $table->decimal('profit_to_100euro', 10, 0);
            $table->decimal('profit_to_200euro', 10, 0);
            $table->decimal('profit_to_500euro', 10, 0);
            $table->decimal('profit_above_500euro', 10, 0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prices');
    }
};
