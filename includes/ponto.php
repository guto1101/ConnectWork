<?php
/**
 * ConnectWork — Regras do ponto eletrônico
 *
 * A sequência da jornada é validada aqui, não no navegador:
 *   (dia vazio) -> entrada
 *   entrada     -> pausa | saida
 *   pausa       -> retorno
 *   retorno     -> pausa | saida
 *   saida       -> dia encerrado
 *
 * A hora gravada é sempre a do servidor. O relógio do dispositivo do
 * funcionário não entra na conta.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/geo.php';

final class Ponto
{
    public const TIPOS = ['entrada', 'pausa', 'retorno', 'saida'];

    public const ROTULOS = [
        'entrada' => 'Entrada',
        'pausa'   => 'Início da pausa',
        'retorno' => 'Fim da pausa',
        'saida'   => 'Saída',
    ];

    /** Próximo tipo permitido, ou null quando a jornada do dia terminou. */
    public static function proximoTipo(?string $ultimo): ?string
    {
        switch ($ultimo) {
            case null:      return 'entrada';
            case 'entrada': return 'pausa';
            case 'pausa':   return 'retorno';
            case 'retorno': return 'pausa';
            case 'saida':   return null;
            default:        return null;
        }
    }

    /** Transições aceitas a partir do último registro do dia. */
    public static function tiposPermitidos(?string $ultimo): array
    {
        switch ($ultimo) {
            case null:      return ['entrada'];
            case 'entrada': return ['pausa', 'saida'];
            case 'pausa':   return ['retorno'];
            case 'retorno': return ['pausa', 'saida'];
            default:        return [];
        }
    }

    /** Batidas do funcionário em uma data, em ordem cronológica. */
    public static function doDia(int $funcionarioId, ?string $data = null): array
    {
        $data = $data ?: date('Y-m-d');
        return Db::todos(
            'pontos',
            'funcionario_id = :f AND data = :d AND status <> :rej',
            ['f' => $funcionarioId, 'd' => $data, 'rej' => 'rejeitado'],
            ['ordem' => 'data_hora ASC']
        );
    }

    public static function ultimoTipoDoDia(int $funcionarioId, ?string $data = null): ?string
    {
        $lista = self::doDia($funcionarioId, $data);
        if (!$lista) {
            return null;
        }
        return $lista[count($lista) - 1]['tipo'];
    }

    /**
     * Registra uma batida.
     *
     * @param array $dados tipo, latitude, longitude, precisao, token, origem
     * @return array{ok:bool, erro?:string, duplicado?:bool, ponto?:array, avaliacao?:array}
     */
    public static function registrar(int $funcionarioId, array $dados): array
    {
        $tipo = $dados['tipo'] ?? '';
        if (!in_array($tipo, self::TIPOS, true)) {
            return ['ok' => false, 'erro' => 'Tipo de registro inválido.'];
        }

        // Reenvio da mesma batida (rede instável) não cria duplicata: o
        // token do cliente tem índice único por empresa.
        $token = $dados['token'] ?? null;
        if ($token !== null && preg_match('/^[0-9a-f-]{36}$/i', (string) $token)) {
            $existente = Db::um('pontos', 'cliente_token = :t', ['t' => $token]);
            if ($existente) {
                return ['ok' => true, 'duplicado' => true, 'ponto' => $existente];
            }
        } else {
            $token = null;
        }

        $hoje    = date('Y-m-d');
        $ultimo  = self::ultimoTipoDoDia($funcionarioId, $hoje);
        $aceitos = self::tiposPermitidos($ultimo);

        if (!in_array($tipo, $aceitos, true)) {
            $msg = $ultimo === 'saida'
                ? 'A jornada de hoje já foi encerrada.'
                : ($ultimo === null
                    ? 'Comece o dia com a entrada.'
                    : 'Depois de "' . self::ROTULOS[$ultimo] . '" o próximo registro é "'
                      . self::ROTULOS[self::proximoTipo($ultimo)] . '".');
            return ['ok' => false, 'erro' => $msg];
        }

        // ---- Localização -------------------------------------------------
        $config = Db::um('empresa_config', 'empresa_id = :cw_emp2', ['cw_emp2' => Db::empresaId()])
            ?: ['exigir_cerca' => 1, 'exigir_gps' => 1, 'precisao_maxima_metros' => 100];

        $lat = $lon = $prec = null;
        if (Geo::coordenadaValida($dados['latitude'] ?? null, $dados['longitude'] ?? null)) {
            $lat  = (float) $dados['latitude'];
            $lon  = (float) $dados['longitude'];
            $prec = isset($dados['precisao']) && is_numeric($dados['precisao'])
                ? (float) $dados['precisao'] : null;
        }

        if ($lat === null && (int) $config['exigir_gps'] === 1) {
            return [
                'ok'  => false,
                'erro' => 'A empresa exige localização para bater ponto. '
                        . 'Autorize o acesso ao GPS no navegador e tente novamente.',
            ];
        }

        $av = Geo::avaliar($lat, $lon, $prec);

        // Fora da cerca com bloqueio ligado: recusa e explica a distância.
        if ((int) $config['exigir_cerca'] === 1 && $av['tem_cerca'] && $av['dentro'] === false) {
            return ['ok' => false, 'erro' => 'Você está fora da área autorizada. ' . $av['motivo'], 'avaliacao' => $av];
        }

        // Precisão baixa ou fora da cerca com bloqueio desligado: grava e
        // manda para revisão, em vez de descartar o registro.
        $status = ($av['precisao_suficiente'] && $av['dentro'] !== false)
            ? 'valido'
            : 'pendente_revisao';

        $id = Db::transacao(static function () use ($funcionarioId, $tipo, $lat, $lon, $prec, $av, $status, $token, $dados) {
            $pontoId = Db::inserir('pontos', [
                'funcionario_id'      => $funcionarioId,
                'tipo'                => $tipo,
                'data'                => date('Y-m-d'),
                'data_hora'           => date('Y-m-d H:i:s'),   // relógio do servidor
                'latitude'            => $lat,
                'longitude'           => $lon,
                'precisao_gps'        => $prec,
                'endereco'            => isset($dados['endereco']) ? mb_substr((string) $dados['endereco'], 0, 255) : null,
                'cerca_id'            => $av['cerca_id'],
                'dentro_cerca'        => $av['dentro'] === null ? null : (int) $av['dentro'],
                'distancia_metros'    => $av['distancia'],
                'precisao_suficiente' => (int) $av['precisao_suficiente'],
                'origem'              => in_array($dados['origem'] ?? '', ['web', 'totem', 'app'], true) ? $dados['origem'] : 'web',
                'ip'                  => ip_cliente(),
                'dispositivo'         => agente_cliente(),
                'cliente_token'       => $token,
                'status'              => $status,
            ]);

            if ($lat !== null) {
                Db::inserir('localizacoes', [
                    'funcionario_id' => $funcionarioId,
                    'origem'         => 'ponto',
                    'ponto_id'       => $pontoId,
                    'latitude'       => $lat,
                    'longitude'      => $lon,
                    'precisao_gps'   => $prec,
                    'endereco'       => isset($dados['endereco']) ? mb_substr((string) $dados['endereco'], 0, 255) : null,
                ]);
            }

            return $pontoId;
        });

        auditar('ponto_registrado', 'pontos', $id, $tipo . ' / ' . $status);

        return [
            'ok'        => true,
            'duplicado' => false,
            'ponto'     => Db::porId('pontos', $id),
            'avaliacao' => $av,
        ];
    }

    /**
     * Minutos trabalhados no dia, descontando pausas.
     * Uma jornada aberta é contada até agora.
     */
    public static function minutosTrabalhados(array $batidas): int
    {
        $total = 0;
        $abertura = null;

        foreach ($batidas as $b) {
            $ts = strtotime($b['data_hora']);
            if ($b['tipo'] === 'entrada' || $b['tipo'] === 'retorno') {
                $abertura = $ts;
            } elseif (($b['tipo'] === 'pausa' || $b['tipo'] === 'saida') && $abertura !== null) {
                $total += $ts - $abertura;
                $abertura = null;
            }
        }

        if ($abertura !== null) {
            $total += time() - $abertura;      // ainda em jornada
        }

        return (int) max(0, round($total / 60));
    }

    public static function formatarMinutos(int $minutos): string
    {
        return sprintf('%02dh%02d', intdiv($minutos, 60), $minutos % 60);
    }

    /** Resumo do dia pronto para a tela. */
    public static function resumoDoDia(int $funcionarioId, ?string $data = null): array
    {
        $batidas = self::doDia($funcionarioId, $data);
        $ultimo  = $batidas ? $batidas[count($batidas) - 1]['tipo'] : null;
        $minutos = self::minutosTrabalhados($batidas);

        return [
            'batidas'    => $batidas,
            'ultimo'     => $ultimo,
            'proximo'    => self::proximoTipo($ultimo),
            'permitidos' => self::tiposPermitidos($ultimo),
            'minutos'    => $minutos,
            'formatado'  => self::formatarMinutos($minutos),
            'em_jornada' => in_array($ultimo, ['entrada', 'retorno'], true),
            'encerrado'  => $ultimo === 'saida',
        ];
    }
}
