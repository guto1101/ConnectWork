<?php
/**
 * ConnectWork — Fila de aprovações da empresa
 *
 * Centraliza a conferência de batidas pendentes e as solicitações de
 * disponibilidade para hora extra. As decisões são gravadas no dado real e
 * registradas na trilha de auditoria.
 */

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/ponto.php';

Auth::exigirNivel(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $acao = entrada('acao');
    $observacao = mb_substr(entrada('observacao'), 0, 255);

    if ($acao === 'decidir_ponto') {
        $pontoId = entrada_int('ponto_id');
        $decisao = entrada('decisao');
        $ponto = $pontoId ? Db::porId('pontos', $pontoId) : null;

        if (!$ponto || $ponto['status'] !== 'pendente_revisao') {
            flash('erro', 'A batida não está mais disponível para decisão.');
        } elseif (!in_array($decisao, ['valido', 'rejeitado'], true)) {
            flash('erro', 'Decisão de ponto inválida.');
        } else {
            Db::atualizar('pontos', $pontoId, [
                'status' => $decisao,
                'justificativa' => $observacao ?: null,
            ]);
            auditar(
                $decisao === 'valido' ? 'ponto_aprovado' : 'ponto_recusado',
                'pontos',
                $pontoId,
                $observacao ?: $ponto['tipo']
            );
            flash('ok', $decisao === 'valido' ? 'Batida aprovada.' : 'Batida recusada.');
        }
    }

    if ($acao === 'decidir_disponibilidade') {
        $disponibilidadeId = entrada_int('disponibilidade_id');
        $decisao = entrada('decisao');
        $registro = $disponibilidadeId ? Db::porId('disponibilidade', $disponibilidadeId) : null;

        if (!$registro || (int) $registro['disponivel'] !== 1 || $registro['status'] !== 'pendente') {
            flash('erro', 'A solicitação não está mais disponível para decisão.');
        } elseif (!in_array($decisao, ['aprovada', 'recusada'], true)) {
            flash('erro', 'Decisão de disponibilidade inválida.');
        } else {
            Db::atualizar('disponibilidade', $disponibilidadeId, [
                'status' => $decisao,
                'decidido_por_usuario_id' => Auth::id(),
                'decidido_em' => date('Y-m-d H:i:s'),
                'motivo_decisao' => $observacao ?: null,
            ]);
            auditar(
                $decisao === 'aprovada' ? 'disponibilidade_aprovada' : 'disponibilidade_recusada',
                'disponibilidade',
                $disponibilidadeId,
                $observacao ?: $registro['data']
            );
            flash('ok', $decisao === 'aprovada' ? 'Disponibilidade aprovada.' : 'Disponibilidade recusada.');
        }
    }

    voltar_para('admin/aprovacoes.php');
}

$de = entrada('de', 'get') ?: date('Y-m-d', strtotime('-31 days'));
$ate = entrada('ate', 'get') ?: date('Y-m-d', strtotime('+90 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) { $de = date('Y-m-d', strtotime('-31 days')); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) { $ate = date('Y-m-d', strtotime('+90 days')); }

$batidas = Db::todos(
    'pontos',
    'status = :status AND data BETWEEN :de AND :ate',
    ['status' => 'pendente_revisao', 'de' => $de, 'ate' => $ate],
    ['ordem' => 'data_hora ASC', 'limite' => 300]
);
$disponibilidades = Db::todos(
    'disponibilidade',
    'disponivel = :disponivel AND status = :status AND data BETWEEN :de AND :ate',
    ['disponivel' => 1, 'status' => 'pendente', 'de' => $de, 'ate' => $ate],
    ['ordem' => 'data ASC, criado_em ASC', 'limite' => 300]
);

$pessoas = [];
foreach (Db::todos('funcionarios', '', [], ['ordem' => 'nome', 'colunas' => 'id, nome, matricula, cargo']) as $pessoa) {
    $pessoas[(int) $pessoa['id']] = $pessoa;
}

cabecalho(
    'Aprovações',
    'aprovacoes',
    'Fila de aprovações',
    'Decida sobre batidas em revisão e solicitações de disponibilidade para hora extra.'
);
?>

<div class="card">
  <div class="card-head"><div><h3>Período</h3><p>Os filtros se aplicam às duas filas.</p></div></div>
  <form method="get" class="form-grid compact">
    <label>De<input type="date" name="de" value="<?= e($de) ?>"></label>
    <label>Até<input type="date" name="ate" value="<?= e($ate) ?>"></label>
    <button class="btn btn-primary" type="submit">Aplicar filtro</button>
  </form>
</div>

<div class="metrics-grid">
  <article class="metric-card"><div class="metric-icon icon-orange"></div><div><span class="metric-label">Batidas pendentes</span><strong class="metric-value"><?= count($batidas) ?></strong><span class="metric-trend down">precisam de conferência</span></div></article>
  <article class="metric-card"><div class="metric-icon icon-blue"></div><div><span class="metric-label">Disponibilidades pendentes</span><strong class="metric-value"><?= count($disponibilidades) ?></strong><span class="metric-trend up">para hora extra</span></div></article>
</div>

<div class="card">
  <div class="card-head"><div><h3>Batidas em revisão</h3><p>Registros enviados para conferência por geofence ou precisão do GPS.</p></div></div>
  <?php if (!$batidas): ?>
    <?= vazio('Nenhuma batida pendente', 'Não há registros de ponto aguardando decisão no período selecionado.') ?>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Funcionário</th><th>Registro</th><th>Data e hora</th><th>Localização</th><th>Justificativa da decisão</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($batidas as $batida): $pessoa = $pessoas[(int) $batida['funcionario_id']] ?? null; ?>
        <tr>
          <td><b><?= e($pessoa['nome'] ?? 'Funcionário removido') ?></b><div class="muted small mono"><?= e($pessoa['matricula'] ?? '—') ?></div></td>
          <td><?= e(Ponto::ROTULOS[$batida['tipo']] ?? $batida['tipo']) ?></td>
          <td class="mono small"><?= e(data_br($batida['data_hora'], true)) ?></td>
          <td class="muted small">
            <?php if ($batida['latitude'] !== null): ?>
              <?= e(number_format((float) $batida['latitude'], 5, '.', '')) ?>, <?= e(number_format((float) $batida['longitude'], 5, '.', '')) ?>
              <?php if ($batida['precisao_gps'] !== null): ?><div>Precisão: ±<?= e(number_format((float) $batida['precisao_gps'], 0, ',', '.')) ?> m</div><?php endif; ?>
            <?php else: ?>Sem GPS<?php endif; ?>
          </td>
          <td>
            <form method="post" style="display:flex;gap:6px;align-items:center;min-width:280px">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="decidir_ponto">
              <input type="hidden" name="ponto_id" value="<?= (int) $batida['id'] ?>">
              <input type="text" name="observacao" maxlength="255" placeholder="Motivo (opcional)">
              <button class="btn btn-success" type="submit" name="decisao" value="valido">Aprovar</button>
              <button class="btn btn-danger" type="submit" name="decisao" value="rejeitado" data-confirma="Recusar esta batida?">Recusar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><div><h3>Disponibilidade para hora extra</h3><p>Solicitações de pessoas que marcaram disponibilidade no período.</p></div></div>
  <?php if (!$disponibilidades): ?>
    <?= vazio('Nenhuma disponibilidade pendente', 'Não há solicitações aguardando decisão no período selecionado.') ?>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Funcionário</th><th>Data</th><th>Período</th><th>Solicitado em</th><th>Motivo da decisão</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($disponibilidades as $disponibilidade): $pessoa = $pessoas[(int) $disponibilidade['funcionario_id']] ?? null; ?>
        <tr>
          <td><b><?= e($pessoa['nome'] ?? 'Funcionário removido') ?></b><div class="muted small"><?= e($pessoa['cargo'] ?? '') ?></div></td>
          <td class="mono"><?= e(data_br($disponibilidade['data'])) ?></td>
          <td><?= e(ucfirst($disponibilidade['periodo'])) ?></td>
          <td class="muted small mono"><?= e(data_br($disponibilidade['criado_em'], true)) ?></td>
          <td>
            <form method="post" style="display:flex;gap:6px;align-items:center;min-width:280px">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="decidir_disponibilidade">
              <input type="hidden" name="disponibilidade_id" value="<?= (int) $disponibilidade['id'] ?>">
              <input type="text" name="observacao" maxlength="255" placeholder="Motivo (opcional)">
              <button class="btn btn-success" type="submit" name="decisao" value="aprovada">Aprovar</button>
              <button class="btn btn-danger" type="submit" name="decisao" value="recusada" data-confirma="Recusar esta disponibilidade?">Recusar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php rodape(); ?>
