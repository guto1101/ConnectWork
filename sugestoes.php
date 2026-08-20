<?php
/**
 * ConnectWork — Sugestões (funcionário)
 * Envio de ideias de melhoria e acompanhamento da devolutiva.
 */

require_once __DIR__ . '/includes/layout.php';

Auth::exigirLogin();
$fid = Auth::funcionarioId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'nova') {
    csrf_exigir();
    $titulo = entrada('titulo');
    $desc   = entrada('descricao');

    if ($titulo === '' || $desc === '') {
        flash('erro', 'Preencha o título e a descrição da sugestão.');
    } else {
        $anonima = isset($_POST['anonima']);
        Db::inserir('sugestoes', [
            'funcionario_id' => $anonima ? null : $fid,
            'anonima'        => $anonima ? 1 : 0,
            'titulo'         => mb_substr($titulo, 0, 160),
            'descricao'      => $desc,
            'area'           => entrada('area') ?: null,
            'status'         => 'recebida',
        ]);
        auditar('sugestao_enviada', 'sugestoes');
        flash('ok', 'Sugestão enviada. Você acompanha o andamento por aqui.');
    }
    voltar_para('sugestoes.php');
}

$minhas = $fid
    ? Db::todos('sugestoes', 'funcionario_id = :f', ['f' => $fid], ['ordem' => 'criado_em DESC'])
    : [];

$implementadas = Db::todos('sugestoes', 'status = :s', ['s' => 'implementada'],
    ['ordem' => 'atualizado_em DESC', 'limite' => 6]);

$cores = ['recebida' => 'gray', 'em_analise' => 'yellow', 'aprovada' => 'blue',
          'implementada' => 'green', 'recusada' => 'red'];
$nomes = ['recebida' => 'Recebida', 'em_analise' => 'Em análise', 'aprovada' => 'Aprovada',
          'implementada' => 'Implementada', 'recusada' => 'Recusada'];

cabecalho('Sugestões', 'sugestoes', 'Sugestões',
    'Ideias dos colaboradores para melhoria contínua.');
?>

<div class="card">
  <div class="card-head"><div><h3>Nova sugestão</h3><p>Compartilhe uma ideia para a empresa.</p></div></div>

  <form method="post" class="form-grid">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="nova">

    <label>Título<input type="text" name="titulo" maxlength="160" required></label>

    <label>Área
      <select name="area">
        <option>Bem-estar</option>
        <option>Processos</option>
        <option>Refeitório</option>
        <option>Tecnologia</option>
        <option>Sustentabilidade</option>
        <option>Segurança</option>
        <option>Outros</option>
      </select>
    </label>

    <label class="wide">Descrição
      <textarea name="descricao" required placeholder="O que você propõe e qual problema isso resolve?"></textarea>
    </label>

    <label class="check"><input type="checkbox" name="anonima" value="1"> Enviar sem meu nome</label>

    <button class="btn btn-success" type="submit">Enviar sugestão</button>
  </form>
</div>

<div class="card">
  <div class="card-head"><div><h3>Minhas sugestões</h3><p>Acompanhe a devolutiva da empresa</p></div></div>

  <?php if (!$minhas): ?>
    <?= vazio('Você ainda não enviou sugestões', 'Use o formulário acima para enviar a primeira.') ?>
  <?php else: ?>
    <div class="grid-3">
      <?php foreach ($minhas as $s): ?>
        <article class="item-card">
          <div class="item-head">
            <?= badge($nomes[$s['status']] ?? $s['status'], $cores[$s['status']] ?? 'gray') ?>
            <?php if ($s['area']): ?><?= badge($s['area'], 'purple') ?><?php endif; ?>
          </div>
          <h4><?= e($s['titulo']) ?></h4>
          <p><?= nl2br(e($s['descricao'])) ?></p>
          <?php if ($s['retorno']): ?>
            <div class="assistant-preview"><b>Retorno da empresa:</b><br><?= nl2br(e($s['retorno'])) ?></div>
          <?php endif; ?>
          <div class="item-meta">
            <span>Enviada em <?= e(data_br($s['criado_em'])) ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($implementadas): ?>
<div class="card">
  <div class="card-head"><div><h3>Já implementadas</h3><p>Ideias que viraram realidade na empresa</p></div></div>
  <div class="grid-3">
    <?php foreach ($implementadas as $s): ?>
      <article class="item-card">
        <div class="item-head"><?= badge('Implementada', 'green') ?></div>
        <h4><?= e($s['titulo']) ?></h4>
        <p><?= e(mb_strimwidth(strip_tags($s['descricao']), 0, 150, '…')) ?></p>
        <div class="item-meta"><span><?= e($s['area'] ?: 'Geral') ?></span></div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php rodape(); ?>
