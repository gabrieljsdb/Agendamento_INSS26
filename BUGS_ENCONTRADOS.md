# Bugs Encontrados no Projeto

## 1. Schema de Email Templates Não Importado (server/db.ts)
**Erro:** `Cannot find name 'emailTemplates'`
**Localização:** Linhas 498, 506, 507, 526, 534, 536
**Causa:** A tabela `emailTemplates` não está sendo importada do schema

## 2. Método sendDailyReport Não Existe (server/services/cronService.ts)
**Erro:** `Property 'sendDailyReport' does not exist on type 'EmailService'`
**Localização:** Linha 64
**Causa:** O método `sendDailyReport` não foi implementado no EmailService

## 3. Tipo para easy-soap-request Não Encontrado (server/services/soapAuthService.ts)
**Erro:** `Could not find a declaration file for module 'easy-soap-request'`
**Localização:** Linha 5
**Causa:** Falta de declaração de tipos para o módulo

## 4. Uso de onSuccess Depreciado em TanStack Query (client/src/pages/admin/EmailSettings.tsx)
**Erro:** `'onSuccess' does not exist in type 'UseTRPCQueryOptions'`
**Localização:** Linha 23
**Causa:** A API do TanStack Query v5 removeu `onSuccess` das opções de query

## 5. Erros de Tipo em Componentes Admin
**Arquivos afetados:**
- client/src/pages/Dashboard.tsx (linha 44)
- client/src/pages/admin/BlockManagement.tsx (linha 27)
- client/src/pages/admin/DailyAppointments.tsx (linha 179)

## 6. Conflito de Props no Componente Button
**Erro:** Type error relacionado à propriedade 'size'
**Localização:** client/src/components/ui/button.tsx:23
**Causa:** Conflito entre as variantes de CVA e as props nativas do botão
