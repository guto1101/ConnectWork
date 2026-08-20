<?php
/**
 * ConnectWork — Auditoria da plataforma (Administrador Master)
 *
 * Trilha de eventos: logins, criação de empresas, mudanças de situação,
 * ações administrativas. Só leitura. É a memória de "quem fez o quê".
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['master']);
Db::plataforma();

$acaoFiltro = entrada('acao', 'get');
$de  = entrada('de', 'get');
$ate = entrada('ate', 'get');

$where = '1 = 1';
$params = [];
if ($acaoFiltro !== '') {
    $where .= ' AND acao LIKE :a';
    $params['a'] = '%' . $acaoFiltro . '%';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
    $where .= ' AND criado_em >= :de';
    $params['de'] = $de . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    $where .= ' AND criado_em <= :ate';
    $params['ate'] = $ate . ' 23:59:59';
}

$logs = Db::todos('auditoria', $where, $params, ['ordem' => 'criado_em DESC', 'limite' => 300]);

// Nomes das empresas para exibir junto
$empresas = [];
foreach (Db::todos('empresas', '', [], ['colunas' => 'id, nome']) as $e) {
    $empresas[(int) $e['id']] = $e['nome'];
}

cabecalho('Auditoria', 'auditoria', 'Auditoria da plataforma',
    'Trilha de eventos de todas as empresas.');
?>

<div class="card">
  <form method="get" class="form-grid compact">
    <label>Ação contém<input type="text" name="acao" value="<?= e($acaoFiltro) ?>" placeholder="login, empresa, ponto..."></label>
    <label>De<input type="date" name="de" value="<?= e($de) ?>"></label>
    <label>Até<input type="date" name="ate" value="<?= e($ate) ?>"></label>
    <button class="btn btn-primary" type="submit">Filtrar</button>
  </form>
</div>

<div class="card">
  <div class="card-head"><div><h3>Eventos</h3><p><?= count($logs) ?> registro(s) — máx. 300</p></div></div>
  <?php if (!$logs): ?>
    <?= vazio('Nenhum evento no filtro', 'Ajuste os critérios acima.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Data</th><th>Empresa</th><th>Ação</th><th>Entidade</th><th>Detalhes</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td class="muted small mono"><?= e(data_br($l['criado_em'], true)) ?></td>
            <td class="muted"><?= e($l['empresa_id'] ? ($empresas[(int) $l['empresa_id']] ?? ('#' . (int) $l['empresa_id'])) : 'Plataforma') ?></td>
            <td><b><?= e($l['acao']) ?></b></td>
            <td class="muted"><?= e($l['entidade'] ?: '—') ?><?= $l['entidade_id'] ? ' #' . (int) $l['entidade_id'] : '' ?></td>
            <td class="muted small"><?= e($l['detalhes'] ? mb_substr($l['detalhes'], 0, 60) : '—') ?></td>
            <td class="muted small mono"><?= e($l['ip'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php rodape(); ?>
