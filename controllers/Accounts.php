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

    /**
     * @var \Backend\Widgets\Form
     */
    public $testFormWidget;

    public function __construct()
    {
        parent::__construct();

        SettingsManager::setContext('Allway.Chat', 'accounts');

        $this->createTestFormWidget();
    }

    public function index_onLoadSendTest()
    {
        if (!$this->user->hasAccess('allway.chat.accounts.send_test')) {
            throw new ValidationException(['account' => 'Você não tem permissão para enviar mensagens de teste']);
        }

        $this->vars['formWidget'] = $this->makeTestFormWidget();
        
        return $this->makePartial('popup_send_test');
    }

    public function onSendTest()
    {
        if (!$this->user->hasAccess('allway.chat.accounts.send_test')) {
            Flash::error('Você não tem permissão para enviar mensagens de teste');
            return;
        }

        $data = post('TestMessage', []);
        
        $account = Account::find($data['account_id'] ?? null);
        $contact = $data['contact'] ?? null;
        $contactName = $data['contact_name'] ?? null;
        $inbox_id = (int) ($data['inbox_id'] ?? 0);
        $messageType = $data['message_type'] ?? 'text';
        $customAttributes = $data['custom_attributes'] ?? [];
        
        $conversationControl = $data['conversation_control'] ?? 'default';
        $conversationId = !empty($data['conversation_id']) ? (int) $data['conversation_id'] : null;
        
        $labels = [];
        if (!empty($data['labels']) && is_array($data['labels'])) {
            foreach ($data['labels'] as $label) {
                if (!empty($label['label'])) {
                    $labels[] = $label['label'];
                }
            }
        }
        
        $conversationStatus = $data['conversation_status'] ?? null;

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

        // Processar custom attributes
        $contactCustomAttributes = [];
        $conversationCustomAttributes = [];
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
                    $value = $attr['value'];
                    
                    if ($type === 'contact') {
                        $contactCustomAttributes[$key] = $value;
                    } else if ($type === 'conversation') {
                        $conversationCustomAttributes[$key] = $value;
                    }
                }
            }
        }

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
            if ($messageType === 'text') {
                $message = $data['text'] ?? null;
                if (!$message) {
                    Flash::error('Mensagem não informada');
                    return;
                }
                $result = AllwayService::sendText($account, $contact, $message, $inbox_id, $contactName, $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $specificConversationId, $conversationStatus);
            } else if ($messageType === 'image') {
                $imageUrl = $data['image_url'] ?? null;
                if (!$imageUrl) {
                    Flash::error('URL da imagem não informada');
                    return;
                }
                $caption = $data['caption'] ?? '';
                $result = AllwayService::sendImage($account, $contact, $imageUrl, $inbox_id, $contactName, $caption, $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $specificConversationId, $conversationStatus);
            } else if ($messageType === 'document') {
                $documentUrl = $data['document_url'] ?? null;
                if (!$documentUrl) {
                    Flash::error('URL do arquivo não informada');
                    return;
                }
                $caption = $data['caption'] ?? '';
                $filename = $data['document_filename'] ?? '';
                $result = AllwayService::sendDocument($account, $contact, $documentUrl, $inbox_id, $contactName, $caption, $filename, $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $specificConversationId, $conversationStatus);
            }
            
            $conversationIdResult = $result['conversation_id'] ?? null;
            
            $messages = [];
            $hasError = false;
            
            // Aplicar labels se especificadas
            if (!empty($labels) && $conversationIdResult) {
                try {
                    AllwayService::addLabelsToConversation($account, $conversationIdResult, $labels);
                    $messages[] = "Labels aplicadas: " . implode(', ', $labels);
                } catch (\Exception $labelException) {
                    $messages[] = "Erro ao aplicar labels: " . $labelException->getMessage();
                    $hasError = true;
                }
            }
            
            // Alterar status da conversa se especificado
            if (!empty($conversationStatus) && $conversationIdResult) {
                try {
                    $statusUpdated = AllwayService::updateConversationStatus($account, $conversationIdResult, $conversationStatus);
                    if ($statusUpdated) {
                        $messages[] = "Status alterado para: " . $conversationStatus;
                    } else {
                        $messages[] = "Erro ao alterar status da conversa";
                        $hasError = true;
                    }
                } catch (\Exception $statusException) {
                    $messages[] = "Erro ao alterar status: " . $statusException->getMessage();
                    $hasError = true;
                }
            }
            
            $baseMessage = "Mensagem enviada com sucesso! Conversa ID: {$conversationIdResult}";
            
            if (!empty($messages)) {
                $fullMessage = $baseMessage . ". " . implode(". ", $messages);
                if ($hasError) {
                    Flash::warning($fullMessage);
                } else {
                    Flash::success($fullMessage);
                }
            } else {
                Flash::success($baseMessage);
            }
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

        try {
            if (!AllwayService::testConnection($model)) {
                throw new \Exception('Falha na conexão com o servidor Allway');
            }
        } catch (\Exception $exception) {
            throw new ValidationException(['token' => 'Erro na conexão: ' . $exception->getMessage()]);
        }
    }

    /**
     * createTestFormWidget creates the test form widget
     */
    protected function createTestFormWidget()
    {
        $config = $this->makeConfig([
            'fields' => $this->getTestFormFields(),
            'model' => new Account,
            'context' => 'create',
            'arrayName' => 'TestMessage',
            'alias' => 'testForm'
        ]);

        $this->testFormWidget = $this->makeWidget(\Backend\Widgets\Form::class, $config);
        $this->testFormWidget->bindToController();
    }

    /**
     * makeTestFormWidget creates the test form widget
     */
    protected function makeTestFormWidget()
    {
        if (!$this->testFormWidget) {
            $this->createTestFormWidget();
        }
        
        return $this->testFormWidget;
    }

    /**
     * getTestFormFields returns the form field configuration
     */
    protected function getTestFormFields(): array
    {
        $accountOptions = [];
        foreach (Account::where('is_active', true)->get() as $account) {
            $accountOptions[$account->id] = $account->name;
        }

        return [
            'account_id' => [
                'label' => 'Conta AllWay',
                'type' => 'dropdown',
                'placeholder' => 'Selecione uma conta',
                'options' => $accountOptions,
                'required' => true,
                'span' => 'full',
                'tab' => 'Configuração'
            ],
            'inbox_id' => [
                'label' => 'Canal',
                'type' => 'dropdown',
                'placeholder' => 'Primeiro selecione uma conta',
                'options' => 'getInboxIdOptions',
                'required' => true,
                'span' => 'full',
                'dependsOn' => 'account_id',
                'tab' => 'Configuração'
            ],
            'contact_name' => [
                'label' => 'Nome do Contato',
                'type' => 'text',
                'placeholder' => 'Digite o nome do contato',
                'required' => true,
                'span' => 'auto',
                'tab' => 'Configuração'
            ],
            'contact' => [
                'label' => 'Contato',
                'type' => 'text',
                'placeholder' => 'Digite o email ou telefone do contato',
                'required' => true,
                'span' => 'auto',
                'tab' => 'Configuração'
            ],
            'conversation_control' => [
                'label' => 'Controle de Conversa',
                'type' => 'dropdown',
                'options' => [
                    'default' => 'Padrão (reutiliza ou cria nova conforme custom fields)',
                    'reuse' => 'Reutilizar conversa existente',
                    'force_new' => 'Sempre criar nova conversa',
                    'specific' => 'Usar conversa específica (informar ID)'
                ],
                'default' => 'default',
                'span' => 'full',
                'comment' => 'Escolha como a conversa deve ser tratada',
                'tab' => 'Configuração'
            ],
            'conversation_id' => [
                'label' => 'ID da Conversa',
                'type' => 'number',
                'placeholder' => 'Digite o ID da conversa específica',
                'trigger' => [
                    'action' => 'show',
                    'field' => 'conversation_control',
                    'condition' => 'value[specific]'
                ],
                'span' => 'full',
                'comment' => 'ID da conversa específica que você quer usar',
                'tab' => 'Configuração'
            ],
            'message_type' => [
                'label' => 'Tipo de Mensagem',
                'type' => 'dropdown',
                'options' => [
                    'text' => 'Texto',
                    'image' => 'Imagem',
                    'document' => 'Arquivo'
                ],
                'default' => 'text',
                'span' => 'full',
                'tab' => 'Mensagem'
            ],
            'text' => [
                'label' => 'Mensagem',
                'type' => 'textarea',
                'size' => 'large',
                'placeholder' => 'Digite a mensagem que deseja enviar',
                'trigger' => [
                    'action' => 'show',
                    'field' => 'message_type',
                    'condition' => 'value[text]'
                ],
                'span' => 'full',
                'tab' => 'Mensagem'
            ],
            'image_url' => [
                'label' => 'URL da Imagem',
                'type' => 'text',
                'placeholder' => 'https://exemplo.com/imagem.jpg',
                'default' => 'https://picsum.photos/536/354',
                'trigger' => [
                    'action' => 'show',
                    'field' => 'message_type',
                    'condition' => 'value[image]'
                ],
                'span' => 'full',
                'comment' => 'URL padrão: https://picsum.photos/536/354',
                'tab' => 'Mensagem'
            ],
            'document_url' => [
                'label' => 'URL do Arquivo',
                'type' => 'text',
                'placeholder' => 'https://exemplo.com/arquivo.pdf',
                'default' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'trigger' => [
                    'action' => 'show',
                    'field' => 'message_type',
                    'condition' => 'value[document]'
                ],
                'span' => 'full',
                'comment' => 'URL padrão: https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'tab' => 'Mensagem'
            ],
            'document_filename' => [
                'label' => 'Nome do Arquivo (opcional)',
                'type' => 'text',
                'placeholder' => 'documento.pdf',
                'trigger' => [
                    'action' => 'show',
                    'field' => 'message_type',
                    'condition' => 'value[document]'
                ],
                'span' => 'full',
                'tab' => 'Mensagem'
            ],
            'caption' => [
                'label' => 'Legenda (opcional)',
                'type' => 'textarea',
                'size' => 'small',
                'placeholder' => 'Digite uma legenda para a imagem ou arquivo',
                'trigger' => [
                    'action' => 'show',
                    'field' => 'message_type',
                    'condition' => 'value[image] || value[document]'
                ],
                'span' => 'full',
                'tab' => 'Mensagem'
            ],
            'custom_attributes' => [
                'label' => 'Campos Personalizados',
                'type' => 'repeater',
                'form' => [
                    'fields' => [
                        'type' => [
                            'label' => 'Tipo',
                            'type' => 'dropdown',
                            'options' => [
                                'contact' => 'Contato',
                                'conversation' => 'Conversa'
                            ],
                            'default' => 'contact',
                            'required' => true,
                            'span' => 'left'
                        ],
                        'key' => [
                            'label' => 'Campo',
                            'type' => 'dropdown',
                            'options' => 'getContactCustomAttributesKeyOptions',
                            'trigger' => [
                                'action' => 'show',
                                'field' => 'type',
                                'condition' => 'value[contact]'
                            ],
                            'required' => true,
                            'span' => 'left',
                            'dependsOn' => ['account_id', 'type']
                        ],
                        'key_conversation' => [
                            'label' => 'Campo',
                            'type' => 'dropdown',
                            'options' => 'getConversationCustomAttributesKeyOptions',
                            'trigger' => [
                                'action' => 'show',
                                'field' => 'type',
                                'condition' => 'value[conversation]'
                            ],
                            'required' => true,
                            'span' => 'left',
                            'dependsOn' => ['account_id', 'type']
                        ],
                        'value' => [
                            'label' => 'Valor',
                            'type' => 'text',
                            'placeholder' => 'Valor do campo',
                            'span' => 'right'
                        ]
                    ]
                ],
                'prompt' => 'Adicionar Campos Personalizados',
                'span' => 'full',
                'comment' => 'Campos personalizados que serão enviados para o Allway chat. Escolha se é para o Contato ou para a Conversa.',
                'tab' => 'Campos Personalizados'
            ],
            'labels' => [
                'label' => 'Etiquetas',
                'type' => 'repeater',
                'form' => [
                    'fields' => [
                        'label' => [
                            'label' => 'Etiqueta',
                            'type' => 'dropdown',
                            'placeholder' => 'Primeiro selecione uma conta',
                            'options' => 'getLabelsOptions',
                            'required' => true,
                            'span' => 'full',
                            'dependsOn' => 'account_id'
                        ]
                    ]
                ],
                'prompt' => 'Adicionar Etiqueta',
                'span' => 'full',
                'comment' => 'Etiquetas que serão aplicadas à conversa no Allway Chat.',
                'tab' => 'Etiquetas'
            ],
            'conversation_status' => [
                'label' => 'Status da Conversa',
                'type' => 'dropdown',
                'options' => [
                    '' => 'Não alterar status',
                    'open' => 'Aberta',
                    'pending' => 'Pendente',
                    'resolved' => 'Resolvida'
                ],
                'default' => '',
                'span' => 'full',
                'comment' => 'Status que será aplicado à conversa após o envio da mensagem.',
                'tab' => 'Configuração'
            ]
        ];
    }

    public function getInboxIdOptions()
    {
        return $this->formGetInboxIdOptions();
    }

    public function formGetInboxIdOptions()
    {
        $accountId = post('TestMessage.account_id') ?: post('account_id');
        $account = Account::find($accountId);
        
        $options = [];
        if ($account) {
            $inboxes = AllwayService::getAccountInboxes($account);
            if ($inboxes) {
                foreach ($inboxes as $inbox) {
                    $options[$inbox['id']] = $inbox['name'] . ' (' . $inbox['channel_type'] . ')';
                }
            }
        }
        
        return $options;
    }


}
