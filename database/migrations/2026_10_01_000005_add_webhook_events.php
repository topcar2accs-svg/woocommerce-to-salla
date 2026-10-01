<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up():void{Schema::create('webhook_events',function(Blueprint $table){$table->id();$table->char('fingerprint',64)->unique();$table->string('event')->index();$table->unsignedBigInteger('merchant_id')->nullable()->index();$table->timestamp('processed_at');});}
 public function down():void{Schema::dropIfExists('webhook_events');}
};
