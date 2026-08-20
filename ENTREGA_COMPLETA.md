# ConnectWork — Pacote completo atualizado

Este pacote reúne todas as alterações realizadas no ConnectWork em uma única pasta de projeto. A versão inclui o login visual organizado no modelo de referência, seleção de empresa, acesso separado do CEO ConnectWork, auditoria administrativa, gestão de usuários e papéis, fila de aprovações, configurações da empresa, schema SQL consolidado e manutenção de funcionários.

## Funcionalidades incluídas

| Área | Arquivos principais | Resultado |
|---|---|---|
| Página inicial | `index.php`, `includes/auth.php`, `includes/db.php`, `css/complementos.css` | Cartão central largo, formulário corporativo à esquerda, seleção de empresa à direita e acesso do CEO separado. |
| Funcionários | `admin/funcionarios.php` | Criar e editar Gerente/Funcionário, redefinir senha com bcrypt, desligar preservando histórico e excluir definitivamente com confirmação. |
| Auditoria | `admin/auditoria.php`, `includes/db.php` | Visualização da trilha somente da própria empresa. |
| Aprovações | `admin/aprovacoes.php`, `disponibilidade.php` | Aprovar ou recusar batidas pendentes e disponibilidade. |
| Configurações | `admin/configuracoes.php`, `includes/geo.php` | Jornada, tolerância, GPS, geofence padrão, feriados e limites do plano. |
| Schema | `connectwork.sql`, `database/20260813_admin_lacunas.sql` | Schema consolidado para instalação nova e migração para banco já existente. |

## Instalação nova

Extraia a pasta `ConnectWork` para `C:\xampp\htdocs\ConnectWork`, inicie Apache e MySQL e importe no phpMyAdmin o arquivo:

```text
ConnectWork/connectwork.sql
```

O `connectwork.sql` já contém as estruturas de Aprovações e Configurações. Não é necessário importar a migração adicional em um banco novo.

## Banco já existente

Não importe novamente o `connectwork.sql` em um banco que já possui dados. Faça backup e importe somente:

```text
ConnectWork/database/20260813_admin_lacunas.sql
```

Depois, substitua os arquivos do projeto. Preserve o `includes/config.php` com os dados reais da sua conexão e preserve a pasta `uploads` se ela contiver arquivos da instalação.

## Verificação da exclusão de funcionários

Em **Administrador → Funcionários**, a linha de cada pessoa possui:

- **Desligar**: bloqueia o acesso, marca a situação como desligado e preserva o histórico.
- **Excluir**: exige confirmação e remove definitivamente o cadastro e a conta de acesso local associada, quando houver. O administrador não pode excluir o próprio cadastro nem contas administrativas `admin` ou `master` por essa tela.

A ação é protegida por CSRF, isolamento da empresa da sessão e registro na auditoria.

## Validações realizadas

Foram validados `php -l` em todos os arquivos PHP do projeto, login por empresa, acesso separado do CEO, seleção visual da empresa, importação do schema consolidado em banco limpo e exclusão definitiva com auditoria em ambiente isolado.
