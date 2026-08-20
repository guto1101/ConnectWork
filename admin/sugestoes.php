<?php
/**
 * ConnectWork — Triagem de sugestões (administrador)
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar') {
    csrf_exigir();
    $id  = entrada_int('id');
    $s   = Db::porId('sugestoes', $id);
    $novo = entrada('status');

    if (!$s) {
        flash('erro', 'Sugestão não encontrada.');
    } elseif (!in_array($novo, ['recebida', 'em_analise', 'aprovada', 'implementada', 'recusada'], true)) {
        flash('erro', 'Situação inválida.');
    } else {
        Db::atualizar('sugestoes', $id, [
            'status'  => $novo,
            'retorno' => entrada('retorno') ?: null,
        ]);
        // Avisa o autor identificado
        if ($s['funcionario_id']) {
            $func = Db::porId('funcionarios', (int) $s['funcionario_id']);
            if ($func && $func['usuario_id']) {
                Db::inserir('notificacoes', [
                    'usuario_id' => (int) $func['usuario_id'],
                    'titulo'     => 'Sua sugestão foi atualizada',
                    'corpo'      => $s['titulo'] . ' — ' . ucfirst($novo),
                    'link'       => 'sugestoes.php',
                ]);
            }
        }
        auditar('sugestao_triada', 'sugestoes', $id, $novo);
        flash('ok', 'Sugestão atualizada.');
    }
    voltar_para('admin/sugestoes.php');
}

$fStatus = entrada('status', 'get');
$where = '1 = 1';
$params = [];
if (in_array($fStatus, ['recebida', 'em_analise', 'aprovada', 'implementada', 'recusada'], true)) {
    $where .= ' AND status = :st';
    $params['st'] = $fStatus;
}

$sugestoes = Db::todos('sugestoes', $where, $params, ['ordem' => 'criado_em DESC', 'limite' => 100]);

$nomes = [];
foreach (Db::todos('funcionarios', '', [], ['colunas' => 'id, nome']) as $f) {
    $nomes[(int) $f['id']] = $f['nome'];
}

$cores = ['recebida' => 'gray', 'em_analise' => 'yellow', 'aprovada' => 'blue', 'implementada' => 'green', 'recusada' => 'red'];

cabecalho('Sugestões', 'sugestoes', 'Sugestões',
    'Triagem e devolutiva das ideias enviadas pela equipe.',
    '<a class="btn btn-ghost" href="' . e(url('exportar.php?tipo=sugestoes')) . '">Exportar CSV</a>');
?>

<div class="card">
  <form method="get">
    <select name="status" onchange="this.form.submit()">
      <option value="">Todas as situações</option>
      <?php foreach (['recebida' => 'Recebidas', 'em_analise' => 'Em análise', 'aprovada' => 'Aprovadas', 'implementada' => 'Implementadas', 'recusada' => 'Recusadas'] as $k => $r): ?>
        <option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= $r ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (!$sugestoes): ?>
  <div class="card"><?= vazio('Nenhuma sugestão', 'Nada neste filtro.') ?></div>
<?php else: ?>
  <div class="stack">
    <?php foreach ($sugestoes as $s): ?>
      <div class="card">
        <div class="card-head">
          <div>
            <h3><?= e($s['titulo']) ?></h3>
            <p>
              <?= (int) $s['anonima'] === 1 ? 'Anônima' : e($nomes[(int) $s['funcionario_id']] ?? 'Autor') ?>
              · <?= e($s['area'] ?: 'Geral') ?> · <?= e(data_br($s['criado_em'])) ?>
            </p>
          </div>
          <?= badge(ucfirst(str_replace('_', ' ', $s['status'])), $cores[$s['status']] ?? 'gray') ?>
        </div>

        <p><?= nl2br(e($s['descricao'])) ?></p>

        <form method="post" class="form-grid compact">
          <?= csrf_campo() ?>
          <input type="hidden" name="acao" value="atualizar">
          <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <label>Situação
            <select name="status">
              <?php foreach (['recebida' => 'Recebida', 'em_analise' => 'Em análise', 'aprovada' => 'Aprovada', 'implementada' => 'Implementada', 'recusada' => 'Recusada'] as $k => $r): ?>
                <option value="<?= $k ?>" <?= $s['status'] === $k ? 'selected' : '' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="wide">Devolutiva para o autor
            <textarea name="retorno" placeholder="Retorno que o autor verá na tela dele."><?= e($s['retorno'] ?? '') ?></textarea>
          </label>
          <button class="btn btn-success" type="submit">Salvar devolutiva</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php rodape(); ?>
