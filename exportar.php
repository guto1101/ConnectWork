<?php
/**
 * ConnectWork — Exportação CSV
 *
 * Um único ponto de saída para planilha. O alcance de cada exportação é
 * o mesmo da tela correspondente: funcionário exporta o próprio ponto,
 * gerente exporta a equipe, administrador exporta a empresa.
 *
 * O CSV sai com BOM UTF-8 e ponto e vírgula, que é o que o Excel em
 * português abre sem pedir configuração.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ponto.php';

Auth::exigirLogin();

$tipo = entrada('tipo', 'get') ?: 'meu_ponto';
$de   = entrada('de', 'get');
$ate  = entrada('ate', 'get');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de))  { $de  = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) { $ate = date('Y-m-d'); }

$admin   = in_array(Auth::nivel(), ['admin', 'master'], true);
$gestao  = Auth::ehGestao();
$fid     = Auth::funcionarioId();

$nomes = [];
foreach (Db::todos('funcionarios', '', [], ['colunas' => 'id, nome, matricula']) as $f) {
    $nomes[(int) $f['id']] = $f;
}

/** Envia o arquivo e encerra. */
function baixar_csv(string $arquivo, array $cabecalho, array $linhas, string $tipo): void
{
    auditar('dados_exportados', 'exportacoes', null, $tipo . ' / ' . count($linhas) . ' linha(s)');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $arquivo . '"');
    header('X-Content-Type-Options: nosniff');

    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");           // BOM: Excel abre com acento correto
    fputcsv($saida, $cabecalho, ';');
    foreach ($linhas as $l) {
        fputcsv($saida, $l, ';');
    }
    fclose($saida);
    exit;
}

switch ($tipo) {

    // -----------------------------------------------------------------
    case 'meu_ponto':
        if (!$fid) { exit('Sua conta não tem cadastro de funcionário.'); }
        $registros = Db::todos('pontos',
            'funcionario_id = :f AND data BETWEEN :de AND :ate AND status <> :rej',
            ['f' => $fid, 'de' => $de, 'ate' => $ate, 'rej' => 'rejeitado'],
            ['ordem' => 'data_hora ASC']);

        $linhas = [];
        foreach ($registros as $r) {
            $linhas[] = [
                date('d/m/Y', strtotime($r['data'])),
                date('H:i:s', strtotime($r['data_hora'])),
                Ponto::ROTULOS[$r['tipo']],
                $r['latitude'] !== null ? number_format((float) $r['latitude'], 6, ',', '') : '',
                $r['longitude'] !== null ? number_format((float) $r['longitude'], 6, ',', '') : '',
                $r['precisao_gps'] !== null ? number_format((float) $r['precisao_gps'], 0, ',', '') : '',
                $r['dentro_cerca'] === null ? 'sem cerca' : ((int) $r['dentro_cerca'] === 1 ? 'dentro' : 'fora'),
                $r['status'],
            ];
        }
        baixar_csv('meu-ponto-' . $de . '-a-' . $ate . '.csv',
            ['Data', 'Hora', 'Registro', 'Latitude', 'Longitude', 'Precisao (m)', 'Cerca', 'Situacao'], $linhas, 'meu_ponto');
        break;

    // -----------------------------------------------------------------
    case 'espelho':
        if (!$gestao) { http_response_code(403); exit('Exportação restrita a gestores.'); }

        $equipe = Auth::equipeVisivel();
        $where  = 'data BETWEEN :de AND :ate AND status <> :rej';
        $params = ['de' => $de, 'ate' => $ate, 'rej' => 'rejeitado'];
        if ($equipe === []) {
            $where .= ' AND 1 = 0';
        } elseif ($equipe !== null) {
            $where .= ' AND funcionario_id IN (' . implode(',', array_map('intval', $equipe)) . ')';
        }

        $registros = Db::todos('pontos', $where, $params, ['ordem' => 'funcionario_id ASC, data_hora ASC']);

        // Totais por funcionário e dia
        $porDia = [];
        foreach ($registros as $r) {
            $porDia[(int) $r['funcionario_id']][$r['data']][] = $r;
        }

        $linhas = [];
        foreach ($porDia as $funcId => $dias) {
            foreach ($dias as $dia => $bs) {
                $entrada = $saida = '';
                foreach ($bs as $b) {
                    if ($b['tipo'] === 'entrada' && $entrada === '') { $entrada = date('H:i', strtotime($b['data_hora'])); }
                    if ($b['tipo'] === 'saida') { $saida = date('H:i', strtotime($b['data_hora'])); }
                }
                $linhas[] = [
                    $nomes[$funcId]['matricula'] ?? '',
                    $nomes[$funcId]['nome'] ?? '',
                    date('d/m/Y', strtotime($dia)),
                    $entrada,
                    $saida,
                    count($bs),
                    Ponto::formatarMinutos(Ponto::minutosTrabalhados($bs)),
                ];
            }
        }
        baixar_csv('espelho-ponto-' . $de . '-a-' . $ate . '.csv',
            ['Matricula', 'Funcionario', 'Data', 'Entrada', 'Saida', 'Batidas', 'Trabalhado'], $linhas, 'espelho_ponto');
        break;

    // -----------------------------------------------------------------
    case 'funcionarios':
        if (!$admin) { http_response_code(403); exit('Exportação restrita ao administrador.'); }
        $lista = Db::todos('funcionarios', '', [], ['ordem' => 'nome']);
        $deptos = [];
        foreach (Db::todos('departamentos') as $d) { $deptos[(int) $d['id']] = $d['nome']; }

        $linhas = [];
        foreach ($lista as $f) {
            $linhas[] = [
                $f['matricula'], $f['nome'], $f['cargo'] ?? '',
                $deptos[(int) $f['departamento_id']] ?? '',
                $nomes[(int) $f['gestor_id']]['nome'] ?? '',
                $f['email'] ?? '', $f['telefone'] ?? '',
                $f['data_admissao'] ? date('d/m/Y', strtotime($f['data_admissao'])) : '',
                $f['status'],
            ];
        }
        baixar_csv('funcionarios.csv',
            ['Matricula', 'Nome', 'Cargo', 'Departamento', 'Gestor', 'E-mail', 'Telefone', 'Admissao', 'Situacao'], $linhas, 'funcionarios');
        break;

    // -----------------------------------------------------------------
    case 'ouvidoria':
        if (!$admin) { http_response_code(403); exit('Exportação restrita ao administrador.'); }
        $lista = Db::todos('ouvidoria', 'criado_em BETWEEN :de AND :ate',
            ['de' => $de . ' 00:00:00', 'ate' => $ate . ' 23:59:59'], ['ordem' => 'criado_em DESC']);

        $linhas = [];
        foreach ($lista as $o) {
            $linhas[] = [
                date('d/m/Y H:i', strtotime($o['criado_em'])),
                $o['assunto'], $o['categoria'], $o['prioridade'], $o['status'],
                (int) $o['anonimo'] === 1 ? 'anonimo' : ($nomes[(int) $o['funcionario_id']]['nome'] ?? 'identificado'),
            ];
        }
        baixar_csv('ouvidoria-' . $de . '-a-' . $ate . '.csv',
            ['Abertura', 'Assunto', 'Categoria', 'Prioridade', 'Situacao', 'Origem'], $linhas, 'ouvidoria');
        break;

    // -----------------------------------------------------------------
    case 'sugestoes':
        if (!$admin) { http_response_code(403); exit('Exportação restrita ao administrador.'); }
        $lista = Db::todos('sugestoes', '', [], ['ordem' => 'criado_em DESC']);

        $linhas = [];
        foreach ($lista as $s) {
            $linhas[] = [
                date('d/m/Y', strtotime($s['criado_em'])),
                $s['titulo'], $s['area'] ?? '', $s['status'],
                (int) $s['anonima'] === 1 ? 'anonima' : ($nomes[(int) $s['funcionario_id']]['nome'] ?? ''),
                $s['retorno'] ?? '',
            ];
        }
        baixar_csv('sugestoes.csv',
            ['Data', 'Titulo', 'Area', 'Situacao', 'Autor', 'Devolutiva'], $linhas, 'sugestoes');
        break;

    // -----------------------------------------------------------------
    default:
        http_response_code(400);
        exit('Tipo de exportação desconhecido.');
}
