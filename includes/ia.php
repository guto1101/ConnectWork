<?php
/**
 * ConnectWork — Assistente
 *
 * Três provedores, escolhidos em includes/config.php (CW_IA_PROVEDOR):
 *
 *   local   responde com os NÚMEROS REAIS da empresa, calculados no
 *           MySQL. Não é uma IA e não finge ser: é um relatório
 *           conversacional. Funciona sem chave e sem internet.
 *   openai  chama a API da OpenAI.
 *   gemini  chama a API do Google Gemini.
 *
 * A chave nunca chega ao navegador: a chamada sai do servidor. O contexto
 * enviado ao provedor externo é sempre agregado (contagens e totais),
 * nunca a lista de pessoas — dado de funcionário não sai da empresa.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ponto.php';

final class IA
{
    /** Números reais da empresa, usados como contexto da resposta. */
    public static function contexto(): array
    {
        $hoje = date('Y-m-d');
        $mes  = date('Y-m-01');

        $minutosMes = 0;
        $porDia = [];
        foreach (Db::todos('pontos', 'data >= :i AND status <> :r',
                 ['i' => $mes, 'r' => 'rejeitado'], ['ordem' => 'data_hora ASC']) as $b) {
            $porDia[$b['funcionario_id'] . '|' . $b['data']][] = $b;
        }
        foreach ($porDia as $bs) { $minutosMes += Ponto::minutosTrabalhados($bs); }

        return [
            'empresa'            => $_SESSION['cw_empresa_nome'] ?? '',
            'funcionarios_ativos'=> Db::contar('funcionarios', 'status = :s', ['s' => 'ativo']),
            'funcionarios_afastados' => Db::contar('funcionarios', 'status = :s', ['s' => 'afastado']),
            'pontos_hoje'        => Db::contar('pontos', 'data = :d', ['d' => $hoje]),
            'presentes_hoje'     => (int) Db::valor('pontos', 'COUNT(DISTINCT funcionario_id) AS t',
                                        'data = :d AND tipo = :t', ['d' => $hoje, 't' => 'entrada']),
            'pontos_em_revisao'  => Db::contar('pontos', 'status = :s', ['s' => 'pendente_revisao']),
            'fora_da_cerca_7d'   => Db::contar('pontos',
                                        'dentro_cerca = 0 AND data >= :i',
                                        ['i' => date('Y-m-d', strtotime('-7 days'))]),
            'horas_mes'          => Ponto::formatarMinutos($minutosMes),
            'relatos_abertos'    => Db::contar('ouvidoria', 'status IN (:a, :b)',
                                        ['a' => 'aberta', 'b' => 'em_analise']),
            'relatos_total'      => Db::contar('ouvidoria'),
            'sugestoes_novas'    => Db::contar('sugestoes', 'status = :s', ['s' => 'recebida']),
            'sugestoes_total'    => Db::contar('sugestoes'),
            'vagas_abertas'      => Db::contar('vagas', 'status = :s', ['s' => 'aberta']),
            'candidaturas'       => Db::contar('candidaturas'),
            'cercas_ativas'      => Db::contar('cercas_virtuais', 'ativa = 1'),
        ];
    }

    /**
     * @return array{texto:string, provedor:string}
     */
    public static function responder(string $pergunta): array
    {
        $contexto = self::contexto();
        $provedor = CW_IA_PROVEDOR;

        if ($provedor !== 'local' && CW_IA_CHAVE === '') {
            $provedor = 'local';                 // sem chave, cai para o modo local
        }

        try {
            if ($provedor === 'openai') {
                return ['texto' => self::viaOpenAI($pergunta, $contexto), 'provedor' => 'openai'];
            }
            if ($provedor === 'gemini') {
                return ['texto' => self::viaGemini($pergunta, $contexto), 'provedor' => 'gemini'];
            }
        } catch (Throwable $e) {
            // Nunca expor detalhes técnicos, chaves ou mensagens do provedor ao usuário.
            error_log('ConnectWork/Assistente: ' . $e->getMessage());
            return [
                'texto' => self::viaLocal($pergunta, $contexto),
                'provedor' => 'local',
            ];
        }

        return ['texto' => self::viaLocal($pergunta, $contexto), 'provedor' => 'local'];
    }

    // -----------------------------------------------------------------
    // Modo local — números reais, sem invenção
    // -----------------------------------------------------------------

    private static function viaLocal(string $pergunta, array $c): string
    {
        $p = mb_strtolower(trim($pergunta));

        $tem = static function (array $termos) use ($p): bool {
            foreach ($termos as $t) {
                if (mb_strpos($p, $t) !== false) {
                    return true;
                }
            }
            return false;
        };

        if ($tem(['olá', 'ola', 'oi', 'bom dia', 'boa tarde', 'boa noite'])) {
            return 'Olá. Posso consultar os dados disponíveis da empresa e orientar você sobre o uso do ConnectWork.';
        }

        if ($tem(['o que você faz', 'o que voce faz', 'o que posso perguntar', 'ajuda', 'comandos'])) {
            return "Posso ajudar com:\n"
                 . "• funcionários e quadro de pessoal\n"
                 . "• ponto, jornada e registros do dia\n"
                 . "• ouvidoria e sugestões\n"
                 . "• vagas e candidaturas\n"
                 . "• cercas virtuais e localização\n"
                 . "• funcionamento das áreas do ConnectWork\n\n"
                 . "Quando uma informação não estiver disponível no sistema, eu aviso em vez de inventar.";
        }

        if ($tem(['ponto', 'batida', 'jornada', 'hora', 'atraso', 'falta', 'presença', 'presente'])) {
            return "Ponto e jornada — {$c['empresa']}\n"
                 . "Batidas hoje: {$c['pontos_hoje']}\n"
                 . "Pessoas com entrada registrada hoje: {$c['presentes_hoje']} de {$c['funcionarios_ativos']} ativos\n"
                 . "Horas trabalhadas no mês: {$c['horas_mes']}\n"
                 . "Batidas aguardando conferência: {$c['pontos_em_revisao']}\n"
                 . "Batidas fora da cerca nos últimos 7 dias: {$c['fora_da_cerca_7d']}\n"
                 . "Cercas virtuais ativas: {$c['cercas_ativas']}";
        }

        if ($tem(['ouvidoria', 'relato', 'denúncia', 'denuncia'])) {
            return "Ouvidoria — {$c['empresa']}\n"
                 . "Relatos abertos ou em análise: {$c['relatos_abertos']}\n"
                 . "Total de relatos recebidos: {$c['relatos_total']}";
        }

        if ($tem(['sugest', 'ideia', 'melhoria'])) {
            return "Sugestões — {$c['empresa']}\n"
                 . "Aguardando triagem: {$c['sugestoes_novas']}\n"
                 . "Total recebido: {$c['sugestoes_total']}";
        }

        if ($tem(['vaga', 'candidat', 'recrutamento', 'seleção', 'selecao'])) {
            return "Vagas — {$c['empresa']}\n"
                 . "Vagas abertas: {$c['vagas_abertas']}\n"
                 . "Candidaturas recebidas: {$c['candidaturas']}";
        }

        if ($tem(['funcionário', 'funcionario', 'equipe', 'pessoas', 'quadro', 'headcount'])) {
            return "Quadro de pessoal — {$c['empresa']}\n"
                 . "Funcionários ativos: {$c['funcionarios_ativos']}\n"
                 . "Funcionários afastados: {$c['funcionarios_afastados']}";
        }

        if ($tem(['gps', 'cerca', 'geofence', 'localiza', 'localização', 'localizacao'])) {
            return "Cerca virtual e localização\n"
                 . "Cercas ativas: {$c['cercas_ativas']}\n"
                 . "Batidas fora da área nos últimos 7 dias: {$c['fora_da_cerca_7d']}\n\n"
                 . "A posição é recebida pelo navegador e a distância é calculada no servidor.";
        }

        if ($tem(['cadastrar funcionário', 'cadastrar funcionario', 'criar funcionário', 'criar funcionario'])) {
            return 'Para cadastrar um funcionário, entre como Administrador da empresa, abra Funcionários e use o formulário de cadastro. Você pode informar matrícula, CPF, e-mail, telefone, cargo, departamento, gestor, admissão, jornada e situação.';
        }

        if ($tem(['empresa', 'plano', 'assinatura', 'limite'])) {
            return "Para dados da plataforma, use a área do Administrador Master. Nesta sessão eu só consulto os dados disponíveis da empresa {$c['empresa']}.";
        }

        if ($tem(['senha', 'login', 'usuário', 'usuario', 'acesso'])) {
            return 'Para alterar dados de acesso de um funcionário, use a área de Funcionários como Administrador. Se o problema for no seu próprio login, saia da conta e entre novamente; para redefinição de senha, procure o administrador da empresa.';
        }

        return "Não encontrei no sistema um dado confiável para responder a essa pergunta.\n\n"
             . "Posso consultar ponto, funcionários, equipe, ouvidoria, sugestões, vagas e cercas virtuais. "
             . "Também posso orientar sobre as funções disponíveis no ConnectWork.";
    }

    // -----------------------------------------------------------------
    // Provedores externos
    // -----------------------------------------------------------------

    private static function instrucao(array $c): string
    {
        return 'Você é o assistente do ConnectWork, um sistema de gestão de pessoas. '
             . 'Responda em português do Brasil, de forma direta e curta. '
             . 'Use APENAS os números abaixo; se a pergunta pedir algo que não está aqui, '
             . "diga que o dado não está disponível em vez de estimar.\n\n"
             . 'Dados atuais da empresa: ' . json_encode($c, JSON_UNESCAPED_UNICODE);
    }

    private static function viaOpenAI(string $pergunta, array $c): string
    {
        $resposta = self::http(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => CW_IA_MODELO ?: 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => self::instrucao($c)],
                    ['role' => 'user',   'content' => $pergunta],
                ],
                'max_tokens' => 600,
            ],
            ['Authorization: Bearer ' . CW_IA_CHAVE]
        );

        return $resposta['choices'][0]['message']['content']
            ?? 'O provedor respondeu em um formato inesperado.';
    }

    private static function viaGemini(string $pergunta, array $c): string
    {
        $modelo = CW_IA_MODELO ?: 'gemini-1.5-flash';
        $resposta = self::http(
            "https://generativelanguage.googleapis.com/v1beta/models/$modelo:generateContent?key=" . urlencode(CW_IA_CHAVE),
            [
                'system_instruction' => ['parts' => [['text' => self::instrucao($c)]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $pergunta]]]],
            ],
            []
        );

        return $resposta['candidates'][0]['content']['parts'][0]['text']
            ?? 'O provedor respondeu em um formato inesperado.';
    }

    private static function http(string $url, array $corpo, array $cabecalhos): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('a extensão cURL do PHP não está ativa');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $cabecalhos),
            CURLOPT_POSTFIELDS     => json_encode($corpo, JSON_UNESCAPED_UNICODE),
        ]);

        $bruto  = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro   = curl_error($ch);
        curl_close($ch);

        if ($bruto === false) {
            throw new RuntimeException('falha de conexão: ' . $erro);
        }
        if ($codigo >= 400) {
            throw new RuntimeException('o provedor devolveu HTTP ' . $codigo);
        }

        return json_decode($bruto, true) ?: [];
    }
}
