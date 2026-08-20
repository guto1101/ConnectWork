# Entrega — Administração da Empresa

Esta atualização implementa as quatro frentes administrativas no ConnectWork, preservando o modelo de quatro níveis, o escopo por empresa e as proteções de sessão já existentes.

## Aplicação

Antes de publicar os arquivos PHP, execute uma única vez a migração abaixo no banco `connectwork`:

```bash
mysql -u root -p connectwork < database/20260813_admin_lacunas.sql
```

Em XAMPP com senha vazia para o usuário `root`, a importação também pode ser feita pelo phpMyAdmin, na aba **Importar**, selecionando o arquivo `database/20260813_admin_lacunas.sql`.

> Faça uma cópia de segurança do banco antes de aplicar qualquer migração em ambiente de produção.

## Arquivos incluídos

| Frente | Arquivo | Finalidade |
|---|---|---|
| Auditoria da empresa | `admin/auditoria.php` | Consulta eventos somente da empresa da sessão, com filtros por ação, entidade e período. |
| Usuários e papéis | `admin/funcionarios.php` | Criação e manutenção de contas de Gerente e Funcionário, redefinição de senha com bcrypt e alteração de papel. |
| Fila de aprovações | `admin/aprovacoes.php` | Aprovação ou recusa de batidas pendentes e disponibilidades para hora extra. |
| Configurações | `admin/configuracoes.php` | Jornada, tolerância, GPS, geofence padrão, feriados e consulta de limites do plano. |
| Navegação | `includes/layout.php` | Itens Aprovações, Auditoria e Configurações no menu do Administrador. |
| Escopo de dados | `includes/db.php` | Suporte seguro a feriados, `empresa_config`, leitura de auditoria da própria empresa e plano atual. |
| Geofence | `includes/geo.php` | Aplicação da geofence padrão configurada na avaliação de ponto. |
| Disponibilidade | `disponibilidade.php` | Solicitação de disponibilidade entra como pendente até decisão administrativa. |
| Exportações | `exportar.php` | Registro de exportações na trilha de auditoria. |
| Migração | `database/20260813_admin_lacunas.sql` | Colunas, índices, chaves estrangeiras e tabela `feriados`. |

## Regras de acesso aplicadas

O Administrador da Empresa cria e edita somente contas de **Gerente** e **Funcionário**. A tela recusa tentativas de atribuir o papel `master` ou `admin`, inclusive quando uma requisição é manipulada. Todas as contas, funcionários, feriados, disponibilidades, pontos e configurações são lidos ou alterados pelo escopo de `empresa_id` da sessão.

A auditoria administrativa não usa o modo de plataforma. A consulta dedicada em `Db::auditoriaDaEmpresa()` obriga o filtro da empresa atual e associa o nome do usuário apenas quando a conta pertence à mesma empresa.

## Estrutura adicionada pela migração

A migração adiciona `cerca_padrao_id` em `empresa_config`, cria a tabela multiempresa `feriados` e adiciona em `disponibilidade` os campos `status`, `decidido_por_usuario_id`, `decidido_em` e `motivo_decisao`. As chaves estrangeiras preservam a integridade entre empresa, usuários e cercas virtuais.

## Validações executadas

| Verificação | Resultado |
|---|---|
| Migração aplicada em MariaDB isolado | Aprovada |
| Sintaxe de todos os arquivos PHP do projeto | 48 arquivos aprovados por `php -l` |
| Criação de conta local como Gerente | Aprovada |
| Hash bcrypt e redefinição de senha | Aprovados |
| Tentativa de elevar conta para Master | Recusada |
| Aprovação de batida de ponto | Aprovada e auditada |
| Aprovação de disponibilidade | Aprovada e auditada |
| Configuração de jornada, tolerância e geofence padrão | Aprovada |
| Cadastro de feriado | Aprovado |
| Auditoria restrita à própria empresa | Aprovada, sem exposição de evento de outra empresa |
| Exportação registrada na auditoria | Aprovada |

