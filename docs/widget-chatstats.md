# Widget de Estatísticas Allway Chat

## Descrição
Este widget permite exibir estatísticas em tempo real do Allway Chat na dashboard do OctoberCMS.

## Funcionalidades

### Tipos de Estatísticas Disponíveis
- **Total de Conversas**: Número total de conversas no período
- **Conversas Abertas**: Conversas com status "open"
- **Conversas Resolvidas**: Conversas com status "resolved"
- **Conversas Pendentes**: Conversas com status "pending"
- **Total de Mensagens**: Número total de mensagens
- **Mensagens Enviadas**: Mensagens outgoing (enviadas pelo sistema)
- **Mensagens Recebidas**: Mensagens incoming (recebidas de clientes)

### Períodos Disponíveis
- Hoje
- Ontem
- Últimos 7 dias
- Últimos 30 dias
- Esta semana
- Semana passada
- Este mês
- Mês passado

### Configurações
- **Título**: Título customizável com suporte a Twig
- **Conta**: Seleção da conta Allway Chat
- **Tipo de Estatística**: Escolha do tipo de dado a exibir
- **Período**: Intervalo de tempo para cálculo
- **Ícone**: Ícone do widget (usando Material Symbols)
- **Cor**: Esquema de cores do widget
- **Mostrar Crescimento**: Exibe percentual de crescimento vs período anterior
- **Filtro por Labels**: Filtra conversas por labels específicas

### Como Usar

1. **Adicionar à Dashboard**:
   - Vá até a Dashboard
   - Clique em "Adicionar Widget"
   - Selecione "Estatísticas Allway Chat"

2. **Configurar o Widget**:
   - **Conta**: Selecione uma conta Allway Chat configurada
   - **Tipo**: Escolha que estatística mostrar
   - **Período**: Defina o intervalo de tempo
   - **Visual**: Configure ícone e cor
   - **Crescimento**: Habilite para comparar com período anterior

3. **Filtros Avançados**:
   - Use "Filtrar por Labels" para estatísticas específicas
   - Exemplo: "urgente,vip" para conversas com essas labels

### Design e Estilo

O widget possui um design moderno e responsivo com:

- **Layout em 3 seções**: Header (título + ícone), valor central destacado, footer (crescimento)
- **Gradientes sutis**: Cores com degradê para adicionar profundidade
- **Animações**: Hover effects no ícone e elevação do card
- **Indicadores visuais**: Badges coloridos para crescimento (↑/↓/→)
- **Contraste otimizado**: Cores automáticas para versões claras e escuras
- **Responsividade**: Adaptação automática para diferentes tamanhos de tela
- **Shadow effects**: Sombras suaves para dar profundidade
- **Typography**: Fontes hierárquicas com pesos e tamanhos otimizados

### Exemplo de Configuração

```yaml
Título: "Conversas Abertas"
Conta: "Conta Principal"
Tipo de Estatística: "Conversas Abertas"
Período: "Últimos 7 dias"
Ícone: "chat_bubble"
Cor: "Verde"
Mostrar crescimento: Sim
Filtrar por Labels: "suporte,urgente"
```

### Crescimento Percentual

Quando habilitado, o widget mostra:
- ↑ Verde: Crescimento positivo
- ↓ Vermelho: Redução
- → Cinza: Sem alteração

### API Utilizada

O widget utiliza os endpoints do Chatwoot:
- `/accounts/{id}/conversations` - Para buscar conversas e mensagens
- Filtros por data, inbox e labels
- Cálculo de estatísticas em tempo real

### Permissões

Requer a permissão `allway.chat.logs` para visualizar o widget.