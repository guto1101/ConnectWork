<?php
/**
 * ConnectWork — Assistente de instalação
 *
 * Roda uma única vez para preparar o sistema no XAMPP:
 *   1. confere o ambiente (PHP, extensões, conexão, tabelas);
 *   2. cria o Administrador Master (empresa_id NULL);
 *   3. opcionalmente cria a primeira empresa cliente, seu administrador
 *      e uma cerca inicial — tudo com senha real via password_hash.
 *
 * Trava de segurança: se já existir um usuário master, o assistente se
 * recusa a rodar de novo. Depois de instalar, apague ou renomeie este
 * arquivo.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/seguranca.php';

sessao_iniciar();
cabecalhos_seguranca();

// ---------------------------------------------------------------------
// Checagem de ambiente
// ---------------------------------------------------------------------
$checagens = [];
$checagens[] = ['PHP 8.1 ou superior', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION];
$checagens[] = ['Extensão PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'ativa' : 'ausente'];
$checagens[] = ['Extensão mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'ativa' : 'ausente'];
$checagens[] = ['Pasta uploads/ gravável', is_writable(__DIR__ . '/uploads'), is_writable(__DIR__ . '/uploads') ? 'ok' : 'sem permissão'];

$conectou = false;
$temTabelas = false;
$temMaster = false;
$erroConexao = '';
try {
    $pdo = conexao();
    $conectou = true;
    $temTabelas = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = 'usuarios'"
    )->fetchColumn() > 0;
    if ($temTabelas) {
        $temMaster = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE nivel = 'master'")->fetchColumn() > 0;
    }
} catch (Throwable $e) {
    $erroConexao = $e->getMessage();
}
$checagens[] = ['Conexão com o banco ' . DB_NOME, $conectou, $conectou ? 'conectado' : 'falhou'];
$checagens[] = ['Tabelas do ConnectWork', $temTabelas, $temTabelas ? 'carregadas' : 'importe connectwork.sql'];

$ambienteOk = $conectou && $temTabelas && !array_filter($checagens, static fn($c) => !$c[1] && $c[0] !== 'Tabelas do ConnectWork');

$mensagem = '';
$erro = '';
$concluido = false;

// ---------------------------------------------------------------------
// Já instalado?
// ---------------------------------------------------------------------
if ($temMaster) {
    $erro = 'O ConnectWork já foi instalado (existe um Administrador Master). '
          . 'Por segurança, apague ou renomeie o arquivo instalar.php.';
}

// ---------------------------------------------------------------------
// Processa a instalação
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$temMaster && $conectou && $temTabelas) {
    csrf_exigir();

    $mNome  = entrada('master_nome');
    $mEmail = mb_strtolower(entrada('master_email'));
    $mUser  = mb_strtolower(entrada('master_usuario'));
    $mSenha = $_POST['master_senha'] ?? '';

    $criarEmpresa = isset($_POST['criar_empresa']);
    $empNome  = entrada('empresa_nome');
    $aNome    = entrada('admin_nome');
    $aEmail   = mb_strtolower(entrada('admin_email'));
    $aUser    = mb_strtolower(entrada('admin_usuario'));
    $aSenha   = $_POST['admin_senha'] ?? '';

    $criarCerca = isset($_POST['criar_cerca']);
    $cercaNome  = entrada('cerca_nome');
    $cercaLat   = entrada('cerca_lat');
    $cercaLon   = entrada('cerca_lon');
    $cercaRaio  = entrada_int('cerca_raio') ?: 150;

    $problemas = [];
    if ($mNome === '') { $problemas[] = 'Informe o nome do Administrador Master.'; }
    if (!filter_var($mEmail, FILTER_VALIDATE_EMAIL)) { $problemas[] = 'E-mail do master inválido.'; }
    if (!preg_match('/^[a-z0-9._-]{3,60}$/', $mUser)) { $problemas[] = 'Usuário do master inválido.'; }
    if (mb_strlen($mSenha) < 8) { $problemas[] = 'A senha do master precisa ter ao menos 8 caracteres.'; }

    if ($criarEmpresa) {
        if ($empNome === '') { $problemas[] = 'Informe o nome da empresa.'; }
        if ($aNome === '')   { $problemas[] = 'Informe o nome do administrador da empresa.'; }
        if (!filter_var($aEmail, FILTER_VALIDATE_EMAIL)) { $problemas[] = 'E-mail do administrador inválido.'; }
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $aUser)) { $problemas[] = 'Usuário do administrador inválido.'; }
        if (mb_strlen($aSenha) < 8) { $problemas[] = 'A senha do administrador precisa ter ao menos 8 caracteres.'; }
        if ($aUser === $mUser || $aEmail === $mEmail) { $problemas[] = 'O administrador da empresa deve ter usuário e e-mail diferentes do master.'; }
        if ($criarCerca && ($cercaNome === '' || !is_numeric($cercaLat) || !is_numeric($cercaLon))) {
            $problemas[] = 'Para criar a cerca inicial, preencha nome, latitude e longitude.';
        }
    }

    if ($problemas) {
        $erro = implode(' ', $problemas);
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Administrador Master (empresa_id NULL)
            $pdo->prepare(
                'INSERT INTO usuarios (empresa_id, nome, email, usuario, senha_hash, nivel, ativo)
                 VALUES (NULL, :nome, :email, :usuario, :hash, :nivel, 1)'
            )->execute([
                'nome'    => $mNome,
                'email'   => $mEmail,
                'usuario' => $mUser,
                'hash'    => password_hash($mSenha, PASSWORD_DEFAULT),
                'nivel'   => 'master',
            ]);

            // 2. Primeira empresa (opcional)
            if ($criarEmpresa) {
                $planoId = (int) ($pdo->query('SELECT id FROM planos ORDER BY preco_mensal ASC LIMIT 1')->fetchColumn() ?: 0);

                $pdo->prepare(
                    'INSERT INTO empresas (nome, plano_id, status, fuso_horario)
                     VALUES (:nome, :plano, :status, :fuso)'
                )->execute([
                    'nome'   => $empNome,
                    'plano'  => $planoId ?: null,
                    'status' => 'ativa',
                    'fuso'   => 'America/Sao_Paulo',
                ]);
                $empresaId = (int) $pdo->lastInsertId();

                $pdo->prepare('INSERT INTO empresa_config (empresa_id) VALUES (:e)')->execute(['e' => $empresaId]);

                $pdo->prepare(
                    'INSERT INTO usuarios (empresa_id, nome, email, usuario, senha_hash, nivel, ativo)
                     VALUES (:emp, :nome, :email, :usuario, :hash, :nivel, 1)'
                )->execute([
                    'emp'     => $empresaId,
                    'nome'    => $aNome,
                    'email'   => $aEmail,
                    'usuario' => $aUser,
                    'hash'    => password_hash($aSenha, PASSWORD_DEFAULT),
                    'nivel'   => 'admin',
                ]);

                if ($criarCerca) {
                    $pdo->prepare(
                        'INSERT INTO cercas_virtuais (empresa_id, nome, latitude, longitude, raio_metros, ativa)
                         VALUES (:emp, :nome, :lat, :lon, :raio, 1)'
                    )->execute([
                        'emp'  => $empresaId,
                        'nome' => mb_substr($cercaNome, 0, 80),
                        'lat'  => (float) $cercaLat,
                        'lon'  => (float) $cercaLon,
                        'raio' => max(20, min(20000, $cercaRaio)),
                    ]);
                }
            }

            $pdo->commit();
            $concluido = true;
            $mensagem = 'Instalação concluída! Agora apague ou renomeie o arquivo instalar.php e faça login.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $erro = 'Falha na instalação: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalação — ConnectWork</title>
  <link rel="stylesheet" href="<?= e(url('css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('css/complementos.css')) ?>">
  <link rel="icon" href="<?= e(url('assets/favicon.png')) ?>">
</head>
<body class="auth-screen">
  <div class="auth-card" style="max-width:640px">
    <div class="auth-brand">
      <img src="<?= e(url('assets/logo.png')) ?>" alt="ConnectWork" height="40">
      <h1>Instalação do ConnectWork</h1>
      <p>Configure o sistema para rodar no seu XAMPP.</p>
    </div>

    <?php if ($mensagem): ?><div class="alert alert-ok"><?= e($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-erro"><?= e($erro) ?></div><?php endif; ?>

    <h3>1. Ambiente</h3>
    <ul class="check-list">
      <?php foreach ($checagens as $c): ?>
        <li>
          <span class="<?= $c[1] ? 'ok' : 'fail' ?>"><?= $c[1] ? '' : '' ?></span>
          <span style="flex:1"><?= e($c[0]) ?></span>
          <span class="muted small mono"><?= e($c[2]) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$conectou): ?>
      <p class="note">Não foi possível conectar ao banco. Confira <b>includes/config.php</b>
        (host, porta, usuário, senha) e se o MySQL do XAMPP está ligado.
        <?php if ($erroConexao): ?><br><span class="mono small"><?= e($erroConexao) ?></span><?php endif; ?></p>
    <?php elseif (!$temTabelas): ?>
      <p class="note">Conectou, mas as tabelas não existem. Importe <b>connectwork.sql</b> no phpMyAdmin
        (banco <b><?= e(DB_NOME) ?></b>) e recarregue esta página.</p>
    <?php endif; ?>

    <?php if ($concluido): ?>
      <div style="text-align:center;margin-top:20px">
        <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Ir para o login</a>
      </div>
    <?php elseif (!$temMaster && $conectou && $temTabelas): ?>
      <form method="post" class="form-grid" style="margin-top:8px">
        <?= csrf_campo() ?>

        <h3 class="wide">2. Administrador Master</h3>
        <p class="wide muted small">A conta que administra a plataforma inteira (todas as empresas).</p>
        <label>Nome<input type="text" name="master_nome" required></label>
        <label>E-mail<input type="email" name="master_email" required></label>
        <label>Usuário<input type="text" name="master_usuario" pattern="[A-Za-z0-9._-]{3,60}" required></label>
        <label>Senha<input type="password" name="master_senha" minlength="8" autocomplete="new-password" required></label>

        <div class="wide" style="border-top:1px solid var(--line);padding-top:12px;margin-top:4px">
          <label class="check"><input type="checkbox" name="criar_empresa" value="1" checked> 3. Criar a primeira empresa agora</label>
        </div>
        <label>Nome da empresa<input type="text" name="empresa_nome"></label>
        <label class="wide" style="visibility:hidden;height:0;margin:0;padding:0"></label>
        <label>Admin da empresa — nome<input type="text" name="admin_nome"></label>
        <label>Admin — e-mail<input type="email" name="admin_email"></label>
        <label>Admin — usuário<input type="text" name="admin_usuario" pattern="[A-Za-z0-9._-]{3,60}"></label>
        <label>Admin — senha<input type="password" name="admin_senha" minlength="8" autocomplete="new-password"></label>

        <div class="wide" style="border-top:1px solid var(--line);padding-top:12px;margin-top:4px">
          <label class="check"><input type="checkbox" name="criar_cerca" value="1"> 4. Criar uma cerca inicial (opcional)</label>
        </div>
        <label>Cerca — nome<input type="text" name="cerca_nome" placeholder="Sede"></label>
        <label>Raio (m)<input type="number" name="cerca_raio" value="150" min="20" max="20000"></label>
        <label>Latitude<input type="text" name="cerca_lat" placeholder="-23.5613"></label>
        <label>Longitude<input type="text" name="cerca_lon" placeholder="-46.6560"></label>

        <button class="btn btn-success full" type="submit">Instalar ConnectWork</button>
      </form>
      <p class="note">Depois de instalar, <b>apague ou renomeie o arquivo instalar.php</b> — ele não deve
        ficar acessível em produção.</p>
    <?php endif; ?>
  </div>
</body>
</html>
