# Entrega — Página Inicial, Empresas e SQL Consolidado

## O que foi atualizado

A página `index.php` agora mostra as empresas ativas cadastradas, permite selecionar uma empresa antes de informar as credenciais e mantém um acesso separado para **CEO ConnectWork**. O login corporativo aceita apenas contas vinculadas à empresa selecionada. O acesso do CEO aceita somente uma conta de nível `master` sem empresa vinculada.

| Arquivo | Atualização |
|---|---|
| `index.php` | Lista empresas ativas, seletor de empresa e acesso separado do CEO ConnectWork. |
| `includes/auth.php` | Valida a empresa selecionada no login e isola o acesso da plataforma. |
| `includes/db.php` | Adiciona a leitura pública limitada de empresas ativas para a tela de acesso. |
| `css/complementos.css` | Estilos responsivos para lista de empresas e cartão do CEO. |
| `connectwork.sql` | Inclui `feriados`, aprovação de disponibilidade e geofence padrão para instalações novas. |

## Instalação nova

Para criar o banco do zero, importe somente o arquivo abaixo no phpMyAdmin:

```text
ConnectWork/connectwork.sql
```

O schema consolidado já contém as tabelas e colunas de **Aprovações** e **Configurações**. Não é necessário importar a migração adicional após uma instalação nova.

## Sistema já instalado

Se o banco `connectwork` já existe e contém dados, **não importe novamente `connectwork.sql`**. Ele é um dump completo e pode recriar tabelas. Mantenha o banco atual e importe apenas uma vez:

```text
ConnectWork/database/20260813_admin_lacunas.sql
```

Depois, substitua os arquivos PHP e CSS do pacote, preservando o seu `includes/config.php` e a pasta `uploads` caso tenham configurações ou arquivos próprios.

## Verificações realizadas

| Verificação | Resultado |
|---|---|
| Importação do `connectwork.sql` consolidado em banco limpo | Aprovada |
| Presença de `feriados` e dos campos de aprovação de disponibilidade | Aprovada |
| Login de administrador na empresa selecionada | Aprovado |
| Bloqueio de CEO no formulário corporativo | Aprovado |
| Acesso do CEO pelo formulário separado | Aprovado |
| Sintaxe PHP de todo o projeto | 48 arquivos aprovados por `php -l` |

