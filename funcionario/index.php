<?php
/**
 * ConnectWork — Dashboard do funcionário
 */

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/ponto.php';

Auth::exigirNivel(['funcionario', 'gerente', 'admin']);

$fid  = Auth::funcionarioId();
$func = Auth::funcionario();

$resumo = $fid ? Ponto::resumoDoDia($fid) : null;

$naoLidas   = $fid ? Db::contar('mensagens', 'destinatario_id = :f AND lida_em IS NULL', ['f' => $fid]) : 0;
$vagas      = Db::contar('vagas', 'status = :s', ['s' => 'aberta']);
$candidatou = $fid ? Db::contar('candidaturas', 'funcionario_id = :f', ['f' => $fid]) : 0;
$sugestoes  = $fid ? Db::contar('sugestoes', 'funcionario_id = :f', ['f' => $fid]) : 0;

// Horas do mês corrente
$doMes = $fid ? Db::todos(
    'pontos',
    'funcionario_id = :f AND data >= :ini AND status <> :rej',
    ['f' => $fid, 'ini' => date('Y-m-01'), 'rej' => 'rejeitado'],
    ['ordem' => 'data_hora ASC']
) : [];

$porDia = [];
foreach ($doMes as $b) { $porDia[$b['data']][] = $b; }
$minutosMes = 0;
foreach ($porDia as $d) { $minutosMes += Ponto::minutosTrabalhados($d); }

// Últimos 7 dias para o gráfico de barras
$serie = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $serie[$dia] = isset($porDia[$dia]) ? Ponto::minutosTrabalhados($porDia[$dia]) : 0;
}
$maxSerie = max(1, max($serie));

$comunicados = Db::todos('comunicados', '', [], ['ordem' => 'publicado_em DESC', 'limite' => 5]);

cabecalho('Dashboard', 'painel',
    'Bem-vindo, ' . e(primeiro_nome(Auth::nome())) . '',
    e($func['cargo'] ?? 'Colaborador') . ' · matrícula ' . e($func['matricula'] ?? '—')
        . ' · ' . e(Auth::empresaNome()),
    '<a class="btn btn-primary" href="' . e(url('ponto.php')) . '">Bater ponto</a>');
?>

<?php if (!$fid): ?>
  <div class="alert alert-erro">
    Sua conta ainda não está vinculada a um cadastro de funcionário. Procure o administrador da empresa.
  </div>
<?php endif; ?>

<div class="metrics-grid">
  <article class="metric-card">
    <div class="metric-icon icon-blue"></div>
    <div>
      <span class="metric-label">Trabalhado hoje</span>
      <strong class="metric-value"><?= e($resumo ? $resumo['formatado'] : '00h00') ?></strong>
      <span class="metric-trend <?= ($resumo && $resumo['em_jornada']) ? 'up' : 'down' ?>">
        <?php if (!$resumo || !$resumo['batidas']): ?>Nenhum registro ainda
        <?php elseif ($resumo['encerrado']): ?>Jornada encerrada
        <?php elseif ($resumo['em_jornada']): ?>Em expediente
        <?php else: ?>Em pausa<?php endif; ?>
      </span>
    </div>
  </article>

  <article class="metric-card">
    <div class="metric-icon icon-green"></div>
    <div>
      <span class="metric-label">Trabalhado no mês</span>
      <strong class="metric-value"><?= e(Ponto::formatarMinutos($minutosMes)) ?></strong>
      <span class="metric-trend up"><?= count($porDia) ?> dia(s) com registro</span>
    </div>
  </article>

  <article class="metric-card">
    <div class="metric-icon icon-purple"></div>
    <div>
      <span class="metric-label">Mensagens não lidas</span>
      <strong class="metric-value"><?= (int) $naoLidas ?></strong>
      <span class="metric-trend up">Comunicação interna</span>
    </div>
  </article>

  <article class="metric-card">
    <div class="metric-icon icon-orange"></div>
    <div>
      <span class="metric-label">Vagas abertas</span>
      <strong class="metric-value"><?= (int) $vagas ?></strong>
      <span class="metric-trend up"><?= (int) $candidatou ?> candidatura(s) sua(s)</span>
    </div>
  </article>
</div>

<div class="charts-grid">
  <div class="card">
    <div class="card-head">
      <div><h3>Suas horas na semana</h3><p>Tempo trabalhado nos últimos 7 dias</p></div>
      <span class="pill">Últimos 7 dias</span>
    </div>
    <div class="bars">
      <?php foreach ($serie as $dia => $min): ?>
        <div class="bar-col">
          <div class="bar" style="height:<?= max(5, round($min / $maxSerie * 170)) ?>px"
               title="<?= e(Ponto::formatarMinutos($min)) ?>"></div>
          <span class="bar-label"><?= e(date('d/m', strtotime($dia))) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Sua situação</h3><p>Resumo pessoal</p></div></div>
    <div class="status-list">
      <div><span>Situação cadastral</span><b><?= badge_status_funcionario($func['status'] ?? 'ativo') ?></b></div>
      <div><span>Admissão</span><b><?= e(data_br($func['data_admissao'] ?? null)) ?></b></div>
      <div><span>Sugestões enviadas</span><b><?= (int) $sugestoes ?></b></div>
      <div><span>Candidaturas</span><b><?= (int) $candidatou ?></b></div>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div><h3>Registros de hoje</h3><p>Sua jornada atual</p></div>
      <a class="btn btn-ghost" href="<?= e(url('ponto.php')) ?>">Abrir ponto</a>
    </div>
    <?php if (!$resumo || !$resumo['batidas']): ?>
      <?= vazio('Você ainda não registrou entrada hoje', 'Use o botão "Bater ponto" acima.') ?>
    <?php else: ?>
      <ul class="punch-list">
        <?php foreach ($resumo['batidas'] as $b): ?>
          <li>
            <span class="<?= $b['tipo'] === 'entrada' ? 'dot-green' : ($b['tipo'] === 'saida' ? 'dot-red' : 'dot-blue') ?>"></span>
            <div>
              <b><?= e(Ponto::ROTULOS[$b['tipo']]) ?> — <?= e(date('H:i', strtotime($b['data_hora']))) ?></b>
              <div class="muted small">
                <?php if ($b['status'] === 'pendente_revisao'): ?>Em conferência
                <?php elseif ((int) $b['dentro_cerca'] === 1): ?>Dentro da área permitida
                <?php elseif ($b['dentro_cerca'] !== null): ?>Fora da área permitida
                <?php else: ?>Sem cerca configurada<?php endif; ?>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Comunicados</h3><p>Avisos da empresa</p></div></div>
    <?php if (!$comunicados): ?>
      <?= vazio('Nenhum comunicado publicado') ?>
    <?php else: ?>
      <ul class="activity">
        <?php foreach ($comunicados as $c): ?>
          <li>
            <span class="dot-blue"></span>
            <div>
              <strong><?= e($c['titulo']) ?></strong>
              <span><?= e(mb_strimwidth(strip_tags($c['corpo']), 0, 130, '…')) ?></span>
              <span class="muted small mono"><?= e(data_br($c['publicado_em'], true)) ?></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h3>Ações rápidas</h3><p>Atalhos do sistema</p></div></div>
  <div class="quick-actions">
    <a class="btn btn-success" href="<?= e(url('ponto.php')) ?>">Bater ponto</a>
    <a class="btn btn-primary" href="<?= e(url('vagas.php')) ?>">Ver vagas internas</a>
    <a class="btn btn-primary" href="<?= e(url('sugestoes.php')) ?>">Enviar sugestão</a>
    <a class="btn btn-ghost" href="<?= e(url('ouvidoria.php')) ?>">Abrir ouvidoria</a>
    <a class="btn btn-ghost" href="<?= e(url('ia.php')) ?>">Perguntar ao assistente</a>
  </div>
</div>

<?php rodape(); ?>
