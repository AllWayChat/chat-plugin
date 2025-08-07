<?php namespace Allway\Chat\NotifyRules;

use Allway\Chat\Classes\AllwayService;
use Allway\Chat\Classes\Helpers\Lazy;
use Allway\Chat\Models\Account;
use RainLab\Notify\Classes\ActionBase;

class UpdateAllwayData extends ActionBase
{
    public function defineValidationRules()
    {
        return [
            'account_type'      => 'required|in:account,account_id',
            'account'           => 'required_if:account_type,account',
            'account_id'        => 'required_if:account_type,account_id',
            'conversation_find' => 'required|in:by_id,by_contact',
            'conversation_id'   => 'required_if:conversation_find,by_id',
            'contact'           => 'required_if:conversation_find,by_contact',
            // Validação personalizada: se buscar por contato, precisa de pelo menos um inbox
        ];
    }

    public function getValidationAttributes()
    {
        return [
            'inbox_id' => 'Canal',
            'inbox_id_text' => 'ID do Canal',
        ];
    }

    /**
     * Validação customizada para inbox quando buscar por contato
     */
    public function afterValidate()
    {
        $conversationFind = $this->host->conversation_find ?? 'by_id';
        
        if ($conversationFind === 'by_contact') {
            $accountType = $this->host->account_type ?? 'account';
            $inboxId = $this->host->inbox_id ?? null;
            $inboxIdText = $this->host->inbox_id_text ?? null;
            
            // Se tipo é 'account', precisa do inbox_id
            if ($accountType === 'account' && empty($inboxId)) {
                throw new \ValidationException(['inbox_id' => 'Canal é obrigatório quando buscar por contato.']);
            }
            
            // Se tipo é 'account_id', precisa do inbox_id_text  
            if ($accountType === 'account_id' && empty($inboxIdText)) {
                throw new \ValidationException(['inbox_id_text' => 'ID do Canal é obrigatório quando buscar por contato.']);
            }
        }
    }

    public function actionDetails()
    {
        return [
            'name'        => 'Allway Chat - Atualizar Dados',
            'description' => 'Atualiza dados de conversa/contato no Allway Chat sem enviar mensagem.',
            'icon'        => 'fa fa-edit'
        ];
    }

    public function defineFormFields()
    {
        return 'update_fields.yaml';
    }

    public function getText()
    {
        $conversationFind = $this->host->conversation_find ?? 'by_id';
        $accountType = $this->host->account_type ?? 'account';
        
        $text = 'Atualizar dados do Allway Chat ';
        
        if ($conversationFind === 'by_id' && !empty($this->host->conversation_id)) {
            $text .= 'na conversa ID: ' . $this->host->conversation_id;
        } elseif ($conversationFind === 'by_contact' && !empty($this->host->contact)) {
            $text .= 'do contato: ' . $this->host->contact;
        }
        
        if ($accountType === 'account' && !empty($this->host->account)) {
            $account = Account::find($this->host->account);
            if ($account) {
                $text .= ' (Conta: ' . $account->name;
                
                if ($conversationFind === 'by_contact' && !empty($this->host->inbox_id)) {
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
            
            if ($conversationFind === 'by_contact' && !empty($this->host->inbox_id_text)) {
                $text .= ', Canal ID: ' . $this->host->inbox_id_text;
            }
            
            $text .= ')';
        }
        
        return $text;
    }

    public function triggerAction($params)
    {
        $params = $params ?: [];
        $data = [];

        $extra_php_code = Lazy::twigRawParser((string) ($this->host->extra_php_code ?? ''), $params);
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

        $conversationFind = $this->host->conversation_find ?? 'by_id';
        $conversationId = null;
        
        if ($conversationFind === 'by_id') {
            $conversationIdRaw = Lazy::twigRawParser((string)($this->host->conversation_id ?? ''), $data);
            $conversationId = !empty($conversationIdRaw) ? (int)$conversationIdRaw : null;
        } elseif ($conversationFind === 'by_contact') {
            // Buscar conversa por contato e canal
            $contactIdentifier = Lazy::twigRawParser((string)($this->host->contact ?? ''), $data);
            
            $inboxId = null;
            if ($accountType === 'account') {
                // Usa o campo dropdown inbox_id
                $inboxId = (int)($this->host->inbox_id ?? 0);
            } else if ($accountType === 'account_id') {
                // Usa o campo texto inbox_id_text
                $inboxIdRaw = Lazy::twigRawParser((string)($this->host->inbox_id_text ?? ''), $data);
                $inboxId = (int)$inboxIdRaw;
            }
            
            if ($contactIdentifier && $inboxId) {
                // Buscar contato
                $contact = AllwayService::findContact($account, $contactIdentifier);
                if ($contact) {
                    // Buscar inbox
                    $inbox = AllwayService::getInbox($account, $inboxId);
                    if ($inbox) {
                        // Buscar conversas do contato no inbox especificado
                        $conversations = AllwayService::getConversationsByInboxAndContact($account, $inbox, $contact);
                        if (!empty($conversations)) {
                            // Usar a conversa mais recente do inbox especificado
                            $conversationId = $conversations[0]['id'] ?? null;
                        }
                        // Removido o fallback que buscava em qualquer inbox
                        // Se não há conversas no inbox especificado, não faz nada
                    }
                }
            }
        }

        if (!$conversationId) {
            return;
        }

        // Processar custom attributes
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

        // Processar etiquetas
        $etiquetas = [];
        $etiquetasData = $this->host->etiquetas ?? [];
        if (is_array($etiquetasData)) {
            foreach ($etiquetasData as $etiquetaItem) {
                if (!empty($etiquetaItem['etiqueta'])) {
                    $etiquetas[] = $etiquetaItem['etiqueta'];
                }
            }
        }
        
        $labelsMode = $this->host->labels_mode ?? 'replace';
        $conversationStatus = Lazy::twigRawParser((string)($this->host->conversation_status ?? ''), $data);

        try {
            // Atualizar custom attributes da conversa se especificados
            if (!empty($conversationCustomAttributes)) {
                AllwayService::updateConversation($account, $conversationId, $conversationCustomAttributes);
            }
            
            // Atualizar custom attributes do contato se especificados
            if (!empty($contactCustomAttributes)) {
                if ($conversationFind === 'by_contact') {
                    $contactIdentifier = Lazy::twigRawParser((string)($this->host->contact ?? ''), $data);
                    AllwayService::updateContactAttributes($account, $contactIdentifier, $contactCustomAttributes);
                } else {
                    // Quando buscar por ID da conversa, precisa buscar o contato da conversa
                    $conversation = AllwayService::getConversationById($account, $conversationId);
                    if ($conversation && !empty($conversation['meta']['sender']['phone_number'])) {
                        AllwayService::updateContactAttributes($account, $conversation['meta']['sender']['phone_number'], $contactCustomAttributes);
                    }
                }
            }
            
            // Aplicar etiquetas se especificadas
            if (!empty($etiquetas)) {
                AllwayService::addLabelsToConversation($account, $conversationId, $etiquetas, $labelsMode);
            }
            
            // Alterar status da conversa se especificado
            if (!empty($conversationStatus)) {
                AllwayService::updateConversationStatus($account, $conversationId, $conversationStatus);
            }
            
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
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