<?php
/**
 * ConnectWork — Comunicados
 * Funcionário lê; gerente e administrador publicam.
 */

require_once __DIR__ . '/includes/layout.php';

Auth::exigirLogin();
$fid  = Auth::funcionarioId();
$pode = in_array(Auth::nivel(), ['gerente', 'admin', 'master'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'publicar') {
    csrf_exigir();
    if (!$pode) {
        flash('erro', 'Seu perfil não publica comunicados.');
    } elseif (!$fid) {
        flash('erro', 'Sua conta precisa estar vinculada a um funcionário para publicar.');
    } else {
        $titulo = entrada('titulo');
        $corpo  = entrada('corpo');
        if ($titulo === '' || $corpo === '') {
            flash('erro', 'Preencha o título e o texto do comunicado.');
        } else {
            $alcance = entrada('alcance');
            $alcance = in_array($alcance, ['empresa', 'departamento', 'equipe'], true) ? $alcance : 'empresa';

            $id = Db::inserir('comunicados', [
                'autor_id'        => $fid,
                'titulo'          => mb_substr($titulo, 0, 160),
                'corpo'           => $corpo,
                'alcance'         => $alcance,
                'departamento_id' => entrada_int('departamento_id') ?: null,
                'gestor_id'       => $alcance === 'equipe' ? $fid : null,
                'fixado'          => isset($_POST['fixado']) ? 1 : 0,
            ]);
            auditar('comunicado_publicado', 'comunicados', $id);
            flash('ok', 'Comunicado publicado.');
        }
    }
    voltar_para('comunicados.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'lido') {
    csrf_exigir();
    $cid = entrada_int('comunicado_id');
    if ($fid && Db::porId('comunicados', $cid)) {
        try {
            Db::inserir('comunicado_leituras', ['comunicado_id' => $cid, 'funcionario_id' => $fid]);
        } catch (Throwable $e) {
            // Já estava marcado como lido (índice único). Nada a fazer.
        }
    }
    voltar_para('comunicados.php');
}

$comunicados = Db::todos('comunicados', '', [], ['ordem' => 'fixado DESC, publicado_em DESC', 'limite' => 40]);
$deptos = Db::todos('departamentos', 'ativo = 1', [], ['ordem' => 'nome']);

$lidos = [];
if ($fid) {
    foreach (Db::todos('comunicado_leituras', 'funcionario_id = :f', ['f' => $fid]) as $l) {
        $lidos[(int) $l['comunicado_id']] = true;
    }
}

$autores = [];
foreach (Db::todos('funcionarios', '', [], ['colunas' => 'id, nome']) as $a) {
    $autores[(int) $a['id']] = $a['nome'];
}

cabecalho('Comunicados', 'comunicados', 'Comunicados',
    'Avisos oficiais da empresa e das equipes.');
?>

<?php if ($pode): ?>
<div class="card">
  <div class="card-head"><div><h3>Publicar comunicado</h3><p>Chega a todos que estiverem no alcance escolhido.</p></div></div>

  <form method="post" class="form-grid">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="publicar">

    <label>Título<input type="text" name="titulo" maxlength="160" required></label>

    <label>Alcance
      <select name="alcance">
        <option value="empresa">Toda a empresa</option>
        <option value="departamento">Um departamento</option>
        <option value="equipe">Minha equipe</option>
      </select>
    </label>

    <label>Departamento (quando aplicável)
      <select name="departamento_id">
        <option value="">—</option>
        <?php foreach ($deptos as $d): ?>
          <option value="<?= (int) $d['id'] ?>"><?= e($d['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="wide">Texto<textarea name="corpo" required></textarea></label>
    <label class="check"><input type="checkbox" name="fixado" value="1"> Fixar no topo</label>

    <button class="btn btn-success" type="submit">Publicar comunicado</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><div><h3>Mural</h3><p><?= count($comunicados) ?> comunicado(s)</p></div></div>

  <?php if (!$comunicados): ?>
    <?= vazio('Nenhum comunicado publicado ainda') ?>
  <?php else: ?>
    <div class="stack">
      <?php foreach ($comunicados as $c): ?>
        <article class="item-card">
          <div class="item-head">
            <?php if ((int) $c['fixado'] === 1): ?><?= badge('Fixado', 'yellow') ?><?php endif; ?>
            <?= badge(ucfirst($c['alcance']), 'blue') ?>
            <?php if (isset($lidos[(int) $c['id']])): ?><?= badge('Lido', 'green') ?><?php endif; ?>
          </div>
          <h4><?= e($c['titulo']) ?></h4>
          <p><?= nl2br(e($c['corpo'])) ?></p>
          <div class="item-meta">
            <span><?= e($autores[(int) $c['autor_id']] ?? 'Empresa') ?></span>
            <span class="mono"><?= e(data_br($c['publicado_em'], true)) ?></span>
          </div>
          <?php if ($fid && !isset($lidos[(int) $c['id']])): ?>
            <form method="post" class="item-actions">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="lido">
              <input type="hidden" name="comunicado_id" value="<?= (int) $c['id'] ?>">
              <button class="btn btn-ghost" type="submit">Marcar como lido</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php rodape(); ?>
