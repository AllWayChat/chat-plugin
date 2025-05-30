<?php namespace Allway\Chat\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class Account extends Model
{
    use Validation;

    public $table = 'allway_chat_accounts';

    public $rules = [
        'name'         => 'required',
        'token'        => 'required',
        'server_url'   => 'required|url',
        'account_id'   => 'required|integer',
    ];

    protected $guarded = [];

    protected $fillable = [
        'name',
        'token',
        'server_url',
        'account_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'account_id' => 'integer',
    ];

    public function getApiUrlAttribute()
    {
        return rtrim($this->server_url, '/') . '/api/v1';
    }

    public function getPublicApiUrlAttribute()
    {
        return rtrim($this->server_url, '/') . '/public/api/v1';
    }

    public function getFullApiUrlAttribute()
    {
        return $this->api_url;
    }

    public function getInboxIdOptions()
    {
        $accountId = post('TestMessage.account_id') ?: post('account_id') ?: $this->id;
        $account = self::find($accountId);
        
        $options = [];
        if ($account) {
            $inboxes = \Allway\Chat\Classes\AllwayService::getAccountInboxes($account);
            if ($inboxes) {
                foreach ($inboxes as $inbox) {
                    $options[$inbox['id']] = $inbox['name'] . ' (' . $inbox['channel_type'] . ')';
                }
            }
        }
        
        return $options;
    }
}
