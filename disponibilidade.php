<?php
/**
 * ConnectWork — Disponibilidade para horas extras
 * Calendário mensal e marcação semanal.
 */

require_once __DIR__ . '/includes/layout.php';

Auth::exigirLogin();
$fid = Auth::funcionarioId();

if (!$fid) {
    cabecalho('Disponibilidade', 'disponibilidade', 'Disponibilidade', 'Calendário de horas extras.');
    echo '<div class="card">' . vazio('Sua conta ainda não tem cadastro de funcionário') . '</div>';
    rodape();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'alternar') {
    csrf_exigir();
    $data = entrada('data');

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $atual = Db::um('disponibilidade',
            'funcionario_id = :f AND data = :d AND periodo = :p',
            ['f' => $fid, 'd' => $data, 'p' => 'integral']);

        if ($atual) {
            if ((int) $atual['disponivel'] === 1) {
                // Retirar uma solicitação cancela a disponibilidade; ela não
                // pode permanecer aprovada depois de ser desmarcada.
                Db::atualizar('disponibilidade', (int) $atual['id'], [
                    'disponivel' => 0,
                    'status' => 'recusada',
                    'decidido_por_usuario_id' => null,
                    'decidido_em' => date('Y-m-d H:i:s'),
                    'motivo_decisao' => 'Disponibilidade cancelada pelo funcionário.',
                ]);
            } else {
                Db::excluir('disponibilidade', (int) $atual['id']);
            }
        } else {
            Db::inserir('disponibilidade', [
                'funcionario_id' => $fid,
                'data'           => $data,
                'periodo'        => 'integral',
                'disponivel'     => 1,
                'status'         => 'pendente',
            ]);
            auditar('disponibilidade_solicitada', 'disponibilidade', null, $data);
        }
    }
    voltar_para('disponibilidade.php?mes=' . entrada('mes'));
}

// Mês exibido
$mes = entrada('mes', 'get') ?: date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) { $mes = date('Y-m'); }
$primeiro = strtotime($mes . '-01');
$diasNoMes = (int) date('t', $primeiro);
$diaSemanaInicio = (int) date('w', $primeiro);

$marcados = [];
foreach (Db::todos('disponibilidade',
    'funcionario_id = :f AND data BETWEEN :i AND :fim',
    ['f' => $fid, 'i' => date('Y-m-01', $primeiro), 'fim' => date('Y-m-t', $primeiro)]) as $d) {
    $marcados[$d['data']] = [
        'disponivel' => (int) $d['disponivel'],
        'status' => $d['status'],
    ];
}

$mesesPt = ['janeiro','fevereiro','março','abril','maio','junho',
            'julho','agosto','setembro','outubro','novembro','dezembro'];
$rotuloMes = $mesesPt[(int) date('n', $primeiro) - 1] . ' de ' . date('Y', $primeiro);

$anterior = date('Y-m', strtotime('-1 month', $primeiro));
$proximo  = date('Y-m', strtotime('+1 month', $primeiro));

// Próximos 7 dias, para a coluna da direita
$semana = [];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("+$i days"));
    $semana[$d] = $marcados[$d] ?? null;
}

$total = Db::contar('disponibilidade',
    'funcionario_id = :f AND disponivel = 1 AND status = :status AND data >= :hoje',
    ['f' => $fid, 'status' => 'aprovada', 'hoje' => date('Y-m-d')]);

cabecalho('Disponibilidade', 'disponibilidade', 'Disponibilidade',
    'Informe em quais dias você pode fazer hora extra. Seu gestor enxerga essa marcação.');
?>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div><h3>Calendário</h3><p><?= e($rotuloMes) ?></p></div>
      <div class="cal-nav">
        <a class="icon-btn" href="?mes=<?= e($anterior) ?>" title="Mês anterior">‹</a>
        <a class="icon-btn" href="?mes=<?= e($proximo) ?>" title="Próximo mês">›</a>
      </div>
    </div>

    <div class="calendar">
      <?php foreach (['D','S','T','Q','Q','S','S'] as $dow): ?>
        <div class="cal-dow"><?= $dow ?></div>
      <?php endforeach; ?>

      <?php for ($i = 0; $i < $diaSemanaInicio; $i++): ?>
        <div class="cal-day muted"></div>
      <?php endfor; ?>

      <?php for ($dia = 1; $dia <= $diasNoMes; $dia++):
          $data = date('Y-m-', $primeiro) . str_pad((string) $dia, 2, '0', STR_PAD_LEFT);
          $classe = 'cal-day';
          if ($data === date('Y-m-d')) { $classe .= ' today'; }
          if (isset($marcados[$data])) {
              $classe .= $marcados[$data]['disponivel'] === 1 ? ' available' : ' unavailable';
          }
      ?>
        <form method="post" style="display:contents">
          <?= csrf_campo() ?>
          <input type="hidden" name="acao" value="alternar">
          <input type="hidden" name="data" value="<?= e($data) ?>">
          <input type="hidden" name="mes" value="<?= e($mes) ?>">
          <button class="<?= $classe ?>" type="submit" title="Alternar disponibilidade"><?= $dia ?></button>
        </form>
      <?php endfor; ?>
    </div>

    <p class="note">
      Clique em um dia para solicitar disponibilidade. A solicitação fica
      aguardando aprovação administrativa antes de ser considerada para hora extra.
    </p>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h3>Próximos 7 dias</h3><p>Clique para alternar</p></div>
      <?= badge($total . ' dia(s) disponível(is)', 'green') ?>
    </div>

    <ul class="week-availability">
      <?php foreach ($semana as $data => $estado):
          $dias = ['domingo','segunda','terça','quarta','quinta','sexta','sábado']; ?>
        <li class="<?= $estado === 1 ? 'on' : '' ?>">
          <span>
            <b><?= e(ucfirst($dias[(int) date('w', strtotime($data))])) ?></b>
            <span class="muted small mono"> <?= e(date('d/m', strtotime($data))) ?></span>
          </span>
          <form method="post">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="alternar">
            <input type="hidden" name="data" value="<?= e($data) ?>">
            <input type="hidden" name="mes" value="<?= e($mes) ?>">
            <button class="status" type="submit">
              <?= $estado === null
                  ? 'Sem marcação'
                  : ($estado['disponivel'] === 0
                      ? 'Indisponível'
                      : ($estado['status'] === 'aprovada'
                          ? 'Disponível aprovada'
                          : ($estado['status'] === 'recusada' ? 'Disponibilidade recusada' : 'Aguardando aprovação'))) ?>
            </button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>

    <form method="post" class="mt">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="alternar">
      <input type="hidden" name="data" value="<?= e(date('Y-m-d')) ?>">
      <input type="hidden" name="mes" value="<?= e($mes) ?>">
      <button class="btn btn-primary full" type="submit">Alternar disponibilidade de hoje</button>
    </form>
  </div>
</div>

<?php rodape(); ?>
