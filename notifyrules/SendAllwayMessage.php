<?php namespace Allway\Chat\NotifyRules;

use Allway\Chat\Classes\Helpers\Lazy;
use Allway\Chat\Classes\AllwayService;
use Allway\Chat\Models\Account;
use RainLab\Notify\Classes\ActionBase;

class SendAllwayMessage extends ActionBase
{
    public function defineValidationRules()
    {
        return [
            'account'           => 'required',
            'inbox'             => 'required',
            'contact_identifier' => 'required',
            'message_type'      => 'required|in:text,image,document',
            'text'              => 'required_if:message_type,text',
            'image'             => 'required_if:message_type,image',
            'document'          => 'required_if:message_type,document',
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
        $contactIdentifier = $this->host->contact_identifier ?? '';

        return 'Enviar ' . $messageTypeLabel . ' para: ' . $contactIdentifier;
    }

    public function triggerAction($params)
    {
        $account = Account::find($this->host->account);

        if (!$account || !$account->is_active) {
            return;
        }

        $contactIdentifier = Lazy::twigRawParser((string)$this->host->contact_identifier, $params);
        $contactName = Lazy::twigRawParser((string)($this->host->contact_name ?? ''), $params);
        $inboxId = (int)$this->host->inbox;
        $messageType = (string)($this->host->message_type ?? 'text');

        try {
            switch ($messageType) {
                case 'text':
                    $content = Lazy::twigRawParser((string)$this->host->text, $params);
                    AllwayService::sendText($account, $contactIdentifier, $content, $inboxId, $contactName);
                    break;

                case 'image':
                    $imageUrl = Lazy::twigRawParser((string)$this->host->image, $params);
                    $caption = Lazy::twigRawParser((string)($this->host->caption ?? ''), $params);
                    AllwayService::sendImage($account, $contactIdentifier, $imageUrl, $inboxId, $contactName, $caption);
                    break;

                case 'document':
                    $documentUrl = Lazy::twigRawParser((string)$this->host->document, $params);
                    $caption = Lazy::twigRawParser((string)($this->host->caption ?? ''), $params);
                    $filename = Lazy::twigRawParser((string)($this->host->document_filename ?? ''), $params);
                    AllwayService::sendDocument($account, $contactIdentifier, $documentUrl, $inboxId, $contactName, $caption, $filename);
                    break;
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
        $accountId = post('account') ?? $this->host->account ?? null;
        
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
                $options[$inbox['id']] = $inbox['name'] . ' (' . $inbox['channel_type'] . ')';
            }
        }
        
        return $options;
    }
} 