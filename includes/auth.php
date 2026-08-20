<?php
/**
 * ConnectWork — Autenticação e níveis de acesso
 *
 * Quatro níveis, do mais amplo para o mais restrito:
 *   master      Administrador Master  — plataforma inteira, sem empresa fixa
 *   admin       Administrador da Empresa
 *   gerente     Gerente — sua equipe
 *   funcionario Funcionário — apenas os próprios dados
 *
 * A sessão guarda o mínimo: id, nível, empresa e o funcionario_id
 * correspondente. Perfil e permissões são sempre relidos do banco.
 */

require_once __DIR__ . '/seguranca.php';
require_once __DIR__ . '/db.php';

final class Auth
{
    public const NIVEIS = ['master', 'admin', 'gerente', 'funcionario'];

    private static ?array $cacheUsuario = null;

    // -----------------------------------------------------------------
    // Entrada e saída
    // -----------------------------------------------------------------

    /**
     * Autentica por usuário ou e-mail dentro da empresa escolhida. Quando
     * empresaId é nulo, o acesso é reservado à plataforma (CEO ConnectWork).
     *
     * @return array{ok:bool,erro?:string,nivel?:string}
     */
    public static function entrar(string $identificador, string $senha, ?int $empresaId = null): array
    {
        sessao_iniciar();
        $identificador = mb_strtolower(trim($identificador));

        if ($identificador === '' || $senha === '') {
            return ['ok' => false, 'erro' => 'Informe usuário e senha.'];
        }

        if (login_bloqueado($identificador)) {
            $minutos = (int) ceil(CW_LOGIN_JANELA / 60);
            return ['ok' => false, 'erro' => "Muitas tentativas. Aguarde $minutos minutos e tente de novo."];
        }

        if ($empresaId !== null && $empresaId > 0) {
            $st = conexao()->prepare(
                'SELECT u.*, e.status AS empresa_status, e.nome AS empresa_nome
                   FROM usuarios u
             INNER JOIN empresas e ON e.id = u.empresa_id
                  WHERE (LOWER(u.usuario) = :ident_u OR LOWER(u.email) = :ident_e)
                    AND u.empresa_id = :empresa_login
                    AND u.nivel <> :nivel_master
                  LIMIT 1'
            );
            $st->execute([
                'ident_u' => $identificador,
                'ident_e' => $identificador,
                'empresa_login' => $empresaId,
                'nivel_master' => 'master',
            ]);
        } else {
            $st = conexao()->prepare(
                'SELECT u.*, NULL AS empresa_status, :nome_plataforma AS empresa_nome
                   FROM usuarios u
                  WHERE (LOWER(u.usuario) = :ident_u OR LOWER(u.email) = :ident_e)
                    AND u.nivel = :nivel_master
                    AND u.empresa_id IS NULL
                  LIMIT 1'
            );
            $st->execute([
                'ident_u' => $identificador,
                'ident_e' => $identificador,
                'nivel_master' => 'master',
                'nome_plataforma' => 'CEO ConnectWork',
            ]);
        }
        $usuario = $st->fetch();

        // password_verify roda mesmo sem usuário encontrado, contra um hash
        // descartável, para que o tempo de resposta não revele quais contas
        // existem.
        $hash = $usuario['senha_hash'] ?? '$2y$10$invalidoinvalidoinvalidoinvalidoinvalidoinvalidoinvalido';
        $senhaConfere = password_verify($senha, $hash);

        if (!$usuario || !$senhaConfere) {
            login_registrar($identificador, false);
            return ['ok' => false, 'erro' => 'Usuário ou senha incorretos.'];
        }

        if ((int) $usuario['ativo'] !== 1) {
            login_registrar($identificador, false);
            return ['ok' => false, 'erro' => 'Esta conta está desativada. Procure o administrador da sua empresa.'];
        }

        if ($empresaId !== null && ($usuario['empresa_status'] ?? '') !== 'ativa') {
            login_registrar($identificador, false);
            return ['ok' => false, 'erro' => 'O acesso da sua empresa está suspenso.'];
        }

        // Custo do bcrypt mudou no servidor? Regrava o hash na entrada válida.
        if (password_needs_rehash($usuario['senha_hash'], PASSWORD_DEFAULT)) {
            $up = conexao()->prepare('UPDATE usuarios SET senha_hash = :h WHERE id = :id');
            $up->execute(['h' => password_hash($senha, PASSWORD_DEFAULT), 'id' => $usuario['id']]);
        }

        self::abrirSessao($usuario);
        login_registrar($identificador, true);
        auditar('login', 'usuarios', $usuario['id']);

        return ['ok' => true, 'nivel' => $usuario['nivel']];
    }

    /** Grava a sessão. session_regenerate_id corta fixação de sessão. */
    private static function abrirSessao(array $usuario): void
    {
        session_regenerate_id(true);

        $_SESSION['cw_usuario_id'] = (int) $usuario['id'];
        $_SESSION['cw_nivel']      = $usuario['nivel'];
        $_SESSION['cw_empresa_id'] = $usuario['empresa_id'] !== null ? (int) $usuario['empresa_id'] : null;
        $_SESSION['cw_nome']       = $usuario['nome'];
        $_SESSION['cw_empresa_nome'] = $usuario['empresa_nome'] ?? 'Plataforma';
        $_SESSION['cw_inicio']     = time();
        $_SESSION['cw_atividade']  = time();
        // Amarra a sessão ao navegador: um cookie copiado para outro
        // agente não passa.
        $_SESSION['cw_impressao']  = self::impressao();

        // Vincula a conta ao cadastro de funcionário, quando houver.
        $_SESSION['cw_funcionario_id'] = null;
        if ($usuario['empresa_id'] !== null) {
            $st = conexao()->prepare(
                'SELECT id FROM funcionarios WHERE usuario_id = :u AND empresa_id = :e LIMIT 1'
            );
            $st->execute(['u' => $usuario['id'], 'e' => $usuario['empresa_id']]);
            $f = $st->fetchColumn();
            $_SESSION['cw_funcionario_id'] = $f !== false ? (int) $f : null;
        }

        $up = conexao()->prepare('UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = :id');
        $up->execute(['id' => $usuario['id']]);
    }

    public static function sair(): void
    {
        sessao_iniciar();
        if (!empty($_SESSION['cw_usuario_id'])) {
            auditar('logout', 'usuarios', $_SESSION['cw_usuario_id']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    private static function impressao(): string
    {
        return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . CW_SESSAO_NOME);
    }

    // -----------------------------------------------------------------
    // Estado
    // -----------------------------------------------------------------

    public static function logado(): bool
    {
        sessao_iniciar();
        if (empty($_SESSION['cw_usuario_id'])) {
            return false;
        }
        if (($_SESSION['cw_impressao'] ?? '') !== self::impressao()) {
            self::sair();
            return false;
        }
        $agora = time();
        $ocioso    = $agora - (int) ($_SESSION['cw_atividade'] ?? 0);
        $decorrido = $agora - (int) ($_SESSION['cw_inicio'] ?? 0);
        if ($ocioso > CW_SESSAO_OCIOSA || $decorrido > CW_SESSAO_MAXIMA) {
            self::sair();
            return false;
        }
        $_SESSION['cw_atividade'] = $agora;
        return true;
    }

    public static function nivel(): ?string
    {
        return self::logado() ? ($_SESSION['cw_nivel'] ?? null) : null;
    }

    public static function id(): ?int
    {
        return self::logado() ? (int) $_SESSION['cw_usuario_id'] : null;
    }

    public static function empresaId(): ?int
    {
        return self::logado() ? ($_SESSION['cw_empresa_id'] ?? null) : null;
    }

    public static function funcionarioId(): ?int
    {
        return self::logado() ? ($_SESSION['cw_funcionario_id'] ?? null) : null;
    }

    public static function nome(): string
    {
        $nome = trim((string) ($_SESSION['cw_nome'] ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        // Sessões antigas podem ter sido criadas antes de o nome ser salvo.
        // Recupera o perfil atual para que o cabeçalho nunca fique vazio.
        $usuario = self::usuario();
        if ($usuario) {
            $nome = trim((string) ($usuario['nome'] ?? ''));
            if ($nome === '') {
                $nome = trim((string) ($usuario['usuario'] ?? ''));
            }
            if ($nome !== '') {
                $_SESSION['cw_nome'] = $nome;
                return $nome;
            }
        }

        return 'Usuário';
    }

    public static function empresaNome(): string
    {
        $nome = trim((string) ($_SESSION['cw_empresa_nome'] ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        $empresaId = $_SESSION['cw_empresa_id'] ?? null;
        if ($empresaId) {
            $st = conexao()->prepare('SELECT nome FROM empresas WHERE id = :id LIMIT 1');
            $st->execute(['id' => (int) $empresaId]);
            $nome = trim((string) ($st->fetchColumn() ?: ''));
            if ($nome !== '') {
                $_SESSION['cw_empresa_nome'] = $nome;
                return $nome;
            }
        }

        return self::ehMaster() ? 'Plataforma' : '';
    }

    public static function ehMaster(): bool
    {
        return self::nivel() === 'master';
    }

    /** true para gerente, admin e master. */
    public static function ehGestao(): bool
    {
        return in_array(self::nivel(), ['gerente', 'admin', 'master'], true);
    }

    /** Linha completa do usuário, relida do banco na primeira chamada. */
    public static function usuario(): ?array
    {
        if (!self::logado()) {
            return null;
        }
        if (self::$cacheUsuario === null) {
            $st = conexao()->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
            $st->execute(['id' => $_SESSION['cw_usuario_id']]);
            $u = $st->fetch();
            if ($u) { unset($u['senha_hash']); }
            self::$cacheUsuario = $u ?: null;
        }
        return self::$cacheUsuario;
    }

    /** Cadastro de funcionário do usuário logado. */
    public static function funcionario(): ?array
    {
        $fid = self::funcionarioId();
        return $fid ? Db::porId('funcionarios', $fid) : null;
    }

    // -----------------------------------------------------------------
    // Guardas de página
    // -----------------------------------------------------------------

    /** Toda página protegida começa com esta linha. */
    public static function exigirLogin(): void
    {
        if (!self::logado()) {
            $destino = $_SERVER['REQUEST_URI'] ?? '';
            header('Location: ' . url('index.php') . '?retorno=' . urlencode($destino));
            exit;
        }
    }

    /**
     * Restringe a página a determinados níveis.
     * O master entra em qualquer área, por definição do papel.
     */
    public static function exigirNivel(array $niveis): void
    {
        self::exigirLogin();
        $nivel = self::nivel();
        if ($nivel === 'master' || in_array($nivel, $niveis, true)) {
            return;
        }
        auditar('acesso_negado', 'pagina', null, $_SERVER['REQUEST_URI'] ?? '');
        http_response_code(403);
        include CW_RAIZ . '/includes/403.php';
        exit;
    }

    /** Página inicial de cada nível. */
    public static function paginaInicial(?string $nivel = null): string
    {
        $nivel = $nivel ?? self::nivel();
        switch ($nivel) {
            case 'master':      return url('master/index.php');
            case 'admin':       return url('admin/index.php');
            case 'gerente':     return url('gerente/index.php');
            case 'funcionario': return url('funcionario/index.php');
            default:            return url('index.php');
        }
    }

    /**
     * Um gerente só enxerga a própria equipe. Devolve os ids de
     * funcionário que o usuário atual pode consultar, ou null quando o
     * alcance é a empresa inteira (admin e master).
     */
    public static function equipeVisivel(): ?array
    {
        $nivel = self::nivel();
        if ($nivel === 'admin' || $nivel === 'master') {
            return null;
        }
        $fid = self::funcionarioId();
        if (!$fid) {
            return [];
        }
        if ($nivel === 'funcionario') {
            return [$fid];
        }
        $equipe = Db::todos('funcionarios', 'gestor_id = :g', ['g' => $fid], ['colunas' => 'id']);
        $ids = array_map(static fn($l) => (int) $l['id'], $equipe);
        $ids[] = $fid;                       // o próprio gerente
        return array_values(array_unique($ids));
    }

    /** O usuário atual pode ver os dados deste funcionário? */
    public static function podeVerFuncionario(int $funcionarioId): bool
    {
        $visiveis = self::equipeVisivel();
        return $visiveis === null || in_array($funcionarioId, $visiveis, true);
    }
}
