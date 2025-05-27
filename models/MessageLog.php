<?php namespace Allway\Chat\Models;

use Carbon\Carbon;
use Model;
use October\Rain\Database\Traits\Validation;

class MessageLog extends Model
{
    use Validation;

    public $table = 'allway_chat_logs';

    public $rules = [];

    protected $guarded = [];

    protected $fillable = [
        'to_contact',
        'account_id',
        'content',
        'error',
        'sent_at',
        'conversation_id',
        'allway_message_id',
    ];

    protected $dates = ['sent_at'];

    protected $jsonable = ['content', 'error'];

    public $timestamps = false;

    public $belongsTo = [
        'account' => Account::class
    ];

    protected function beforeCreate()
    {
        $this->sent_at = Carbon::now();
    }
}
