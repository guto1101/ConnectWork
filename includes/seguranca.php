<?php
/**
 * ConnectWork — Segurança
 *
 * Sessão endurecida, CSRF, escape de saída, leitura de entrada e
 * bloqueio de força bruta no login.
 */

require_once __DIR__ . '/conexao.php';

/** Inicia a sessão com cookie restrito. Chamar antes de qualquer saída. */
function sessao_iniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name(CW_SESSAO_NOME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,                 // JavaScript não lê o cookie
        'secure'   => $https,               // só trafega em HTTPS quando houver
        'samesite' => 'Lax',                // corta CSRF vindo de outro site
    ]);
    ini_set('session.use_strict_mode', '1'); // recusa id de sessão inventado
    session_start();
}

/** Escapa para HTML. Toda saída de dado do banco passa por aqui. */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Lê um campo de POST/GET já com trim, sem notice quando ausente. */
function entrada(string $campo, string $origem = 'post', string $padrao = ''): string
{
    $fonte = $origem === 'get' ? $_GET : $_POST;
    $v = $fonte[$campo] ?? $padrao;
    return is_string($v) ? trim($v) : $padrao;
}

function entrada_int(string $campo, string $origem = 'post', int $padrao = 0): int
{
    $fonte = $origem === 'get' ? $_GET : $_POST;
    return isset($fonte[$campo]) ? (int) $fonte[$campo] : $padrao;
}

// ---------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------

function csrf_token(): string
{
    sessao_iniciar();
    if (empty($_SESSION['cw_csrf'])) {
        $_SESSION['cw_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cw_csrf'];
}

/** Campo pronto para colar dentro de qualquer <form method="post">. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valido(?string $token = null): bool
{
    sessao_iniciar();
    $token = $token
        ?? $_POST['csrf']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';
    return !empty($_SESSION['cw_csrf']) && is_string($token)
        && hash_equals($_SESSION['cw_csrf'], $token);
}

/** Interrompe a requisição quando o token não confere. */
function csrf_exigir(): void
{
    if (!csrf_valido()) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página e tente novamente.');
    }
}

// ---------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------

function ip_cliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function agente_cliente(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/** Resposta JSON e fim da requisição. */
function responder_json(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Cabeçalhos de defesa aplicados em toda página HTML. */
function cabecalhos_seguranca(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');
}

// ---------------------------------------------------------------------
// Auditoria
// ---------------------------------------------------------------------

/** Grava um evento na trilha de auditoria. Nunca derruba a requisição. */
function auditar(string $acao, ?string $entidade = null, $entidadeId = null, ?string $detalhes = null): void
{
    try {
        $st = conexao()->prepare(
            'INSERT INTO auditoria (empresa_id, usuario_id, acao, entidade, entidade_id, detalhes, ip, user_agent)
             VALUES (:empresa_id, :usuario_id, :acao, :entidade, :entidade_id, :detalhes, :ip, :ua)'
        );
        $st->execute([
            'empresa_id'  => $_SESSION['cw_empresa_id'] ?? null,
            'usuario_id'  => $_SESSION['cw_usuario_id'] ?? null,
            'acao'        => $acao,
            'entidade'    => $entidade,
            'entidade_id' => $entidadeId,
            'detalhes'    => $detalhes,
            'ip'          => ip_cliente(),
            'ua'          => agente_cliente(),
        ]);
    } catch (Throwable $e) {
        error_log('ConnectWork/auditar: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// Bloqueio de força bruta
// ---------------------------------------------------------------------

/** Quantas falhas recentes existem para este identificador ou IP. */
function login_falhas_recentes(string $identificador): int
{
    $st = conexao()->prepare(
        'SELECT COUNT(*) FROM login_tentativas
          WHERE sucesso = 0
            AND criado_em > (NOW() - INTERVAL :janela SECOND)
            AND (identificador = :ident OR ip = :ip)'
    );
    $st->execute([
        'janela' => CW_LOGIN_JANELA,
        'ident'  => $identificador,
        'ip'     => ip_cliente(),
    ]);
    return (int) $st->fetchColumn();
}

function login_bloqueado(string $identificador): bool
{
    return login_falhas_recentes($identificador) >= CW_LOGIN_MAX_TENTATIVAS;
}

function login_registrar(string $identificador, bool $sucesso): void
{
    $st = conexao()->prepare(
        'INSERT INTO login_tentativas (identificador, ip, sucesso) VALUES (:ident, :ip, :ok)'
    );
    $st->execute([
        'ident' => mb_substr($identificador, 0, 160),
        'ip'    => ip_cliente(),
        'ok'    => $sucesso ? 1 : 0,
    ]);

    if ($sucesso) {
        // Login válido zera o contador daquele identificador.
        $limpar = conexao()->prepare(
            'DELETE FROM login_tentativas WHERE identificador = :ident AND sucesso = 0'
        );
        $limpar->execute(['ident' => mb_substr($identificador, 0, 160)]);
    }
}
