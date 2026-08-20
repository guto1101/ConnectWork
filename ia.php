<?php
/**
 * ConnectWork — Assistente
 * Conversa gravada em ia_conversas / ia_mensagens.
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/ia.php';

Auth::exigirLogin();

$conversa = Db::um('ia_conversas', 'usuario_id = :u', ['u' => Auth::id()], ['ordem' => 'id DESC']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();

    if (($_POST['acao'] ?? '') === 'limpar' && $conversa) {
        Db::excluir('ia_conversas', (int) $conversa['id']);
        voltar_para('ia.php');
    }

    $pergunta = entrada('pergunta');
    if ($pergunta !== '') {
        if (!$conversa) {
            $id = Db::inserir('ia_conversas', [
                'usuario_id' => Auth::id(),
                'titulo'     => mb_strimwidth($pergunta, 0, 60, '…'),
            ]);
            $conversa = Db::porId('ia_conversas', $id);
        }

        Db::inserir('ia_mensagens', [
            'conversa_id' => (int) $conversa['id'],
            'papel'       => 'usuario',
            'conteudo'    => $pergunta,
        ]);

        $r = IA::responder($pergunta);

        Db::inserir('ia_mensagens', [
            'conversa_id' => (int) $conversa['id'],
            'papel'       => 'assistente',
            'conteudo'    => $r['texto'],
            'provedor'    => $r['provedor'],
        ]);
    }
    voltar_para('ia.php');
}

$mensagens = $conversa
    ? Db::todos('ia_mensagens', 'conversa_id = :c', ['c' => $conversa['id']], ['ordem' => 'id ASC', 'limite' => 60])
    : [];

$provedorAtivo = (CW_IA_PROVEDOR !== 'local' && CW_IA_CHAVE !== '') ? CW_IA_PROVEDOR : 'local';

cabecalho('Assistente', 'assistente', 'Assistente ConnectWork',
    'Consulte dados e obtenha orientação sobre o sistema.',
    $mensagens ? '<form method="post" style="display:inline">' . csrf_campo()
        . '<input type="hidden" name="acao" value="limpar">'
        . '<button class="btn btn-ghost" type="submit" data-confirma="Apagar toda a conversa?">Limpar conversa</button></form>' : '');
?>

<div class="alert alert-info">
  <b>Assistente ConnectWork.</b> Consulte os dados disponíveis da empresa e peça orientação sobre as funções do sistema.
  As respostas usam informações do ambiente atual e não inventam dados que não estejam disponíveis.
</div>

<div class="card chat-card">
  <div class="ai-quick">
    <?php foreach (['Resumo da empresa', 'Como está o ponto hoje?', 'Situação da ouvidoria',
                    'Vagas e candidaturas', 'Explicar a cerca virtual'] as $atalho): ?>
      <form method="post" style="display:inline">
        <?= csrf_campo() ?>
        <input type="hidden" name="pergunta" value="<?= e($atalho) ?>">
        <button class="btn btn-ghost" type="submit"><?= e($atalho) ?></button>
      </form>
    <?php endforeach; ?>
  </div>

  <div class="chat-area ai-area" id="chatArea">
    <?php if (!$mensagens): ?>
      <div class="chat-empty">
        Pergunte alguma coisa sobre a operação da empresa.<br>
        Os atalhos acima são um bom começo.
      </div>
    <?php else: ?>
      <?php foreach ($mensagens as $m): ?>
        <div class="chat-msg<?= $m['papel'] === 'usuario' ? ' mine' : '' ?>">
          <?= nl2br(e($m['conteudo'])) ?>
          <span class="meta">
            <?= $m['papel'] === 'usuario' ? 'Você' : 'Assistente ConnectWork' ?>
            · <?= e(date('H:i', strtotime($m['criado_em']))) ?>
          </span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <form class="chat-input" method="post">
    <?= csrf_campo() ?>
    <input type="text" name="pergunta" placeholder="Digite sua pergunta..." required>
    <button class="btn btn-primary" type="submit">Perguntar</button>
  </form>
</div>

<script>
  var area = document.getElementById('chatArea');
  if (area) { area.scrollTop = area.scrollHeight; }
</script>

<?php rodape(); ?>
