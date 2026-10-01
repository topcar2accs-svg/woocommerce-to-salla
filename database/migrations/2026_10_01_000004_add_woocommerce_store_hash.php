<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('woocommerce_connections', function (Blueprint $table) {
            $table->char('store_hash',64)->nullable()->after('store_url');
            $table->unique(['merchant_id','store_hash']);
        });
    }
    public function down(): void { Schema::table('woocommerce_connections', fn(Blueprint $table)=>$table->dropColumn('store_hash')); }
};
