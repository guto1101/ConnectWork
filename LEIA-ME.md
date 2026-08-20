# ConnectWork

Sistema web de RH e ponto para pequenas e médias empresas, feito em **PHP + MySQL**
para rodar no **XAMPP**. Multi-empresa (cada empresa só enxerga os próprios dados),
com quatro níveis de acesso: **Administrador Master**, **Administrador da empresa**,
**Gerente** e **Funcionário**.

## O que já vem pronto

- **Ponto eletrônico** com sequência entrada → pausa → retorno → saída, hora carimbada
  no servidor e **cerca virtual** (geofence) avaliada por Haversine no servidor.
- **Ouvidoria** com relato identificado ou anônimo (protocolo `CW-XXXX-XXXX`).
- **Sugestões**, **vagas internas** com funil de candidatos, **mensagens**,
  **comunicados**, **disponibilidade**, **notificações** e **busca**.
- **Assistente de IA** com três modos (local / OpenAI / Gemini) — a chave nunca vai ao
  navegador.
- Painéis por perfil, **espelho de ponto**, **relatórios** e **exportação CSV**.
- Painel da **plataforma** (master): empresas, planos e auditoria.

## Requisitos

- XAMPP com **PHP 8.1+** e **MySQL/MariaDB**.
- Extensões PHP: `pdo_mysql` e `mbstring` (já vêm no XAMPP).

## Instalação (passo a passo)

1. Copie a pasta `connectwork` para dentro de `xampp/htdocs/`.
2. Ligue **Apache** e **MySQL** no painel do XAMPP.
3. Abra o **phpMyAdmin** (`http://localhost/phpmyadmin`), crie um banco chamado
   `connectwork` (cotejamento `utf8mb4_unicode_ci`) e **importe** o arquivo
   `connectwork.sql`.
4. Se necessário, ajuste usuário/senha do banco em `includes/config.php`
   (padrão do XAMPP: usuário `root`, senha vazia).
5. Acesse `http://localhost/connectwork/instalar.php` e siga o assistente:
   ele confere o ambiente, cria o **Administrador Master** e, se você quiser, já cria a
   **primeira empresa**, o administrador dela e uma **cerca inicial**.
6. **Apague ou renomeie** `instalar.php` (e `testes.php`) depois de instalar.
7. Entre em `http://localhost/connectwork/` com o usuário que você criou.

## Configuração da IA (opcional)

Em `includes/config.php`, defina `CW_IA_PROVEDOR` como `local`, `openai` ou `gemini`.
Nos modos externos, informe a chave em `CW_IA_CHAVE`. No modo `local`, o assistente
responde com números reais do banco, sem enviar nada para fora.

## Estrutura

```
connectwork/
├─ index.php            login e cadastro de empresa
├─ instalar.php         assistente de instalação (apague após usar)
├─ testes.php           suíte de testes (apague após usar)
├─ dashboard.php        roteia para o painel do perfil
├─ ponto.php            registro de ponto do funcionário
├─ admin/               painéis do administrador da empresa
├─ gerente/             painéis do gerente (restritos à equipe)
├─ master/             painéis da plataforma (todas as empresas)
├─ funcionario/         painel do funcionário
├─ api/ponto.php        endpoint JSON do ponto
├─ includes/            núcleo (db, auth, geo, ponto, ia, layout, segurança)
├─ css/ js/ assets/     interface
└─ connectwork.sql      esquema do banco (25 tabelas)
```

## Segurança e isolamento entre empresas

O MySQL não tem *row-level security*, então o isolamento é feito na camada
`includes/db.php`: toda leitura, escrita e exclusão recebe automaticamente o
`empresa_id` da sessão; o `empresa_id` que venha de formulário é descartado; e SQL
escrito à mão que toque uma tabela de empresa **sem** citar `empresa_id` é recusado.
O arquivo `testes.php` comprova essas garantias contra o banco (isolamento de
leitura/escrita/exclusão, trava do SQL livre, geofence, sequência do ponto e alcance
do gerente).

## Personalização visual

`css/style.css` traz o tema (Poppins, azul `#2563eb`→`#0ea5e9`, cards arredondados).
Você pode substituir `css/style.css` e `assets/logo.png` pelos seus arquivos originais —
`css/complementos.css` é aditivo e não precisa ser tocado.
