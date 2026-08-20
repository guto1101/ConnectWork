# ConnectWork — preparação para produção

Antes de publicar:

1. Crie um banco MySQL/MariaDB vazio e um usuário exclusivo para a aplicação.
2. Importe `connectwork.sql`.
3. Edite `includes/config.php` com os dados reais do banco.
4. Em produção, altere:
   `define('CW_AMBIENTE', 'producao');`
5. Ative HTTPS no domínio.
6. Mantenha `instalar.php` bloqueado/removido. O `.htaccess` desta versão já impede acesso direto.
7. Não publique `testes.php`, backups ou arquivos `.sql`. O `.htaccess` bloqueia esses formatos.
8. Garanta permissão de escrita apenas na pasta `uploads/`.
9. Confirme que a extensão PHP cURL está ativa se quiser usar um provedor externo no Assistente.
10. Faça um cadastro de teste, crie um administrador, um gerente e um funcionário e confirme as permissões de cada perfil.
11. Rode `testes.php` apenas em ambiente controlado; ele foi projetado para criar dados temporários e removê-los ao final.

## Banco e isolamento

O sistema mantém `empresa_id` nos registros da empresa e a classe `Db` aplica o escopo da empresa nas operações suportadas. Não altere a camada `includes/db.php` para remover esse controle.

## Assistente

O Assistente ConnectWork funciona sem chave externa com dados calculados no banco. Para respostas generativas externas, configure um provedor e uma chave em `includes/config.php`; a chave deve permanecer somente no servidor.

## Observação

Nenhum sistema deve ser considerado "100% seguro" apenas por uma revisão de código. Faça testes de aceitação, backup, monitoramento e revisão periódica após publicar.
