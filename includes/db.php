<?php
/**
 * ConnectWork — Camada de acesso a dados com escopo de empresa
 *
 * POR QUE ESTA CLASSE EXISTE
 * O MySQL não tem row level security. Sem uma camada como esta, o
 * isolamento entre empresas passa a depender de cada programador lembrar
 * de escrever "AND empresa_id = ?" em toda consulta — e um esquecimento
 * é uma empresa lendo os dados de outra, sem nenhuma rede de proteção.
 *
 * Aqui o filtro é automático:
 *   - leitura, alteração e exclusão recebem empresa_id da sessão;
 *   - a inserção grava empresa_id da sessão e descarta o que vier do
 *     formulário, então ninguém cria registro em empresa alheia;
 *   - SQL livre (Db::consulta) é inspecionado: se cita uma tabela de
 *     empresa e não cita empresa_id, a chamada falha em vez de vazar.
 *
 * O Administrador Master é a única exceção e precisa declarar em qual
 * empresa está operando com Db::comoMaster($empresaId).
 */

require_once __DIR__ . '/conexao.php';

final class Db
{
    /** Tabelas que carregam empresa_id. O filtro é obrigatório em todas. */
    private const TABELAS_EMPRESA = [
        'empresa_config', 'feriados', 'usuarios', 'departamentos', 'funcionarios',
        'cercas_virtuais', 'pontos', 'localizacoes',
        'mensagens', 'comunicados', 'comunicado_leituras', 'notificacoes',
        'ouvidoria', 'ouvidoria_respostas', 'sugestoes',
        'vagas', 'candidaturas',
        'disponibilidade', 'arquivos', 'relatorios',
        'ia_conversas', 'ia_mensagens',
    ];

    /** Tabelas da plataforma, sem dono. Alcance restrito ao nível master. */
    private const TABELAS_GLOBAIS = ['planos', 'empresas', 'login_tentativas', 'auditoria'];

    /** Empresa escolhida pelo master quando opera dentro de um cliente. */
    private static ?int $empresaMaster = null;

    /** Libera as tabelas globais para a requisição atual. */
    private static bool $modoPlataforma = false;

    // -----------------------------------------------------------------
    // Escopo
    // -----------------------------------------------------------------

    /**
     * Empresa ativa da requisição. Vem da sessão; o master pode
     * sobrescrever com comoMaster().
     */
    public static function empresaId(): ?int
    {
        if (self::$empresaMaster !== null) {
            return self::$empresaMaster;
        }
        $id = $_SESSION['cw_empresa_id'] ?? null;
        return $id === null ? null : (int) $id;
    }

    /** O master passa a operar dentro de uma empresa específica. */
    public static function comoMaster(int $empresaId): void
    {
        if (($_SESSION['cw_nivel'] ?? '') !== 'master') {
            throw new RuntimeException('Somente o Administrador Master pode escolher a empresa.');
        }
        self::$empresaMaster = $empresaId;
    }

    /** Libera as tabelas da plataforma (planos, empresas, auditoria). */
    public static function plataforma(): void
    {
        if (($_SESSION['cw_nivel'] ?? '') !== 'master') {
            throw new RuntimeException('Área da plataforma restrita ao Administrador Master.');
        }
        self::$modoPlataforma = true;
    }

    // -----------------------------------------------------------------
    // Leitura
    // -----------------------------------------------------------------

    /**
     * @param string $tabela  nome validado contra a lista branca
     * @param string $where   condições adicionais, sempre com placeholders
     * @param array  $params  valores dos placeholders
     * @param array  $opcoes  colunas, ordem, limite
     */
    public static function todos(string $tabela, string $where = '', array $params = [], array $opcoes = []): array
    {
        [$sql, $params] = self::montarSelect($tabela, $where, $params, $opcoes);
        $st = conexao()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Primeira linha ou null. */
    public static function um(string $tabela, string $where = '', array $params = [], array $opcoes = []): ?array
    {
        $opcoes['limite'] = 1;
        $linhas = self::todos($tabela, $where, $params, $opcoes);
        return $linhas[0] ?? null;
    }

    /** Registro pela chave primária, já preso à empresa da sessão. */
    public static function porId(string $tabela, int $id, array $opcoes = []): ?array
    {
        self::validarTabela($tabela);
        $chave = self::chavePrimaria($tabela);
        return self::um($tabela, "$chave = :cw_id", ['cw_id' => $id], $opcoes);
    }

    /** Contagem com o mesmo escopo. */
    public static function contar(string $tabela, string $where = '', array $params = []): int
    {
        $linha = self::um($tabela, $where, $params, ['colunas' => 'COUNT(*) AS total']);
        return (int) ($linha['total'] ?? 0);
    }

    /** Primeiro valor da primeira linha (para SUM, MAX, etc.). */
    public static function valor(string $tabela, string $colunas, string $where = '', array $params = [])
    {
        $linha = self::um($tabela, $where, $params, ['colunas' => $colunas]);
        return $linha === null ? null : reset($linha);
    }

    /**
     * Lista pública e limitada das empresas ativas para a escolha na tela
     * de acesso. Nenhum dado interno da empresa é exposto nesta consulta.
     */
    public static function empresasAtivasParaLogin(): array
    {
        $st = conexao()->prepare(
            'SELECT id, nome, segmento FROM empresas WHERE status = :status ORDER BY nome LIMIT 200'
        );
        $st->execute(['status' => 'ativa']);
        return $st->fetchAll();
    }

    /**
     * Retorna os dados do plano associado à empresa da sessão sem liberar
     * acesso genérico às tabelas globais empresas e planos.
     */
    public static function planoDaEmpresa(): ?array
    {
        $empresaId = self::exigirEmpresa('planos');
        $sql = 'SELECT e.id AS empresa_id, e.plano_id, p.nome, p.limite_funcionarios, p.preco_mensal, p.recursos '
             . 'FROM empresas e '
             . 'LEFT JOIN planos p ON p.id = e.plano_id '
             . 'WHERE e.id = :cw_plano_emp '
             . 'LIMIT 1';
        $st = conexao()->prepare($sql);
        $st->execute(['cw_plano_emp' => $empresaId]);
        return $st->fetch() ?: null;
    }

    /**
     * Lê a auditoria somente da empresa da sessão. A tabela é global para
     * permitir a visão da plataforma, portanto o administrador da empresa
     * usa este caminho dedicado em vez de liberar Db::plataforma().
     */
    public static function auditoriaDaEmpresa(string $where = '', array $params = [], int $limite = 300): array
    {
        $empresaId = self::exigirEmpresa('auditoria');
        $condicoes = ['a.empresa_id = :cw_aud_emp'];
        if ($where !== '') {
            $condicoes[] = "($where)";
        }

        $sql = 'SELECT a.*, u.nome AS usuario_nome, u.usuario AS usuario_login '
             . 'FROM auditoria a '
             . 'LEFT JOIN usuarios u ON u.id = a.usuario_id AND u.empresa_id = :cw_aud_usuario_emp '
             . 'WHERE ' . implode(' AND ', $condicoes) . ' '
             . 'ORDER BY a.criado_em DESC '
             . 'LIMIT ' . max(1, min(500, $limite));

        $params['cw_aud_emp'] = $empresaId;
        $params['cw_aud_usuario_emp'] = $empresaId;
        $st = conexao()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    // -----------------------------------------------------------------
    // Escrita
    // -----------------------------------------------------------------

    /** Insere e devolve o id gerado. empresa_id vem sempre da sessão. */
    public static function inserir(string $tabela, array $dados): int
    {
        self::validarTabela($tabela);

        if (self::ehTabelaEmpresa($tabela)) {
            unset($dados['empresa_id']);              // ignora o que vier do formulário
            $dados['empresa_id'] = self::exigirEmpresa($tabela);
        }

        $colunas = array_keys($dados);
        self::validarColunas($colunas);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $tabela,
            implode(', ', array_map(static fn($c) => "`$c`", $colunas)),
            implode(', ', array_map(static fn($c) => ":$c", $colunas))
        );

        $st = conexao()->prepare($sql);
        $st->execute($dados);
        return (int) conexao()->lastInsertId();
    }

    /** Atualiza um registro. Nunca permite mover o registro de empresa. */
    public static function atualizar(string $tabela, int $id, array $dados): int
    {
        self::validarTabela($tabela);
        unset($dados['empresa_id'], $dados['id']);

        if ($dados === []) {
            return 0;
        }

        $colunas = array_keys($dados);
        self::validarColunas($colunas);

        $sets   = implode(', ', array_map(static fn($c) => "`$c` = :$c", $colunas));
        $chave   = self::chavePrimaria($tabela);
        $where   = "$chave = :cw_id";
        $params  = $dados + ['cw_id' => $id];

        if (self::ehTabelaEmpresa($tabela)) {
            $where .= ' AND empresa_id = :cw_emp';
            $params['cw_emp'] = self::exigirEmpresa($tabela);
        } else {
            self::exigirPlataforma($tabela);
        }

        $st = conexao()->prepare("UPDATE `$tabela` SET $sets WHERE $where");
        $st->execute($params);
        return $st->rowCount();
    }

    /** Exclui um registro dentro do escopo. */
    public static function excluir(string $tabela, int $id): int
    {
        self::validarTabela($tabela);
        $chave  = self::chavePrimaria($tabela);
        $where   = "$chave = :cw_id";
        $params  = ['cw_id' => $id];

        if (self::ehTabelaEmpresa($tabela)) {
            $where .= ' AND empresa_id = :cw_emp';
            $params['cw_emp'] = self::exigirEmpresa($tabela);
        } else {
            self::exigirPlataforma($tabela);
        }

        $st = conexao()->prepare("DELETE FROM `$tabela` WHERE $where");
        $st->execute($params);
        return $st->rowCount();
    }

    // -----------------------------------------------------------------
    // SQL livre (joins, agregações, relatórios)
    // -----------------------------------------------------------------

    /**
     * Executa SQL escrito à mão, com uma trava: se a consulta toca uma
     * tabela de empresa e não menciona empresa_id, é recusada. É o
     * equivalente prático da policy que o MySQL não oferece — o erro
     * aparece em desenvolvimento, não como vazamento em produção.
     */
    public static function consulta(string $sql, array $params = []): array
    {
        self::conferirEscopoNoSql($sql);
        $params = self::parametrosEscopoSql($sql, $params);
        $st = conexao()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Mesma trava, para comandos que não retornam linhas. */
    public static function executar(string $sql, array $params = []): int
    {
        self::conferirEscopoNoSql($sql);
        $params = self::parametrosEscopoSql($sql, $params);
        $st = conexao()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** Atalho: empresa_id da sessão pronto para entrar nos parâmetros. */
    public static function escopo(array $params = []): array
    {
        return $params + ['cw_emp' => self::empresaId()];
    }

    public static function transacao(callable $fn)
    {
        $pdo = conexao();
        $pdo->beginTransaction();
        try {
            $r = $fn($pdo);
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    private static function ehTabelaEmpresa(string $tabela): bool
    {
        return in_array($tabela, self::TABELAS_EMPRESA, true);
    }

    /** empresa_config usa empresa_id como chave primária; as demais usam id. */
    private static function chavePrimaria(string $tabela): string
    {
        return $tabela === 'empresa_config' ? 'empresa_id' : 'id';
    }

    private static function validarTabela(string $tabela): void
    {
        if (!self::ehTabelaEmpresa($tabela) && !in_array($tabela, self::TABELAS_GLOBAIS, true)) {
            throw new InvalidArgumentException("Tabela desconhecida: $tabela");
        }
    }

    /** Nomes de coluna nunca vêm do usuário; a checagem é rede de segurança. */
    private static function validarColunas(array $colunas): void
    {
        foreach ($colunas as $c) {
            if (!preg_match('/^[a-z_][a-z0-9_]*$/i', (string) $c)) {
                throw new InvalidArgumentException("Nome de coluna inválido: $c");
            }
        }
    }

    private static function exigirEmpresa(string $tabela): int
    {
        $id = self::empresaId();
        if (!$id) {
            throw new RuntimeException(
                "Acesso a '$tabela' sem empresa definida. " .
                'Faça login ou chame Db::comoMaster($empresaId).'
            );
        }
        return $id;
    }

    private static function exigirPlataforma(string $tabela): void
    {
        if (!self::$modoPlataforma) {
            throw new RuntimeException("'$tabela' é tabela da plataforma. Chame Db::plataforma() antes.");
        }
    }

    private static function montarSelect(string $tabela, string $where, array $params, array $opcoes): array
    {
        self::validarTabela($tabela);

        $colunas = $opcoes['colunas'] ?? '*';
        if (!preg_match('/^[A-Za-z0-9_,.\s*()`]+$/', $colunas)) {
            throw new InvalidArgumentException('Lista de colunas inválida.');
        }

        $condicoes = [];
        if (self::ehTabelaEmpresa($tabela)) {
            $condicoes[] = 'empresa_id = :cw_emp';
            $params['cw_emp'] = self::exigirEmpresa($tabela);
        } else {
            self::exigirPlataforma($tabela);
        }
        if ($where !== '') {
            $condicoes[] = "($where)";
        }

        $sql = "SELECT $colunas FROM `$tabela`";
        if ($condicoes) {
            $sql .= ' WHERE ' . implode(' AND ', $condicoes);
        }

        if (!empty($opcoes['ordem'])) {
            if (!preg_match('/^[A-Za-z0-9_,.\s]+(ASC|DESC)?$/i', $opcoes['ordem'])) {
                throw new InvalidArgumentException('Cláusula de ordenação inválida.');
            }
            $sql .= ' ORDER BY ' . $opcoes['ordem'];
        }
        if (!empty($opcoes['limite'])) {
            $sql .= ' LIMIT ' . (int) $opcoes['limite'];
            if (!empty($opcoes['deslocamento'])) {
                $sql .= ' OFFSET ' . (int) $opcoes['deslocamento'];
            }
        }

        return [$sql, $params];
    }

    /** A trava do SQL livre. */
    private static function conferirEscopoNoSql(string $sql): void
    {
        $normalizado = strtolower($sql);

        foreach (self::TABELAS_EMPRESA as $tabela) {
            if (!preg_match('/\b' . preg_quote($tabela, '/') . '\b/', $normalizado)) {
                continue;
            }

            // Não basta mencionar empresa_id: a consulta precisa usar o
            // placeholder controlado pelo escopo atual. Assim um valor
            // enviado pelo formulário não pode escolher outra empresa.
            if (strpos($normalizado, 'empresa_id') === false
                || !preg_match('/\bcw_emp\b/', $normalizado)) {
                throw new RuntimeException(
                    "Consulta em '$tabela' sem escopo seguro. " .
                    'Use "empresa_id = :cw_emp".'
                );
            }
        }
    }

    /**
     * O parâmetro cw_emp nunca é confiado ao chamador: quando a consulta
     * usa o placeholder de escopo, ele é substituído pela empresa ativa
     * (ou pela empresa escolhida explicitamente pelo Master).
     */
    private static function parametrosEscopoSql(string $sql, array $params): array
    {
        if (preg_match('/\bcw_emp\b/i', $sql)) {
            $params['cw_emp'] = self::exigirEmpresa('SQL');
        }
        return $params;
    }
}
