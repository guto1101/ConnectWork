<?php
/**
 * ConnectWork — Funcionários e acessos da empresa
 *
 * O administrador mantém pessoas, contas de acesso e papéis de gerente ou
 * funcionário. Contas master e admin não são criadas nem alteradas por esta
 * tela. Toda leitura e escrita de tabelas multiempresa passa por Db.
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['admin']);

const NIVEIS_GERENCIAVEIS = ['funcionario', 'gerente'];

/** Valida o identificador usado pela conta local. */
function usuario_valido(string $usuario): bool
{
    return (bool) preg_match('/^[a-z0-9._-]{3,60}$/', $usuario);
}

/** Confirma se o identificador ou e-mail já está em uso na própria empresa. */
function conta_local_duplicada(string $usuario, string $email, ?int $ignorarUsuarioId = null): bool
{
    $conta = Db::um(
        'usuarios',
        'LOWER(usuario) = :u OR LOWER(email) = :e',
        ['u' => $usuario, 'e' => $email]
    );
    return $conta !== null && (int) $conta['id'] !== (int) $ignorarUsuarioId;
}

/** Retorna o nível permitido ou o padrão mais restrito. */
function nivel_gerenciavel(string $nivel): string
{
    return in_array($nivel, NIVEIS_GERENCIAVEIS, true) ? $nivel : 'funcionario';
}

$editar = null;

// ---------------------------------------------------------------------
// Cadastro ou edição de funcionário
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    csrf_exigir();

    $id        = entrada_int('id');
    $nome      = entrada('nome');
    $matricula = entrada('matricula');
    $cpf       = entrada('cpf');
    $email     = mb_strtolower(entrada('email'));
    $telefone  = entrada('telefone');
    $cargo     = entrada('cargo');
    $deptoId   = entrada_int('departamento_id');
    $gestorId  = entrada_int('gestor_id');
    $admissao  = entrada('data_admissao');
    $jornada   = max(0, min(1440, entrada_int('jornada') ?: 480));
    $status    = entrada('status');

    $criarAcesso = isset($_POST['criar_acesso']);
    $usuario     = mb_strtolower(entrada('usuario'));
    $senha       = $_POST['senha'] ?? '';
    $nivelRecebido = entrada('nivel');
    $nivelAcesso = nivel_gerenciavel($nivelRecebido);

    $status = in_array($status, ['ativo', 'afastado', 'desligado'], true) ? $status : 'ativo';
    $erros = [];

    if ($nome === '') { $erros[] = 'Informe o nome.'; }
    if ($matricula === '') { $erros[] = 'Informe a matrícula.'; }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $erros[] = 'E-mail inválido.'; }
    if ($gestorId && $gestorId === $id) { $erros[] = 'Um funcionário não pode ser o próprio gestor.'; }
    if ($deptoId && !Db::porId('departamentos', $deptoId)) { $erros[] = 'Departamento inválido.'; }
    if ($gestorId && !Db::porId('funcionarios', $gestorId)) { $erros[] = 'Gestor inválido.'; }

    if (!$id) {
        $plano = Db::planoDaEmpresa();
        $limite = isset($plano['limite_funcionarios']) ? (int) $plano['limite_funcionarios'] : null;
        if ($status !== 'desligado' && $limite !== null
            && Db::contar('funcionarios', 'status <> :desligado', ['desligado' => 'desligado']) >= $limite) {
            $erros[] = "O plano da empresa permite até $limite funcionários ativos.";
        }
    }

    if ($criarAcesso && !$id) {
        if (!in_array($nivelRecebido, NIVEIS_GERENCIAVEIS, true)) { $erros[] = 'O papel de acesso deve ser Gerente ou Funcionário.'; }
        if ($email === '') { $erros[] = 'Informe o e-mail para criar uma conta de acesso.'; }
        if (!usuario_valido($usuario)) { $erros[] = 'Usuário de acesso inválido.'; }
        if (mb_strlen($senha) < 8) { $erros[] = 'A senha inicial deve ter ao menos 8 caracteres.'; }
        if (!$erros && conta_local_duplicada($usuario, $email)) {
            $erros[] = 'Já existe uma conta com esse usuário ou e-mail nesta empresa.';
        }
    }

    if ($erros) {
        flash('erro', implode(' ', $erros));
        $editar = $_POST;
    } else {
        try {
            Db::transacao(static function () use (
                $id, $nome, $matricula, $cpf, $email, $telefone, $cargo, $deptoId,
                $gestorId, $admissao, $jornada, $status, $criarAcesso, $usuario, $senha, $nivelAcesso
            ) {
                $dados = [
                    'matricula'              => $matricula,
                    'nome'                   => $nome,
                    'cpf'                    => $cpf ?: null,
                    'email'                  => $email ?: null,
                    'telefone'               => $telefone ?: null,
                    'cargo'                  => $cargo ?: null,
                    'departamento_id'        => $deptoId ?: null,
                    'gestor_id'              => $gestorId ?: null,
                    'data_admissao'          => preg_match('/^\d{4}-\d{2}-\d{2}$/', $admissao) ? $admissao : null,
                    'jornada_diaria_minutos' => $jornada,
                    'status'                 => $status,
                ];

                if ($id) {
                    $atual = Db::porId('funcionarios', $id);
                    if (!$atual) { throw new RuntimeException('Funcionário não encontrado.'); }
                    Db::atualizar('funcionarios', $id, $dados);

                    // Mantém nome e e-mail apenas de contas locais que o
                    // administrador pode gerenciar; admin e master ficam fora.
                    if (!empty($atual['usuario_id'])) {
                        $conta = Db::porId('usuarios', (int) $atual['usuario_id']);
                        if (!$conta || !in_array($conta['nivel'], NIVEIS_GERENCIAVEIS, true)) {
                            throw new RuntimeException('Conta administrativa não pode ser alterada nesta tela.');
                        }
                        $dadosConta = ['nome' => $nome];
                        if ($email !== '') { $dadosConta['email'] = $email; }
                        Db::atualizar('usuarios', (int) $atual['usuario_id'], $dadosConta);
                    }
                } else {
                    $usuarioId = null;
                    if ($criarAcesso) {
                        $usuarioId = Db::inserir('usuarios', [
                            'nome'          => $nome,
                            'email'         => $email,
                            'usuario'       => $usuario,
                            'senha_hash'    => password_hash($senha, PASSWORD_DEFAULT),
                            'nivel'         => $nivelAcesso,
                            'ativo'         => $status === 'desligado' ? 0 : 1,
                            'trocar_senha'  => 1,
                        ]);
                    }
                    $dados['usuario_id'] = $usuarioId;
                    Db::inserir('funcionarios', $dados);
                }
            });

            auditar($id ? 'funcionario_editado' : 'funcionario_criado', 'funcionarios', $id ?: null);
            flash('ok', $id ? 'Funcionário atualizado.' : 'Funcionário cadastrado.');
            voltar_para('admin/funcionarios.php');
        } catch (Throwable $ex) {
            error_log('ConnectWork/funcionarios salvar: ' . $ex->getMessage());
            flash('erro', 'Não foi possível salvar. Verifique se a matrícula, CPF, usuário ou e-mail já existem.');
            $editar = $_POST;
        }
    }
}

// ---------------------------------------------------------------------
// Conta de acesso e papel (somente gerente ou funcionário)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'acesso_salvar') {
    csrf_exigir();

    $funcionarioId = entrada_int('funcionario_id');
    $funcionario = $funcionarioId ? Db::porId('funcionarios', $funcionarioId) : null;
    $usuario = mb_strtolower(entrada('usuario_acesso'));
    $email = mb_strtolower(entrada('email_acesso'));
    $senha = $_POST['senha_acesso'] ?? '';
    $nivelRecebido = entrada('nivel_acesso');
    $nivel = nivel_gerenciavel($nivelRecebido);
    $ativo = isset($_POST['acesso_ativo']) ? 1 : 0;
    $erros = [];

    if (!$funcionario) { $erros[] = 'Funcionário não encontrado.'; }
    if (!in_array($nivelRecebido, NIVEIS_GERENCIAVEIS, true)) { $erros[] = 'O papel de acesso deve ser Gerente ou Funcionário.'; }
    if (!usuario_valido($usuario)) { $erros[] = 'Usuário de acesso inválido.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erros[] = 'E-mail de acesso inválido.'; }

    $contaAtual = $funcionario && !empty($funcionario['usuario_id'])
        ? Db::porId('usuarios', (int) $funcionario['usuario_id'])
        : null;

    if ($contaAtual && !in_array($contaAtual['nivel'], NIVEIS_GERENCIAVEIS, true)) {
        $erros[] = 'Contas de Administrador e Administrador Master não podem ser gerenciadas nesta tela.';
    }
    if (!$contaAtual && mb_strlen($senha) < 8) {
        $erros[] = 'Informe uma senha inicial de ao menos 8 caracteres.';
    }
    if ($senha !== '' && mb_strlen($senha) < 8) {
        $erros[] = 'A nova senha deve ter ao menos 8 caracteres.';
    }
    if (!$erros && conta_local_duplicada($usuario, $email, $contaAtual ? (int) $contaAtual['id'] : null)) {
        $erros[] = 'Já existe uma conta com esse usuário ou e-mail nesta empresa.';
    }

    if ($erros) {
        flash('erro', implode(' ', $erros));
    } else {
        try {
            Db::transacao(static function () use ($funcionario, $contaAtual, $usuario, $email, $senha, $nivel, $ativo) {
                if ($contaAtual) {
                    $dadosConta = [
                        'nome'    => $funcionario['nome'],
                        'email'   => $email,
                        'usuario' => $usuario,
                        'nivel'   => $nivel,
                        'ativo'   => $ativo,
                    ];
                    if ($senha !== '') {
                        $dadosConta['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
                        $dadosConta['trocar_senha'] = 1;
                    }
                    Db::atualizar('usuarios', (int) $contaAtual['id'], $dadosConta);
                } else {
                    $novoUsuarioId = Db::inserir('usuarios', [
                        'nome'         => $funcionario['nome'],
                        'email'        => $email,
                        'usuario'      => $usuario,
                        'senha_hash'   => password_hash($senha, PASSWORD_DEFAULT),
                        'nivel'        => $nivel,
                        'ativo'        => $ativo,
                        'trocar_senha' => 1,
                    ]);
                    Db::atualizar('funcionarios', (int) $funcionario['id'], [
                        'usuario_id' => $novoUsuarioId,
                        'email'      => $email,
                    ]);
                }
            });

            auditar($contaAtual ? 'acesso_atualizado' : 'acesso_criado', 'funcionarios', (int) $funcionario['id'], $nivel);
            flash('ok', $contaAtual ? 'Conta de acesso atualizada.' : 'Conta de acesso criada.');
        } catch (Throwable $ex) {
            error_log('ConnectWork/acesso funcionario: ' . $ex->getMessage());
            flash('erro', 'Não foi possível salvar a conta. Verifique se o usuário ou e-mail já existe.');
        }
    }
    voltar_para('admin/funcionarios.php?editar=' . $funcionarioId);
}

// ---------------------------------------------------------------------
// Exclusão definitiva
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    csrf_exigir();
    $id = entrada_int('id');
    $funcionario = $id ? Db::porId('funcionarios', $id) : null;
    $conta = $funcionario && !empty($funcionario['usuario_id'])
        ? Db::porId('usuarios', (int) $funcionario['usuario_id'])
        : null;

    if (!$funcionario) {
        flash('erro', 'Funcionário não encontrado.');
    } elseif (Auth::funcionarioId() === $id) {
        flash('erro', 'Você não pode excluir o próprio cadastro enquanto está conectado.');
    } elseif ($conta && !in_array($conta['nivel'], NIVEIS_GERENCIAVEIS, true)) {
        flash('erro', 'Contas administrativas não podem ser excluídas nesta tela.');
    } else {
        try {
            Db::transacao(function () use ($id, $funcionario, $conta): void {
                if ($conta) {
                    Db::excluir('usuarios', (int) $conta['id']);
                }
                Db::excluir('funcionarios', $id);
            });
            auditar('funcionario_excluido', 'funcionarios', $id, $funcionario['nome']);
            flash('ok', 'Funcionário excluído definitivamente.');
        } catch (Throwable $ex) {
            error_log('ConnectWork/excluir funcionario: ' . $ex->getMessage());
            flash('erro', 'Não foi possível excluir o funcionário. Verifique os vínculos existentes.');
        }
    }
    voltar_para('admin/funcionarios.php');
}

// ---------------------------------------------------------------------
// Alterar situação rápida
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'status') {
    csrf_exigir();
    $id = entrada_int('id');
    $novo = entrada('status');
    $funcionario = $id ? Db::porId('funcionarios', $id) : null;

    if ($funcionario && in_array($novo, ['ativo', 'afastado', 'desligado'], true)) {
        $conta = !empty($funcionario['usuario_id'])
            ? Db::porId('usuarios', (int) $funcionario['usuario_id'])
            : null;
        if ($conta && !in_array($conta['nivel'], NIVEIS_GERENCIAVEIS, true)) {
            flash('erro', 'A situação de uma conta administrativa não pode ser alterada nesta tela.');
        } else {
            $extra = ['status' => $novo];
            if ($novo === 'desligado') { $extra['data_desligamento'] = date('Y-m-d'); }
            if ($novo === 'ativo') { $extra['data_desligamento'] = null; }
            Db::atualizar('funcionarios', $id, $extra);

            if ($conta) {
                Db::atualizar('usuarios', (int) $conta['id'], ['ativo' => $novo === 'desligado' ? 0 : 1]);
            }
            auditar('funcionario_status', 'funcionarios', $id, $novo);
            flash('ok', 'Situação atualizada.');
        }
    }
    voltar_para('admin/funcionarios.php');
}

// Carrega para edição e conta associada dentro do escopo da empresa.
$edicaoId = entrada_int('editar', 'get');
if ($edicaoId && !$editar) { $editar = Db::porId('funcionarios', $edicaoId); }
$contaEdicao = $editar && !empty($editar['usuario_id'])
    ? Db::porId('usuarios', (int) $editar['usuario_id'])
    : null;

// ---------------------------------------------------------------------
// Listagem
// ---------------------------------------------------------------------
$busca = entrada('q', 'get');
$fStatus = entrada('status', 'get');
$where = '1 = 1';
$params = [];
if ($busca !== '') {
    $termo = '%' . $busca . '%';
    $where .= ' AND (nome LIKE :q_nome OR matricula LIKE :q_matricula OR cargo LIKE :q_cargo)';
    $params['q_nome'] = $termo;
    $params['q_matricula'] = $termo;
    $params['q_cargo'] = $termo;
}
if (in_array($fStatus, ['ativo', 'afastado', 'desligado'], true)) {
    $where .= ' AND status = :st';
    $params['st'] = $fStatus;
}

$funcionarios = Db::todos('funcionarios', $where, $params, ['ordem' => 'nome', 'limite' => 300]);
$departamentos = Db::todos('departamentos', 'ativo = 1', [], ['ordem' => 'nome']);
$gestores = Db::todos('funcionarios', "status = 'ativo'", [], ['ordem' => 'nome', 'colunas' => 'id, nome']);

$deptoNome = [];
foreach ($departamentos as $d) { $deptoNome[(int) $d['id']] = $d['nome']; }
$gestorNome = [];
foreach ($gestores as $g) { $gestorNome[(int) $g['id']] = $g['nome']; }
$val = static fn(string $campo, $padrao = '') => e($editar[$campo] ?? $padrao);

cabecalho(
    'Funcionários',
    'funcionarios',
    'Funcionários e acessos',
    'Cadastro de pessoas, contas locais e papéis da empresa.',
    '<a class="btn btn-ghost" href="' . e(url('exportar.php?tipo=funcionarios')) . '">Exportar CSV</a>'
);
?>

<div class="card">
  <div class="card-head">
    <div>
      <h3><?= $editar && !empty($editar['id']) ? 'Editar funcionário' : 'Novo funcionário' ?></h3>
      <p>Campos com * são obrigatórios.</p>
    </div>
    <?php if ($editar && !empty($editar['id'])): ?>
      <a class="btn btn-ghost" href="<?= e(url('admin/funcionarios.php')) ?>">Cancelar edição</a>
    <?php endif; ?>
  </div>

  <form method="post" class="form-grid">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="id" value="<?= e($editar['id'] ?? '') ?>">

    <label>Nome *<input type="text" name="nome" value="<?= $val('nome') ?>" required></label>
    <label>Matrícula *<input type="text" name="matricula" value="<?= $val('matricula') ?>" required></label>
    <label>CPF<input type="text" name="cpf" value="<?= $val('cpf') ?>" placeholder="000.000.000-00"></label>
    <label>E-mail<input type="email" name="email" value="<?= $val('email') ?>"></label>
    <label>Telefone<input type="tel" name="telefone" value="<?= $val('telefone') ?>"></label>
    <label>Cargo<input type="text" name="cargo" value="<?= $val('cargo') ?>"></label>

    <label>Departamento
      <select name="departamento_id">
        <option value="">—</option>
        <?php foreach ($departamentos as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= (int) ($editar['departamento_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Gestor responsável
      <select name="gestor_id">
        <option value="">—</option>
        <?php foreach ($gestores as $g): if ((int) $g['id'] === (int) ($editar['id'] ?? 0)) continue; ?>
          <option value="<?= (int) $g['id'] ?>" <?= (int) ($editar['gestor_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Admissão<input type="date" name="data_admissao" value="<?= $val('data_admissao') ?>"></label>
    <label>Jornada diária (min)<input type="number" name="jornada" min="0" max="1440" value="<?= e($editar['jornada_diaria_minutos'] ?? 480) ?>"></label>
    <label>Situação
      <select name="status">
        <?php foreach (['ativo' => 'Ativo', 'afastado' => 'Afastado', 'desligado' => 'Desligado'] as $k => $r): ?>
          <option value="<?= $k ?>" <?= ($editar['status'] ?? 'ativo') === $k ? 'selected' : '' ?>><?= $r ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <?php if (!$editar || empty($editar['id'])): ?>
      <div class="wide" style="border-top:1px solid var(--line);padding-top:12px;margin-top:4px">
        <label class="check"><input type="checkbox" name="criar_acesso" value="1" <?= !empty($editar['criar_acesso']) ? 'checked' : '' ?>> Criar conta de acesso agora</label>
      </div>
      <label>Usuário de acesso<input type="text" name="usuario" value="<?= $val('usuario') ?>" pattern="[A-Za-z0-9._-]{3,60}"></label>
      <label>Senha inicial<input type="password" name="senha" minlength="8" autocomplete="new-password"></label>
      <label>Papel da conta
        <select name="nivel">
          <option value="funcionario">Funcionário</option>
          <option value="gerente" <?= ($editar['nivel'] ?? '') === 'gerente' ? 'selected' : '' ?>>Gerente</option>
        </select>
      </label>
    <?php endif; ?>

    <button class="btn btn-success" type="submit"><?= $editar && !empty($editar['id']) ? 'Salvar alterações' : 'Cadastrar funcionário' ?></button>
  </form>
</div>

<?php if ($editar && !empty($editar['id'])): ?>
<div class="card">
  <div class="card-head">
    <div>
      <h3>Conta de acesso e papel</h3>
      <p>Somente os papéis Gerente e Funcionário podem ser administrados aqui.</p>
    </div>
  </div>

  <?php if ($contaEdicao && !in_array($contaEdicao['nivel'], NIVEIS_GERENCIAVEIS, true)): ?>
    <div class="alert alert-info">Esta pessoa possui uma conta administrativa. O papel dela não pode ser alterado nesta tela.</div>
  <?php else: ?>
    <form method="post" class="form-grid compact">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="acesso_salvar">
      <input type="hidden" name="funcionario_id" value="<?= (int) $editar['id'] ?>">
      <label>Usuário de acesso *<input type="text" name="usuario_acesso" value="<?= e($contaEdicao['usuario'] ?? '') ?>" pattern="[A-Za-z0-9._-]{3,60}" required></label>
      <label>E-mail de acesso *<input type="email" name="email_acesso" value="<?= e($contaEdicao['email'] ?? ($editar['email'] ?? '')) ?>" required></label>
      <label><?= $contaEdicao ? 'Nova senha (opcional)' : 'Senha inicial *' ?><input type="password" name="senha_acesso" minlength="8" autocomplete="new-password" <?= $contaEdicao ? '' : 'required' ?>></label>
      <label>Papel
        <select name="nivel_acesso">
          <option value="funcionario" <?= ($contaEdicao['nivel'] ?? 'funcionario') === 'funcionario' ? 'selected' : '' ?>>Funcionário</option>
          <option value="gerente" <?= ($contaEdicao['nivel'] ?? '') === 'gerente' ? 'selected' : '' ?>>Gerente</option>
        </select>
      </label>
      <label class="check"><input type="checkbox" name="acesso_ativo" value="1" <?= !$contaEdicao || (int) $contaEdicao['ativo'] === 1 ? 'checked' : '' ?>> Conta ativa</label>
      <button class="btn btn-primary" type="submit"><?= $contaEdicao ? 'Salvar acesso' : 'Criar acesso' ?></button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><div><h3>Equipe cadastrada</h3><p><?= count($funcionarios) ?> pessoa(s)</p></div></div>

  <form method="get" class="form-grid compact" style="margin-bottom:14px">
    <label>Buscar<input type="text" name="q" value="<?= e($busca) ?>" placeholder="Nome, matrícula ou cargo"></label>
    <label>Situação
      <select name="status">
        <option value="">Todas</option>
        <?php foreach (['ativo' => 'Ativos', 'afastado' => 'Afastados', 'desligado' => 'Desligados'] as $k => $r): ?>
          <option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= $r ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn-primary" type="submit">Filtrar</button>
  </form>

  <?php if (!$funcionarios): ?>
    <?= vazio('Nenhum funcionário encontrado', 'Ajuste o filtro ou cadastre o primeiro no formulário acima.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nome</th><th>Matrícula</th><th>Cargo</th><th>Departamento</th><th>Gestor</th><th>Acesso</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($funcionarios as $f): ?>
          <tr>
            <td><b><?= e($f['nome']) ?></b><?php if ($f['email']): ?><div class="muted small"><?= e($f['email']) ?></div><?php endif; ?></td>
            <td class="mono"><?= e($f['matricula']) ?></td>
            <td class="muted"><?= e($f['cargo'] ?: '—') ?></td>
            <td class="muted"><?= e($deptoNome[(int) $f['departamento_id']] ?? '—') ?></td>
            <td class="muted"><?= e($gestorNome[(int) $f['gestor_id']] ?? '—') ?></td>
            <td><?= $f['usuario_id'] ? badge('Com login', 'blue') : badge('Sem login', 'gray') ?></td>
            <td><?= badge_status_funcionario($f['status']) ?></td>
            <td class="right" style="white-space:nowrap">
              <a class="btn btn-ghost" href="<?= e(url('admin/funcionarios.php?editar=' . (int) $f['id'])) ?>">Editar</a>
              <?php if ($f['status'] !== 'desligado'): ?>
                <form method="post" style="display:inline">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="status">
                  <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                  <input type="hidden" name="status" value="desligado">
                  <button class="btn btn-danger" type="submit" data-confirma="Desligar <?= e($f['nome']) ?>? O acesso será bloqueado e o histórico preservado.">Desligar</button>
                </form>
              <?php else: ?>
                <form method="post" style="display:inline">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="status">
                  <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                  <input type="hidden" name="status" value="ativo">
                  <button class="btn btn-ghost" type="submit">Reativar</button>
                </form>
              <?php endif; ?>
              <form method="post" style="display:inline">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                <button class="btn btn-danger" type="submit" data-confirma="Excluir definitivamente <?= e($f['nome']) ?>? Esta ação remove o cadastro, a conta de acesso e os vínculos relacionados.">Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php rodape(); ?>
