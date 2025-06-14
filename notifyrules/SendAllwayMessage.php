<?php namespace Allway\Chat\NotifyRules;

use Allway\Chat\Classes\AllwayService;
use Allway\Chat\Classes\Helpers\Lazy;
use Allway\Chat\Models\Account;
use RainLab\Notify\Classes\ActionBase;

class SendAllwayMessage extends ActionBase
{
    public function defineValidationRules()
    {
        return [
            'account_type'   => 'required|in:account,account_id',
            'account'        => 'required_if:account_type,account',
            'account_id'     => 'required_if:account_type,account_id',
            'inbox_id'       => 'required_if:account_type,account',
            'inbox_id_text'  => 'required_if:account_type,account_id',
            'contact'        => 'required',
            'message_type'   => 'required|in:text,image,document',
            'text'           => 'required_if:message_type,text',
            'image_url'      => 'required_if:message_type,image',
            'document_url'   => 'required_if:message_type,document',
        ];
    }

    public function actionDetails()
    {
        return [
            'name'        => 'Allway Chat - Enviar Mensagem',
            'description' => 'Envia uma mensagem através do Allway Chat.',
            'icon'        => 'fa fa-comments'
        ];
    }

    public function defineFormFields()
    {
        return 'fields.yaml';
    }

    public function getText()
    {
        $messageType = $this->host->message_type ?? 'text';
        $messageTypeLabel = $this->getMessageTypeOptions()[$messageType] ?? $messageType;
        $contact = $this->host->contact ?? '';
        $conversationControl = $this->host->conversation_control ?? 'default';
        $accountType = $this->host->account_type ?? 'account';
        
        $text = 'Enviar ' . $messageTypeLabel . ' para: ' . $contact;
        
        if ($accountType === 'account' && !empty($this->host->account)) {
            $account = Account::find($this->host->account);
            if ($account) {
                $text .= ' (Conta: ' . $account->name;
                
                if (!empty($this->host->inbox_id)) {
                    $inboxes = AllwayService::getAccountInboxes($account);
                    foreach ($inboxes as $inbox) {
                        if ($inbox['id'] == $this->host->inbox_id) {
                            $text .= ', Canal: ' . $inbox['name'] . ' (' . $inbox['channel_type'] . ')';
                            break;
                        }
                    }
                }
                
                $text .= ')';
            }
        } elseif ($accountType === 'account_id' && !empty($this->host->account_id)) {
            $text .= ' (ID da conta: ' . $this->host->account_id;
            
            if (!empty($this->host->inbox_id_text)) {
                $text .= ', Canal ID: ' . $this->host->inbox_id_text;
            }
            
            $text .= ')';
        }
        
        // Adicionar informação sobre o controle de conversa se não for padrão
        if ($conversationControl !== 'default') {
            $controlLabels = [
                'reuse' => 'reutilizando conversa',
                'force_new' => 'criando nova conversa',
                'specific' => 'na conversa específica'
            ];
            
            if (isset($controlLabels[$conversationControl])) {
                $text .= ' (' . $controlLabels[$conversationControl];
                
                if ($conversationControl === 'specific' && !empty($this->host->conversation_id)) {
                    $text .= ': ' . $this->host->conversation_id;
                }
                
                $text .= ')';
            }
        }
        
        return $text;
    }

    public function triggerAction($params)
    {
        $params = $params ?: [];
        $data = [];

        $extra_php_code = Lazy::twigRawParser((string) $this->host->extra_php_code, $params);
        if ($extra_php_code) {
            foreach ($params as $key => $value) {
                $$key = $value;
            }
            eval("$extra_php_code");
            foreach ($params as $key => $value) {
                unset($$key);
            }
        }

        $data = $params + $data;

        $accountType = $this->host->account_type ?? 'account';
        $account = null;
        
        if ($accountType === 'account') {
            $account = Account::find($this->host->account);
        } else if ($accountType === 'account_id') {
            $accountId = Lazy::twigRawParser((string)$this->host->account_id, $data);
            $account = Account::find($accountId);
        }

        if (!$account || !$account->is_active) {
            return;
        }

        $inboxId = null;
        
        if ($accountType === 'account') {
            $inboxId = (int)$this->host->inbox_id;
        } else if ($accountType === 'account_id') {
            $inboxIdRaw = Lazy::twigRawParser((string)$this->host->inbox_id_text, $data);
            $inboxId = (int)$inboxIdRaw;
        }

        $contactIdentifier = Lazy::twigRawParser((string)$this->host->contact, $data);
        $contactName = Lazy::twigRawParser((string)($this->host->contact_name ?? ''), $data);
        $messageType = (string)($this->host->message_type ?? 'text');

        $conversationControl = (string)($this->host->conversation_control ?? 'default');
        $conversationIdRaw = Lazy::twigRawParser((string)($this->host->conversation_id ?? ''), $data);
        $conversationId = !empty($conversationIdRaw) ? (int)$conversationIdRaw : null;

        $contactCustomAttributes = [];
        $conversationCustomAttributes = [];
        $customAttributes = $this->host->custom_attributes ?? [];
        
        if (is_array($customAttributes)) {
            foreach ($customAttributes as $attr) {
                $type = $attr['type'] ?? 'contact';
                $key = null;
                $value = null;
                
                if ($type === 'contact' && !empty($attr['key'])) {
                    $key = $attr['key'];
                } else if ($type === 'conversation' && !empty($attr['key_conversation'])) {
                    $key = $attr['key_conversation'];
                }
                
                if ($key && isset($attr['value'])) {
                    $key = Lazy::twigRawParser((string)$key, $data);
                    $value = Lazy::twigRawParser((string)$attr['value'], $data);
                    
                    if ($type === 'contact') {
                        $contactCustomAttributes[$key] = $value;
                    } else if ($type === 'conversation') {
                        $conversationCustomAttributes[$key] = $value;
                    }
                }
            }
        }

        $etiquetas = [];
        $etiquetasData = $this->host->etiquetas ?? [];
        if (is_array($etiquetasData)) {
            foreach ($etiquetasData as $etiquetaItem) {
                if (!empty($etiquetaItem['etiqueta'])) {
                    $etiquetas[] = $etiquetaItem['etiqueta'];
                }
            }
        }

        $conversationStatus = Lazy::twigRawParser((string)($this->host->conversation_status ?? ''), $data);

        $forceNewConversation = false;
        $specificConversationId = null;
        
        switch ($conversationControl) {
            case 'force_new':
                $forceNewConversation = true;
                break;
            case 'specific':
                $specificConversationId = $conversationId;
                break;
            case 'reuse':
            case 'default':
            default:
                break;
        }

        try {
            $result = null;
            
            switch ($messageType) {
                case 'text':
                    $content = Lazy::twigRawParser((string)$this->host->text, $data);
                    $result = AllwayService::sendText($account, $contactIdentifier, $content, $inboxId, $contactName, $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $specificConversationId, $conversationStatus);
                    break;

                case 'image':
                    $imageUrl = Lazy::twigRawParser((string)$this->host->image_url, $data);
                    $caption = Lazy::twigRawParser((string)($this->host->caption ?? ''), $data);
                    $result = AllwayService::sendImage($account, $contactIdentifier, $imageUrl, $inboxId, $contactName, $caption, $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $specificConversationId, $conversationStatus);
                    break;

                case 'document':
                    $documentUrl = Lazy::twigRawParser((string)$this->host->document_url, $data);
                    $caption = Lazy::twigRawParser((string)($this->host->caption ?? ''), $data);
                    $filename = Lazy::twigRawParser((string)($this->host->document_filename ?? ''), $data);
                    $result = AllwayService::sendDocument($account, $contactIdentifier, $documentUrl, $inboxId, $contactName, $caption, $filename, $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $specificConversationId, $conversationStatus);
                    break;
            }
            
            // Aplicar etiquetas se foram especificadas e se temos o resultado da mensagem
            if (!empty($etiquetas) && $result && isset($result['conversation_id'])) {
                AllwayService::addLabelsToConversation($account, $result['conversation_id'], $etiquetas);
            }
            
            // Alterar status da conversa se especificado
            if (!empty($conversationStatus) && $result && isset($result['conversation_id'])) {
                AllwayService::updateConversationStatus($account, $result['conversation_id'], $conversationStatus);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getMessageTypeOptions(): array
    {
        return [
            'text'     => 'Texto',
            'image'    => 'Imagem',
            'document' => 'Arquivo',
        ];
    }

    public function getAccountOptions(): array
    {
        $options = [];
        
        foreach (Account::where('is_active', true)->get() as $account) {
            $options[$account->id] = $account->name;
        }

        return $options;
    }
    
    public function getInboxOptions(): array
    {
        $accountId = post('account') ?? post('account_id') ?? $this->host->account ?? $this->host->account_id ?? null;
        
        if (!$accountId) {
            return [];
        }
        
        $account = Account::find($accountId);
        if (!$account) {
            return [];
        }
        
        $options = [];
        $inboxes = AllwayService::getAccountInboxes($account);
        
        if ($inboxes) {
            foreach ($inboxes as $inbox) {
                $options[$inbox['id']] = $inbox['name'] . ' (' . $inbox['channel_type'] . ') - ID: ' . $inbox['id'];
            }
        }
        
        return $options;
    }
    
    public function getEtiquetasOptions(): array
    {
        $accountId = post('account') ?? post('account_id') ?? $this->host->account ?? $this->host->account_id ?? null;
        
        if (!$accountId) {
            return [];
        }
        
        $account = Account::find($accountId);
        if (!$account) {
            return [];
        }
        
        return $account->formGetLabelsOptions($accountId);
    }
    
    public function getContactCustomAttributesKeyOptions(): array
    {
        $accountId = post('account') ?? post('account_id') ?? $this->host->account ?? $this->host->account_id ?? null;
        
        if (!$accountId) {
            return [];
        }
        
        $account = Account::find($accountId);
        if (!$account) {
            return [];
        }
        
        return $account->formGetCustomAttributesOptions($accountId, 1); // 1 = contact attributes
    }
    
    public function getConversationCustomAttributesKeyOptions(): array
    {
        $accountId = post('account') ?? post('account_id') ?? $this->host->account ?? $this->host->account_id ?? null;
        
        if (!$accountId) {
            return [];
        }
        
        $account = Account::find($accountId);
        if (!$account) {
            return [];
        }
        
        return $account->formGetCustomAttributesOptions($accountId, 0); // 0 = conversation attributes
    }
} 