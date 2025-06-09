<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::table('allway_chat_accounts', function (Blueprint $table) {
            if (Schema::hasIndex('allway_chat_accounts', 'allway_chat_accounts_server_url_account_id_unique')) {
                $table->dropUnique(['server_url', 'account_id']);
            }
        });
    }

    public function down()
    {
        Schema::table('allway_chat_accounts', function (Blueprint $table) {
            if (Schema::hasIndex('allway_chat_accounts', 'allway_chat_accounts_server_url_account_id_unique')) {
                $table->unique(['server_url', 'account_id']);
            }
        });
    }
}; 