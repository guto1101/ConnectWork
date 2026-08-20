<?php
/**
 * ConnectWork — Dashboard do gerente
 *
 * Enxerga apenas a própria equipe (subordinados diretos + ele mesmo).
 * Todo número aqui é filtrado por Auth::equipeVisivel(): o gerente nunca
 * vê gente de outro gestor, e a camada Db ainda garante que tudo é da
 * mesma empresa.
 */

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/ponto.php';

Auth::exigirNivel(['gerente']);

$equipe = Auth::equipeVisivel() ?: [Auth::funcionarioId()];
$hoje = date('Y-m-d');

// IN seguro: os ids vêm do banco (Auth::equipeVisivel) e são forçados a inteiro.
$inSql = implode(',', array_map('intval', $equipe));

$totalEquipe = count($equipe);

$presentesHoje = (int) Db::valor('pontos', 'COUNT(DISTINCT funcionario_id) AS t',
    "data = :d AND tipo = :t AND funcionario_id IN ($inSql)",
    ['d' => $hoje, 't' => 'entrada']);

$revisao = Db::contar('pontos',
    "status = :s AND funcionario_id IN ($inSql)",
    ['s' => 'pendente_revisao']);

$semRegistro = max(0, $totalEquipe - $presentesHoje);

// Presença dos últimos 7 dias na equipe
$serie = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $serie[$dia] = (int) Db::valor('pontos', 'COUNT(DISTINCT funcionario_id) AS t',
        "data = :d AND tipo = :t AND funcionario_id IN ($inSql)",
        ['d' => $dia, 't' => 'entrada']);
}
$maxSerie = max(1, max($serie));

// Batidas de hoje da equipe
$batidasHoje = Db::todos('pontos',
    "data = :d AND funcionario_id IN ($inSql)",
    ['d' => $hoje],
    ['ordem' => 'data_hora DESC', 'limite' => 12]);

$nomes = [];
foreach (Db::todos('funcionarios', "id IN ($inSql)", [], ['colunas' => 'id, nome, cargo']) as $f) {
    $nomes[(int) $f['id']] = $f;
}

cabecalho('Dashboard', 'painel',
    'Painel da equipe',
    'Sua equipe em ' . e(data_extenso()),
    '<a class="btn btn-primary" href="' . e(url('gerente/equipe.php')) . '">Ver equipe</a>');
?>

<div class="metrics-grid">
  <article class="metric-card"><div class="metric-icon icon-blue"></div><div>
    <span class="metric-label">Minha equipe</span><strong class="metric-value"><?= (int) $totalEquipe ?></strong>
    <span class="metric-trend up">pessoa(s)</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-green"></div><div>
    <span class="metric-label">Presentes hoje</span><strong class="metric-value"><?= (int) $presentesHoje ?></strong>
    <span class="metric-trend <?= $semRegistro > 0 ? 'down' : 'up' ?>"><?= (int) $semRegistro ?> sem registro</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-orange"></div><div>
    <span class="metric-label">Ponto a conferir</span><strong class="metric-value"><?= (int) $revisao ?></strong>
    <span class="metric-trend down">batidas pendentes</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-purple"></div><div>
    <span class="metric-label">Batidas hoje</span><strong class="metric-value"><?= count($batidasHoje) ?></strong>
    <span class="metric-trend up">na equipe</span></div></article>
</div>

<div class="charts-grid">
  <div class="card">
    <div class="card-head"><div><h3>Presença da equipe</h3><p>Entradas distintas por dia</p></div><span class="pill">7 dias</span></div>
    <div class="bars">
      <?php foreach ($serie as $dia => $qtd): ?>
        <div class="bar-col">
          <div class="bar" style="height:<?= max(5, round($qtd / $maxSerie * 170)) ?>px" title="<?= (int) $qtd ?>"></div>
          <span class="bar-label"><?= e(date('d/m', strtotime($dia))) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Atalhos</h3><p>Rotina do gestor</p></div></div>
    <div class="quick-actions" style="flex-direction:column;align-items:stretch">
      <a class="btn btn-primary" href="<?= e(url('gerente/ponto.php')) ?>">Espelho de ponto da equipe</a>
      <a class="btn btn-ghost" href="<?= e(url('gerente/ponto.php?filtro=revisao')) ?>">Conferir batidas pendentes</a>
      <a class="btn btn-ghost" href="<?= e(url('gerente/equipe.php')) ?>">Ver equipe</a>
      <a class="btn btn-ghost" href="<?= e(url('comunicados.php')) ?>">Comunicados</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h3>Batidas de hoje</h3><p>Registros recentes da equipe</p></div>
    <a class="btn btn-ghost" href="<?= e(url('gerente/ponto.php')) ?>">Espelho completo</a>
  </div>
  <?php if (!$batidasHoje): ?>
    <?= vazio('Nenhuma batida hoje', 'Assim que a equipe registrar ponto, aparece aqui.') ?>
  <?php else: ?>
    <ul class="punch-list">
      <?php foreach ($batidasHoje as $b): ?>
        <li>
          <span class="<?= $b['tipo'] === 'entrada' ? 'dot-green' : ($b['tipo'] === 'saida' ? 'dot-red' : 'dot-blue') ?>"></span>
          <div style="flex:1">
            <b><?= e($nomes[(int) $b['funcionario_id']]['nome'] ?? 'Funcionário') ?></b>
            <div class="muted small"><?= e(Ponto::ROTULOS[$b['tipo']]) ?> · <?= e(date('H:i', strtotime($b['data_hora']))) ?></div>
          </div>
          <?php if ($b['status'] === 'pendente_revisao'): ?><?= badge('Conferir', 'yellow') ?>
          <?php elseif ((int) $b['dentro_cerca'] === 1): ?><?= badge('No local', 'green') ?>
          <?php elseif ($b['dentro_cerca'] !== null): ?><?= badge('Fora', 'red') ?>
          <?php else: ?><?= badge('Sem cerca', 'gray') ?><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php rodape(); ?>
