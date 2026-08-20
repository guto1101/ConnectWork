<?php
/**
 * ConnectWork — Empresas (Administrador Master)
 *
 * Cadastro e manutenção das empresas clientes: plano, situação e a
 * criação do administrador inicial de cada empresa. Tudo aqui é área da
 * plataforma, então abrimos com Db::plataforma(). A conta de admin da
 * empresa é criada num INSERT direto (a tabela usuarios é global no
 * ponto de vista do master), com o empresa_id da nova empresa.
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['master']);
Db::plataforma();

$pdo = conexao();

/** Valida e salva a logo de uma empresa em uploads/logos. */
function salvar_logo_empresa(int $empresaId, array $arquivo): bool
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return false;
    }
    if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível receber a logo enviada.');
    }
    if (($arquivo['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('A logo deve ter no máximo 2 MB.');
    }

    $tmp = $arquivo['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Arquivo de logo inválido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $extensoes = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];
    if (!isset($extensoes[$mime])) {
        throw new RuntimeException('A logo deve estar em PNG, JPG ou WebP.');
    }

    $dir = CW_UPLOAD_DIR . '/logos';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar o diretório de logos.');
    }

    foreach (glob($dir . '/empresa_' . $empresaId . '.*') ?: [] as $antiga) {
        if (is_file($antiga)) {
            @unlink($antiga);
        }
    }

    $destino = $dir . '/empresa_' . $empresaId . '.' . $extensoes[$mime];
    if (!move_uploaded_file($tmp, $destino)) {
        throw new RuntimeException('Não foi possível salvar a logo.');
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'nova') {
        $nome     = entrada('nome');
        $razao    = entrada('razao_social');
        $cnpj     = entrada('cnpj');
        $segmento = entrada('segmento');
        $planoId  = entrada_int('plano_id');
        $fuso     = entrada('fuso_horario') ?: 'America/Sao_Paulo';

        // Administrador inicial da empresa
        $admNome  = entrada('admin_nome');
        $admEmail = mb_strtolower(entrada('admin_email'));
        $admUser  = mb_strtolower(entrada('admin_usuario'));
        $admSenha = $_POST['admin_senha'] ?? '';

        $erros = [];
        if ($nome === '')       { $erros[] = 'Informe o nome da empresa.'; }
        if ($admNome === '')    { $erros[] = 'Informe o nome do administrador.'; }
        if (!filter_var($admEmail, FILTER_VALIDATE_EMAIL)) { $erros[] = 'E-mail do administrador inválido.'; }
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $admUser)) { $erros[] = 'Usuário do administrador inválido.'; }
        if (mb_strlen($admSenha) < 8) { $erros[] = 'A senha do administrador precisa ter ao menos 8 caracteres.'; }

        if (!$erros) {
            $ja = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE LOWER(usuario) = :u OR LOWER(email) = :e');
            $ja->execute(['u' => $admUser, 'e' => $admEmail]);
            if ((int) $ja->fetchColumn() > 0) { $erros[] = 'Já existe uma conta com esse usuário ou e-mail.'; }
        }

        if ($erros) {
            flash('erro', implode(' ', $erros));
        } else {
            try {
                $pdo->beginTransaction();

                $ins = $pdo->prepare(
                    'INSERT INTO empresas (nome, razao_social, cnpj, segmento, plano_id, status, fuso_horario)
                     VALUES (:nome, :razao, :cnpj, :segmento, :plano, :status, :fuso)'
                );
                $ins->execute([
                    'nome'     => $nome,
                    'razao'    => $razao ?: null,
                    'cnpj'     => $cnpj ?: null,
                    'segmento' => $segmento ?: null,
                    'plano'    => $planoId ?: null,
                    'status'   => 'ativa',
                    'fuso'     => $fuso,
                ]);
                $empresaId = (int) $pdo->lastInsertId();

                // Logo opcional da empresa
                if (isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    salvar_logo_empresa($empresaId, $_FILES['logo']);
                }

                // Configuração inicial da empresa
                $pdo->prepare('INSERT INTO empresa_config (empresa_id) VALUES (:e)')
                    ->execute(['e' => $empresaId]);

                // Administrador da empresa
                $insU = $pdo->prepare(
                    'INSERT INTO usuarios (empresa_id, nome, email, usuario, senha_hash, nivel, ativo)
                     VALUES (:emp, :nome, :email, :usuario, :hash, :nivel, 1)'
                );
                $insU->execute([
                    'emp'     => $empresaId,
                    'nome'    => $admNome,
                    'email'   => $admEmail,
                    'usuario' => $admUser,
                    'hash'    => password_hash($admSenha, PASSWORD_DEFAULT),
                    'nivel'   => 'admin',
                ]);

                $pdo->commit();
                auditar('empresa_criada', 'empresas', $empresaId, $nome);
                flash('ok', 'Empresa e administrador criados com sucesso.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('ConnectWork/master empresas: ' . $e->getMessage());
                flash('erro', 'Não foi possível criar. Verifique se o CNPJ já existe.');
            }
        }
        voltar_para('master/empresas.php');
    } elseif ($acao === 'logo') {
        $id = entrada_int('id');
        if ($id <= 0 || !Db::porId('empresas', $id)) {
            flash('erro', 'Empresa não encontrada.');
        } else {
            try {
                salvar_logo_empresa($id, $_FILES['logo'] ?? []);
                auditar('empresa_logo_atualizada', 'empresas', $id, 'Logo da empresa atualizada');
                flash('ok', 'Logo da empresa atualizada.');
            } catch (Throwable $e) {
                error_log('ConnectWork/master logo: ' . $e->getMessage());
                flash('erro', $e->getMessage());
            }
        }
        voltar_para('master/empresas.php');
    } elseif ($acao === 'situacao') {
        $id  = entrada_int('id');
        $novo = entrada('status');
        if (in_array($novo, ['ativa', 'suspensa', 'cancelada'], true) && Db::porId('empresas', $id)) {
            Db::atualizar('empresas', $id, ['status' => $novo]);
            // Suspender/cancelar bloqueia os acessos daquela empresa.
            $pdo->prepare('UPDATE usuarios SET ativo = :a WHERE empresa_id = :e')
                ->execute(['a' => $novo === 'ativa' ? 1 : 0, 'e' => $id]);
            auditar('empresa_situacao', 'empresas', $id, $novo);
            flash('ok', 'Situação da empresa atualizada.');
        }
        voltar_para('master/empresas.php');
    } elseif ($acao === 'plano') {
        $id = entrada_int('id');
        $planoId = entrada_int('plano_id');
        if (Db::porId('empresas', $id)) {
            Db::atualizar('empresas', $id, ['plano_id' => $planoId ?: null]);
            flash('ok', 'Plano da empresa atualizado.');
        }
        voltar_para('master/empresas.php');
    }
}

$empresas = Db::todos('empresas', '', [], ['ordem' => 'nome']);
$planos   = Db::todos('planos', 'ativo = 1', [], ['ordem' => 'preco_mensal']);
$planoNome = [];
foreach ($planos as $p) { $planoNome[(int) $p['id']] = $p['nome']; }

// Contagem de funcionários por empresa (visão da plataforma)
$funcPorEmpresa = [];
foreach ($pdo->query(
    "SELECT empresa_id, COUNT(*) AS t FROM funcionarios WHERE status <> 'desligado' GROUP BY empresa_id"
) as $l) {
    $funcPorEmpresa[(int) $l['empresa_id']] = (int) $l['t'];
}

cabecalho('Empresas', 'empresas', 'Empresas clientes',
    'Cadastro e manutenção das empresas da plataforma.');
?>

<div class="card">
  <div class="card-head"><div><h3>Nova empresa</h3><p>Cria a empresa e o administrador inicial dela</p></div></div>
  <form method="post" class="form-grid" enctype="multipart/form-data">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="nova">

    <label>Nome da empresa *<input type="text" name="nome" required></label>
    <label>Razão social<input type="text" name="razao_social"></label>
    <label>CNPJ<input type="text" name="cnpj" placeholder="00.000.000/0000-00"></label>
    <label>Segmento<input type="text" name="segmento"></label>
    <label>Plano
      <select name="plano_id">
        <option value="">— sem plano —</option>
        <?php foreach ($planos as $p): ?>
          <option value="<?= (int) $p['id'] ?>"><?= e($p['nome']) ?> (até <?= (int) $p['limite_funcionarios'] ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Fuso horário<input type="text" name="fuso_horario" value="America/Sao_Paulo"></label>
    <label>Logo da empresa<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><span class="muted small">PNG, JPG ou WebP, até 2 MB.</span></label>

    <div class="wide" style="border-top:1px solid var(--line);padding-top:12px;margin-top:4px">
      <h4 style="margin:0 0 4px">Administrador da empresa</h4>
      <p class="muted small">Essa conta gerencia a empresa (funcionários, cercas, ouvidoria).</p>
    </div>
    <label>Nome do administrador *<input type="text" name="admin_nome" required></label>
    <label>E-mail *<input type="email" name="admin_email" required></label>
    <label>Usuário de acesso *<input type="text" name="admin_usuario" pattern="[A-Za-z0-9._-]{3,60}" required></label>
    <label>Senha inicial *<input type="password" name="admin_senha" minlength="8" autocomplete="new-password" required></label>

    <button class="btn btn-success" type="submit">Criar empresa</button>
  </form>
</div>

<div class="card">
  <div class="card-head"><div><h3>Empresas cadastradas</h3><p><?= count($empresas) ?> no total</p></div></div>
  <?php if (!$empresas): ?>
    <?= vazio('Nenhuma empresa ainda', 'Cadastre a primeira acima.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Empresa</th><th>Logo</th><th>Plano</th><th>Funcionários</th><th>Situação</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($empresas as $emp): $id = (int) $emp['id']; ?>
          <tr>
            <td><b><?= e($emp['nome']) ?></b><?php if ($emp['segmento']): ?><div class="muted small"><?= e($emp['segmento']) ?></div><?php endif; ?></td>
            <td>
              <form method="post" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="logo">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required>
                <button class="btn btn-ghost" type="submit">Atualizar logo</button>
              </form>
            </td>
            <td>
              <form method="post" style="display:flex;gap:6px;align-items:center">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="plano">
                <input type="hidden" name="id" value="<?= $id ?>">
                <select name="plano_id" onchange="this.form.submit()">
                  <option value="">—</option>
                  <?php foreach ($planos as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) $emp['plano_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td class="mono"><?= (int) ($funcPorEmpresa[$id] ?? 0) ?></td>
            <td>
              <?= $emp['status'] === 'ativa' ? badge('Ativa', 'green')
                 : ($emp['status'] === 'suspensa' ? badge('Suspensa', 'yellow') : badge('Cancelada', 'red')) ?>
            </td>
            <td style="white-space:nowrap">
              <?php foreach (['ativa' => 'Ativar', 'suspensa' => 'Suspender', 'cancelada' => 'Cancelar'] as $st => $rot): if ($emp['status'] === $st) continue; ?>
                <form method="post" style="display:inline">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="situacao">
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="status" value="<?= $st ?>">
                  <button class="btn <?= $st === 'cancelada' ? 'btn-danger' : 'btn-ghost' ?>" type="submit"
                    <?= $st === 'cancelada' ? 'data-confirma="Cancelar ' . e($emp['nome']) . '? Os acessos serão bloqueados."' : '' ?>><?= $rot ?></button>
                </form>
              <?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<p class="note">
  O Administrador Master vê as empresas e seus totais, mas não abre o conteúdo interno de cada uma
  (ponto, ouvidoria, pessoas) por aqui — esse isolamento é proposital. Para dar suporte dentro de uma
  empresa é preciso entrar nela de forma explícita, o que fica registrado na auditoria.
</p>

<?php rodape(); ?>
