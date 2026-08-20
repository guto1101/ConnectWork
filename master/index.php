<?php
/**
 * ConnectWork — Painel da plataforma (Administrador Master)
 *
 * O master enxerga a plataforma inteira: empresas, planos e auditoria.
 * Ele NÃO enxerga o conteúdo interno das empresas (ponto, ouvidoria,
 * pessoas) sem antes "entrar" numa empresa por Db::comoMaster(). Este
 * painel fica no nível de cima — números de empresas e planos.
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['master']);
Db::plataforma();

$empresasAtivas   = Db::contar('empresas', "status = 'ativa'");
$empresasSusp     = Db::contar('empresas', "status = 'suspensa'");
$empresasCancel   = Db::contar('empresas', "status = 'cancelada'");
$totalEmpresas    = Db::contar('empresas');
$totalPlanos      = Db::contar('planos', 'ativo = 1');

// Contas e funcionários somados na plataforma (sem entrar em cada empresa)
$totalUsuarios = (int) conexao()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$totalFunc     = (int) conexao()->query('SELECT COUNT(*) FROM funcionarios')->fetchColumn();

// MRR estimado: soma do preço do plano das empresas ativas
$mrr = (float) conexao()->query(
    "SELECT COALESCE(SUM(p.preco_mensal),0)
       FROM empresas e JOIN planos p ON p.id = e.plano_id
      WHERE e.status = 'ativa'"
)->fetchColumn();

$empresas = Db::todos('empresas', '', [], ['ordem' => 'criado_em DESC', 'limite' => 8]);
$planoNome = [];
foreach (Db::todos('planos') as $p) { $planoNome[(int) $p['id']] = $p['nome']; }

// Últimos eventos de auditoria da plataforma
$logs = Db::todos('auditoria', '', [], ['ordem' => 'criado_em DESC', 'limite' => 8]);

cabecalho('Plataforma', 'painel', 'Painel da plataforma',
    'Visão geral de todas as empresas do ConnectWork.',
    '<a class="btn btn-primary" href="' . e(url('master/empresas.php')) . '">Nova empresa</a>');
?>

<div class="metrics-grid">
  <article class="metric-card"><div class="metric-icon icon-blue"></div><div>
    <span class="metric-label">Empresas ativas</span><strong class="metric-value"><?= (int) $empresasAtivas ?></strong>
    <span class="metric-trend up"><?= (int) $totalEmpresas ?> no total</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-green"></div><div>
    <span class="metric-label">Funcionários</span><strong class="metric-value"><?= (int) $totalFunc ?></strong>
    <span class="metric-trend up"><?= (int) $totalUsuarios ?> conta(s) de acesso</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-purple"></div><div>
    <span class="metric-label">Planos ativos</span><strong class="metric-value"><?= (int) $totalPlanos ?></strong>
    <span class="metric-trend up"><?= (int) $empresasSusp ?> empresa(s) suspensa(s)</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-orange"></div><div>
    <span class="metric-label">Receita mensal estimada</span><strong class="metric-value">R$ <?= e(number_format($mrr, 0, ',', '.')) ?></strong>
    <span class="metric-trend up">planos das ativas</span></div></article>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h3>Empresas recentes</h3><p>Últimos cadastros</p></div>
      <a class="btn btn-ghost" href="<?= e(url('master/empresas.php')) ?>">Ver todas</a></div>
    <?php if (!$empresas): ?>
      <?= vazio('Nenhuma empresa ainda', 'Cadastre a primeira empresa cliente.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Empresa</th><th>Plano</th><th>Situação</th><th>Desde</th></tr></thead>
          <tbody>
          <?php foreach ($empresas as $emp): ?>
            <tr>
              <td><b><?= e($emp['nome']) ?></b><?php if ($emp['cnpj']): ?><div class="muted small mono"><?= e($emp['cnpj']) ?></div><?php endif; ?></td>
              <td class="muted"><?= e($planoNome[(int) $emp['plano_id']] ?? '—') ?></td>
              <td>
                <?= $emp['status'] === 'ativa' ? badge('Ativa', 'green')
                   : ($emp['status'] === 'suspensa' ? badge('Suspensa', 'yellow') : badge('Cancelada', 'red')) ?>
              </td>
              <td class="muted small"><?= e(data_br($emp['criado_em'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Auditoria recente</h3><p>Eventos da plataforma</p></div>
      <a class="btn btn-ghost" href="<?= e(url('master/auditoria.php')) ?>">Ver tudo</a></div>
    <?php if (!$logs): ?>
      <?= vazio('Sem eventos registrados') ?>
    <?php else: ?>
      <ul class="activity">
        <?php foreach ($logs as $l): ?>
          <li>
            <span class="dot-blue"></span>
            <div style="flex:1">
              <strong><?= e($l['acao']) ?></strong>
              <span><?= e($l['entidade'] ?: '—') ?>
                <?= $l['entidade_id'] ? '#' . (int) $l['entidade_id'] : '' ?>
                · <?= e(data_br($l['criado_em'], true)) ?></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h3>Administração da plataforma</h3><p>Atalhos</p></div></div>
  <div class="quick-actions">
    <a class="btn btn-primary" href="<?= e(url('master/empresas.php')) ?>">Empresas</a>
    <a class="btn btn-primary" href="<?= e(url('master/planos.php')) ?>">Planos</a>
    <a class="btn btn-ghost" href="<?= e(url('master/auditoria.php')) ?>">Auditoria</a>
  </div>
</div>

<?php rodape(); ?>
