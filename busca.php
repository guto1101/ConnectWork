<?php
/**
 * ConnectWork — Busca geral
 *
 * A busca respeita o perfil de quem procura: funcionário encontra apenas
 * o que já poderia abrir pelo menu. Relato de ouvidoria alheio, por
 * exemplo, nunca aparece para quem não é administrador.
 */

require_once __DIR__ . '/includes/layout.php';

Auth::exigirLogin();

$q     = entrada('q', 'get');
$fid   = Auth::funcionarioId();
$admin = in_array(Auth::nivel(), ['admin', 'master'], true);

$funcionarios = $vagas = $sugestoes = $relatos = $comunicados = [];

if (mb_strlen($q) >= 2) {
    $como = '%' . $q . '%';

    $funcionarios = Db::todos('funcionarios',
        '(nome LIKE :q OR cargo LIKE :q OR matricula LIKE :q) AND status <> :d',
        ['q' => $como, 'd' => 'desligado'],
        ['ordem' => 'nome', 'limite' => 12]);

    $vagas = $admin
        ? Db::todos('vagas', 'titulo LIKE :q OR descricao LIKE :q', ['q' => $como], ['ordem' => 'criado_em DESC', 'limite' => 10])
        : Db::todos('vagas', '(titulo LIKE :q OR descricao LIKE :q) AND status = :s',
            ['q' => $como, 's' => 'aberta'], ['ordem' => 'publicada_em DESC', 'limite' => 10]);

    $sugestoes = $admin
        ? Db::todos('sugestoes', 'titulo LIKE :q OR descricao LIKE :q', ['q' => $como], ['ordem' => 'criado_em DESC', 'limite' => 10])
        : ($fid ? Db::todos('sugestoes', '(titulo LIKE :q OR descricao LIKE :q) AND funcionario_id = :f',
            ['q' => $como, 'f' => $fid], ['ordem' => 'criado_em DESC', 'limite' => 10]) : []);

    $relatos = $admin
        ? Db::todos('ouvidoria', 'assunto LIKE :q OR descricao LIKE :q', ['q' => $como], ['ordem' => 'criado_em DESC', 'limite' => 10])
        : ($fid ? Db::todos('ouvidoria', '(assunto LIKE :q) AND funcionario_id = :f AND anonimo = 0',
            ['q' => $como, 'f' => $fid], ['ordem' => 'criado_em DESC', 'limite' => 10]) : []);

    $comunicados = Db::todos('comunicados', 'titulo LIKE :q OR corpo LIKE :q', ['q' => $como],
        ['ordem' => 'publicado_em DESC', 'limite' => 10]);
}

$total = count($funcionarios) + count($vagas) + count($sugestoes) + count($relatos) + count($comunicados);

cabecalho('Busca', '', 'Busca',
    mb_strlen($q) >= 2 ? $total . ' resultado(s) para "' . e($q) . '"' : 'Digite ao menos 2 caracteres.');
?>

<div class="card">
  <form method="get" class="form-grid compact">
    <label class="wide">O que você procura?
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nome, cargo, vaga, assunto..." autofocus>
    </label>
    <button class="btn btn-primary" type="submit">Buscar</button>
  </form>
</div>

<?php if (mb_strlen($q) >= 2 && $total === 0): ?>
  <div class="card"><?= vazio('Nada encontrado', 'Tente outro termo ou parte do nome.') ?></div>
<?php endif; ?>

<?php if ($funcionarios): ?>
<div class="card">
  <div class="card-head"><div><h3>Pessoas</h3><p><?= count($funcionarios) ?> encontrada(s)</p></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Nome</th><th>Cargo</th><th>Matrícula</th><th>Situação</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($funcionarios as $f): ?>
        <tr>
          <td><b><?= e($f['nome']) ?></b></td>
          <td class="muted"><?= e($f['cargo'] ?: '—') ?></td>
          <td class="mono"><?= e($f['matricula']) ?></td>
          <td><?= badge_status_funcionario($f['status']) ?></td>
          <td class="right">
            <?php if ((int) $f['id'] !== $fid): ?>
              <a class="btn btn-ghost" href="<?= e(url('mensagens.php?com=' . (int) $f['id'])) ?>">Mensagem</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($vagas): ?>
<div class="card">
  <div class="card-head"><div><h3>Vagas</h3><p><?= count($vagas) ?> encontrada(s)</p></div></div>
  <ul class="activity">
    <?php foreach ($vagas as $v): ?>
      <li>
        <span class="dot-blue"></span>
        <div style="flex:1">
          <strong><a href="<?= e(url($admin ? 'admin/vagas.php' : 'vagas.php')) ?>"><?= e($v['titulo']) ?></a></strong>
          <span><?= e(mb_strimwidth(strip_tags($v['descricao']), 0, 110, '…')) ?></span>
        </div>
        <?= badge(ucfirst($v['status']), $v['status'] === 'aberta' ? 'green' : 'gray') ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($sugestoes): ?>
<div class="card">
  <div class="card-head"><div><h3>Sugestões</h3><p><?= count($sugestoes) ?> encontrada(s)</p></div></div>
  <ul class="activity">
    <?php foreach ($sugestoes as $s): ?>
      <li>
        <span class="dot-yellow"></span>
        <div style="flex:1">
          <strong><a href="<?= e(url($admin ? 'admin/sugestoes.php' : 'sugestoes.php')) ?>"><?= e($s['titulo']) ?></a></strong>
          <span><?= e(data_br($s['criado_em'])) ?> · <?= e($s['area'] ?: 'Geral') ?></span>
        </div>
        <?= badge(ucfirst(str_replace('_', ' ', $s['status'])), 'gray') ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($relatos): ?>
<div class="card">
  <div class="card-head"><div><h3>Ouvidoria</h3><p><?= count($relatos) ?> encontrado(s)</p></div></div>
  <ul class="activity">
    <?php foreach ($relatos as $r): ?>
      <li>
        <span class="dot-red"></span>
        <div style="flex:1">
          <strong><a href="<?= e(url($admin ? 'admin/ouvidoria.php' : 'ouvidoria.php')) ?>"><?= e($r['assunto']) ?></a></strong>
          <span><?= e(ucfirst($r['categoria'])) ?> · <?= e(data_br($r['criado_em'])) ?></span>
        </div>
        <?= badge_status_ouvidoria($r['status']) ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($comunicados): ?>
<div class="card">
  <div class="card-head"><div><h3>Comunicados</h3><p><?= count($comunicados) ?> encontrado(s)</p></div></div>
  <ul class="activity">
    <?php foreach ($comunicados as $c): ?>
      <li>
        <span class="dot-green"></span>
        <div style="flex:1">
          <strong><a href="<?= e(url('comunicados.php')) ?>"><?= e($c['titulo']) ?></a></strong>
          <span><?= e(mb_strimwidth(strip_tags($c['corpo']), 0, 110, '…')) ?></span>
        </div>
        <span class="muted small mono"><?= e(data_br($c['publicado_em'])) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php rodape(); ?>
