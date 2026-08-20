<?php
/**
 * ConnectWork — Configuração central
 *
 * Único arquivo que precisa ser editado ao trocar de servidor.
 * Em hospedagem, defina CW_AMBIENTE como 'producao': isso desliga a
 * exibição de erros na tela e liga o cookie de sessão como Secure.
 */

// ---------------------------------------------------------------------
// Ambiente
// ---------------------------------------------------------------------
define('CW_AMBIENTE', 'desenvolvimento');   // 'desenvolvimento' | 'producao'
define('CW_NOME', 'ConnectWork');
define('CW_VERSAO', '1.0.0');

// ---------------------------------------------------------------------
// Banco de dados (XAMPP: usuário root, senha vazia)
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_PORTA', 3306);
define('DB_NOME', 'connectwork');
define('DB_USUARIO', 'root');
define('DB_SENHA', '');

// ---------------------------------------------------------------------
// Sessão e segurança
// ---------------------------------------------------------------------
define('CW_SESSAO_NOME', 'connectwork_sid');
define('CW_SESSAO_OCIOSA', 30 * 60);        // 30 min sem atividade encerra
define('CW_SESSAO_MAXIMA', 12 * 60 * 60);   // 12 h de duração absoluta
define('CW_LOGIN_MAX_TENTATIVAS', 5);
define('CW_LOGIN_JANELA', 15 * 60);         // bloqueio de 15 min após o limite

// ---------------------------------------------------------------------
// Uploads
// ---------------------------------------------------------------------
define('CW_UPLOAD_DIR', dirname(__DIR__) . '/uploads');
define('CW_UPLOAD_MAX_BYTES', 8 * 1024 * 1024);
define('CW_UPLOAD_MIMES', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);

// ---------------------------------------------------------------------
// Inteligência artificial
// A chave nunca vai para o navegador: todas as chamadas passam pelo
// servidor. Sem chave configurada o sistema responde pelo provedor
// 'local', que trabalha apenas com os dados do próprio banco.
// ---------------------------------------------------------------------
define('CW_IA_PROVEDOR', 'local');          // 'local' | 'openai' | 'gemini'
define('CW_IA_CHAVE', '');
define('CW_IA_MODELO', '');

// ---------------------------------------------------------------------
// Ajustes derivados — não editar
// ---------------------------------------------------------------------
date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

if (CW_AMBIENTE === 'producao') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

/** Caminho absoluto da raiz do projeto. */
define('CW_RAIZ', dirname(__DIR__));

/**
 * Monta uma URL relativa à raiz da aplicação, funcione ela em
 * http://localhost/connectwork/ ou na raiz de um domínio.
 */
function url(string $caminho = ''): string
{
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
        $raiz   = str_replace('\\', '/', CW_RAIZ);
        $doc    = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
        $base   = ($doc && strpos($raiz, $doc) === 0)
            ? rtrim(substr($raiz, strlen($doc)), '/')
            : rtrim(dirname($script), '/');
    }
    return $base . '/' . ltrim($caminho, '/');
}
