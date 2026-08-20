# ConnectWork
<?php
/**
 * ConnectWork — Acesso ao sistema
 *
 * A pessoa escolhe primeiro a empresa em que trabalha e só então informa
 * suas credenciais. O acesso do CEO ConnectWork permanece separado e não
 * compartilha o contexto de nenhuma empresa.
 */

require_once __DIR__ . '/includes/auth.php';

sessao_iniciar();
cabecalhos_seguranca();

if (Auth::logado()) {
    header('Location: ' . Auth::paginaInicial());
    exit;
}

$aba = entrada('aba', 'get') === 'cadastro' ? 'cadastro' : 'login';
$modoCeo = $aba === 'login' && entrada('acesso', 'get') === 'ceo';
$erro = '';
$ok = '';
$dados = [
    'login_usuario' => '',
    'empresa_id' => entrada_int('empresa', 'get'),
    'nome' => '',
    'email' => '',
    'empresa' => '',
    'usuario' => '',
];

$empresasAtivas = Db::empresasAtivasParaLogin();
$empresasPorId = [];
foreach ($empresasAtivas as $empresaAtiva) {
    $empresasPorId[(int) $empresaAtiva['id']] = $empresaAtiva;
}
if (!isset($empresasPorId[(int) $dados['empresa_id']])) {
    $dados['empresa_id'] = 0;
}

// ---------------------------------------------------------------------
// Entrar em uma empresa escolhida
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'login') {
    csrf_exigir();
    $dados['login_usuario'] = entrada('usuario');
    $dados['empresa_id'] = entrada_int('empresa_id');

    if (!isset($empresasPorId[(int) $dados['empresa_id']])) {
        $erro = 'Escolha uma empresa ativa para entrar.';
    } else {
        $r = Auth::entrar($dados['login_usuario'], $_POST['senha'] ?? '', (int) $dados['empresa_id']);
        if ($r['ok']) {
            $retorno = entrada('retorno', 'get');
            if ($retorno !== '' && $retorno[0] === '/' && strpos($retorno, '//') === false) {
                header('Location: ' . $retorno);
            } else {
                header('Location: ' . Auth::paginaInicial($r['nivel']));
            }
            exit;
        }
        $erro = $r['erro'];
    }
}

// ---------------------------------------------------------------------
// Acesso da plataforma: somente CEO ConnectWork (nível master)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'login_ceo') {
    csrf_exigir();
    $dados['login_usuario'] = entrada('usuario_ceo');
    $r = Auth::entrar($dados['login_usuario'], $_POST['senha_ceo'] ?? '', null);

    if ($r['ok']) {
        header('Location: ' . Auth::paginaInicial($r['nivel']));
        exit;
    }
    $erro = $r['erro'];
}

// ---------------------------------------------------------------------
// Cadastrar empresa
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastro') {
    csrf_exigir();
    $aba = 'cadastro';

    $dados['nome'] = entrada('nome');
    $dados['email'] = mb_strtolower(entrada('email'));
    $dados['empresa'] = entrada('empresa');
    $dados['usuario'] = mb_strtolower(entrada('novo_usuario'));
    $senha = $_POST['nova_senha'] ?? '';

    if ($dados['nome'] === '' || $dados['empresa'] === '' || $dados['usuario'] === '') {
        $erro = 'Preencha nome, empresa e usuário.';
    } elseif (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (mb_strlen($senha) < 8) {
        $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    } elseif (!preg_match('/^[a-z0-9._-]{3,60}$/', $dados['usuario'])) {
        $erro = 'O usuário aceita letras, números, ponto, hífen e sublinhado (3 a 60 caracteres).';
    } else {
        $pdo = conexao();
        $ja = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE LOWER(email) = :e OR LOWER(usuario) = :u');
        $ja->execute(['e' => $dados['email'], 'u' => $dados['usuario']]);

        if ((int) $ja->fetchColumn() > 0) {
            $erro = 'Já existe uma conta com esse e-mail ou usuário.';
        } else {
            try {
                $pdo->beginTransaction();
                $planoId = $pdo->query('SELECT id FROM planos ORDER BY limite_funcionarios LIMIT 1')->fetchColumn();

                $pdo->prepare('INSERT INTO empresas (nome, plano_id, status) VALUES (:n, :p, :s)')
                    ->execute(['n' => $dados['empresa'], 'p' => $planoId ?: null, 's' => 'ativa']);
                $empresaId = (int) $pdo->lastInsertId();

                $pdo->prepare('INSERT INTO empresa_config (empresa_id) VALUES (:e)')
                    ->execute(['e' => $empresaId]);

                $pdo->prepare(
                    'INSERT INTO usuarios (empresa_id, nome, email, usuario, senha_hash, nivel, ativo)
                     VALUES (:e, :n, :em, :u, :h, :nv, 1)'
                )->execute([
                    'e' => $empresaId,
                    'n' => $dados['nome'],
                    'em' => $dados['email'],
                    'u' => $dados['usuario'],
                    'h' => password_hash($senha, PASSWORD_DEFAULT),
                    'nv' => 'admin',
                ]);
                $usuarioId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO funcionarios (empresa_id, usuario_id, matricula, nome, email, cargo, data_admissao, status)
                     VALUES (:e, :u, :m, :n, :em, :c, CURDATE(), :st)'
                )->execute([
                    'e' => $empresaId,
                    'u' => $usuarioId,
                    'm' => '0001',
                    'n' => $dados['nome'],
                    'em' => $dados['email'],
                    'c' => 'Administrador',
                    'st' => 'ativo',
                ]);

                $pdo->commit();
                $r = Auth::entrar($dados['usuario'], $senha, $empresaId);
                if ($r['ok']) {
                    header('Location: ' . Auth::paginaInicial($r['nivel']));
                    exit;
                }
                $ok = 'Empresa criada. Selecione a empresa abaixo e entre com as credenciais definidas.';
                $aba = 'login';
                $dados['empresa_id'] = $empresaId;
                $empresasAtivas = Db::empresasAtivasParaLogin();
                $empresasPorId = [];
                foreach ($empresasAtivas as $empresaAtiva) { $empresasPorId[(int) $empresaAtiva['id']] = $empresaAtiva; }
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('ConnectWork/cadastro: ' . $ex->getMessage());
                $erro = 'Não foi possível concluir o cadastro agora. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConnectWork — Acesso</title>
<link rel="icon" type="image/png" href="<?= e(url('assets/logo.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('css/complementos.css')) ?>">
</head>
<body>

<section class="auth-screen">
  <div class="auth-card auth-card-wide">
    <div class="auth-brand">
      <img src="<?= e(url('assets/logo.png')) ?>" alt="ConnectWork">
      <div>
        <h1>ConnectWork</h1>
        <p>Selecione sua empresa para acessar com segurança.</p>
      </div>
    </div>

    <div class="auth-tabs">
      <a class="auth-tab<?= $aba === 'login' ? ' active' : '' ?>" href="<?= e(url('index.php')) ?>">Entrar</a>
      <a class="auth-tab<?= $aba === 'cadastro' ? ' active' : '' ?>" href="<?= e(url('index.php')) ?>?aba=cadastro">Cadastrar empresa</a>
    </div>

    <?php if ($erro !== ''): ?><div class="alert alert-erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
    <?php if ($ok !== ''): ?><div class="alert alert-ok" role="status"><?= e($ok) ?></div><?php endif; ?>

    <?php if ($aba === 'login' && $modoCeo): ?>
      <div class="ceo-login-panel">
        <span class="access-step">Acesso da plataforma</span>
        <h2>CEO ConnectWork</h2>
        <p>Use esta área somente para administrar a plataforma ConnectWork.</p>
        <form class="auth-form" method="post">
          <?= csrf_campo() ?>
          <input type="hidden" name="acao" value="login_ceo">
          <label for="usuario_ceo">Usuário ou e-mail do CEO
            <input type="text" id="usuario_ceo" name="usuario_ceo" autocomplete="username" required autofocus>
          </label>
          <label for="senha_ceo">Senha
            <input type="password" id="senha_ceo" name="senha_ceo" autocomplete="current-password" required>
          </label>
          <button class="btn btn-primary full" type="submit">Acessar plataforma</button>
        </form>
        <a class="auth-back-link" href="<?= e(url('index.php')) ?>">← Voltar para o acesso das empresas</a>
      </div>

    <?php elseif ($aba === 'login'): ?>
      <div class="login-flow">
        <aside class="company-picker" aria-label="Empresas disponíveis">
          <div class="company-picker-head">
            <span class="access-step">Acesso por empresa</span>
            <h2>Escolha onde deseja entrar</h2>
            <p>Selecione uma empresa para preencher o acesso. O login será validado somente para a empresa escolhida.</p>
          </div>
          <?php if (!$empresasAtivas): ?>
            <div class="empty"><b>Nenhuma empresa ativa</b> Cadastre a primeira empresa para começar.</div>
          <?php else: ?>
            <div class="company-list">
              <?php foreach ($empresasAtivas as $empresaAtiva): ?>
                <a class="company-option<?= (int) $dados['empresa_id'] === (int) $empresaAtiva['id'] ? ' selected' : '' ?>" href="<?= e(url('index.php?empresa=' . (int) $empresaAtiva['id'])) ?>">
                  <span class="company-initials"><?= e(mb_strtoupper(mb_substr($empresaAtiva['nome'], 0, 1))) ?></span>
                  <span class="company-copy">
                    <span class="company-name"><?= e($empresaAtiva['nome']) ?></span>
                    <span class="company-meta"><?= e(!empty($empresaAtiva['segmento']) ? $empresaAtiva['segmento'] : 'Empresa ativa') ?></span>
                  </span>
                  <span class="company-arrow">Selecionar</span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </aside>

        <section class="company-login-panel">
          <form class="auth-form" method="post">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="login">
            <label for="empresa_id">Empresa de acesso
              <select id="empresa_id" name="empresa_id" required>
                <option value="">Escolha a empresa</option>
                <?php foreach ($empresasAtivas as $empresaAtiva): ?>
                  <option value="<?= (int) $empresaAtiva['id'] ?>" <?= (int) $dados['empresa_id'] === (int) $empresaAtiva['id'] ? 'selected' : '' ?>><?= e($empresaAtiva['nome']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label for="usuario">Usuário ou e-mail
              <input type="text" id="usuario" name="usuario" value="<?= e($dados['login_usuario']) ?>" autocomplete="username" required autofocus>
            </label>
            <label for="senha">Senha
              <input type="password" id="senha" name="senha" autocomplete="current-password" required>
            </label>
            <button class="btn btn-primary full" type="submit">Entrar no sistema</button>
            <p class="auth-help">Escolha a empresa, informe suas credenciais e acesse o ambiente correto.</p>
          </form>
          <a class="auth-ceo-link" href="<?= e(url('index.php?acesso=ceo')) ?>"><span>CEO ConnectWork</span> Acesso exclusivo à plataforma →</a>
        </section>
      </div>

    <?php else: ?>
      <form class="auth-form" method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="cadastro">
        <label for="empresa">Nome da empresa<input type="text" id="empresa" name="empresa" value="<?= e($dados['empresa']) ?>" required></label>
        <label for="nome">Seu nome completo<input type="text" id="nome" name="nome" value="<?= e($dados['nome']) ?>" required></label>
        <label for="email">E-mail<input type="email" id="email" name="email" value="<?= e($dados['email']) ?>" required></label>
        <label for="novo_usuario">Usuário de acesso<input type="text" id="novo_usuario" name="novo_usuario" value="<?= e($dados['usuario']) ?>" pattern="[A-Za-z0-9._-]{3,60}" required></label>
        <label for="nova_senha">Senha (mínimo 8 caracteres)<input type="password" id="nova_senha" name="nova_senha" minlength="8" autocomplete="new-password" required></label>
        <button class="btn btn-success full" type="submit">Criar empresa e entrar</button>
        <p class="auth-help">A conta criada será a administradora da empresa e poderá cadastrar a equipe.</p>
      </form>
    <?php endif; ?>
  </div>
</section>

</body>
</html>
