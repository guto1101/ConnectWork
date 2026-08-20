<?php
/**
 * ConnectWork — Suíte de testes de segurança e regras de negócio
 *
 * Prova, contra o banco real, que as invariantes do sistema se sustentam:
 *
 *   • uma empresa nunca lê, altera ou exclui dados de outra;
 *   • SQL livre sem filtro de empresa é recusado pela camada Db;
 *   • a distância Haversine e a avaliação de cerca funcionam;
 *   • a sequência do ponto (entrada→pausa→retorno→saída) é respeitada;
 *   • coordenada (0,0) é rejeitada;
 *   • o alcance do gerente cobre a própria equipe e não além.
 *
 * Cria duas empresas de teste (__TESTE Alfa / __TESTE Beta), roda tudo e
 * apaga o que criou no final — passando ou falhando.
 *
 * NÃO deixe este arquivo acessível em produção.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/seguranca.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/geo.php';
require_once __DIR__ . '/includes/ponto.php';

$cli = PHP_SAPI === 'cli';
$nl  = $cli ? "\n" : "<br>\n";

if (!$cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">';
    echo '<title>Testes — ConnectWork</title>';
    echo '<style>body{font:15px/1.6 system-ui,Segoe UI,Roboto,sans-serif;max-width:820px;margin:30px auto;padding:0 16px;color:#0f172a}'
       . '.ok{color:#059669}.fail{color:#dc2626;font-weight:700}h1{font-size:1.4rem}'
       . 'code{background:#f1f5f9;padding:1px 5px;border-radius:4px}hr{border:0;border-top:1px solid #e2e8f0;margin:18px 0}</style>';
    echo '</head><body><h1>ConnectWork — Testes de segurança e regras</h1>';
}

$total = 0;
$falhas = 0;

/** Registra o resultado de uma verificação. */
function checar(string $descricao, bool $condicao): void
{
    global $total, $falhas, $nl;
    $total++;
    if ($condicao) {
        echo '  [OK]   ' . $descricao . $nl;
    } else {
        $falhas++;
        echo (PHP_SAPI === 'cli' ? '  [FALHA] ' : '  <span class="fail">[FALHA]</span> ') . $descricao . $nl;
    }
}

/** Simula o login de um usuário definindo a sessão como o Auth faria. */
function entrarComo(int $usuarioId, int $empresaId, string $nivel, ?int $funcionarioId = null): void
{
    $_SESSION['cw_usuario_id']     = $usuarioId;
    $_SESSION['cw_empresa_id']     = $empresaId;
    $_SESSION['cw_nivel']          = $nivel;
    $_SESSION['cw_funcionario_id'] = $funcionarioId;
    // Zera qualquer escolha de master anterior
    $ref = new ReflectionClass('Db');
    foreach (['empresaMaster' => null, 'modoPlataforma' => false] as $prop => $valor) {
        if ($ref->hasProperty($prop)) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, $valor);
        }
    }
}

$pdo = conexao();
$_SESSION = [];

echo $nl . '── Preparando empresas de teste ──' . $nl;

// Limpa restos de execuções anteriores
$pdo->exec("DELETE FROM empresas WHERE nome IN ('__TESTE Alfa', '__TESTE Beta')");

$pdo->prepare("INSERT INTO empresas (nome, status) VALUES ('__TESTE Alfa', 'ativa')")->execute();
$alfaId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO empresas (nome, status) VALUES ('__TESTE Beta', 'ativa')")->execute();
$betaId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO empresa_config (empresa_id) VALUES (:e)')->execute(['e' => $alfaId]);
$pdo->prepare('INSERT INTO empresa_config (empresa_id) VALUES (:e)')->execute(['e' => $betaId]);

// Um admin em cada empresa
$mkUser = static function (PDO $pdo, ?int $emp, string $nome, string $user, string $nivel): int {
    $pdo->prepare(
        'INSERT INTO usuarios (empresa_id, nome, email, usuario, senha_hash, nivel, ativo)
         VALUES (:emp, :nome, :email, :user, :hash, :nivel, 1)'
    )->execute([
        'emp' => $emp, 'nome' => $nome, 'email' => $user . '@teste.local',
        'user' => $user, 'hash' => password_hash('senhaTeste123', PASSWORD_DEFAULT), 'nivel' => $nivel,
    ]);
    return (int) $pdo->lastInsertId();
};

$alfaAdminId = $mkUser($pdo, $alfaId, 'Admin Alfa', '__t_alfa_adm', 'admin');
$betaAdminId = $mkUser($pdo, $betaId, 'Admin Beta', '__t_beta_adm', 'admin');

echo "Alfa=#$alfaId  Beta=#$betaId" . $nl;

// ---------------------------------------------------------------------
echo $nl . '── 1. Isolamento de LEITURA ──' . $nl;
// ---------------------------------------------------------------------

// Cada empresa cadastra um departamento próprio
entrarComo($alfaAdminId, $alfaId, 'admin');
$deptoAlfa = Db::inserir('departamentos', ['nome' => 'Setor Alfa', 'ativo' => 1]);

entrarComo($betaAdminId, $betaId, 'admin');
$deptoBeta = Db::inserir('departamentos', ['nome' => 'Setor Beta', 'ativo' => 1]);

// Beta lista departamentos: só pode ver o seu
$vistosBeta = array_map(static fn($d) => (int) $d['id'], Db::todos('departamentos'));
checar('Beta enxerga o próprio departamento', in_array($deptoBeta, $vistosBeta, true));
checar('Beta NÃO enxerga o departamento da Alfa', !in_array($deptoAlfa, $vistosBeta, true));

// Beta tenta ler o departamento da Alfa por id → deve vir vazio
checar('porId cruzado devolve nada (Beta lendo depto da Alfa)', Db::porId('departamentos', $deptoAlfa) === null);

// Alfa vê o seu e não o da Beta
entrarComo($alfaAdminId, $alfaId, 'admin');
$vistosAlfa = array_map(static fn($d) => (int) $d['id'], Db::todos('departamentos'));
checar('Alfa enxerga o próprio departamento', in_array($deptoAlfa, $vistosAlfa, true));
checar('Alfa NÃO enxerga o departamento da Beta', !in_array($deptoBeta, $vistosAlfa, true));

// ---------------------------------------------------------------------
echo $nl . '── 2. Isolamento de ESCRITA ──' . $nl;
// ---------------------------------------------------------------------

// Alfa tenta atualizar o departamento da Beta → 0 linhas afetadas
$linhas = Db::atualizar('departamentos', $deptoBeta, ['nome' => 'Invadido']);
checar('UPDATE cruzado não afeta nenhuma linha', $linhas === 0);

$beta = $pdo->query('SELECT nome FROM departamentos WHERE id = ' . $deptoBeta)->fetchColumn();
checar('Nome do departamento da Beta permanece intacto', $beta === 'Setor Beta');

// INSERT ignora empresa_id vindo "de fora": Alfa tenta forjar empresa_id = Beta
$forjadoId = Db::inserir('departamentos', ['nome' => 'Forjado', 'ativo' => 1, 'empresa_id' => $betaId]);
$donoForjado = (int) $pdo->query('SELECT empresa_id FROM departamentos WHERE id = ' . $forjadoId)->fetchColumn();
checar('INSERT descarta empresa_id forjado e grava a empresa da sessão', $donoForjado === $alfaId);

// ---------------------------------------------------------------------
echo $nl . '── 3. Isolamento de EXCLUSÃO ──' . $nl;
// ---------------------------------------------------------------------

$removidas = Db::excluir('departamentos', $deptoBeta);   // Alfa tentando apagar o da Beta
checar('DELETE cruzado não remove nada', $removidas === 0);
$aindaExiste = (int) $pdo->query('SELECT COUNT(*) FROM departamentos WHERE id = ' . $deptoBeta)->fetchColumn();
checar('Departamento da Beta continua existindo', $aindaExiste === 1);

// ---------------------------------------------------------------------
echo $nl . '── 4. Trava do SQL livre ──' . $nl;
// ---------------------------------------------------------------------

$travou = false;
try {
    // Consulta em tabela de empresa SEM citar empresa_id deve ser recusada
    Db::consulta('SELECT * FROM funcionarios');
} catch (Throwable $e) {
    $travou = true;
}
checar('SQL livre sem filtro de empresa_id é recusado', $travou);

$passou = true;
try {
    Db::consulta('SELECT COUNT(*) AS t FROM funcionarios WHERE empresa_id = :cw_emp', Db::escopo());
} catch (Throwable $e) {
    $passou = false;
}
checar('SQL livre COM empresa_id = :cw_emp é aceito', $passou);

// ---------------------------------------------------------------------
echo $nl . '── 5. Geolocalização (Haversine e cerca) ──' . $nl;
// ---------------------------------------------------------------------

// Av. Paulista → Praça da Sé ≈ 2,7 km (tolerância ampla)
$d = Geo::distancia(-23.5613, -46.6560, -23.5505, -46.6333);
checar('Distância Paulista→Sé entre 2 e 4 km', $d > 2000 && $d < 4000);

$dZero = Geo::distancia(-23.5613, -46.6560, -23.5613, -46.6560);
checar('Distância de um ponto até ele mesmo é ~0', $dZero < 1.0);

checar('Coordenada (0,0) é rejeitada', Geo::coordenadaValida(0.0, 0.0) === false);
checar('Coordenada de São Paulo é aceita', Geo::coordenadaValida(-23.5613, -46.6560) === true);

// Cerca: dentro
entrarComo($alfaAdminId, $alfaId, 'admin');
Db::inserir('cercas_virtuais', [
    'nome' => 'Sede Alfa', 'latitude' => -23.5613, 'longitude' => -46.6560,
    'raio_metros' => 200, 'ativa' => 1,
]);
$dentro = Geo::avaliar(-23.5613, -46.6560, 15.0);
checar('Ponto no centro da cerca é avaliado como dentro', ($dentro['dentro'] ?? null) === true);

$fora = Geo::avaliar(-23.5505, -46.6333, 15.0);   // Praça da Sé, longe da cerca
checar('Ponto longe da cerca é avaliado como fora', ($fora['dentro'] ?? null) === false);

// ---------------------------------------------------------------------
echo $nl . '── 6. Sequência do ponto ──' . $nl;
// ---------------------------------------------------------------------

checar('Sem batidas, o próximo tipo é entrada', Ponto::proximoTipo(null) === 'entrada');
checar('Depois de entrada, próximo é pausa', Ponto::proximoTipo('entrada') === 'pausa');
checar('Depois de pausa, próximo é retorno', Ponto::proximoTipo('pausa') === 'retorno');
checar('Depois de saída, não há próximo', Ponto::proximoTipo('saida') === null);

checar('Após entrada, saída é permitida', in_array('saida', Ponto::tiposPermitidos('entrada'), true));
checar('Sem histórico, só entrada é permitida', Ponto::tiposPermitidos(null) === ['entrada']);
checar('Após pausa, entrada NÃO é permitida', !in_array('entrada', Ponto::tiposPermitidos('pausa'), true));

// minutos trabalhados: entrada 08:00, saída 12:00 → 240 min
$hoje = date('Y-m-d');
$mins = Ponto::minutosTrabalhados([
    ['tipo' => 'entrada', 'data_hora' => $hoje . ' 08:00:00'],
    ['tipo' => 'saida',   'data_hora' => $hoje . ' 12:00:00'],
]);
checar('Entrada 08:00 → saída 12:00 = 240 minutos', $mins === 240);

// entrada 08:00, pausa 12:00, retorno 13:00, saída 17:00 → 8h = 480 min
$mins2 = Ponto::minutosTrabalhados([
    ['tipo' => 'entrada', 'data_hora' => $hoje . ' 08:00:00'],
    ['tipo' => 'pausa',   'data_hora' => $hoje . ' 12:00:00'],
    ['tipo' => 'retorno', 'data_hora' => $hoje . ' 13:00:00'],
    ['tipo' => 'saida',   'data_hora' => $hoje . ' 17:00:00'],
]);
checar('Jornada com 1h de almoço = 480 minutos', $mins2 === 480);

// ---------------------------------------------------------------------
echo $nl . '── 7. Alcance do gerente ──' . $nl;
// ---------------------------------------------------------------------

require_once __DIR__ . '/includes/auth.php';

entrarComo($alfaAdminId, $alfaId, 'admin');

// Gerente (funcionário) + dois liderados + um de fora da equipe
$gerUserId = $mkUser($pdo, $alfaId, 'Gerente Alfa', '__t_alfa_ger', 'gerente');
$gerFuncId = Db::inserir('funcionarios', [
    'matricula' => '__T-GER', 'nome' => 'Gerente Alfa', 'usuario_id' => $gerUserId, 'status' => 'ativo',
]);
$sub1 = Db::inserir('funcionarios', ['matricula' => '__T-S1', 'nome' => 'Liderado 1', 'gestor_id' => $gerFuncId, 'status' => 'ativo']);
$sub2 = Db::inserir('funcionarios', ['matricula' => '__T-S2', 'nome' => 'Liderado 2', 'gestor_id' => $gerFuncId, 'status' => 'ativo']);
$outro = Db::inserir('funcionarios', ['matricula' => '__T-OUT', 'nome' => 'Fora da equipe', 'status' => 'ativo']);

// Agora "logamos" como o gerente
entrarComo($gerUserId, $alfaId, 'gerente', $gerFuncId);
$visiveis = Auth::equipeVisivel();

checar('Equipe do gerente inclui ele mesmo', in_array($gerFuncId, $visiveis, true));
checar('Equipe do gerente inclui o liderado 1', in_array($sub1, $visiveis, true));
checar('Equipe do gerente inclui o liderado 2', in_array($sub2, $visiveis, true));
checar('Equipe do gerente NÃO inclui quem é de fora', !in_array($outro, $visiveis, true));
checar('podeVerFuncionario: verdadeiro para liderado', Auth::podeVerFuncionario($sub1) === true);
checar('podeVerFuncionario: falso para quem é de fora', Auth::podeVerFuncionario($outro) === false);

// Admin enxerga todos (equipeVisivel devolve null = sem restrição)
entrarComo($alfaAdminId, $alfaId, 'admin');
checar('Admin não tem restrição de equipe (null)', Auth::equipeVisivel() === null);
checar('Admin pode ver qualquer funcionário da empresa', Auth::podeVerFuncionario($outro) === true);

// ---------------------------------------------------------------------
echo $nl . '── Limpando dados de teste ──' . $nl;
// ---------------------------------------------------------------------

$_SESSION = [];
// ON DELETE CASCADE remove usuários, funcionários, departamentos e cercas das empresas de teste
$pdo->exec("DELETE FROM empresas WHERE id IN ($alfaId, $betaId)");
$sobrou = (int) $pdo->query("SELECT COUNT(*) FROM empresas WHERE nome IN ('__TESTE Alfa', '__TESTE Beta')")->fetchColumn();
checar('Empresas de teste removidas ao final', $sobrou === 0);

// ---------------------------------------------------------------------
echo ($cli ? "\n" : '<hr>');
$passaram = $total - $falhas;
$resumo = "Resultado: $passaram/$total verificações passaram";
if ($falhas === 0) {
    echo ($cli ? "\033[32m$resumo — TUDO OK\033[0m\n" : '<h2 class="ok">' . $resumo . ' — TUDO OK</h2>');
} else {
    echo ($cli ? "\033[31m$resumo — $falhas FALHA(S)\033[0m\n" : '<h2 class="fail">' . $resumo . " — $falhas falha(s)</h2>");
}

if (!$cli) {
    echo '<p><a href="' . e(url('index.php')) . '">Voltar ao login</a> · lembre de apagar <code>testes.php</code> em produção.</p>';
    echo '</body></html>';
}

exit($falhas === 0 ? 0 : 1);
