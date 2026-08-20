<?php
/**
 * ConnectWork — Apuração da ouvidoria (administrador)
 *
 * Apura relatos, responde ao denunciante e registra notas internas.
 *
 * No relato anônimo não existe a quem "abrir" a identidade: a coluna
 * funcionario_id está NULL e o vínculo com a pessoa simplesmente não foi
 * gravado. O administrador conversa com o denunciante pela resposta
 * visível, que ele lê pelo protocolo.
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $acao = $_POST['acao'] ?? '';
    $id   = entrada_int('id');
    $relato = $id ? Db::porId('ouvidoria', $id) : null;

    if (!$relato) {
        flash('erro', 'Relato não encontrado.');
    } elseif ($acao === 'status') {
        $novo = entrada('status');
        if (in_array($novo, ['aberta', 'em_analise', 'respondida', 'encerrada'], true)) {
            Db::atualizar('ouvidoria', $id, ['status' => $novo]);
            auditar('ouvidoria_status', 'ouvidoria', $id, $novo);
            flash('ok', 'Situação do relato atualizada.');
        }
    } elseif ($acao === 'responder') {
        $corpo = entrada('corpo');
        $interna = isset($_POST['interna']);
        if ($corpo === '') {
            flash('erro', 'Escreva a resposta antes de enviar.');
        } else {
            Db::inserir('ouvidoria_respostas', [
                'ouvidoria_id'        => $id,
                'autor_id'            => Auth::funcionarioId(),
                'corpo'               => $corpo,
                'visivel_denunciante' => $interna ? 0 : 1,
            ]);
            // Responder ao denunciante move o relato para "respondida";
            // nota interna não muda o status visível para ele.
            if (!$interna && $relato['status'] !== 'encerrada') {
                Db::atualizar('ouvidoria', $id, ['status' => 'respondida']);
            }
            auditar('ouvidoria_resposta', 'ouvidoria', $id, $interna ? 'interna' : 'ao denunciante');
            flash('ok', $interna ? 'Nota interna registrada.' : 'Resposta enviada ao denunciante.');
        }
    }
    voltar_para('admin/ouvidoria.php' . ($id ? '?ver=' . $id : ''));
}

$fStatus = entrada('status', 'get');
$where = '1 = 1';
$params = [];
if (in_array($fStatus, ['aberta', 'em_analise', 'respondida', 'encerrada'], true)) {
    $where .= ' AND status = :st';
    $params['st'] = $fStatus;
}

$relatos = Db::todos('ouvidoria', $where, $params, ['ordem' => 'criado_em DESC', 'limite' => 100]);

$verId = entrada_int('ver', 'get');
$aberto = $verId ? Db::porId('ouvidoria', $verId) : null;
$respostas = $aberto
    ? Db::todos('ouvidoria_respostas', 'ouvidoria_id = :o', ['o' => $aberto['id']], ['ordem' => 'criado_em ASC'])
    : [];

$nomes = [];
foreach (Db::todos('funcionarios', '', [], ['colunas' => 'id, nome']) as $f) {
    $nomes[(int) $f['id']] = $f['nome'];
}

$stats = [
    'aberta'     => Db::contar('ouvidoria', 'status = :s', ['s' => 'aberta']),
    'em_analise' => Db::contar('ouvidoria', 'status = :s', ['s' => 'em_analise']),
    'respondida' => Db::contar('ouvidoria', 'status = :s', ['s' => 'respondida']),
    'encerrada'  => Db::contar('ouvidoria', 'status = :s', ['s' => 'encerrada']),
];

cabecalho('Ouvidoria', 'ouvidoria', 'Ouvidoria',
    'Apuração de relatos e denúncias.',
    '<a class="btn btn-ghost" href="' . e(url('exportar.php?tipo=ouvidoria')) . '">Exportar CSV</a>');
?>

<div class="metrics-grid">
  <article class="metric-card"><div class="metric-icon icon-orange"></div><div>
    <span class="metric-label">Abertos</span><strong class="metric-value"><?= (int) $stats['aberta'] ?></strong>
    <span class="metric-trend down">aguardando início</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-blue"></div><div>
    <span class="metric-label">Em análise</span><strong class="metric-value"><?= (int) $stats['em_analise'] ?></strong>
    <span class="metric-trend up">em apuração</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-purple"></div><div>
    <span class="metric-label">Respondidos</span><strong class="metric-value"><?= (int) $stats['respondida'] ?></strong>
    <span class="metric-trend up">retorno enviado</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-green"></div><div>
    <span class="metric-label">Encerrados</span><strong class="metric-value"><?= (int) $stats['encerrada'] ?></strong>
    <span class="metric-trend up">concluídos</span></div></article>
</div>

<div class="grid-2" style="grid-template-columns:1fr 1.3fr">
  <div class="card">
    <div class="card-head"><div><h3>Relatos</h3><p><?= count($relatos) ?> no filtro</p></div></div>

    <form method="get" style="margin-bottom:12px">
      <select name="status" onchange="this.form.submit()">
        <option value="">Todos os status</option>
        <?php foreach (['aberta' => 'Abertos', 'em_analise' => 'Em análise', 'respondida' => 'Respondidos', 'encerrada' => 'Encerrados'] as $k => $r): ?>
          <option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= $r ?></option>
        <?php endforeach; ?>
      </select>
    </form>

    <?php if (!$relatos): ?>
      <?= vazio('Nenhum relato', 'Nada a apurar neste filtro.') ?>
    <?php else: ?>
      <ul class="activity">
        <?php foreach ($relatos as $r): ?>
          <li>
            <span class="<?= $r['status'] === 'aberta' ? 'dot-red' : ($r['status'] === 'encerrada' ? 'dot-green' : 'dot-blue') ?>"></span>
            <div style="flex:1">
              <strong><a href="<?= e(url('admin/ouvidoria.php?ver=' . (int) $r['id'])) ?>"><?= e($r['assunto']) ?></a></strong>
              <span>
                <?= e(ucfirst($r['categoria'])) ?> · <?= e(ucfirst($r['prioridade'])) ?>
                · <?= (int) $r['anonimo'] === 1 ? 'anônimo' : e($nomes[(int) $r['funcionario_id']] ?? 'identificado') ?>
                · <?= e(data_br($r['criado_em'])) ?>
              </span>
            </div>
            <?= badge_status_ouvidoria($r['status']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card">
    <?php if (!$aberto): ?>
      <div class="card-head"><div><h3>Detalhe do relato</h3><p>Selecione um relato à esquerda</p></div></div>
      <?= vazio('Nenhum relato aberto', 'Clique em um assunto para apurar.') ?>
    <?php else: ?>
      <div class="card-head">
        <div>
          <h3><?= e($aberto['assunto']) ?></h3>
          <p><?= e(ucfirst($aberto['categoria'])) ?> · prioridade <?= e($aberto['prioridade']) ?>
             · <?= (int) $aberto['anonimo'] === 1 ? 'anônimo' : e($nomes[(int) $aberto['funcionario_id']] ?? 'identificado') ?></p>
        </div>
        <?= badge_status_ouvidoria($aberto['status']) ?>
      </div>

      <div class="assistant-preview"><?= nl2br(e($aberto['descricao'])) ?></div>

      <form method="post" class="form-grid compact mt">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="status">
        <input type="hidden" name="id" value="<?= (int) $aberto['id'] ?>">
        <label>Mudar situação
          <select name="status">
            <?php foreach (['aberta' => 'Aberta', 'em_analise' => 'Em análise', 'respondida' => 'Respondida', 'encerrada' => 'Encerrada'] as $k => $r): ?>
              <option value="<?= $k ?>" <?= $aberto['status'] === $k ? 'selected' : '' ?>><?= $r ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn btn-ghost" type="submit">Atualizar situação</button>
      </form>

      <h4 class="mt">Histórico e respostas</h4>
      <?php if (!$respostas): ?>
        <p class="muted small">Nenhuma resposta ainda.</p>
      <?php else: ?>
        <div class="stack">
          <?php foreach ($respostas as $resp): ?>
            <div class="assistant-preview" style="<?= (int) $resp['visivel_denunciante'] === 0 ? 'border-left-color:var(--yellow);background:rgba(217,119,6,.08)' : '' ?>">
              <?= (int) $resp['visivel_denunciante'] === 0 ? '<b>Nota interna</b><br>' : '' ?>
              <?= nl2br(e($resp['corpo'])) ?>
              <div class="small muted mono mt">
                <?= e($nomes[(int) $resp['autor_id']] ?? 'Empresa') ?> · <?= e(data_br($resp['criado_em'], true)) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="form-grid mt">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="responder">
        <input type="hidden" name="id" value="<?= (int) $aberto['id'] ?>">
        <label class="wide">Nova resposta<textarea name="corpo" required placeholder="Escreva a resposta ou a nota de apuração."></textarea></label>
        <label class="check"><input type="checkbox" name="interna" value="1"> Nota interna (não visível ao denunciante)</label>
        <button class="btn btn-success" type="submit">Registrar</button>
      </form>

      <p class="note">
        A resposta comum fica visível para quem consultar o protocolo. A nota interna serve para o
        registro da apuração e nunca aparece ao denunciante.
      </p>
    <?php endif; ?>
  </div>
</div>

<?php rodape(); ?>
