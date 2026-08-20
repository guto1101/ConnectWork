<?php
/**
 * ConnectWork — Ouvidoria (funcionário)
 *
 * Relato identificado ou anônimo.
 *
 * No relato anônimo o funcionario_id fica NULL — não existe coluna que
 * ligue o relato ao autor. O acompanhamento é feito por um protocolo
 * mostrado UMA vez; no banco guardamos só o hash dele, então nem quem
 * tem acesso ao MySQL consegue colher códigos válidos ou descobrir quem
 * denunciou.
 */

require_once __DIR__ . '/includes/layout.php';

Auth::exigirLogin();

$fid = Auth::funcionarioId();
$protocoloNovo = '';

/** Código no formato CW-XXXX-XXXX, sem caracteres ambíguos. */
function gerar_protocolo(): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $bloco = static function () use ($alfabeto) {
        $s = '';
        for ($i = 0; $i < 4; $i++) { $s .= $alfabeto[random_int(0, strlen($alfabeto) - 1)]; }
        return $s;
    };
    return 'CW-' . $bloco() . '-' . $bloco();
}

// ---------------------------------------------------------------------
// Novo relato
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'novo') {
    csrf_exigir();

    $assunto    = entrada('assunto');
    $descricao  = entrada('descricao');
    $categoria  = entrada('categoria');
    $prioridade = entrada('prioridade');
    $anonimo    = isset($_POST['anonimo']);

    $categorias  = ['assedio', 'seguranca', 'etica', 'financeiro', 'discriminacao', 'outro'];
    $prioridades = ['baixa', 'media', 'alta', 'critica'];

    if ($assunto === '' || $descricao === '') {
        flash('erro', 'Preencha o assunto e a descrição do relato.');
    } else {
        $protocolo = gerar_protocolo();
        Db::inserir('ouvidoria', [
            'protocolo_hash' => hash('sha256', $protocolo),
            'anonimo'        => $anonimo ? 1 : 0,
            'funcionario_id' => $anonimo ? null : $fid,
            'categoria'      => in_array($categoria, $categorias, true) ? $categoria : 'outro',
            'assunto'        => mb_substr($assunto, 0, 160),
            'descricao'      => $descricao,
            'prioridade'     => in_array($prioridade, $prioridades, true) ? $prioridade : 'media',
            'status'         => 'aberta',
        ]);

        auditar('ouvidoria_aberta', 'ouvidoria', null, $anonimo ? 'anonimo' : 'identificado');
        $_SESSION['cw_protocolo_novo'] = $protocolo;
        voltar_para('ouvidoria.php');
    }
}

if (!empty($_SESSION['cw_protocolo_novo'])) {
    $protocoloNovo = $_SESSION['cw_protocolo_novo'];
    unset($_SESSION['cw_protocolo_novo']);
}

// ---------------------------------------------------------------------
// Consulta por protocolo
// ---------------------------------------------------------------------
$consultado = null;
$erroConsulta = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'consultar') {
    csrf_exigir();
    $codigo = strtoupper(trim(entrada('protocolo')));
    $consultado = Db::um('ouvidoria', 'protocolo_hash = :h', ['h' => hash('sha256', $codigo)]);
    if (!$consultado) {
        $erroConsulta = 'Nenhum relato encontrado com esse protocolo nesta empresa.';
    }
}

// Relatos identificados do próprio funcionário
$meus = $fid
    ? Db::todos('ouvidoria', 'funcionario_id = :f AND anonimo = 0', ['f' => $fid], ['ordem' => 'criado_em DESC'])
    : [];

cabecalho('Ouvidoria', 'ouvidoria', 'Ouvidoria',
    'Canal de relatos, denúncias e transparência.');
?>

<?php if ($protocoloNovo !== ''): ?>
  <div class="card">
    <div class="card-head"><div><h3>Relato registrado</h3><p>Guarde o protocolo abaixo</p></div></div>
    <p class="protocolo"><?= e($protocoloNovo) ?></p>
    <p class="note">
      Este código aparece <b>uma única vez</b> e é a forma de acompanhar o relato.
      Anote antes de sair desta página — não guardamos o código, apenas uma
      impressão dele, então ninguém consegue recuperá-lo depois (inclusive nós).
    </p>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <div><h3>Novo relato</h3><p>Você escolhe se o relato leva o seu nome ou não.</p></div>
  </div>

  <form method="post" class="form-grid">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="novo">

    <label>Assunto<input type="text" name="assunto" maxlength="160" required></label>

    <label>Categoria
      <select name="categoria">
        <option value="assedio">Assédio</option>
        <option value="seguranca">Segurança</option>
        <option value="etica">Ética e conduta</option>
        <option value="financeiro">Financeiro</option>
        <option value="discriminacao">Discriminação</option>
        <option value="outro" selected>Outros</option>
      </select>
    </label>

    <label>Prioridade
      <select name="prioridade">
        <option value="baixa">Baixa</option>
        <option value="media" selected>Média</option>
        <option value="alta">Alta</option>
        <option value="critica">Crítica</option>
      </select>
    </label>

    <label class="wide">Descrição
      <textarea name="descricao" required placeholder="Descreva o que aconteceu, quando e onde."></textarea>
    </label>

    <label class="check">
      <input type="checkbox" name="anonimo" value="1"> Enviar como anônimo
    </label>

    <button class="btn btn-success" type="submit">Enviar relato</button>
  </form>

  <p class="note">
    No envio anônimo o relato não guarda nenhuma ligação com a sua conta.
    Em compensação, o acompanhamento só é possível pelo protocolo.
  </p>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h3>Acompanhar por protocolo</h3><p>Para relatos anônimos</p></div></div>

    <form method="post" class="form-grid compact">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="consultar">
      <label class="wide">Protocolo
        <input type="text" name="protocolo" placeholder="CW-XXXX-XXXX" required>
      </label>
      <button class="btn btn-primary" type="submit">Consultar</button>
    </form>

    <?php if ($erroConsulta !== ''): ?>
      <div class="alert alert-erro mt"><?= e($erroConsulta) ?></div>
    <?php endif; ?>

    <?php if ($consultado): ?>
      <div class="item-card mt">
        <div class="item-head">
          <?= badge_status_ouvidoria($consultado['status']) ?>
          <?= badge(ucfirst($consultado['categoria']), 'purple') ?>
        </div>
        <h4><?= e($consultado['assunto']) ?></h4>
        <p><?= nl2br(e($consultado['descricao'])) ?></p>

        <?php
        $respostas = Db::todos('ouvidoria_respostas',
            'ouvidoria_id = :o AND visivel_denunciante = 1',
            ['o' => $consultado['id']], ['ordem' => 'criado_em ASC']);
        ?>
        <?php if ($respostas): ?>
          <div class="stack">
            <?php foreach ($respostas as $r): ?>
              <div class="assistant-preview">
                <?= nl2br(e($r['corpo'])) ?>
                <div class="small muted mono mt"><?= e(data_br($r['criado_em'], true)) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="muted small">Ainda sem resposta da empresa.</p>
        <?php endif; ?>

        <div class="item-meta">
          <span>Aberto em <?= e(data_br($consultado['criado_em'])) ?></span>
          <span><?= ((int) $consultado['anonimo'] === 1) ? 'Anônimo' : 'Identificado' ?></span>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Meus relatos identificados</h3><p>Relatos enviados com o seu nome</p></div></div>

    <?php if (!$meus): ?>
      <?= vazio('Nenhum relato identificado', 'Relatos anônimos não aparecem aqui, por definição.') ?>
    <?php else: ?>
      <ul class="activity">
        <?php foreach ($meus as $m): ?>
          <li>
            <span class="<?= $m['status'] === 'aberta' ? 'dot-red' : 'dot-blue' ?>"></span>
            <div style="flex:1">
              <strong><?= e($m['assunto']) ?></strong>
              <span><?= e(ucfirst($m['categoria'])) ?> · <?= e(data_br($m['criado_em'])) ?></span>
            </div>
            <?= badge_status_ouvidoria($m['status']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<?php rodape(); ?>
