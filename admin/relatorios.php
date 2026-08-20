<?php
/**
 * ConnectWork — Relatórios (administrador)
 *
 * Visão consolidada de ponto, pessoas, ouvidoria e sugestões dentro de um
 * período, com atalhos para exportar cada bloco em CSV. Os números são
 * sempre da empresa do administrador — a camada Db cuida disso.
 */

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/ponto.php';

Auth::exigirNivel(['admin']);

$de  = entrada('de', 'get');
$ate = entrada('ate', 'get');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de))  { $de  = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) { $ate = date('Y-m-d'); }
if ($de > $ate) { [$de, $ate] = [$ate, $de]; }

// Pessoas
$ativos     = Db::contar('funcionarios', 'status = :s', ['s' => 'ativo']);
$afastados  = Db::contar('funcionarios', 'status = :s', ['s' => 'afastado']);
$desligados = Db::contar('funcionarios', 'status = :s', ['s' => 'desligado']);
$comLogin   = Db::contar('funcionarios', 'usuario_id IS NOT NULL AND status <> :d', ['d' => 'desligado']);

// Ponto no período
$batidas   = Db::contar('pontos', 'data BETWEEN :de AND :ate', ['de' => $de, 'ate' => $ate]);
$revisao   = Db::contar('pontos', 'status = :s AND data BETWEEN :de AND :ate', ['s' => 'pendente_revisao', 'de' => $de, 'ate' => $ate]);
$foraCerca = Db::contar('pontos', 'dentro_cerca = 0 AND data BETWEEN :de AND :ate', ['de' => $de, 'ate' => $ate]);
$diasComPonto = (int) Db::valor('pontos', 'COUNT(DISTINCT data) AS t', 'data BETWEEN :de AND :ate', ['de' => $de, 'ate' => $ate]);

// Minutos trabalhados no período (por funcionário → total)
$totMin = 0;
$porFunc = [];
$linhas = Db::todos('pontos', 'data BETWEEN :de AND :ate',
    ['de' => $de, 'ate' => $ate], ['ordem' => 'funcionario_id, data_hora ASC', 'limite' => 20000]);
$agrupado = [];
foreach ($linhas as $l) {
    $agrupado[(int) $l['funcionario_id']][$l['data']][] = $l;
}
foreach ($agrupado as $fid => $dias) {
    $min = 0;
    foreach ($dias as $batidasDoDia) {
        $min += Ponto::minutosTrabalhados($batidasDoDia);
    }
    $porFunc[$fid] = $min;
    $totMin += $min;
}
arsort($porFunc);

// Ouvidoria e sugestões
$ouvAberta = Db::contar('ouvidoria', 'status IN (:a, :b)', ['a' => 'aberta', 'b' => 'em_analise']);
$ouvTotal  = Db::contar('ouvidoria', 'criado_em BETWEEN :de AND :ate', ['de' => $de . ' 00:00:00', 'ate' => $ate . ' 23:59:59']);
$sugTotal  = Db::contar('sugestoes', 'criado_em BETWEEN :de AND :ate', ['de' => $de . ' 00:00:00', 'ate' => $ate . ' 23:59:59']);
$sugImplem = Db::contar('sugestoes', 'status = :s', ['s' => 'implementada']);

$nomes = [];
foreach (Db::todos('funcionarios', '', [], ['colunas' => 'id, nome']) as $f) {
    $nomes[(int) $f['id']] = $f['nome'];
}

$horas = static fn(int $min) => sprintf('%dh%02d', intdiv($min, 60), $min % 60);
$qs = 'de=' . urlencode($de) . '&ate=' . urlencode($ate);

cabecalho('Relatórios', 'relatorios', 'Relatórios',
    'Consolidado do período de ' . e(data_br($de)) . ' a ' . e(data_br($ate)) . '.');
?>

<div class="card">
  <form method="get" class="form-grid compact">
    <label>De<input type="date" name="de" value="<?= e($de) ?>"></label>
    <label>Até<input type="date" name="ate" value="<?= e($ate) ?>"></label>
    <button class="btn btn-primary" type="submit">Aplicar período</button>
  </form>
</div>

<div class="metrics-grid">
  <article class="metric-card"><div class="metric-icon icon-blue"></div><div>
    <span class="metric-label">Ativos</span><strong class="metric-value"><?= (int) $ativos ?></strong>
    <span class="metric-trend up"><?= (int) $comLogin ?> com acesso</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-green"></div><div>
    <span class="metric-label">Batidas no período</span><strong class="metric-value"><?= (int) $batidas ?></strong>
    <span class="metric-trend up"><?= (int) $diasComPonto ?> dia(s) com ponto</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-orange"></div><div>
    <span class="metric-label">A conferir</span><strong class="metric-value"><?= (int) $revisao ?></strong>
    <span class="metric-trend down"><?= (int) $foraCerca ?> fora da cerca</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-purple">⏱️</div><div>
    <span class="metric-label">Horas totais</span><strong class="metric-value"><?= e($horas($totMin)) ?></strong>
    <span class="metric-trend up">no período</span></div></article>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h3>Quadro de pessoal</h3><p>Situação atual</p></div>
      <a class="btn btn-ghost" href="<?= e(url('exportar.php?tipo=funcionarios')) ?>">Exportar CSV</a></div>
    <div class="status-list">
      <div><span>Ativos</span><b><?= (int) $ativos ?></b></div>
      <div><span>Afastados</span><b><?= (int) $afastados ?></b></div>
      <div><span>Desligados</span><b><?= (int) $desligados ?></b></div>
      <div><span>Com conta de acesso</span><b><?= (int) $comLogin ?></b></div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Ouvidoria e sugestões</h3><p>No período e acumulado</p></div></div>
    <div class="status-list">
      <div><span>Relatos abertos</span><b><?= (int) $ouvAberta ?></b></div>
      <div><span>Relatos no período</span><b><?= (int) $ouvTotal ?></b></div>
      <div><span>Sugestões no período</span><b><?= (int) $sugTotal ?></b></div>
      <div><span>Sugestões implementadas</span><b><?= (int) $sugImplem ?></b></div>
    </div>
    <div class="quick-actions mt">
      <a class="btn btn-ghost" href="<?= e(url('exportar.php?tipo=ouvidoria')) ?>">Ouvidoria CSV</a>
      <a class="btn btn-ghost" href="<?= e(url('exportar.php?tipo=sugestoes')) ?>">Sugestões CSV</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h3>Horas por funcionário</h3><p>Somatório do período selecionado</p></div>
    <a class="btn btn-ghost" href="<?= e(url('exportar.php?tipo=espelho&' . $qs)) ?>">Espelho CSV</a>
  </div>
  <?php if (!$porFunc): ?>
    <?= vazio('Nenhuma batida no período', 'Ajuste as datas acima.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Funcionário</th><th>Horas trabalhadas</th><th></th></tr></thead>
        <tbody>
        <?php $maxMin = max($porFunc) ?: 1; foreach ($porFunc as $fid => $min): ?>
          <tr>
            <td><b><?= e($nomes[$fid] ?? 'Funcionário') ?></b></td>
            <td class="mono"><?= e($horas($min)) ?></td>
            <td style="width:45%">
              <div class="progress"><span style="width:<?= max(3, round($min / $maxMin * 100)) ?>%"></span></div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php rodape(); ?>
