<?php namespace Allway\Chat\Controllers;

use Backend\Behaviors\FormController;
use Backend\Behaviors\ListController;
use Allway\Chat\Classes\AllwayService;
use Allway\Chat\Models\Account;
use October\Rain\Exception\ValidationException;
use System\Classes\SettingsManager;
use Flash;
use General\BackendSkin\Controller\BaseController;

class Accounts extends BaseController
{
    public $implement = [
        ListController::class,
        FormController::class
    ];

    public $listConfig = 'config_list.yaml';

    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = [
        'allway.chat.accounts',
        'allway.chat.accounts.*'
    ];

    public function __construct()
    {
        parent::__construct();

        SettingsManager::setContext('Allway.Chat', 'accounts');
    }

    public function index_onLoadSendTest()
    {
        if (!$this->user->hasAccess('allway.chat.accounts.send_test')) {
            throw new ValidationException(['account' => 'Você não tem permissão para enviar mensagens de teste']);
        }

        $accountId = post('account_id');
        $account = Account::find($accountId);
        
        $this->vars['account_id'] = $accountId;
        $this->vars['inboxes'] = [];
        
        if ($account) {
            $inboxes = AllwayService::getAccountInboxes($account);
            if ($inboxes) {
                $this->vars['inboxes'] = $inboxes;
            }
        }
        
        return $this->makePartial('popup_send_test');
    }

    public function onSendTest()
    {
        if (!$this->user->hasAccess('allway.chat.accounts.send_test')) {
            Flash::error('Você não tem permissão para enviar mensagens de teste');
            return;
        }

        $account = Account::find(post('account_id'));
        $contact = post('contact');
        $contactName = post('contact_name');
        $inbox_id = (int) post('inbox_id');
        $messageType = post('message_type', 'text');

        if (!$contact) {
            Flash::error('Contato não informado');
            return;
        }

        if (!$contactName) {
            Flash::error('Nome do contato não informado');
            return;
        }

        if (!$account) {
            Flash::error('Conta não encontrada');
            return;
        }
        
        if (!$inbox_id) {
            Flash::error('Canal não selecionado');
            return;
        }

        try {
            if ($messageType === 'text') {
                $message = post('message');
                if (!$message) {
                    Flash::error('Mensagem não informada');
                    return;
                }
                AllwayService::sendText($account, $contact, $message, $inbox_id, $contactName);
            } else if ($messageType === 'image') {
                $imageUrl = post('image_url');
                if (!$imageUrl) {
                    Flash::error('URL da imagem não informada');
                    return;
                }
                $caption = post('caption', '');
                AllwayService::sendImage($account, $contact, $imageUrl, $inbox_id, $contactName, $caption);
            } else if ($messageType === 'document') {
                $documentUrl = post('document_url');
                if (!$documentUrl) {
                    Flash::error('URL do arquivo não informada');
                    return;
                }
                $caption = post('caption', '');
                $filename = post('filename', '');
                AllwayService::sendDocument($account, $contact, $documentUrl, $inbox_id, $contactName, $caption, $filename);
            }
            
            Flash::success('Mensagem enviada com sucesso');
        } catch (\Exception $exception) {
            Flash::error('Erro ao enviar mensagem: ' . $exception->getMessage());
        }
    }

    public function onTestConnection()
    {
        $account = Account::find(post('account_id'));

        if (!$account) {
            Flash::error('Conta não encontrada');
            return;
        }

        try {
            if (AllwayService::testConnection($account)) {
                Flash::success('Conexão testada com sucesso');
            } else {
                Flash::error('Falha na conexão com o servidor Allway');
            }
        } catch (\Exception $exception) {
            Flash::error('Erro na conexão: ' . $exception->getMessage());
        }

        return redirect()->refresh();
    }

    public function formBeforeCreate($model)
    {
        $model->fill((array)post('Account'));

        // Test the connection before saving
        try {
            if (!AllwayService::testConnection($model)) {
                throw new \Exception('Falha na conexão com o servidor Allway');
            }
        } catch (\Exception $exception) {
            throw new ValidationException(['token' => 'Erro na conexão: ' . $exception->getMessage()]);
        }
    }
}
