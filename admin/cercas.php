<?php
/**
 * ConnectWork — Cercas virtuais e regras de ponto (administrador)
 *
 * Aqui o administrador define ONDE o ponto pode ser batido e com que
 * rigor. É de propósito que só o administrador mexe nisto: se o
 * funcionário pudesse cadastrar a própria área, a cerca não protegeria
 * nada.
 */

require_once __DIR__ . '/../includes/layout.php';

Auth::exigirNivel(['admin']);

// Garante que exista uma linha de configuração da empresa
if (!Db::um('empresa_config', 'empresa_id = :cw_emp2', ['cw_emp2' => Db::empresaId()])) {
    Db::inserir('empresa_config', []);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_cerca') {
        $id   = entrada_int('id');
        $nome = entrada('nome');
        $lat  = entrada('latitude');
        $lon  = entrada('longitude');
        $raio = entrada_int('raio') ?: 150;

        if ($nome === '' || !is_numeric($lat) || !is_numeric($lon)) {
            flash('erro', 'Preencha nome, latitude e longitude (use ponto como separador decimal).');
        } elseif ((float) $lat < -90 || (float) $lat > 90 || (float) $lon < -180 || (float) $lon > 180) {
            flash('erro', 'Coordenadas fora dos limites válidos.');
        } else {
            $dados = [
                'nome'        => mb_substr($nome, 0, 80),
                'latitude'    => (float) $lat,
                'longitude'   => (float) $lon,
                'raio_metros' => max(20, min(20000, $raio)),
            ];
            try {
                if ($id && Db::porId('cercas_virtuais', $id)) {
                    Db::atualizar('cercas_virtuais', $id, $dados);
                    flash('ok', 'Cerca atualizada.');
                } else {
                    $dados['ativa'] = 1;
                    Db::inserir('cercas_virtuais', $dados);
                    flash('ok', 'Cerca cadastrada.');
                }
                auditar('cerca_salva', 'cercas_virtuais', $id ?: null);
            } catch (Throwable $e) {
                flash('erro', 'Já existe uma cerca com esse nome.');
            }
        }
    } elseif ($acao === 'alternar') {
        $id = entrada_int('id');
        $c  = Db::porId('cercas_virtuais', $id);
        if ($c) {
            Db::atualizar('cercas_virtuais', $id, ['ativa' => (int) $c['ativa'] === 1 ? 0 : 1]);
            flash('ok', 'Situação da cerca atualizada.');
        }
    } elseif ($acao === 'excluir') {
        $id = entrada_int('id');
        if (Db::porId('cercas_virtuais', $id)) {
            Db::excluir('cercas_virtuais', $id);
            flash('ok', 'Cerca removida.');
        }
    } elseif ($acao === 'regras') {
        $config = Db::um('empresa_config', 'empresa_id = :cw_emp2', ['cw_emp2' => Db::empresaId()]);
        Db::atualizar('empresa_config', (int) $config['empresa_id'], [
            'exigir_cerca'           => isset($_POST['exigir_cerca']) ? 1 : 0,
            'exigir_gps'             => isset($_POST['exigir_gps']) ? 1 : 0,
            'precisao_maxima_metros' => max(10, min(2000, entrada_int('precisao') ?: 100)),
            'jornada_diaria_minutos' => max(0, min(1440, entrada_int('jornada') ?: 480)),
            'tolerancia_atraso_minutos' => max(0, min(120, entrada_int('tolerancia') ?: 10)),
        ]);
        flash('ok', 'Regras de ponto atualizadas.');
    }
    voltar_para('admin/cercas.php');
}

$edicao = null;
$edicaoId = entrada_int('editar', 'get');
if ($edicaoId) { $edicao = Db::porId('cercas_virtuais', $edicaoId); }

$cercas = Db::todos('cercas_virtuais', '', [], ['ordem' => 'nome']);
$config = Db::um('empresa_config', 'empresa_id = :cw_emp2', ['cw_emp2' => Db::empresaId()]);

cabecalho('Cercas virtuais', 'cercas', 'Cercas virtuais e regras de ponto',
    'Defina onde o ponto pode ser registrado e com que rigor.');
?>

<div class="card">
  <div class="card-head"><div><h3>Regras de ponto da empresa</h3><p>Valem para todos os funcionários</p></div></div>
  <form method="post" class="form-grid">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="regras">
    <label class="check"><input type="checkbox" name="exigir_gps" value="1" <?= (int) $config['exigir_gps'] === 1 ? 'checked' : '' ?>> Exigir GPS para bater ponto</label>
    <label class="check"><input type="checkbox" name="exigir_cerca" value="1" <?= (int) $config['exigir_cerca'] === 1 ? 'checked' : '' ?>> Bloquear batida fora da cerca</label>
    <label>Precisão máxima aceita (m)<input type="number" name="precisao" min="10" max="2000" value="<?= (int) $config['precisao_maxima_metros'] ?>"></label>
    <label>Jornada diária padrão (min)<input type="number" name="jornada" min="0" max="1440" value="<?= (int) $config['jornada_diaria_minutos'] ?>"></label>
    <label>Tolerância de atraso (min)<input type="number" name="tolerancia" min="0" max="120" value="<?= (int) $config['tolerancia_atraso_minutos'] ?>"></label>
    <button class="btn btn-success" type="submit">Salvar regras</button>
  </form>
  <p class="note">
    Com <b>bloquear batida fora da cerca</b> desligado, a batida fora da área é aceita mas fica marcada
    para conferência do gestor — em vez de ser recusada ou descartada. Batidas com precisão de GPS acima
    do limite também vão para conferência.
  </p>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div><h3><?= $edicao ? 'Editar cerca' : 'Nova cerca' ?></h3><p>Centro e raio da área permitida</p></div>
      <?php if ($edicao): ?><a class="btn btn-ghost" href="<?= e(url('admin/cercas.php')) ?>">Cancelar</a><?php endif; ?>
    </div>
    <form method="post" class="form-grid compact">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar_cerca">
      <input type="hidden" name="id" value="<?= e($edicao['id'] ?? '') ?>">
      <label class="wide">Nome<input type="text" name="nome" value="<?= e($edicao['nome'] ?? '') ?>" required></label>
      <label>Latitude<input type="text" name="latitude" value="<?= e($edicao['latitude'] ?? '') ?>" placeholder="-23.5613" required></label>
      <label>Longitude<input type="text" name="longitude" value="<?= e($edicao['longitude'] ?? '') ?>" placeholder="-46.6560" required></label>
      <label>Raio (m)<input type="number" name="raio" min="20" max="20000" value="<?= e($edicao['raio_metros'] ?? 150) ?>"></label>
      <button class="btn btn-success" type="submit"><?= $edicao ? 'Salvar cerca' : 'Cadastrar cerca' ?></button>
    </form>
    <p class="note">
      Dica: abra o Google Maps, clique com o botão direito no local e copie as coordenadas
      (latitude, longitude) que aparecem no topo do menu.
    </p>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Cercas cadastradas</h3><p><?= count($cercas) ?> cerca(s)</p></div></div>
    <?php if (!$cercas): ?>
      <?= vazio('Nenhuma cerca cadastrada', 'Sem cerca, o ponto é aceito de qualquer lugar e fica marcado para conferência.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nome</th><th>Coordenadas</th><th>Raio</th><th>Situação</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($cercas as $c): ?>
            <tr>
              <td><b><?= e($c['nome']) ?></b></td>
              <td class="mono small">
                <?= e(number_format((float) $c['latitude'], 5, '.', '')) ?>,
                <?= e(number_format((float) $c['longitude'], 5, '.', '')) ?>
              </td>
              <td class="mono"><?= (int) $c['raio_metros'] ?> m</td>
              <td><?= (int) $c['ativa'] === 1 ? badge('Ativa', 'green') : badge('Inativa', 'gray') ?></td>
              <td class="right" style="white-space:nowrap">
                <a class="btn btn-ghost" href="<?= e(url('admin/cercas.php?editar=' . (int) $c['id'])) ?>">Editar</a>
                <form method="post" style="display:inline">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="alternar">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="btn btn-ghost" type="submit"><?= (int) $c['ativa'] === 1 ? 'Desativar' : 'Ativar' ?></button>
                </form>
                <form method="post" style="display:inline">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="excluir">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="btn btn-danger" type="submit" data-confirma="Remover a cerca <?= e($c['nome']) ?>?">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php rodape(); ?>
