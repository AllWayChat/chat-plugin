<?php namespace Allway\Chat\Models;

use Allway\Chat\Classes\AllwayService;
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

    /**
     * Busca as labels disponíveis na conta Allway Chat
     */
    public function getLabelsOptions()
    {
        $accountId = post('TestMessage.account_id') ?: post('account_id') ?: $this->id;
        return $this->formGetLabelsOptions($accountId);
    }

    public function formGetLabelsOptions($accountId)
    {
        $account = self::find($accountId);
        
        $options = [];
        if ($account) {
            try {
                $labels = AllwayService::getAccountLabels($account);
                if ($labels) {
                    foreach ($labels as $label) {
                        if (is_array($label) && isset($label['title'])) {
                            $title = $label['title'];
                            $description = isset($label['description']) ? " - {$label['description']}" : '';
                            $displayText = $title . $description;
                            
                            if (isset($label['color']) && !empty($label['color'])) {
                                $options[$title] = [$displayText, $label['color']];
                            } else {
                                $options[$title] = $displayText;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Erro ao buscar labels do Allway Chat: ' . $e->getMessage());
            }
        }
        
        return $options;
    }

    /**
     * Busca os custom attributes de contato disponíveis na conta Allway Chat
     */
    public function getContactCustomAttributesOptions()
    {
        $accountId = post('TestMessage.account_id') ?: post('account_id') ?: $this->id;
        return $this->formGetCustomAttributesOptions($accountId, 1); // 1 = contact attributes
    }

    /**
     * Busca os custom attributes de conversa disponíveis na conta Allway Chat
     */
    public function getConversationCustomAttributesOptions()
    {
        $accountId = post('TestMessage.account_id') ?: post('account_id') ?: $this->id;
        return $this->formGetCustomAttributesOptions($accountId, 0); // 0 = conversation attributes
    }

    public function formGetCustomAttributesOptions($accountId, $attributeModel = 0)
    {
        $account = self::find($accountId);
        
        $options = [];
        if ($account) {
            try {
                $attributes = AllwayService::getAccountCustomAttributes($account, $attributeModel);
                if ($attributes) {
                    foreach ($attributes as $attribute) {
                        if (is_array($attribute) && isset($attribute['attribute_key']) && isset($attribute['attribute_display_name'])) {
                            $key = $attribute['attribute_key'];
                            $displayName = $attribute['attribute_display_name'];
                            $type = isset($attribute['attribute_display_type']) ? " ({$attribute['attribute_display_type']})" : '';
                            $description = isset($attribute['attribute_description']) ? " - {$attribute['attribute_description']}" : '';
                            
                            $displayText = $displayName . $type . $description;
                            $options[$key] = $displayText;
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Erro ao buscar custom attributes do Allway Chat: ' . $e->getMessage());
            }
        }
        
        return $options;
    }

    /**
     * Opções para dropdown de custom attributes de contato (para uso em formulários)
     */
    public function getContactCustomAttributesKeyOptions()
    {
        $accountId = post('TestMessage.account_id') ?: post('account_id') ?: $this->id;
        return $this->formGetCustomAttributesOptions($accountId, 1); // 1 = contact attributes
    }

    /**
     * Opções para dropdown de custom attributes de conversa (para uso em formulários)
     */
    public function getConversationCustomAttributesKeyOptions()
    {
        $accountId = post('TestMessage.account_id') ?: post('account_id') ?: $this->id;
        return $this->formGetCustomAttributesOptions($accountId, 0); // 0 = conversation attributes
    }
}
