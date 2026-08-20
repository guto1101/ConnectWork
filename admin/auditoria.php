<?php
/**
 * ConnectWork — Auditoria da empresa
 *
 * Visão somente leitura da trilha gerada por seguranca.php. A tabela de
 * auditoria é global por suportar a plataforma, mas esta página usa a
 * consulta dedicada de Db para obrigar empresa_id da sessão.
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['admin']);

$acao = entrada('acao', 'get');
$entidade = entrada('entidade', 'get');
$de = entrada('de', 'get');
$ate = entrada('ate', 'get');

$where = '1 = 1';
$params = [];
if ($acao !== '') {
    $where .= ' AND a.acao LIKE :acao';
    $params['acao'] = '%' . mb_substr($acao, 0, 60) . '%';
}
if ($entidade !== '') {
    $where .= ' AND a.entidade LIKE :entidade';
    $params['entidade'] = '%' . mb_substr($entidade, 0, 60) . '%';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
    $where .= ' AND a.criado_em >= :de';
    $params['de'] = $de . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    $where .= ' AND a.criado_em <= :ate';
    $params['ate'] = $ate . ' 23:59:59';
}

$logs = Db::auditoriaDaEmpresa($where, $params, 300);

cabecalho(
    'Auditoria',
    'auditoria',
    'Auditoria da empresa',
    'Eventos de acesso, ponto, aprovações, exportações e manutenção administrativa.'
);
?>

<div class="card">
  <div class="card-head"><div><h3>Filtros</h3><p>Consulte os eventos da sua empresa.</p></div></div>
  <form method="get" class="form-grid compact">
    <label>Ação contém<input type="text" name="acao" value="<?= e($acao) ?>" placeholder="ponto, aprovacao, exportacao..."></label>
    <label>Entidade contém<input type="text" name="entidade" value="<?= e($entidade) ?>" placeholder="funcionarios, pontos..."></label>
    <label>De<input type="date" name="de" value="<?= e($de) ?>"></label>
    <label>Até<input type="date" name="ate" value="<?= e($ate) ?>"></label>
    <button class="btn btn-primary" type="submit">Filtrar</button>
  </form>
</div>

<div class="card">
  <div class="card-head"><div><h3>Eventos registrados</h3><p><?= count($logs) ?> registro(s), limitado aos 300 mais recentes.</p></div></div>
  <?php if (!$logs): ?>
    <?= vazio('Nenhum evento no filtro', 'Ajuste os critérios para consultar outro período ou ação.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Data</th><th>Responsável</th><th>Ação</th><th>Entidade</th><th>Detalhes</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td class="muted small mono"><?= e(data_br($log['criado_em'], true)) ?></td>
            <td>
              <b><?= e($log['usuario_nome'] ?: 'Conta indisponível') ?></b>
              <?php if (!empty($log['usuario_login'])): ?><div class="muted small">@<?= e($log['usuario_login']) ?></div><?php endif; ?>
            </td>
            <td><b><?= e($log['acao']) ?></b></td>
            <td class="muted"><?= e($log['entidade'] ?: '—') ?><?= $log['entidade_id'] ? ' #' . (int) $log['entidade_id'] : '' ?></td>
            <td class="muted small"><?= e($log['detalhes'] ? mb_substr($log['detalhes'], 0, 180) : '—') ?></td>
            <td class="muted small mono"><?= e($log['ip'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php rodape(); ?>
