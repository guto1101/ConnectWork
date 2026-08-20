<?php
/**
 * ConnectWork — Cerca virtual (geofence)
 *
 * A avaliação acontece inteiramente no servidor. O navegador envia
 * latitude, longitude e precisão; quem decide se a batida está dentro da
 * área autorizada é este arquivo. Um cliente adulterado pode mentir sobre
 * onde está, mas não pode mentir sobre o veredito.
 */

require_once __DIR__ . '/db.php';

final class Geo
{
    private const RAIO_TERRA_M = 6371000.0;

    /** Distância em metros entre dois pontos (fórmula de Haversine). */
    public static function distancia(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return self::RAIO_TERRA_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function coordenadaValida($lat, $lon): bool
    {
        if (!is_numeric($lat) || !is_numeric($lon)) {
            return false;
        }
        $lat = (float) $lat;
        $lon = (float) $lon;
        // (0,0) fica no Atlântico e é o valor que aparece quando o
        // navegador falha em obter posição — tratamos como inválido.
        if (abs($lat) < 0.0001 && abs($lon) < 0.0001) {
            return false;
        }
        return $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180;
    }

    /**
     * Avalia uma posição contra as cercas ativas da empresa.
     *
     * @return array{
     *   cerca_id:?int, cerca_nome:?string, distancia:?float,
     *   dentro:?bool, precisao_suficiente:bool, tem_cerca:bool, motivo:string
     * }
     */
    public static function avaliar(?float $lat, ?float $lon, ?float $precisao): array
    {
        $config = Db::um('empresa_config', 'empresa_id = :cw_emp2', ['cw_emp2' => Db::empresaId()])
            ?: ['precisao_maxima_metros' => 100, 'exigir_cerca' => 1, 'exigir_gps' => 1];

        $limitePrecisao = (float) $config['precisao_maxima_metros'];
        $cercaPadraoId = !empty($config['cerca_padrao_id']) ? (int) $config['cerca_padrao_id'] : null;
        $cercas = $cercaPadraoId
            ? Db::todos('cercas_virtuais', 'id = :padrao AND ativa = 1', ['padrao' => $cercaPadraoId], ['ordem' => 'nome'])
            : Db::todos('cercas_virtuais', 'ativa = 1', [], ['ordem' => 'nome']);

        $resultado = [
            'cerca_id'            => null,
            'cerca_nome'          => null,
            'distancia'           => null,
            'dentro'              => null,
            'precisao_suficiente' => true,
            'tem_cerca'           => count($cercas) > 0,
            'motivo'              => '',
        ];

        if ($lat === null || $lon === null) {
            $resultado['precisao_suficiente'] = false;
            $resultado['motivo'] = 'Sem coordenadas.';
            return $resultado;
        }

        // Precisão ruim não é reprovação automática: a batida entra como
        // pendente de revisão, porque o funcionário pode estar em local
        // com sinal fraco e o ponto não pode se perder por causa disso.
        if ($precisao !== null && $precisao > $limitePrecisao) {
            $resultado['precisao_suficiente'] = false;
            $resultado['motivo'] = sprintf(
                'Precisão de %.0f m acima do limite de %.0f m.', $precisao, $limitePrecisao
            );
        }

        if (!$resultado['tem_cerca']) {
            $resultado['motivo'] = $resultado['motivo'] ?: 'Nenhuma cerca cadastrada.';
            return $resultado;
        }

        // Sem padrão, fica com a cerca mais próxima; dentro de qualquer uma
        // já basta. Com padrão configurado, a lista contém somente essa cerca.
        $melhor = null;
        foreach ($cercas as $c) {
            $d = self::distancia($lat, $lon, (float) $c['latitude'], (float) $c['longitude']);
            $dentro = $d <= (float) $c['raio_metros'];
            if ($melhor === null
                || ($dentro && !$melhor['dentro'])
                || ($dentro === $melhor['dentro'] && $d < $melhor['distancia'])) {
                $melhor = ['cerca' => $c, 'distancia' => $d, 'dentro' => $dentro];
            }
        }

        $resultado['cerca_id']   = (int) $melhor['cerca']['id'];
        $resultado['cerca_nome'] = $melhor['cerca']['nome'];
        $resultado['distancia']  = round($melhor['distancia'], 2);
        $resultado['dentro']     = $melhor['dentro'];

        if (!$melhor['dentro']) {
            $resultado['motivo'] = sprintf(
                'A %s m de %s (raio de %s m).',
                number_format($melhor['distancia'], 0, ',', '.'),
                $melhor['cerca']['nome'],
                number_format((float) $melhor['cerca']['raio_metros'], 0, ',', '.')
            );
        }

        return $resultado;
    }

    /** Texto curto para exibir junto da batida. */
    public static function resumo(array $avaliacao): string
    {
        if (!$avaliacao['tem_cerca']) {
            return 'Sem cerca configurada';
        }
        if ($avaliacao['dentro'] === true) {
            return 'Dentro de ' . $avaliacao['cerca_nome'];
        }
        return 'Fora da área — ' . $avaliacao['motivo'];
    }
}
