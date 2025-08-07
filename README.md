# Plugin Allway Chat

Plugin de integração com Chatwoot (renomeado como Allway Chat) para OctoberCMS.

## Funcionalidades

### 1. Integração com API do Chatwoot
- Envio de mensagens de texto, imagem e documentos
- Gerenciamento de conversas e contatos
- Aplicação e gerenciamento de labels
- Alteração de status de conversas

### 2. Report Widget para Dashboard
- Widget de estatísticas em tempo real
- Múltiplos tipos de métricas disponíveis
- Configuração flexível de períodos
- Indicadores de crescimento
- Filtros por labels e inboxes

### 3. Sistema de Notificações
- Regras de notificação configuráveis
- Integração com sistema de notify do OctoberCMS
- Logs de mensagens enviadas

## Widget de Estatísticas

### Métricas Disponíveis
- **Conversas**: Total, abertas, resolvidas, pendentes
- **Mensagens**: Total, enviadas, recebidas

### Períodos Suportados
- Hoje / Ontem
- Últimos 7/30 dias
- Esta semana / Semana passada
- Este mês / Mês passado

### Configurações
- Título customizável (com suporte Twig)
- Seleção de conta Allway Chat
- Escolha do tipo de estatística
- Período de análise
- Ícone e esquema de cores
- Indicador de crescimento
- Filtros por labels

### Como Adicionar o Widget

1. **Via Dashboard**:
   - Acesse a Dashboard do OctoberCMS
   - Clique em "Adicionar Widget"
   - Selecione "Estatísticas Allway Chat"
   - Configure as opções desejadas

2. **Configuração Recomendada**:
   ```yaml
   Título: "Conversas Abertas"
   Conta: [Selecione uma conta configurada]
   Tipo: "Conversas Abertas"
   Período: "Últimos 30 dias"
   Ícone: "comments"
   Cor: "Verde"
   Mostrar crescimento: Sim
   ```

## Comando de Teste

Para testar as estatísticas via linha de comando:

```bash
php artisan allway:test-stats
```

Este comando mostra:
- Lista de contas configuradas
- Estatísticas de conversas e mensagens
- Teste de diferentes períodos
- Detecção de erros de conectividade

## Exemplo de Saída

```
=== Teste de Estatísticas Allway Chat ===

--- Conta: Viale Hotéis ---
API URL: https://chat-viale.openwave.cloud/api/v1

📊 Estatísticas de Conversas (30 dias):
+----------------------+-------+
| Total de Conversas   | 25    |
| Conversas Abertas    | 25    |
| Conversas Resolvidas | 0     |
| Conversas Pendentes  | 0     |
+----------------------+-------+

💬 Estatísticas de Mensagens (30 dias):
+---------------------+-------+
| Total de Mensagens  | 25    |
| Mensagens Enviadas  | 0     |
| Mensagens Recebidas | 25    |
+---------------------+-------+
```

## Estrutura de Arquivos

```
plugins/allway/chat/
├── reportwidgets/
│   └── ChatStats.php              # Widget principal
│   └── chatstats/
│       ├── css/chatstats.css      # Estilos do widget
│       └── partials/
│           └── _chatstats.php     # Template do widget
├── classes/
│   └── AllwayService.php          # Métodos para API (+ estatísticas)
├── console/
│   └── TestChatStats.php          # Comando de teste
├── docs/
│   └── widget-chatstats.md        # Documentação detalhada
└── Plugin.php                     # Registro do widget e comando
```

## API de Estatísticas

### Métodos Adicionados ao AllwayService

#### `getConversationsStats()`
Busca estatísticas de conversas com filtros por período, inbox e labels.

```php
$stats = AllwayService::getConversationsStats($account, '30dias', [], ['urgente']);
// Retorna: ['total_conversations' => 25, 'open_conversations' => 25, ...]
```

#### `getMessagesStats()`
Busca estatísticas de mensagens por período e inbox.

```php
$stats = AllwayService::getMessagesStats($account, 'esta_semana', [1, 2]);
// Retorna: ['total_messages' => 50, 'outgoing_messages' => 20, ...]
```

#### `getPeriodDates()`
Converte períodos legíveis em datas ISO para a API.

```php
$dates = AllwayService::getPeriodDates('30dias');
// Retorna: ['2024-01-01T00:00:00.000Z', '2024-01-31T23:59:59.999Z']
```

## Permissões

O widget requer a permissão `allway.chat.logs` para ser visualizado na dashboard.

## Troubleshooting

1. **Widget não aparece**: Verifique se o usuário tem a permissão `allway.chat.logs`
2. **Erro de conta**: Confirme se existe ao menos uma conta Allway Chat configurada
3. **Dados zerados**: Verifique conectividade com a API do Chatwoot
4. **Teste via comando**: Use `php artisan allway:test-stats` para diagnóstico