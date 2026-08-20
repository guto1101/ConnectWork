<?php
/**
 * ConnectWork — Departamentos (administrador)
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id   = entrada_int('id');
        $nome = entrada('nome');
        if ($nome === '') {
            flash('erro', 'Informe o nome do departamento.');
        } else {
            try {
                if ($id && Db::porId('departamentos', $id)) {
                    Db::atualizar('departamentos', $id, ['nome' => $nome]);
                    flash('ok', 'Departamento atualizado.');
                } else {
                    Db::inserir('departamentos', ['nome' => $nome, 'ativo' => 1]);
                    flash('ok', 'Departamento criado.');
                }
            } catch (Throwable $e) {
                flash('erro', 'Já existe um departamento com esse nome.');
            }
        }
    } elseif ($acao === 'alternar') {
        $id = entrada_int('id');
        $d  = Db::porId('departamentos', $id);
        if ($d) {
            Db::atualizar('departamentos', $id, ['ativo' => (int) $d['ativo'] === 1 ? 0 : 1]);
            flash('ok', 'Situação do departamento atualizada.');
        }
    }
    voltar_para('admin/departamentos.php');
}

$edicao = null;
$edicaoId = entrada_int('editar', 'get');
if ($edicaoId) { $edicao = Db::porId('departamentos', $edicaoId); }

$departamentos = Db::todos('departamentos', '', [], ['ordem' => 'nome']);

// Contagem de funcionários por departamento
$contagem = [];
foreach (Db::consulta(
    'SELECT departamento_id, COUNT(*) AS t FROM funcionarios
      WHERE empresa_id = :cw_emp AND status <> :d AND departamento_id IS NOT NULL
      GROUP BY departamento_id',
    Db::escopo(['d' => 'desligado'])
) as $l) {
    $contagem[(int) $l['departamento_id']] = (int) $l['t'];
}

cabecalho('Departamentos', 'departamentos', 'Departamentos',
    'Setores da empresa para organizar pessoas e comunicados.');
?>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div><h3><?= $edicao ? 'Editar departamento' : 'Novo departamento' ?></h3><p>Nome exibido em toda a empresa</p></div>
      <?php if ($edicao): ?><a class="btn btn-ghost" href="<?= e(url('admin/departamentos.php')) ?>">Cancelar</a><?php endif; ?>
    </div>
    <form method="post" class="form-grid compact">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar">
      <input type="hidden" name="id" value="<?= e($edicao['id'] ?? '') ?>">
      <label class="wide">Nome<input type="text" name="nome" value="<?= e($edicao['nome'] ?? '') ?>" required></label>
      <button class="btn btn-success" type="submit"><?= $edicao ? 'Salvar' : 'Criar departamento' ?></button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Departamentos</h3><p><?= count($departamentos) ?> cadastrado(s)</p></div></div>
    <?php if (!$departamentos): ?>
      <?= vazio('Nenhum departamento ainda', 'Crie o primeiro no formulário ao lado.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nome</th><th>Pessoas</th><th>Situação</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($departamentos as $d): ?>
            <tr>
              <td><b><?= e($d['nome']) ?></b></td>
              <td class="mono"><?= (int) ($contagem[(int) $d['id']] ?? 0) ?></td>
              <td><?= (int) $d['ativo'] === 1 ? badge('Ativo', 'green') : badge('Inativo', 'gray') ?></td>
              <td class="right" style="white-space:nowrap">
                <a class="btn btn-ghost" href="<?= e(url('admin/departamentos.php?editar=' . (int) $d['id'])) ?>">Editar</a>
                <form method="post" style="display:inline">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="alternar">
                  <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                  <button class="btn btn-ghost" type="submit"><?= (int) $d['ativo'] === 1 ? 'Desativar' : 'Reativar' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php rodape(); ?>
