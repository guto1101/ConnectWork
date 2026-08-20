<?php
/**
 * ConnectWork — Dashboard do administrador da empresa
 *
 * Visão geral da operação: quadro de pessoal, ponto do dia, ouvidoria,
 * sugestões e vagas. Todos os números vêm da camada Db, então já chegam
 * restritos à empresa do administrador.
 */

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/ponto.php';

Auth::exigirNivel(['admin']);

$hoje = date('Y-m-d');

$ativos     = Db::contar('funcionarios', 'status = :s', ['s' => 'ativo']);
$afastados  = Db::contar('funcionarios', 'status = :s', ['s' => 'afastado']);
$deptos     = Db::contar('departamentos', 'ativo = 1');
$presentes  = (int) Db::valor('pontos', 'COUNT(DISTINCT funcionario_id) AS t',
                  'data = :d AND tipo = :t', ['d' => $hoje, 't' => 'entrada']);
$emRevisao  = Db::contar('pontos', 'status = :s', ['s' => 'pendente_revisao']);
$foraCerca  = Db::contar('pontos', 'dentro_cerca = 0 AND data >= :i',
                  ['i' => date('Y-m-d', strtotime('-7 days'))]);
$relatos    = Db::contar('ouvidoria', 'status IN (:a, :b)', ['a' => 'aberta', 'b' => 'em_analise']);
$sugNovas   = Db::contar('sugestoes', 'status = :s', ['s' => 'recebida']);
$vagas      = Db::contar('vagas', 'status = :s', ['s' => 'aberta']);
$candidatos = Db::contar('candidaturas', 'status = :s', ['s' => 'inscrita']);
$cercas     = Db::contar('cercas_virtuais', 'ativa = 1');

$semRegistro = max(0, $ativos - $presentes);

// Presença dos últimos 7 dias (pessoas distintas com entrada por dia)
$serie = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $serie[$dia] = (int) Db::valor('pontos', 'COUNT(DISTINCT funcionario_id) AS t',
        'data = :d AND tipo = :t', ['d' => $dia, 't' => 'entrada']);
}
$maxSerie = max(1, max($serie));

$ultimosRelatos = Db::todos('ouvidoria', 'status IN (:a, :b)',
    ['a' => 'aberta', 'b' => 'em_analise'], ['ordem' => 'criado_em DESC', 'limite' => 5]);

$ultimasBatidas = Db::todos('pontos', 'data = :d', ['d' => $hoje],
    ['ordem' => 'data_hora DESC', 'limite' => 8]);

$nomes = [];
foreach (Db::todos('funcionarios', '', [], ['colunas' => 'id, nome']) as $f) {
    $nomes[(int) $f['id']] = $f['nome'];
}

cabecalho('Dashboard', 'painel',
    'Painel de ' . e(Auth::empresaNome()),
    'Visão geral da operação em ' . e(data_extenso()),
    '<a class="btn btn-primary" href="' . e(url('admin/funcionarios.php')) . '">Cadastrar funcionário</a>');
?>

<div class="metrics-grid">
  <article class="metric-card">
    <div class="metric-icon icon-blue"></div>
    <div>
      <span class="metric-label">Funcionários ativos</span>
      <strong class="metric-value"><?= (int) $ativos ?></strong>
      <span class="metric-trend up"><?= (int) $afastados ?> afastado(s) · <?= (int) $deptos ?> setor(es)</span>
    </div>
  </article>

  <article class="metric-card">
    <div class="metric-icon icon-green"></div>
    <div>
      <span class="metric-label">Presentes hoje</span>
      <strong class="metric-value"><?= (int) $presentes ?></strong>
      <span class="metric-trend <?= $semRegistro > 0 ? 'down' : 'up' ?>"><?= (int) $semRegistro ?> sem registro</span>
    </div>
  </article>

  <article class="metric-card">
    <div class="metric-icon icon-orange"></div>
    <div>
      <span class="metric-label">Ponto a conferir</span>
      <strong class="metric-value"><?= (int) $emRevisao ?></strong>
      <span class="metric-trend down"><?= (int) $foraCerca ?> fora da cerca (7 dias)</span>
    </div>
  </article>

  <article class="metric-card">
    <div class="metric-icon icon-purple"></div>
    <div>
      <span class="metric-label">Ouvidoria aberta</span>
      <strong class="metric-value"><?= (int) $relatos ?></strong>
      <span class="metric-trend down">relatos aguardando</span>
    </div>
  </article>
</div>

<div class="charts-grid">
  <div class="card">
    <div class="card-head">
      <div><h3>Presença na semana</h3><p>Pessoas distintas com entrada por dia</p></div>
      <span class="pill">Últimos 7 dias</span>
    </div>
    <div class="bars">
      <?php foreach ($serie as $dia => $qtd): ?>
        <div class="bar-col">
          <div class="bar" style="height:<?= max(5, round($qtd / $maxSerie * 170)) ?>px"
               title="<?= (int) $qtd ?> presente(s)"></div>
          <span class="bar-label"><?= e(date('d/m', strtotime($dia))) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Pendências</h3><p>O que precisa de atenção</p></div></div>
    <div class="status-list">
      <div><span>Ponto a conferir</span><b><?= (int) $emRevisao ?></b></div>
      <div><span>Relatos na ouvidoria</span><b><?= (int) $relatos ?></b></div>
      <div><span>Sugestões novas</span><b><?= (int) $sugNovas ?></b></div>
      <div><span>Candidatos a triar</span><b><?= (int) $candidatos ?></b></div>
      <div><span>Cercas ativas</span><b><?= (int) $cercas ?></b></div>
    </div>
    <div class="quick-actions mt">
      <a class="btn btn-ghost" href="<?= e(url('admin/ponto.php?filtro=revisao')) ?>">Conferir ponto</a>
      <a class="btn btn-ghost" href="<?= e(url('admin/ouvidoria.php')) ?>">Ver ouvidoria</a>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div><h3>Últimas batidas de hoje</h3><p>Registros recentes de ponto</p></div>
      <a class="btn btn-ghost" href="<?= e(url('admin/ponto.php')) ?>">Espelho completo</a>
    </div>
    <?php if (!$ultimasBatidas): ?>
      <?= vazio('Nenhuma batida registrada hoje ainda') ?>
    <?php else: ?>
      <ul class="punch-list">
        <?php foreach ($ultimasBatidas as $b): ?>
          <li>
            <span class="<?= $b['tipo'] === 'entrada' ? 'dot-green' : ($b['tipo'] === 'saida' ? 'dot-red' : 'dot-blue') ?>"></span>
            <div style="flex:1">
              <b><?= e($nomes[(int) $b['funcionario_id']] ?? 'Funcionário') ?></b>
              <div class="muted small">
                <?= e(Ponto::ROTULOS[$b['tipo']]) ?> · <?= e(date('H:i', strtotime($b['data_hora']))) ?>
              </div>
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

  <div class="card">
    <div class="card-head">
      <div><h3>Ouvidoria em aberto</h3><p>Relatos aguardando apuração</p></div>
      <a class="btn btn-ghost" href="<?= e(url('admin/ouvidoria.php')) ?>">Abrir</a>
    </div>
    <?php if (!$ultimosRelatos): ?>
      <?= vazio('Nenhum relato em aberto', 'Tudo em dia por aqui.') ?>
    <?php else: ?>
      <ul class="activity">
        <?php foreach ($ultimosRelatos as $r): ?>
          <li>
            <span class="dot-red"></span>
            <div style="flex:1">
              <strong><?= e($r['assunto']) ?></strong>
              <span><?= e(ucfirst($r['categoria'])) ?> · <?= e(data_br($r['criado_em'])) ?>
                · <?= (int) $r['anonimo'] === 1 ? 'anônimo' : 'identificado' ?></span>
            </div>
            <?= badge_status_ouvidoria($r['status']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h3>Ações rápidas</h3><p>Atalhos de administração</p></div></div>
  <div class="quick-actions">
    <a class="btn btn-primary" href="<?= e(url('admin/funcionarios.php')) ?>">Funcionários</a>
    <a class="btn btn-primary" href="<?= e(url('admin/cercas.php')) ?>">Cercas virtuais</a>
    <a class="btn btn-ghost" href="<?= e(url('admin/vagas.php')) ?>">Publicar vaga</a>
    <a class="btn btn-ghost" href="<?= e(url('admin/relatorios.php')) ?>">Relatórios</a>
    <a class="btn btn-ghost" href="<?= e(url('comunicados.php')) ?>">Novo comunicado</a>
  </div>
</div>

<?php rodape(); ?>
