<?php
/**
 * ConnectWork — Espelho de ponto (compartilhado)
 *
 * Usado pelo administrador (empresa inteira) e pelo gerente (só a sua
 * equipe). O alcance vem de Auth::equipeVisivel(), então a mesma tela
 * serve os dois sem duplicar regra de acesso.
 */

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/ponto.php';

/**
 * @param string $pagina caminho da própria página (para o redirect)
 * @param string $chave  item de menu a destacar
 */
function render_espelho_ponto(string $pagina, string $chave, string $titulo, string $subtitulo): void
{
    // ---- Ações de conferência ---------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'conferir') {
        csrf_exigir();
        $id    = entrada_int('ponto_id');
        $novo  = entrada('novo_status');
        $ponto = Db::porId('pontos', $id);

        if (!$ponto) {
            flash('erro', 'Registro não encontrado.');
        } elseif (!Auth::podeVerFuncionario((int) $ponto['funcionario_id'])) {
            flash('erro', 'Este registro não é da sua equipe.');
        } elseif (!in_array($novo, ['valido', 'rejeitado'], true)) {
            flash('erro', 'Ação inválida.');
        } else {
            Db::atualizar('pontos', $id, [
                'status'        => $novo,
                'justificativa' => mb_substr(entrada('justificativa'), 0, 255) ?: null,
            ]);
            auditar('ponto_conferido', 'pontos', $id, $novo);
            flash('ok', $novo === 'valido' ? 'Batida aprovada.' : 'Batida rejeitada.');
        }
        voltar_para($pagina);
    }

    // ---- Filtros ----------------------------------------------------
    $de     = entrada('de', 'get') ?: date('Y-m-d', strtotime('-6 days'));
    $ate    = entrada('ate', 'get') ?: date('Y-m-d');
    $filtro = entrada('filtro', 'get');
    $funcId = entrada_int('funcionario', 'get');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de))  { $de = date('Y-m-d', strtotime('-6 days')); }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) { $ate = date('Y-m-d'); }

    $equipe = Auth::equipeVisivel();
    $where  = 'data BETWEEN :de AND :ate';
    $params = ['de' => $de, 'ate' => $ate];

    if ($equipe === []) {
        $where .= ' AND 1 = 0';
    } elseif ($equipe !== null) {
        $where .= ' AND funcionario_id IN (' . implode(',', array_map('intval', $equipe)) . ')';
    }

    if ($funcId && Auth::podeVerFuncionario($funcId)) {
        $where .= ' AND funcionario_id = :fid';
        $params['fid'] = $funcId;
    }
    if ($filtro === 'revisao') {
        $where .= ' AND status = :st';
        $params['st'] = 'pendente_revisao';
    } elseif ($filtro === 'fora') {
        $where .= ' AND dentro_cerca = 0';
    }

    $registros = Db::todos('pontos', $where, $params, ['ordem' => 'data_hora DESC', 'limite' => 400]);

    $pessoas = $equipe === null
        ? Db::todos('funcionarios', '', [], ['ordem' => 'nome', 'colunas' => 'id, nome, matricula'])
        : ($equipe === [] ? [] : Db::todos('funcionarios',
            'id IN (' . implode(',', array_map('intval', $equipe)) . ')', [],
            ['ordem' => 'nome', 'colunas' => 'id, nome, matricula']));

    $mapa = [];
    foreach ($pessoas as $p) { $mapa[(int) $p['id']] = $p; }

    // Totais por funcionário e dia
    $porFuncDia = [];
    foreach ($registros as $r) {
        $porFuncDia[(int) $r['funcionario_id']][$r['data']][] = $r;
    }
    foreach ($porFuncDia as $f => $dias) {
        foreach ($dias as $d => $bs) {
            usort($bs, static fn($a, $b) => strcmp($a['data_hora'], $b['data_hora']));
            $porFuncDia[$f][$d] = $bs;
        }
    }

    $emRevisao = 0;
    $foraCerca = 0;
    foreach ($registros as $r) {
        if ($r['status'] === 'pendente_revisao') { $emRevisao++; }
        if ($r['dentro_cerca'] !== null && (int) $r['dentro_cerca'] === 0) { $foraCerca++; }
    }

    cabecalho($titulo, $chave, $titulo, $subtitulo,
        '<a class="btn btn-ghost" href="' . e(url('exportar.php?tipo=espelho&de=' . $de . '&ate=' . $ate)) . '">Exportar CSV</a>');
    ?>

    <div class="card">
      <div class="card-head"><div><h3>Filtros</h3><p>Período e situação</p></div></div>
      <form method="get" class="form-grid compact">
        <label>De<input type="date" name="de" value="<?= e($de) ?>"></label>
        <label>Até<input type="date" name="ate" value="<?= e($ate) ?>"></label>
        <label>Funcionário
          <select name="funcionario">
            <option value="">Todos</option>
            <?php foreach ($pessoas as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= $funcId === (int) $p['id'] ? 'selected' : '' ?>>
                <?= e($p['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Situação
          <select name="filtro">
            <option value="">Todas</option>
            <option value="revisao" <?= $filtro === 'revisao' ? 'selected' : '' ?>>Aguardando conferência</option>
            <option value="fora" <?= $filtro === 'fora' ? 'selected' : '' ?>>Fora da cerca</option>
          </select>
        </label>
        <button class="btn btn-primary" type="submit">Aplicar</button>
      </form>
    </div>

    <div class="metrics-grid">
      <article class="metric-card">
        <div class="metric-icon icon-blue"></div>
        <div>
          <span class="metric-label">Batidas no período</span>
          <strong class="metric-value"><?= count($registros) ?></strong>
          <span class="metric-trend up"><?= e(date('d/m', strtotime($de))) ?> a <?= e(date('d/m', strtotime($ate))) ?></span>
        </div>
      </article>
      <article class="metric-card">
        <div class="metric-icon icon-orange"></div>
        <div>
          <span class="metric-label">Aguardando conferência</span>
          <strong class="metric-value"><?= $emRevisao ?></strong>
          <span class="metric-trend down">precisa de decisão</span>
        </div>
      </article>
      <article class="metric-card">
        <div class="metric-icon icon-purple"></div>
        <div>
          <span class="metric-label">Fora da cerca</span>
          <strong class="metric-value"><?= $foraCerca ?></strong>
          <span class="metric-trend down">no período filtrado</span>
        </div>
      </article>
      <article class="metric-card">
        <div class="metric-icon icon-green"></div>
        <div>
          <span class="metric-label">Pessoas no alcance</span>
          <strong class="metric-value"><?= count($pessoas) ?></strong>
          <span class="metric-trend up"><?= Auth::nivel() === 'gerente' ? 'sua equipe' : 'empresa inteira' ?></span>
        </div>
      </article>
    </div>

    <div class="card">
      <div class="card-head"><div><h3>Totais por dia</h3><p>Horas apuradas descontando as pausas</p></div></div>

      <?php if (!$porFuncDia): ?>
        <?= vazio('Nenhum registro no período') ?>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Funcionário</th><th>Data</th><th>Entrada</th><th>Saída</th><th>Batidas</th><th>Trabalhado</th></tr></thead>
            <tbody>
            <?php foreach ($porFuncDia as $fId => $dias): ?>
              <?php foreach ($dias as $dia => $bs):
                  $entrada = null; $saida = null;
                  foreach ($bs as $b) {
                      if ($b['tipo'] === 'entrada' && $entrada === null) { $entrada = $b['data_hora']; }
                      if ($b['tipo'] === 'saida') { $saida = $b['data_hora']; }
                  } ?>
                <tr>
                  <td><b><?= e($mapa[$fId]['nome'] ?? 'Funcionário') ?></b></td>
                  <td class="mono"><?= e(date('d/m/Y', strtotime($dia))) ?></td>
                  <td class="mono"><?= $entrada ? e(date('H:i', strtotime($entrada))) : '—' ?></td>
                  <td class="mono"><?= $saida ? e(date('H:i', strtotime($saida))) : '—' ?></td>
                  <td class="mono"><?= count($bs) ?></td>
                  <td class="mono"><b><?= e(Ponto::formatarMinutos(Ponto::minutosTrabalhados($bs))) ?></b></td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head">
        <div><h3>Registros detalhados</h3><p>Cada batida com localização e situação</p></div>
      </div>

      <?php if (!$registros): ?>
        <?= vazio('Nenhuma batida no filtro escolhido') ?>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Funcionário</th><th>Quando</th><th>Registro</th><th>Local</th><th>Situação</th><th>Conferência</th></tr>
            </thead>
            <tbody>
            <?php foreach ($registros as $r): ?>
              <tr>
                <td><?= e($mapa[(int) $r['funcionario_id']]['nome'] ?? '—') ?></td>
                <td class="mono"><?= e(data_br($r['data_hora'], true)) ?></td>
                <td><?= e(Ponto::ROTULOS[$r['tipo']]) ?></td>
                <td class="mono small">
                  <?php if ($r['latitude'] !== null): ?>
                    <?= e(number_format((float) $r['latitude'], 5, '.', '')) ?>,
                    <?= e(number_format((float) $r['longitude'], 5, '.', '')) ?>
                    <?php if ($r['precisao_gps'] !== null): ?>
                      <div class="muted">±<?= e(number_format((float) $r['precisao_gps'], 0, ',', '.')) ?> m</div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="muted">sem GPS</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($r['status'] === 'pendente_revisao'): ?><?= badge('Conferir', 'yellow') ?>
                  <?php elseif ($r['status'] === 'rejeitado'): ?><?= badge('Rejeitada', 'red') ?>
                  <?php elseif ($r['dentro_cerca'] === null): ?><?= badge('Sem cerca', 'gray') ?>
                  <?php elseif ((int) $r['dentro_cerca'] === 1): ?><?= badge('No local', 'green') ?>
                  <?php else: ?>
                    <?= badge(number_format((float) $r['distancia_metros'], 0, ',', '.') . ' m fora', 'red') ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($r['status'] === 'pendente_revisao'): ?>
                    <form method="post" style="display:flex;gap:5px">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="acao" value="conferir">
                      <input type="hidden" name="ponto_id" value="<?= (int) $r['id'] ?>">
                      <button class="btn btn-success" type="submit" name="novo_status" value="valido">Aprovar</button>
                      <button class="btn btn-danger" type="submit" name="novo_status" value="rejeitado"
                              data-confirma="Rejeitar esta batida?">Rejeitar</button>
                    </form>
                  <?php elseif ($r['justificativa']): ?>
                    <span class="muted small"><?= e($r['justificativa']) ?></span>
                  <?php else: ?>
                    <span class="muted small">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php
    rodape();
}
