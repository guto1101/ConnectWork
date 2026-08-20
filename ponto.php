<?php
/**
 * ConnectWork — Controle de ponto
 *
 * Relógio, botões de registro, status do GPS e lista de batidas.
 *
 * A área permitida NÃO é definida aqui: quem define a cerca é o
 * administrador da empresa, em admin/cercas.php. Se o próprio
 * funcionário pudesse cadastrar a área onde bate ponto, a cerca não
 * controlaria nada.
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/ponto.php';

Auth::exigirLogin();

$fid = Auth::funcionarioId();
if (!$fid) {
    cabecalho('Ponto', 'ponto', 'Controle de Ponto', 'Entrada, pausa, retorno, saída e GPS.');
    echo '<div class="card">' . vazio(
        'Sua conta ainda não tem cadastro de funcionário',
        'O administrador da empresa precisa vincular seu usuário a um funcionário antes do primeiro registro.'
    ) . '</div>';
    rodape();
    exit;
}

$resumo   = Ponto::resumoDoDia($fid);
$config   = Db::um('empresa_config', 'empresa_id = :cw_emp2', ['cw_emp2' => Db::empresaId()]);
$cercas   = Db::todos('cercas_virtuais', 'ativa = 1', [], ['ordem' => 'nome']);
$exigeGps = !$config || (int) $config['exigir_gps'] === 1;
$bloqueia = !$config || (int) $config['exigir_cerca'] === 1;

$historico = Db::todos(
    'pontos',
    'funcionario_id = :f AND status <> :rej',
    ['f' => $fid, 'rej' => 'rejeitado'],
    ['ordem' => 'data_hora DESC', 'limite' => 12]
);

$statusTexto = $resumo['encerrado'] ? '● Expediente encerrado'
    : ($resumo['em_jornada'] ? '● Em expediente'
    : ($resumo['ultimo'] === 'pausa' ? '● Em pausa' : '● Fora do expediente'));

/** Bolinha colorida da lista. */
function ponto_dot(array $b): string
{
    if ($b['status'] === 'pendente_revisao') { return 'dot-yellow'; }
    switch ($b['tipo']) {
        case 'entrada': return 'dot-green';
        case 'saida':   return 'dot-red';
        case 'pausa':   return 'dot-yellow';
        default:        return 'dot-blue';
    }
}

cabecalho('Ponto', Auth::nivel() === 'gerente' ? 'meuponto' : 'ponto',
    'Controle de Ponto',
    'Entrada, pausa, retorno, saída, GPS e cerca virtual.');
?>

<div class="clock-card">
  <div>
    <span class="clock-status" id="workStatus"><?= e($statusTexto) ?></span>
    <h2 class="clock-time" id="clockTime"><?= e(date('H:i:s')) ?></h2>
    <p class="clock-date" id="clockDate">
      <?= e(data_extenso()) ?> · <?= e($resumo['formatado']) ?> trabalhadas hoje
    </p>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div>
        <h3>Registrar ponto</h3>
        <p>O horário é carimbado pelo servidor. O GPS é conferido no momento do registro.</p>
      </div>
    </div>

    <div id="retornoBatida"></div>

    <?php if ($resumo['encerrado']): ?>
      <div class="alert alert-ok">
        Jornada encerrada hoje às
        <b><?= e(date('H:i', strtotime($resumo['batidas'][count($resumo['batidas']) - 1]['data_hora']))) ?></b>.
        O próximo registro será amanhã.
      </div>
    <?php else: ?>
      <div class="punch-actions">
        <?php
        $estilo = ['entrada' => 'btn-success', 'pausa' => 'btn-ghost', 'retorno' => 'btn-primary', 'saida' => 'btn-danger'];
        foreach (Ponto::TIPOS as $tipo):
            $liberado = in_array($tipo, $resumo['permitidos'], true);
        ?>
          <button class="btn <?= $estilo[$tipo] ?>" data-bater="<?= e($tipo) ?>"
                  <?= $liberado ? '' : 'disabled title="Indisponível na sequência atual da jornada"' ?>>
            <?= e(Ponto::ROTULOS[$tipo]) ?>
          </button>
        <?php endforeach; ?>
      </div>

      <div class="gps-status" id="gpsStatus">
        <?= $exigeGps ? 'GPS: obtendo localização…' : 'GPS: opcional nesta empresa.' ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h3>Área permitida</h3>
        <p>Definida pelo administrador da empresa.</p>
      </div>
      <?php if (Auth::nivel() === 'admin'): ?>
        <a class="btn btn-ghost" href="<?= e(url('admin/cercas.php')) ?>">Editar cercas</a>
      <?php endif; ?>
    </div>

    <?php if (!$cercas): ?>
      <?= vazio('Nenhuma cerca cadastrada', 'Sem cerca configurada, o ponto é aceito de qualquer lugar e fica marcado para conferência.') ?>
    <?php else: ?>
      <ul class="punch-list">
        <?php foreach ($cercas as $c): ?>
          <li>
            <span class="dot-blue"></span>
            <div>
              <b><?= e($c['nome']) ?></b>
              <div class="muted small mono">
                <?= e(number_format((float) $c['latitude'], 5, '.', '')) ?>,
                <?= e(number_format((float) $c['longitude'], 5, '.', '')) ?>
                · raio de <?= (int) $c['raio_metros'] ?> m
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="note">
        <?= $bloqueia
            ? 'Fora dessas áreas o registro é recusado.'
            : 'Fora dessas áreas o registro é aceito, mas entra para conferência do gestor.' ?>
        Batidas com GPS impreciso (acima de <?= (int) ($config['precisao_maxima_metros'] ?? 100) ?> m)
        também vão para conferência em vez de serem descartadas.
      </p>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h3>Registros de hoje</h3><p>Sua jornada atual</p></div></div>
    <?php if (!$resumo['batidas']): ?>
      <?= vazio('Nenhum registro hoje', 'Comece pela entrada.') ?>
    <?php else: ?>
      <ul class="punch-list">
        <?php foreach ($resumo['batidas'] as $b): ?>
          <li>
            <span class="<?= ponto_dot($b) ?>"></span>
            <div style="flex:1">
              <b><?= e(Ponto::ROTULOS[$b['tipo']]) ?> — <?= e(date('H:i:s', strtotime($b['data_hora']))) ?></b>
              <div class="muted small">
                <?php if ($b['latitude'] !== null): ?>
                  <span class="mono"><?= e(number_format((float) $b['latitude'], 5, '.', '')) ?>,
                  <?= e(number_format((float) $b['longitude'], 5, '.', '')) ?></span>
                  <?php if ($b['precisao_gps'] !== null): ?>
                    · ±<?= e(number_format((float) $b['precisao_gps'], 0, ',', '.')) ?> m
                  <?php endif; ?>
                <?php else: ?>
                  Sem coordenadas
                <?php endif; ?>
              </div>
              <?php if ($b['status'] === 'pendente_revisao'): ?>
                <?= badge('Em conferência', 'yellow') ?>
              <?php elseif ($b['dentro_cerca'] === null): ?>
                <?= badge('Sem cerca', 'gray') ?>
              <?php elseif ((int) $b['dentro_cerca'] === 1): ?>
                <?= badge('Dentro da área', 'green') ?>
              <?php else: ?>
                <?= badge(number_format((float) $b['distancia_metros'], 0, ',', '.') . ' m fora', 'red') ?>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h3>Histórico recente</h3><p>Suas últimas 12 batidas</p></div>
      <a class="btn btn-ghost" href="<?= e(url('exportar.php?tipo=meu_ponto')) ?>">Exportar CSV</a>
    </div>
    <?php if (!$historico): ?>
      <?= vazio('Sem histórico ainda') ?>
    <?php else: ?>
      <ul class="punch-list">
        <?php foreach ($historico as $b): ?>
          <li>
            <span class="<?= ponto_dot($b) ?>"></span>
            <div>
              <b><?= e(Ponto::ROTULOS[$b['tipo']]) ?></b>
              <div class="muted mono small"><?= e(data_br($b['data_hora'], true)) ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<script>
  window.CW_PONTO = {
    endpoint: <?= json_encode(url('api/ponto.php')) ?>,
    exigeGps: <?= $exigeGps ? 'true' : 'false' ?>
  };
</script>

<?php rodape(); ?>
