# Sistema de Agendamento INSS - TODO

## Fase 1: Arquitetura de Banco de Dados
- [ ] Definir schema de usuários com sincronização SOAP
- [ ] Criar tabela de agendamentos com validações
- [ ] Implementar tabela de bloqueios (dias/horários)
- [ ] Criar tabela de histórico de ações
- [ ] Definir índices e relacionamentos

## Fase 2: Autenticação SOAP OAB/SC
- [ ] Integrar cliente SOAP para validação OAB/SC
- [ ] Implementar login com CPF/OAB
- [ ] Sincronizar dados de usuário (nome, email, OAB)
- [ ] Validar credenciais contra serviço SOAP
- [ ] Armazenar dados de forma segura

## Fase 3: Calendário e Sistema de Agendamento
- [ ] Integrar FullCalendar no frontend
- [ ] Implementar busca de horários disponíveis
- [ ] Validar: não fins de semana
- [ ] Validar: horário 08:00-12:00
- [ ] Validar: não permite agendamento no dia atual
- [ ] Validar: limite de 2 agendamentos por mês
- [ ] Validar: bloqueio de 2h após cancelamento
- [ ] Validar: não permite agendamento após 19h para dia seguinte
- [ ] Criar modal de confirmação de agendamento

## Fase 4: Painel de Usuário
- [ ] Listar próximos agendamentos
- [ ] Exibir histórico completo de agendamentos
- [ ] Permitir cancelamento com validações
- [ ] Exibir detalhes de cada agendamento
- [ ] Dashboard com resumo de agendamentos

## Fase 5: Notificações por Email
- [ ] Configurar serviço de email
- [ ] Template de confirmação de agendamento
- [ ] Enviar email ao confirmar agendamento
- [ ] Enviar email ao cancelar agendamento
- [ ] Implementar rotina diária para administradores

## Fase 6: Painel Administrativo
- [ ] Dashboard com estatísticas de agendamentos
- [ ] Visualizar todos os agendamentos
- [ ] Gerenciar bloqueios de dias inteiros
- [ ] Gerenciar bloqueios de horários específicos
- [ ] Adicionar motivo aos bloqueios
- [ ] Remover bloqueios
- [ ] Visualizar logs de ações

## Fase 7: Testes e Deploy
- [ ] Testes unitários de validações
- [ ] Testes de integração SOAP
- [ ] Testes de fluxo de agendamento
- [ ] Validar todas as regras de negócio
- [ ] Teste de carga
- [ ] Deploy e monitoramento
