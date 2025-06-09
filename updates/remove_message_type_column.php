<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::table('allway_chat_logs', function (Blueprint $table) {
            if (Schema::hasColumn('allway_chat_logs', 'message_type')) {
                $table->dropColumn('message_type');
            }
        });
    }

    public function down()
    {
        Schema::table('allway_chat_logs', function (Blueprint $table) {
            if (Schema::hasColumn('allway_chat_logs', 'message_type')) {
                $table->string('message_type')->after('account_id');
            }
        });
    }
}; 