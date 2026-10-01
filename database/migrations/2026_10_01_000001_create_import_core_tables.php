<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('salla_merchant_id')->unique();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('woocommerce_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('store_url', 2048);
            $table->text('consumer_key');
            $table->text('consumer_secret');
            $table->string('status')->default('pending');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->index('merchant_id');
        });
        Schema::create('imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('woocommerce_connection_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft')->index();
            $table->json('settings')->nullable();
            $table->unsignedInteger('total_products')->default(0);
            $table->unsignedInteger('completed_products')->default(0);
            $table->unsignedInteger('failed_products')->default(0);
            $table->timestamp('scan_started_at')->nullable();
            $table->timestamp('scan_completed_at')->nullable();
            $table->timestamp('import_started_at')->nullable();
            $table->timestamp('import_completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('import_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('import_id');
            $table->unsignedBigInteger('source_product_id');
            $table->unsignedBigInteger('destination_product_id')->nullable();
            $table->string('source_type');
            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->json('source_snapshot');
            $table->json('normalized_data');
            $table->string('validation_status')->default('pending');
            $table->string('import_status')->default('pending')->index();
            $table->string('verification_status')->default('pending');
            $table->string('current_step')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->foreign('import_id')->references('id')->on('imports')->cascadeOnDelete();
            $table->unique(['import_id', 'source_product_id']);
        });
        Schema::create('product_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('woocommerce_connection_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('source_product_id');
            $table->unsignedBigInteger('salla_product_id');
            $table->timestamps();
            $table->unique(['merchant_id','woocommerce_connection_id','source_product_id'], 'product_mapping_source_unique');
        });
        Schema::create('variant_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_product_id');
            $table->unsignedBigInteger('source_variant_id');
            $table->unsignedBigInteger('salla_variant_id');
            $table->string('signature');
            $table->timestamps();
            $table->foreign('import_product_id')->references('id')->on('import_products')->cascadeOnDelete();
            $table->unique(['import_product_id','source_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_mappings'); Schema::dropIfExists('product_mappings'); Schema::dropIfExists('import_products');
        Schema::dropIfExists('imports'); Schema::dropIfExists('woocommerce_connections'); Schema::dropIfExists('merchants');
    }
};
