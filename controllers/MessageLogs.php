<?php namespace Allway\Chat\Controllers;

use Backend\Behaviors\FormController;
use Backend\Behaviors\ListController;
use Backend\Classes\Controller;
use Flash;
use Allway\Chat\Classes\AllwayService;
use Allway\Chat\Models\MessageLog;
use October\Rain\Exception\ValidationException;
use System\Classes\SettingsManager;

class MessageLogs extends Controller
{
    public $implement = [
        ListController::class,
        FormController::class
    ];

    public $listConfig = 'config_list.yaml';

    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = [
        'allway.chat.logs'
    ];

    public function __construct()
    {
        parent::__construct();

        SettingsManager::setContext('Allway.Chat', 'messagelog');
    }

    public function index_onEmptyLog()
    {
        MessageLog::truncate();
        Flash::success('Logs de mensagens apagados com sucesso.');
        return $this->listRefresh();
    }

    public function index_onRetryMessage()
    {
        $message_log_id = post('message_log_id');

        $messageLog = MessageLog::find($message_log_id);

        if ($messageLog) {

            try {
                AllwayService::send(
                    $messageLog->account,
                    $messageLog->to_contact,
                    $messageLog->content
                );
            } catch (\Exception $exception) {
                throw new ValidationException(['account' => $exception->getMessage()]);
            }

            Flash::success('Mensagem enviada novamente com sucesso.');

            return $this->listRefresh();
        } else {
            Flash::error('Mensagem não encontrada.');
        }
    }
}
