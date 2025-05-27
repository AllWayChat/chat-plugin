<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('allway_chat_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('to_contact');
            $table->integer('account_id')->unsigned();
            $table->text('content')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at');
            $table->integer('conversation_id')->unsigned()->nullable();
            $table->integer('allway_message_id')->unsigned()->nullable();
            
            $table->foreign('account_id')->references('id')->on('allway_chat_accounts')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('allway_chat_logs');
    }
}; 