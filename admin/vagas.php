<?php
/**
 * ConnectWork — Vagas internas (administrador)
 *
 * Publicação de vagas e acompanhamento do funil de candidatos.
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['admin']);

$TIPOS = ['efetivo' => 'Efetivo', 'temporario' => 'Temporário', 'estagio' => 'Estágio', 'aprendiz' => 'Aprendiz'];
$MODOS = ['presencial' => 'Presencial', 'hibrido' => 'Híbrido', 'remoto' => 'Remoto'];
$FASES = ['inscrita' => 'Inscrita', 'triagem' => 'Triagem', 'entrevista' => 'Entrevista', 'aprovada' => 'Aprovada', 'reprovada' => 'Reprovada'];
$CORES = ['inscrita' => 'gray', 'triagem' => 'yellow', 'entrevista' => 'blue', 'aprovada' => 'green', 'reprovada' => 'red'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id       = entrada_int('id');
        $titulo   = entrada('titulo');
        $descricao = entrada('descricao');
        $requisitos = entrada('requisitos');
        $deptoId  = entrada_int('departamento_id');
        $tipo     = entrada('tipo');
        $modo     = entrada('modalidade');
        $salario  = str_replace(',', '.', entrada('salario'));
        $qtd      = entrada_int('vagas_abertas') ?: 1;
        $encerra  = entrada('encerra_em');
        $publicar = ($_POST['status'] ?? '') === 'aberta';

        $tipo = array_key_exists($tipo, $TIPOS) ? $tipo : 'efetivo';
        $modo = array_key_exists($modo, $MODOS) ? $modo : 'presencial';

        if ($titulo === '' || $descricao === '') {
            flash('erro', 'Título e descrição são obrigatórios.');
        } else {
            $dados = [
                'titulo'          => mb_substr($titulo, 0, 160),
                'descricao'       => $descricao,
                'requisitos'      => $requisitos ?: null,
                'departamento_id' => $deptoId ?: null,
                'tipo'            => $tipo,
                'modalidade'      => $modo,
                'salario'         => is_numeric($salario) ? (float) $salario : null,
                'vagas_abertas'   => max(1, min(9999, $qtd)),
                'status'          => $publicar ? 'aberta' : 'rascunho',
                'encerra_em'      => preg_match('/^\d{4}-\d{2}-\d{2}$/', $encerra) ? $encerra : null,
            ];

            if ($id && Db::porId('vagas', $id)) {
                // Ao publicar pela primeira vez, carimba a data de publicação.
                $atual = Db::porId('vagas', $id);
                if ($publicar && empty($atual['publicada_em'])) {
                    $dados['publicada_em'] = date('Y-m-d H:i:s');
                }
                Db::atualizar('vagas', $id, $dados);
                flash('ok', 'Vaga atualizada.');
            } else {
                $dados['criado_por'] = Auth::id();
                if ($publicar) { $dados['publicada_em'] = date('Y-m-d H:i:s'); }
                Db::inserir('vagas', $dados);
                flash('ok', $publicar ? 'Vaga publicada.' : 'Rascunho de vaga salvo.');
            }
            auditar('vaga_salva', 'vagas', $id ?: null);
        }
        voltar_para('admin/vagas.php');
    } elseif ($acao === 'encerrar') {
        $id = entrada_int('id');
        if (Db::porId('vagas', $id)) {
            Db::atualizar('vagas', $id, ['status' => 'encerrada']);
            flash('ok', 'Vaga encerrada.');
        }
        voltar_para('admin/vagas.php');
    } elseif ($acao === 'reabrir') {
        $id = entrada_int('id');
        if (Db::porId('vagas', $id)) {
            Db::atualizar('vagas', $id, ['status' => 'aberta']);
            flash('ok', 'Vaga reaberta.');
        }
        voltar_para('admin/vagas.php');
    } elseif ($acao === 'candidatura') {
        $cid  = entrada_int('id');
        $novo = entrada('status');
        $cand = $cid ? Db::porId('candidaturas', $cid) : null;
        if ($cand && array_key_exists($novo, $FASES)) {
            Db::atualizar('candidaturas', $cid, ['status' => $novo]);
            // Avisa o candidato
            $func = Db::porId('funcionarios', (int) $cand['funcionario_id']);
            if ($func && $func['usuario_id']) {
                Db::inserir('notificacoes', [
                    'usuario_id' => (int) $func['usuario_id'],
                    'titulo'     => 'Atualização da sua candidatura',
                    'corpo'      => 'Sua candidatura mudou para: ' . $FASES[$novo],
                    'link'       => 'vagas.php',
                ]);
            }
            auditar('candidatura_status', 'candidaturas', $cid, $novo);
            flash('ok', 'Situação da candidatura atualizada.');
        }
        voltar_para('admin/vagas.php?vaga=' . (int) ($cand['vaga_id'] ?? 0));
    }
}

$edicao = null;
$edicaoId = entrada_int('editar', 'get');
if ($edicaoId) { $edicao = Db::porId('vagas', $edicaoId); }

$vagas = Db::todos('vagas', '', [], ['ordem' => 'criado_em DESC', 'limite' => 100]);
$departamentos = Db::todos('departamentos', 'ativo = 1', [], ['ordem' => 'nome']);
$deptoNome = [];
foreach ($departamentos as $d) { $deptoNome[(int) $d['id']] = $d['nome']; }

$nomes = [];
foreach (Db::todos('funcionarios', '', [], ['colunas' => 'id, nome']) as $f) {
    $nomes[(int) $f['id']] = $f['nome'];
}

// Contagem de candidatos por vaga
$candCount = [];
foreach (Db::consulta(
    'SELECT vaga_id, COUNT(*) AS t FROM candidaturas WHERE empresa_id = :cw_emp GROUP BY vaga_id',
    Db::escopo()
) as $l) {
    $candCount[(int) $l['vaga_id']] = (int) $l['t'];
}

// Vaga aberta para ver o funil
$vagaSel = entrada_int('vaga', 'get');
$vagaAberta = $vagaSel ? Db::porId('vagas', $vagaSel) : null;
$candidatos = $vagaAberta
    ? Db::todos('candidaturas', 'vaga_id = :v', ['v' => $vagaAberta['id']], ['ordem' => 'criado_em ASC'])
    : [];

cabecalho('Vagas', 'vagas', 'Vagas internas',
    'Publicação de oportunidades e acompanhamento de candidatos.');
?>

<div class="card">
  <div class="card-head">
    <div><h3><?= $edicao ? 'Editar vaga' : 'Nova vaga' ?></h3><p>Publique ou salve como rascunho</p></div>
    <?php if ($edicao): ?><a class="btn btn-ghost" href="<?= e(url('admin/vagas.php')) ?>">Cancelar</a><?php endif; ?>
  </div>
  <form method="post" class="form-grid">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="id" value="<?= e($edicao['id'] ?? '') ?>">

    <label class="wide">Título *<input type="text" name="titulo" value="<?= e($edicao['titulo'] ?? '') ?>" required></label>
    <label class="wide">Descrição *<textarea name="descricao" required><?= e($edicao['descricao'] ?? '') ?></textarea></label>
    <label class="wide">Requisitos<textarea name="requisitos"><?= e($edicao['requisitos'] ?? '') ?></textarea></label>

    <label>Departamento
      <select name="departamento_id">
        <option value="">—</option>
        <?php foreach ($departamentos as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= (int) ($edicao['departamento_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Tipo
      <select name="tipo">
        <?php foreach ($TIPOS as $k => $r): ?><option value="<?= $k ?>" <?= ($edicao['tipo'] ?? '') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Modalidade
      <select name="modalidade">
        <?php foreach ($MODOS as $k => $r): ?><option value="<?= $k ?>" <?= ($edicao['modalidade'] ?? '') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Salário (R$)<input type="text" name="salario" value="<?= e($edicao['salario'] ?? '') ?>" placeholder="0,00"></label>
    <label>Nº de vagas<input type="number" name="vagas_abertas" min="1" max="9999" value="<?= e($edicao['vagas_abertas'] ?? 1) ?>"></label>
    <label>Encerra em<input type="date" name="encerra_em" value="<?= e($edicao['encerra_em'] ?? '') ?>"></label>
    <label>Publicação
      <select name="status">
        <option value="rascunho" <?= ($edicao['status'] ?? 'rascunho') === 'rascunho' ? 'selected' : '' ?>>Salvar como rascunho</option>
        <option value="aberta" <?= ($edicao['status'] ?? '') === 'aberta' ? 'selected' : '' ?>>Publicar agora</option>
      </select>
    </label>

    <button class="btn btn-success" type="submit"><?= $edicao ? 'Salvar vaga' : 'Criar vaga' ?></button>
  </form>
</div>

<div class="card">
  <div class="card-head"><div><h3>Vagas cadastradas</h3><p><?= count($vagas) ?> no total</p></div></div>
  <?php if (!$vagas): ?>
    <?= vazio('Nenhuma vaga ainda', 'Publique a primeira no formulário acima.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Título</th><th>Departamento</th><th>Tipo</th><th>Candidatos</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($vagas as $v): ?>
          <tr>
            <td><b><?= e($v['titulo']) ?></b></td>
            <td class="muted"><?= e($deptoNome[(int) $v['departamento_id']] ?? '—') ?></td>
            <td class="muted"><?= e($TIPOS[$v['tipo']] ?? $v['tipo']) ?></td>
            <td class="mono">
              <a href="<?= e(url('admin/vagas.php?vaga=' . (int) $v['id'])) ?>"><?= (int) ($candCount[(int) $v['id']] ?? 0) ?></a>
            </td>
            <td>
              <?= $v['status'] === 'aberta' ? badge('Aberta', 'green')
                 : ($v['status'] === 'encerrada' ? badge('Encerrada', 'gray') : badge('Rascunho', 'yellow')) ?>
            </td>
            <td class="right" style="white-space:nowrap">
              <a class="btn btn-ghost" href="<?= e(url('admin/vagas.php?editar=' . (int) $v['id'])) ?>">Editar</a>
              <?php if ($v['status'] === 'aberta'): ?>
                <form method="post" style="display:inline"><?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="encerrar"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                  <button class="btn btn-ghost" type="submit">Encerrar</button>
                </form>
              <?php elseif ($v['status'] === 'encerrada'): ?>
                <form method="post" style="display:inline"><?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="reabrir"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                  <button class="btn btn-ghost" type="submit">Reabrir</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($vagaAberta): ?>
  <div class="card">
    <div class="card-head">
      <div><h3>Candidatos — <?= e($vagaAberta['titulo']) ?></h3><p><?= count($candidatos) ?> inscrito(s)</p></div>
      <a class="btn btn-ghost" href="<?= e(url('admin/vagas.php')) ?>">Fechar funil</a>
    </div>
    <?php if (!$candidatos): ?>
      <?= vazio('Nenhum candidato ainda', 'Assim que alguém se inscrever, aparece aqui.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Candidato</th><th>Inscrição</th><th>Carta</th><th>Fase</th><th>Mover para</th></tr></thead>
          <tbody>
          <?php foreach ($candidatos as $c): ?>
            <tr>
              <td><b><?= e($nomes[(int) $c['funcionario_id']] ?? 'Funcionário') ?></b></td>
              <td class="muted small"><?= e(data_br($c['criado_em'])) ?></td>
              <td class="muted small"><?= $c['carta'] ? e(mb_substr($c['carta'], 0, 60)) . '…' : '—' ?></td>
              <td><?= badge($FASES[$c['status']] ?? $c['status'], $CORES[$c['status']] ?? 'gray') ?></td>
              <td>
                <form method="post" style="display:flex;gap:6px">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="candidatura">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <select name="status">
                    <?php foreach ($FASES as $k => $r): ?>
                      <option value="<?= $k ?>" <?= $c['status'] === $k ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-ghost" type="submit">Aplicar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php rodape(); ?>
