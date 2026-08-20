<?php
/**
 * ConnectWork — Vagas internas (funcionário)
 * Lista as oportunidades abertas e registra a candidatura.
 */

require_once __DIR__ . '/includes/layout.php';

Auth::exigirLogin();
$fid = Auth::funcionarioId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'candidatar') {
    csrf_exigir();
    $vagaId = entrada_int('vaga_id');
    $vaga   = Db::porId('vagas', $vagaId);

    if (!$fid) {
        flash('erro', 'Sua conta ainda não tem cadastro de funcionário.');
    } elseif (!$vaga || $vaga['status'] !== 'aberta') {
        flash('erro', 'Esta vaga não está mais aberta.');
    } elseif (Db::contar('candidaturas', 'vaga_id = :v AND funcionario_id = :f', ['v' => $vagaId, 'f' => $fid]) > 0) {
        flash('info', 'Você já se candidatou a esta vaga.');
    } else {
        Db::inserir('candidaturas', [
            'vaga_id'        => $vagaId,
            'funcionario_id' => $fid,
            'carta'          => entrada('carta') ?: null,
            'status'         => 'inscrita',
        ]);
        auditar('candidatura', 'vagas', $vagaId);
        flash('ok', 'Candidatura registrada. O RH avisa sobre os próximos passos.');
    }
    voltar_para('vagas.php');
}

$vagas = Db::todos('vagas', 'status = :s', ['s' => 'aberta'], ['ordem' => 'publicada_em DESC']);

$minhas = [];
if ($fid) {
    foreach (Db::todos('candidaturas', 'funcionario_id = :f', ['f' => $fid]) as $c) {
        $minhas[(int) $c['vaga_id']] = $c;
    }
}

$deptos = [];
foreach (Db::todos('departamentos') as $d) { $deptos[(int) $d['id']] = $d['nome']; }

$corStatus = ['inscrita' => 'blue', 'triagem' => 'yellow', 'entrevista' => 'purple',
              'aprovada' => 'green', 'reprovada' => 'red'];

cabecalho('Vagas', 'vagas', 'Vagas Internas',
    'Oportunidades abertas para quem já é da casa.');
?>

<?php if (!$vagas): ?>
  <div class="card">
    <?= vazio('Nenhuma vaga aberta no momento', 'Assim que o RH publicar uma oportunidade, ela aparece aqui.') ?>
  </div>
<?php else: ?>
  <div class="grid-3">
    <?php foreach ($vagas as $v):
        $jaCandidatou = isset($minhas[(int) $v['id']]);
        $cand = $minhas[(int) $v['id']] ?? null;
    ?>
      <article class="item-card">
        <div class="item-head">
          <?= badge(ucfirst($v['tipo']), 'blue') ?>
          <?= badge(ucfirst($v['modalidade']), 'purple') ?>
          <?php if ($v['departamento_id'] && isset($deptos[(int) $v['departamento_id']])): ?>
            <?= badge($deptos[(int) $v['departamento_id']], 'gray') ?>
          <?php endif; ?>
        </div>

        <h4><?= e($v['titulo']) ?></h4>
        <p><?= nl2br(e(mb_strimwidth($v['descricao'], 0, 320, '…'))) ?></p>

        <?php if ($v['requisitos']): ?>
          <p class="small muted"><b>Requisitos:</b> <?= e(mb_strimwidth($v['requisitos'], 0, 180, '…')) ?></p>
        <?php endif; ?>

        <div class="item-meta">
          <span><?= $v['salario'] !== null ? 'R$ ' . e(number_format((float) $v['salario'], 2, ',', '.')) : 'Salário a combinar' ?></span>
          <span><?= (int) $v['vagas_abertas'] ?> vaga(s)</span>
        </div>

        <div class="item-actions">
          <?php if ($jaCandidatou): ?>
            <?= badge('Você se candidatou · ' . ucfirst($cand['status']), $corStatus[$cand['status']] ?? 'gray') ?>
          <?php else: ?>
            <form method="post" style="width:100%">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="candidatar">
              <input type="hidden" name="vaga_id" value="<?= (int) $v['id'] ?>">
              <input type="text" name="carta" placeholder="Por que você quer esta vaga? (opcional)" style="margin-bottom:8px">
              <button class="btn btn-success full" type="submit">Candidatar-se</button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($minhas): ?>
<div class="card mt">
  <div class="card-head"><div><h3>Minhas candidaturas</h3><p>Andamento de cada processo</p></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Vaga</th><th>Enviada em</th><th>Situação</th></tr></thead>
      <tbody>
      <?php foreach ($minhas as $vagaId => $c):
          $v = Db::porId('vagas', (int) $vagaId); ?>
        <tr>
          <td><b><?= e($v['titulo'] ?? 'Vaga removida') ?></b></td>
          <td class="mono"><?= e(data_br($c['criado_em'])) ?></td>
          <td><?= badge(ucfirst($c['status']), $corStatus[$c['status']] ?? 'gray') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php rodape(); ?>
