<?php
/**
 * ConnectWork — Planos (Administrador Master)
 *
 * Catálogo de planos: nome, limite de funcionários, preço e situação.
 * O limite definido aqui é o que a tela de funcionários da empresa
 * respeita ao cadastrar gente nova.
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['master']);
Db::plataforma();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id     = entrada_int('id');
        $nome   = entrada('nome');
        $limite = entrada_int('limite_funcionarios') ?: 25;
        $preco  = str_replace(',', '.', entrada('preco_mensal'));

        if ($nome === '') {
            flash('erro', 'Informe o nome do plano.');
        } else {
            $dados = [
                'nome'                => mb_substr($nome, 0, 60),
                'limite_funcionarios' => max(1, min(1000000, $limite)),
                'preco_mensal'        => is_numeric($preco) ? (float) $preco : 0,
            ];
            try {
                if ($id && Db::porId('planos', $id)) {
                    Db::atualizar('planos', $id, $dados);
                    flash('ok', 'Plano atualizado.');
                } else {
                    $dados['ativo'] = 1;
                    Db::inserir('planos', $dados);
                    flash('ok', 'Plano criado.');
                }
                auditar('plano_salvo', 'planos', $id ?: null);
            } catch (Throwable $e) {
                flash('erro', 'Já existe um plano com esse nome.');
            }
        }
    } elseif ($acao === 'alternar') {
        $id = entrada_int('id');
        $p  = Db::porId('planos', $id);
        if ($p) {
            Db::atualizar('planos', $id, ['ativo' => (int) $p['ativo'] === 1 ? 0 : 1]);
            flash('ok', 'Situação do plano atualizada.');
        }
    }
    voltar_para('master/planos.php');
}

$edicao = null;
$edicaoId = entrada_int('editar', 'get');
if ($edicaoId) { $edicao = Db::porId('planos', $edicaoId); }

$planos = Db::todos('planos', '', [], ['ordem' => 'preco_mensal']);

// Quantas empresas usam cada plano
$uso = [];
foreach (conexao()->query('SELECT plano_id, COUNT(*) AS t FROM empresas WHERE plano_id IS NOT NULL GROUP BY plano_id') as $l) {
    $uso[(int) $l['plano_id']] = (int) $l['t'];
}

cabecalho('Planos', 'planos', 'Planos', 'Catálogo de planos da plataforma.');
?>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div><h3><?= $edicao ? 'Editar plano' : 'Novo plano' ?></h3><p>Limite e preço mensal</p></div>
      <?php if ($edicao): ?><a class="btn btn-ghost" href="<?= e(url('master/planos.php')) ?>">Cancelar</a><?php endif; ?>
    </div>
    <form method="post" class="form-grid compact">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar">
      <input type="hidden" name="id" value="<?= e($edicao['id'] ?? '') ?>">
      <label class="wide">Nome<input type="text" name="nome" value="<?= e($edicao['nome'] ?? '') ?>" required></label>
      <label>Limite de funcionários<input type="number" name="limite_funcionarios" min="1" value="<?= e($edicao['limite_funcionarios'] ?? 25) ?>"></label>
      <label>Preço mensal (R$)<input type="text" name="preco_mensal" value="<?= e($edicao['preco_mensal'] ?? '0.00') ?>"></label>
      <button class="btn btn-success" type="submit"><?= $edicao ? 'Salvar plano' : 'Criar plano' ?></button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Planos cadastrados</h3><p><?= count($planos) ?> plano(s)</p></div></div>
    <?php if (!$planos): ?>
      <?= vazio('Nenhum plano ainda', 'Crie o primeiro ao lado.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Plano</th><th>Limite</th><th>Preço</th><th>Empresas</th><th>Situação</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($planos as $p): $id = (int) $p['id']; ?>
            <tr>
              <td><b><?= e($p['nome']) ?></b></td>
              <td class="mono"><?= (int) $p['limite_funcionarios'] ?></td>
              <td class="mono">R$ <?= e(number_format((float) $p['preco_mensal'], 2, ',', '.')) ?></td>
              <td class="mono"><?= (int) ($uso[$id] ?? 0) ?></td>
              <td><?= (int) $p['ativo'] === 1 ? badge('Ativo', 'green') : badge('Inativo', 'gray') ?></td>
              <td class="right" style="white-space:nowrap">
                <a class="btn btn-ghost" href="<?= e(url('master/planos.php?editar=' . $id)) ?>">Editar</a>
                <form method="post" style="display:inline">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="alternar">
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <button class="btn btn-ghost" type="submit"><?= (int) $p['ativo'] === 1 ? 'Desativar' : 'Ativar' ?></button>
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
