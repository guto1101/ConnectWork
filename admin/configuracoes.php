<?php
/**
 * ConnectWork — Configurações da empresa
 *
 * Reúne regras gerais de jornada, tolerância, GPS/geofence, feriados e a
 * leitura dos limites do plano. Os limites são apenas consultados aqui;
 * alterações de plano permanecem na plataforma.
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['admin']);

$config = Db::um('empresa_config', 'empresa_id = :empresa_config_id', ['empresa_config_id' => Db::empresaId()]);
if (!$config) {
    Db::inserir('empresa_config', []);
    $config = Db::um('empresa_config', 'empresa_id = :empresa_config_id', ['empresa_config_id' => Db::empresaId()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $acao = entrada('acao');

    if ($acao === 'salvar_regras') {
        $cercaPadraoId = entrada_int('cerca_padrao_id') ?: null;
        $cercaPadrao = $cercaPadraoId ? Db::porId('cercas_virtuais', $cercaPadraoId) : null;

        if ($cercaPadraoId && (!$cercaPadrao || (int) $cercaPadrao['ativa'] !== 1)) {
            flash('erro', 'Selecione uma cerca ativa da própria empresa ou deixe a opção em branco.');
        } else {
            Db::atualizar('empresa_config', (int) Db::empresaId(), [
                'exigir_cerca' => isset($_POST['exigir_cerca']) ? 1 : 0,
                'exigir_gps' => isset($_POST['exigir_gps']) ? 1 : 0,
                'precisao_maxima_metros' => max(10, min(2000, entrada_int('precisao') ?: 100)),
                'jornada_diaria_minutos' => max(0, min(1440, entrada_int('jornada') ?: 480)),
                'tolerancia_atraso_minutos' => max(0, min(120, entrada_int('tolerancia') ?: 10)),
                'cerca_padrao_id' => $cercaPadraoId,
            ]);
            auditar('configuracoes_atualizadas', 'empresa_config', (int) Db::empresaId());
            flash('ok', 'Configurações da empresa atualizadas.');
        }
    }

    if ($acao === 'salvar_feriado') {
        $id = entrada_int('id');
        $data = entrada('data');
        $nome = mb_substr(entrada('nome'), 0, 120);
        $tipo = entrada('tipo');
        $tipos = ['nacional', 'estadual', 'municipal', 'empresa'];
        $existente = $id ? Db::porId('feriados', $id) : null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $nome === '' || !in_array($tipo, $tipos, true)) {
            flash('erro', 'Informe uma data, nome e tipo de feriado válidos.');
        } elseif ($id && !$existente) {
            flash('erro', 'Feriado não encontrado.');
        } else {
            try {
                $dados = ['data' => $data, 'nome' => $nome, 'tipo' => $tipo];
                if ($existente) {
                    Db::atualizar('feriados', $id, $dados);
                    auditar('feriado_atualizado', 'feriados', $id, $data . ' / ' . $nome);
                } else {
                    $novoId = Db::inserir('feriados', $dados);
                    auditar('feriado_criado', 'feriados', $novoId, $data . ' / ' . $nome);
                }
                flash('ok', 'Feriado salvo.');
            } catch (Throwable $ex) {
                error_log('ConnectWork/feriados salvar: ' . $ex->getMessage());
                flash('erro', 'Já existe um feriado cadastrado para esta data.');
            }
        }
    }

    if ($acao === 'excluir_feriado') {
        $id = entrada_int('id');
        $feriado = $id ? Db::porId('feriados', $id) : null;
        if ($feriado) {
            Db::excluir('feriados', $id);
            auditar('feriado_excluido', 'feriados', $id, $feriado['data'] . ' / ' . $feriado['nome']);
            flash('ok', 'Feriado removido.');
        }
    }

    voltar_para('admin/configuracoes.php');
}

$config = Db::um('empresa_config', 'empresa_id = :empresa_config_id', ['empresa_config_id' => Db::empresaId()]);
$cercas = Db::todos('cercas_virtuais', '', [], ['ordem' => 'nome']);
$feriadoEdicaoId = entrada_int('editar_feriado', 'get');
$feriadoEdicao = $feriadoEdicaoId ? Db::porId('feriados', $feriadoEdicaoId) : null;
$feriados = Db::todos('feriados', 'data >= :inicio', ['inicio' => date('Y-01-01')], ['ordem' => 'data', 'limite' => 200]);
$plano = Db::planoDaEmpresa();
$ativos = Db::contar('funcionarios', 'status <> :desligado', ['desligado' => 'desligado']);
$recursos = [];
if (!empty($plano['recursos'])) {
    $json = json_decode((string) $plano['recursos'], true);
    $recursos = is_array($json) ? $json : [];
}

cabecalho(
    'Configurações',
    'configuracoes',
    'Configurações da empresa',
    'Regras de jornada, geofence, feriados e limites do plano.'
);
?>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h3>Regras de ponto</h3><p>Valem para todas as pessoas da empresa.</p></div></div>
    <form method="post" class="form-grid compact">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar_regras">
      <label class="check"><input type="checkbox" name="exigir_gps" value="1" <?= (int) $config['exigir_gps'] === 1 ? 'checked' : '' ?>> Exigir GPS para registrar ponto</label>
      <label class="check"><input type="checkbox" name="exigir_cerca" value="1" <?= (int) $config['exigir_cerca'] === 1 ? 'checked' : '' ?>> Bloquear batida fora da geofence</label>
      <label>Precisão máxima aceita (m)<input type="number" name="precisao" min="10" max="2000" value="<?= (int) $config['precisao_maxima_metros'] ?>"></label>
      <label>Jornada diária padrão (min)<input type="number" name="jornada" min="0" max="1440" value="<?= (int) $config['jornada_diaria_minutos'] ?>"></label>
      <label>Tolerância de atraso (min)<input type="number" name="tolerancia" min="0" max="120" value="<?= (int) $config['tolerancia_atraso_minutos'] ?>"></label>
      <label>Geofence padrão
        <select name="cerca_padrao_id">
          <option value="">Usar qualquer cerca ativa</option>
          <?php foreach ($cercas as $cerca): if ((int) $cerca['ativa'] !== 1) continue; ?>
            <option value="<?= (int) $cerca['id'] ?>" <?= (int) ($config['cerca_padrao_id'] ?? 0) === (int) $cerca['id'] ? 'selected' : '' ?>><?= e($cerca['nome']) ?> — <?= (int) $cerca['raio_metros'] ?> m</option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-success" type="submit">Salvar configurações</button>
    </form>
    <p class="note">A geofence padrão é usada como referência prioritária no ponto. Cadastre ou mantenha as cercas em <a href="<?= e(url('admin/cercas.php')) ?>">Cercas virtuais</a>.</p>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Plano contratado</h3><p>Limites definidos pela plataforma.</p></div></div>
    <?php if (!$plano || !$plano['plano_id']): ?>
      <?= vazio('Plano não vinculado', 'Solicite ao Administrador Master a vinculação de um plano para esta empresa.') ?>
    <?php else: ?>
      <div class="metrics-grid" style="grid-template-columns:1fr">
        <article class="metric-card"><div><span class="metric-label">Plano</span><strong class="metric-value" style="font-size:22px"><?= e($plano['nome']) ?></strong><span class="metric-trend up"><?= $ativos ?> de <?= (int) $plano['limite_funcionarios'] ?> funcionários ativos</span></div></article>
      </div>
      <div class="table-wrap"><table>
        <thead><tr><th>Recurso</th><th>Situação</th></tr></thead>
        <tbody>
        <?php foreach ($recursos as $recurso => $liberado): ?>
          <tr><td><?= e(ucfirst($recurso)) ?></td><td><?= $liberado ? badge('Disponível', 'green') : badge('Não contratado', 'gray') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h3>Feriados</h3><p>Cadastre os dias não trabalhados aplicáveis à empresa.</p></div>
    <?php if ($feriadoEdicao): ?><a class="btn btn-ghost" href="<?= e(url('admin/configuracoes.php')) ?>">Cancelar edição</a><?php endif; ?>
  </div>
  <form method="post" class="form-grid compact" style="margin-bottom:16px">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="salvar_feriado">
    <input type="hidden" name="id" value="<?= (int) ($feriadoEdicao['id'] ?? 0) ?>">
    <label>Data<input type="date" name="data" value="<?= e($feriadoEdicao['data'] ?? '') ?>" required></label>
    <label>Nome<input type="text" name="nome" maxlength="120" value="<?= e($feriadoEdicao['nome'] ?? '') ?>" placeholder="Ex.: Aniversário da cidade" required></label>
    <label>Tipo
      <select name="tipo">
        <?php foreach (['nacional' => 'Nacional', 'estadual' => 'Estadual', 'municipal' => 'Municipal', 'empresa' => 'Empresa'] as $valor => $rotulo): ?>
          <option value="<?= $valor ?>" <?= ($feriadoEdicao['tipo'] ?? 'empresa') === $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn-success" type="submit"><?= $feriadoEdicao ? 'Salvar feriado' : 'Adicionar feriado' ?></button>
  </form>

  <?php if (!$feriados): ?>
    <?= vazio('Nenhum feriado cadastrado', 'Cadastre os feriados que devem ser considerados no calendário da empresa.') ?>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Data</th><th>Nome</th><th>Tipo</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($feriados as $feriado): ?>
        <tr>
          <td class="mono"><?= e(data_br($feriado['data'])) ?></td>
          <td><b><?= e($feriado['nome']) ?></b></td>
          <td><?= e(ucfirst($feriado['tipo'])) ?></td>
          <td class="right">
            <a class="btn btn-ghost" href="<?= e(url('admin/configuracoes.php?editar_feriado=' . (int) $feriado['id'])) ?>">Editar</a>
            <form method="post" style="display:inline">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="excluir_feriado">
              <input type="hidden" name="id" value="<?= (int) $feriado['id'] ?>">
              <button class="btn btn-danger" type="submit" data-confirma="Remover o feriado <?= e($feriado['nome']) ?>?">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php rodape(); ?>
