<?php
/**
 * ConnectWork — Mensagens internas
 *
 * Chat entre funcionários, gerentes e administradores da MESMA empresa.
 * A lista de destinatários vem da camada Db, então já chega filtrada por
 * empresa — não existe conversa entre empresas diferentes.
 */

require_once __DIR__ . '/includes/layout.php';

Auth::exigirLogin();
$fid = Auth::funcionarioId();

if (!$fid) {
    cabecalho('Mensagens', 'mensagens', 'Mensagens Internas', 'Canal interno de comunicação.');
    echo '<div class="card">' . vazio('Sua conta ainda não tem cadastro de funcionário',
        'O administrador precisa vincular seu usuário a um funcionário para liberar o chat.') . '</div>';
    rodape();
    exit;
}

$colegas = Db::todos('funcionarios', 'id <> :eu AND status = :s',
    ['eu' => $fid, 's' => 'ativo'], ['ordem' => 'nome']);

$comId = entrada_int('com', 'get');
if ($comId && !Db::porId('funcionarios', $comId)) {
    $comId = 0;                                   // id de outra empresa: descartado
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'enviar') {
    csrf_exigir();
    $para  = entrada_int('destinatario');
    $corpo = entrada('corpo');
    $destino = Db::porId('funcionarios', $para);   // confirma que é da mesma empresa

    if (!$destino) {
        flash('erro', 'Destinatário inválido.');
    } elseif ($corpo === '') {
        flash('erro', 'Escreva a mensagem antes de enviar.');
    } else {
        Db::inserir('mensagens', [
            'remetente_id'    => $fid,
            'destinatario_id' => $para,
            'corpo'           => $corpo,
        ]);
        if ($destino['usuario_id']) {
            Db::inserir('notificacoes', [
                'usuario_id' => (int) $destino['usuario_id'],
                'titulo'     => 'Nova mensagem de ' . Auth::nome(),
                'corpo'      => mb_strimwidth($corpo, 0, 120, '…'),
                'link'       => 'mensagens.php?com=' . $fid,
            ]);
        }
    }
    voltar_para('mensagens.php?com=' . $para);
}

// Marca como lidas as mensagens da conversa aberta
if ($comId) {
    Db::executar(
        'UPDATE mensagens SET lida_em = NOW()
          WHERE empresa_id = :cw_emp AND destinatario_id = :eu AND remetente_id = :dele AND lida_em IS NULL',
        Db::escopo(['eu' => $fid, 'dele' => $comId])
    );
}

$conversa = $comId ? Db::todos('mensagens',
    '((remetente_id = :eu AND destinatario_id = :ele) OR (remetente_id = :ele2 AND destinatario_id = :eu2))',
    ['eu' => $fid, 'ele' => $comId, 'ele2' => $comId, 'eu2' => $fid],
    ['ordem' => 'criado_em ASC', 'limite' => 200]) : [];

// Contagem de não lidas por remetente, para destacar na lista
$naoLidas = [];
foreach (Db::consulta(
    'SELECT remetente_id, COUNT(*) AS t FROM mensagens
      WHERE empresa_id = :cw_emp AND destinatario_id = :eu AND lida_em IS NULL
      GROUP BY remetente_id',
    Db::escopo(['eu' => $fid])
) as $l) {
    $naoLidas[(int) $l['remetente_id']] = (int) $l['t'];
}

$outro = $comId ? Db::porId('funcionarios', $comId) : null;

cabecalho('Mensagens', 'mensagens', 'Mensagens Internas',
    'Canal interno de comunicação da empresa.');
?>

<div class="grid-2" style="grid-template-columns:300px 1fr">
  <div class="card">
    <div class="card-head"><div><h3>Contatos</h3><p><?= count($colegas) ?> pessoa(s)</p></div></div>
    <?php if (!$colegas): ?>
      <?= vazio('Nenhum colega cadastrado ainda') ?>
    <?php else: ?>
      <ul class="activity">
        <?php foreach ($colegas as $c):
            $n = $naoLidas[(int) $c['id']] ?? 0; ?>
          <li>
            <div class="avatar-sm"><?= e(iniciais($c['nome'])) ?></div>
            <div style="flex:1">
              <strong><a href="<?= e(url('mensagens.php?com=' . (int) $c['id'])) ?>"><?= e($c['nome']) ?></a></strong>
              <span><?= e($c['cargo'] ?: 'Colaborador') ?></span>
            </div>
            <?php if ($n > 0): ?><?= badge((string) $n, 'red') ?><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card chat-card">
    <div class="tabs">
      <span class="tab active"><?= $outro ? e($outro['nome']) : 'Selecione um contato' ?></span>
    </div>

    <div class="chat-area" id="chatArea">
      <?php if (!$outro): ?>
        <div class="chat-empty">Escolha um contato à esquerda para começar a conversa.</div>
      <?php elseif (!$conversa): ?>
        <div class="chat-empty">Nenhuma mensagem ainda. Diga olá.</div>
      <?php else: ?>
        <?php foreach ($conversa as $m): ?>
          <div class="chat-msg<?= (int) $m['remetente_id'] === $fid ? ' mine' : '' ?>">
            <?= nl2br(e($m['corpo'])) ?>
            <span class="meta"><?= e(data_br($m['criado_em'], true)) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($outro): ?>
      <form class="chat-input" method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="enviar">
        <input type="hidden" name="destinatario" value="<?= (int) $outro['id'] ?>">
        <input type="text" name="corpo" placeholder="Escreva sua mensagem..." required autofocus>
        <button class="btn btn-primary" type="submit">Enviar</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
  var area = document.getElementById('chatArea');
  if (area) { area.scrollTop = area.scrollHeight; }
</script>

<?php rodape(); ?>
