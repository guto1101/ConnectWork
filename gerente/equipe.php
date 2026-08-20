<?php
/**
 * ConnectWork — Minha equipe (gerente)
 *
 * Lista os subordinados diretos com um resumo de presença do dia. Só
 * leitura: quem cadastra e edita pessoas é o administrador. O gerente
 * acompanha, confere ponto e se comunica.
 */

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/ponto.php';

Auth::exigirNivel(['gerente']);

$fid = Auth::funcionarioId();
$hoje = date('Y-m-d');

// Subordinados diretos (sem incluir o próprio gerente nesta lista)
$equipe = Db::todos('funcionarios', 'gestor_id = :g AND status <> :d',
    ['g' => $fid, 'd' => 'desligado'], ['ordem' => 'nome']);

$deptos = [];
foreach (Db::todos('departamentos') as $d) { $deptos[(int) $d['id']] = $d['nome']; }

// Situação de ponto de cada um hoje
$situacao = [];
foreach ($equipe as $f) {
    $id = (int) $f['id'];
    $batidas = Db::todos('pontos', 'funcionario_id = :f AND data = :d',
        ['f' => $id, 'd' => $hoje], ['ordem' => 'data_hora ASC']);
    $ultimo = $batidas ? end($batidas) : null;
    $situacao[$id] = [
        'tem_entrada' => (bool) array_filter($batidas, static fn($b) => $b['tipo'] === 'entrada'),
        'ultimo'      => $ultimo,
        'minutos'     => Ponto::minutosTrabalhados($batidas),
        'pendente'    => (bool) array_filter($batidas, static fn($b) => $b['status'] === 'pendente_revisao'),
    ];
}

$horas = static fn(int $m) => sprintf('%dh%02d', intdiv($m, 60), $m % 60);

cabecalho('Minha equipe', 'equipe', 'Minha equipe',
    'Acompanhe a presença e o ponto do dia dos seus liderados.',
    '<a class="btn btn-primary" href="' . e(url('gerente/ponto.php')) . '">Espelho de ponto</a>');
?>

<?php if (!$equipe): ?>
  <div class="card">
    <?= vazio('Nenhum liderado vinculado a você',
        'Peça ao administrador para definir você como gestor responsável dos funcionários da sua equipe.') ?>
  </div>
<?php else: ?>
  <div class="cards-grid">
    <?php foreach ($equipe as $f):
      $id = (int) $f['id'];
      $st = $situacao[$id]; ?>
      <div class="card person-card">
        <div class="person-head">
          <div class="avatar"><?= e(iniciais($f['nome'])) ?></div>
          <div>
            <h4><?= e($f['nome']) ?></h4>
            <p class="muted small"><?= e($f['cargo'] ?: 'Sem cargo') ?>
              <?php if ($f['departamento_id'] && isset($deptos[(int) $f['departamento_id']])): ?>
                · <?= e($deptos[(int) $f['departamento_id']]) ?>
              <?php endif; ?>
            </p>
          </div>
        </div>

        <div class="person-status">
          <?php if ($st['pendente']): ?>
            <?= badge('Ponto a conferir', 'yellow') ?>
          <?php elseif ($st['tem_entrada']): ?>
            <?= badge('Presente hoje', 'green') ?>
          <?php else: ?>
            <?= badge('Sem registro hoje', 'gray') ?>
          <?php endif; ?>
          <?= badge_status_funcionario($f['status']) ?>
        </div>

        <div class="person-meta">
          <div><span class="muted small">Horas hoje</span><b><?= e($horas($st['minutos'])) ?></b></div>
          <div><span class="muted small">Última batida</span>
            <b><?= $st['ultimo'] ? e(Ponto::ROTULOS[$st['ultimo']['tipo']] . ' ' . date('H:i', strtotime($st['ultimo']['data_hora']))) : '—' ?></b>
          </div>
        </div>

        <div class="quick-actions mt">
          <a class="btn btn-ghost" href="<?= e(url('gerente/ponto.php?funcionario=' . $id)) ?>">Ver ponto</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php rodape(); ?>
