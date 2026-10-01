<?php

use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{Schema::create('woocommerce_auth_requests',function(Blueprint $t){$t->id();$t->char('nonce_hash',64)->unique();$t->foreignId('merchant_id')->constrained()->cascadeOnDelete();$t->string('store_url',2048);$t->timestamp('expires_at')->index();$t->timestamp('used_at')->nullable();$t->timestamps();});}public function down():void{Schema::dropIfExists('woocommerce_auth_requests');}};
