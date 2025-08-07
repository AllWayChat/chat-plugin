<?php namespace Allway\Chat\ReportWidgets;

use Backend\Classes\ReportWidgetBase;
use Exception;
use Allway\Chat\Models\Account;
use Allway\Chat\Classes\AllwayService;
use General\General\Classes\Traits\IconOptionsTrait;
use Twig;

/**
 * ChatStats Report Widget para estatísticas do Allway Chat
 */
class ChatStats extends ReportWidgetBase
{
    use IconOptionsTrait;

    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'AllwayChatStatsReportWidget';

    /**
     * Retorna as opções de contas disponíveis
     */
    public function getAccountOptions()
    {
        $accounts = Account::all();
        $options = [];

        foreach ($accounts as $account) {
            $options[$account->id] = $account->name . ' (' . $account->api_url . ')';
        }

        return $options;
    }

    /**
     * defineProperties for the widget
     */
    public function defineProperties()
    {
        $accountOptions = $this->getAccountOptions();
        
        return [
            'title' => [
                'title' => 'Título do Widget',
                'default' => 'Estatísticas Allway Chat',
                'type' => 'string',
                'validationPattern' => '^.+$',
                'validationMessage' => 'O título é obrigatório',
                'description' => 'Suporte a marcações Twig',
            ],
            'account_id' => [
                'title' => 'Conta Allway Chat',
                'type' => 'dropdown',
                'required' => true,
                'validationMessage' => 'Selecione uma conta do Allway Chat',
                'options' => $accountOptions,
                'description' => 'Conta do Allway Chat para buscar as estatísticas',
            ],
            'stat_type' => [
                'title' => 'Tipo de Estatística',
                'type' => 'dropdown',
                'default' => 'conversations',
                'options' => [
                    'conversations' => 'Conversas',
                    'messages' => 'Mensagens',
                    'open_conversations' => 'Conversas Abertas',
                    'resolved_conversations' => 'Conversas Resolvidas',
                    'pending_conversations' => 'Conversas Pendentes',
                    'outgoing_messages' => 'Mensagens Enviadas',
                    'incoming_messages' => 'Mensagens Recebidas',
                ],
                'description' => 'Tipo de estatística a ser exibida',
            ],
            'period' => [
                'title' => 'Período',
                'type' => 'dropdown',
                'default' => '30dias',
                'options' => [
                    'hoje' => 'Hoje',
                    'ontem' => 'Ontem',
                    '7dias' => 'Últimos 7 dias',
                    '30dias' => 'Últimos 30 dias',
                    'esta_semana' => 'Esta semana',
                    'semana_passada' => 'Semana passada',
                    'este_mes' => 'Este mês',
                    'mes_passado' => 'Mês passado',
                ],
                'description' => 'Período para calcular as estatísticas',
            ],
            'icon' => [
                'title' => 'Ícone',
                'default' => 'comments',
                'type' => 'dropdown',
            ],
            'color' => [
                'title' => 'Cor do Widget',
                'type' => 'dropdown',
                'default' => 'primary',
                'placeholder' => 'Selecione uma cor',
                'options' => [
                    'secondary' => 'Padrão',
                    'primary' => 'Azul',
                    'light-primary' => 'Azul Claro',
                    'success' => 'Verde',
                    'light-success' => 'Verde Claro',
                    'info' => 'Roxo',
                    'light-info' => 'Roxo Claro',
                    'warning' => 'Amarelo',
                    'light-warning' => 'Amarelo Claro',
                    'danger' => 'Vermelho',
                    'light-danger' => 'Vermelho Claro',
                    'dark' => 'Escuro',
                    'light-dark' => 'Escuro Claro',
                ],
            ],
            'show_growth' => [
                'title' => 'Mostrar crescimento',
                'type' => 'checkbox',
                'default' => true,
                'description' => 'Mostrar percentual de crescimento comparado ao período anterior',
            ],
            'labels_filter' => [
                'title' => 'Filtrar por Labels',
                'type' => 'string',
                'description' => 'Labels separadas por vírgula para filtrar conversas (apenas para estatísticas de conversas)',
            ],
            'ignore_labels' => [
                'title' => 'Ignorar Labels',
                'type' => 'string',
                'description' => 'Labels a serem excluídas do cálculo, separadas por vírgula',
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function render()
    {
        try {
            $this->prepareVars();
        } catch (Exception $ex) {
            $this->vars['error'] = $ex->getMessage();
        }

        return $this->makePartial('chatstats');
    }

    /**
     * Prepara as variáveis para a view
     */
    public function prepareVars()
    {
        // Configuração visual
        $color = $this->property('color');
        $this->vars['bgColor'] = 'bg-' . $color;
        
        // Definir cores baseado se é cor clara ou escura
        $lightColors = ['light-primary', 'light-success', 'light-info', 'light-warning', 'light-danger', 'light-dark', 'secondary'];
        $darkColors = ['primary', 'success', 'info', 'warning', 'danger', 'dark'];
        
        if (in_array($color, $lightColors)) {
            // Cores claras - texto escuro
            $this->vars['iconColor'] = 'text-' . str_replace('light-', '', $color);
            $this->vars['textColor'] = 'text-gray-800';
            $this->vars['descriptionColor'] = 'text-gray-600';
        } else {
            // Cores escuras - texto branco
            $this->vars['iconColor'] = 'text-white';
            $this->vars['textColor'] = 'text-white';
            $this->vars['descriptionColor'] = 'text-gray-200';
        }

        // Título com suporte a Twig
        try {
            $this->vars['title'] = Twig::parse((string)$this->property('title'));
        } catch (Exception $exception) {
            $this->vars['title'] = e($this->property('title'));
        }

        // Buscar dados da conta
        $accountId = $this->property('account_id');
        if (!$accountId) {
            throw new Exception('Conta do Allway Chat não selecionada.');
        }

        $account = Account::find($accountId);
        if (!$account) {
            throw new Exception('Conta do Allway Chat não encontrada.');
        }

        // Buscar estatísticas
        $statType = $this->property('stat_type');
        $period = $this->property('period');
        $labelsFilter = array_filter(array_map('trim', explode(',', $this->property('labels_filter', ''))));
        $ignoreLabels = array_filter(array_map('trim', explode(',', $this->property('ignore_labels', ''))));

        $this->vars['statValue'] = $this->getStatValue($account, $statType, $period, $labelsFilter, $ignoreLabels);
        $this->vars['statLabel'] = $this->getStatLabel($statType);
        $this->vars['periodLabel'] = $this->getPeriodLabel($period);

        // Calcular crescimento se habilitado
        if ($this->property('show_growth')) {
            $this->vars['growth'] = $this->calculateGrowth($account, $statType, $period, $labelsFilter);
        }
    }

        /**
     * Busca o valor da estatística
     */
    private function getStatValue(Account $account, string $statType, string $period, array $labelsFilter, array $ignoreLabels = []): int
    {
        if (in_array($statType, ['conversations', 'open_conversations', 'resolved_conversations', 'pending_conversations'])) {
            // Se há labels a ignorar, usar lógica de subtração
            if (!empty($ignoreLabels)) {
                return $this->calculateWithIgnoredLabels($account, $statType, $period, $labelsFilter, $ignoreLabels);
            }
            
            $stats = AllwayService::getConversationsStats($account, $period, [], $labelsFilter);
            
            switch ($statType) {
                case 'conversations':
                    return $stats['total_conversations'];
                case 'open_conversations':
                    return $stats['open_conversations'];
                case 'resolved_conversations':
                    return $stats['resolved_conversations'];
                case 'pending_conversations':
                    return $stats['pending_conversations'];
            }
        } else {
            $stats = AllwayService::getMessagesStats($account, $period, []);
            
            switch ($statType) {
                case 'messages':
                    return $stats['total_messages'];
                case 'outgoing_messages':
                    return $stats['outgoing_messages'];
                case 'incoming_messages':
                    return $stats['incoming_messages'];
            }
        }
        
        return 0;
    }

    /**
     * Calcula valor com labels ignoradas
     */
    private function calculateWithIgnoredLabels(Account $account, string $statType, string $period, array $labelsFilter, array $ignoreLabels): int
    {
        // Buscar com filtro de labels (se houver)
        $baseStats = AllwayService::getConversationsStats($account, $period, [], $labelsFilter);
        $baseTotal = $baseStats['total_conversations'];
        
        // Se não há filtro base, usar total geral
        if (empty($labelsFilter)) {
            $totalStats = AllwayService::getConversationsStats($account, $period, [], []);
            $baseTotal = $totalStats['total_conversations'];
        }
        
        // Buscar soma das labels a ignorar
        $ignoredTotal = 0;
        if (!empty($ignoreLabels)) {
            $ignoredStats = AllwayService::getConversationsStats($account, $period, [], $ignoreLabels);
            $ignoredTotal = $ignoredStats['total_conversations'];
        }
        
        // Retornar a diferença
        return max(0, $baseTotal - $ignoredTotal);
    }

    /**
     * Retorna o label da estatística
     */
    private function getStatLabel(string $statType): string
    {
        $labels = [
            'conversations' => 'Total de Conversas',
            'messages' => 'Total de Mensagens',
            'open_conversations' => 'Conversas Abertas',
            'resolved_conversations' => 'Conversas Resolvidas',
            'pending_conversations' => 'Conversas Pendentes',
            'outgoing_messages' => 'Mensagens Enviadas',
            'incoming_messages' => 'Mensagens Recebidas',
        ];

        return $labels[$statType] ?? 'Estatística';
    }

    /**
     * Retorna o label do período
     */
    private function getPeriodLabel(string $period): string
    {
        $labels = [
            'hoje' => 'hoje',
            'ontem' => 'ontem',
            '7dias' => 'nos últimos 7 dias',
            '30dias' => 'nos últimos 30 dias',
            'esta_semana' => 'nesta semana',
            'semana_passada' => 'na semana passada',
            'este_mes' => 'neste mês',
            'mes_passado' => 'no mês passado',
        ];

        return $labels[$period] ?? 'no período selecionado';
    }

    /**
     * Calcula o crescimento comparado ao período anterior
     */
    private function calculateGrowth(Account $account, string $statType, string $period, array $labelsFilter): array
    {
        $previousPeriod = $this->getPreviousPeriod($period);
        
        $currentValue = $this->getStatValue($account, $statType, $period, $labelsFilter);
        $previousValue = $this->getStatValue($account, $statType, $previousPeriod, $labelsFilter);

        if ($previousValue == 0) {
            return [
                'percentage' => $currentValue > 0 ? 100 : 0,
                'direction' => $currentValue > 0 ? 'up' : 'neutral',
                'text' => $currentValue > 0 ? '+100%' : '0%'
            ];
        }

        $percentage = round((($currentValue - $previousValue) / $previousValue) * 100, 1);
        $direction = $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'neutral');
        $text = ($percentage > 0 ? '+' : '') . $percentage . '%';

        return [
            'percentage' => abs($percentage),
            'direction' => $direction,
            'text' => $text
        ];
    }

    /**
     * Retorna o período anterior baseado no período atual
     */
    private function getPreviousPeriod(string $period): string
    {
        $mapping = [
            'hoje' => 'ontem',
            'ontem' => 'ontem', // Mantém o mesmo
            '7dias' => '7dias', // Seria necessário criar lógica mais complexa
            '30dias' => '30dias', // Seria necessário criar lógica mais complexa
            'esta_semana' => 'semana_passada',
            'semana_passada' => 'semana_passada', // Mantém o mesmo
            'este_mes' => 'mes_passado',
            'mes_passado' => 'mes_passado', // Mantém o mesmo
        ];

        return $mapping[$period] ?? $period;
    }



    /**
     * @inheritDoc
     */
    protected function loadAssets()
    {
        $this->addCss('/plugins/allway/chat/reportwidgets/chatstats/css/chatstats.css');
    }
}