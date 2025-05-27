<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::table('allway_chat_accounts', function (Blueprint $table) {
            $table->dropUnique(['server_url', 'account_id']);
        });
    }

    public function down()
    {
        Schema::table('allway_chat_accounts', function (Blueprint $table) {
            $table->unique(['server_url', 'account_id']);
        });
    }
}; 