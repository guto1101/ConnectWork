<?php
/**
 * ConnectWork — Notificações
 * Avisos gerados pelo sistema (mensagens, ouvidoria, sugestões).
 */

require_once __DIR__ . '/includes/layout.php';

Auth::exigirLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'ler_todas') {
        Db::executar(
            'UPDATE notificacoes SET lida_em = NOW()
              WHERE empresa_id = :cw_emp AND usuario_id = :u AND lida_em IS NULL',
            Db::escopo(['u' => Auth::id()])
        );
        flash('ok', 'Todas as notificações foram marcadas como lidas.');
    } elseif ($acao === 'limpar') {
        Db::executar(
            'DELETE FROM notificacoes WHERE empresa_id = :cw_emp AND usuario_id = :u AND lida_em IS NOT NULL',
            Db::escopo(['u' => Auth::id()])
        );
        flash('ok', 'Notificações lidas removidas.');
    }
    voltar_para('notificacoes.php');
}

$lista = Db::todos('notificacoes', 'usuario_id = :u', ['u' => Auth::id()],
    ['ordem' => 'criado_em DESC', 'limite' => 60]);

$naoLidas = 0;
foreach ($lista as $n) { if ($n['lida_em'] === null) { $naoLidas++; } }

$acoes = '<form method="post" style="display:inline">' . csrf_campo()
       . '<input type="hidden" name="acao" value="ler_todas">'
       . '<button class="btn btn-primary" type="submit">Marcar todas como lidas</button></form> '
       . '<form method="post" style="display:inline">' . csrf_campo()
       . '<input type="hidden" name="acao" value="limpar">'
       . '<button class="btn btn-ghost" type="submit" data-confirma="Remover as notificações já lidas?">Limpar lidas</button></form>';

cabecalho('Notificações', '', 'Notificações',
    $naoLidas . ' não lida(s) de ' . count($lista) . ' recente(s)', $acoes);
?>

<div class="card">
  <?php if (!$lista): ?>
    <?= vazio('Nenhuma notificação', 'Avisos de mensagens, ouvidoria e sugestões aparecem aqui.') ?>
  <?php else: ?>
    <ul class="activity">
      <?php foreach ($lista as $n): ?>
        <li>
          <span class="<?= $n['lida_em'] === null ? 'dot-blue' : 'dot-green' ?>"></span>
          <div style="flex:1">
            <strong>
              <?php if ($n['link']): ?>
                <a href="<?= e(url($n['link'])) ?>"><?= e($n['titulo']) ?></a>
              <?php else: ?>
                <?= e($n['titulo']) ?>
              <?php endif; ?>
            </strong>
            <?php if ($n['corpo']): ?><span><?= e($n['corpo']) ?></span><?php endif; ?>
            <span class="mono small"><?= e(data_br($n['criado_em'], true)) ?></span>
          </div>
          <?= $n['lida_em'] === null ? badge('Nova', 'blue') : badge('Lida', 'gray') ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php rodape(); ?>
