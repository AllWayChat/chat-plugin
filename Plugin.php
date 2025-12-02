<?php namespace Allway\Chat;

use Backend\Facades\Backend;
use Allway\Chat\Models\MessageLog;
use Allway\Chat\Models\Settings;
use Allway\Chat\NotifyRules\SendAllwayMessage;
use Allway\Chat\NotifyRules\UpdateAllwayData;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;

class Plugin extends PluginBase
{
    public function registerPermissions()
    {
        return [
            'allway.chat.dashboard' => [
                'tab' => 'Allway Chat',
                'label' => 'Acessar Dashboard'
            ],
        ];
    }

    public function registerNotificationRules()
    {
        return [
            'actions' => [
                SendAllwayMessage::class,
                UpdateAllwayData::class,
            ],
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'Configurações',
                'category'    => 'Allway Chat',
                'class'       => Settings::class,
                'permissions' => ['allway.chat.settings'],
                'order'       => 500,
                'keywords'    => 'allway chat api',
            ],
            'logs'     => [
                'label'       => 'Registros de Mensagens',
                'description' => 'Ver registros de mensagens enviadas.',
                'category'    => SettingsManager::CATEGORY_LOGS,
                'icon'        => 'icon-envelope-o',
                'url'         => Backend::url('allway/chat/messagelogs'),
                'order'       => 900,
                'keywords'    => 'allway chat api log',
                'permissions' => ['allway.chat.logs']
            ],
            'accounts' => [
                'label'       => 'Contas Allway',
                'description' => 'Gerenciar contas do Allway Chat.',
                'category'    => 'Allway Chat',
                'icon'        => 'icon-comments',
                'url'         => Backend::url('allway/chat/accounts'),
                'order'       => 900,
                'keywords'    => 'allway chat api account',
                'permissions' => ['allway.chat.accounts']
            ],
        ];
    }

    public function registerSchedule($schedule)
    {
        $schedule->call(function () {
            $days = Settings::get('purge_days', 7);
            $date = now()->subDays($days);
            MessageLog::where('sent_at', '<', $date)->delete();
        })->daily();
    }

    public function registerReportWidgets()
    {
        return [
            \Allway\Chat\ReportWidgets\ChatStats::class => [
                'label' => 'Estatísticas Allway Chat',
                'context' => 'dashboard',
                'permissions' => ['allway.chat.dashboard']
            ]
        ];
    }
}
