<?php
/**
 * ConnectWork — Conexão com o banco
 *
 * Ponto único de acesso ao MySQL. Nenhum outro arquivo abre conexão.
 *
 * PDO com emulação de prepared statements DESLIGADA: as consultas vão ao
 * servidor já separadas de seus parâmetros, que é o que de fato bloqueia
 * injeção de SQL. Com a emulação ligada o driver remonta a string no
 * cliente e a proteção depende do escape.
 */

require_once __DIR__ . '/config.php';

function conexao(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORTA, DB_NOME);

    try {
        $pdo = new PDO($dsn, DB_USUARIO, DB_SENHA, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        // O PHP e o MySQL precisam concordar no fuso, senão NOW() e date()
        // divergem e o cálculo de jornada sai errado.
        $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');
        $pdo->exec("SET time_zone = '$offset'");
    } catch (PDOException $e) {
        if (CW_AMBIENTE === 'producao') {
            error_log('ConnectWork/conexao: ' . $e->getMessage());
            http_response_code(503);
            exit('Serviço temporariamente indisponível.');
        }
        throw $e;
    }

    return $pdo;
}
