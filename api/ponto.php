<?php
/**
 * ConnectWork — API do ponto eletrônico
 *
 * POST  api/ponto.php
 *   tipo       entrada | pausa | retorno | saida
 *   latitude   decimal
 *   longitude  decimal
 *   precisao   metros informados pelo navegador
 *   token      uuid gerado no cliente (evita batida duplicada)
 *
 * O que o cliente manda é matéria-prima, não veredito: a hora é do
 * servidor, e é o servidor que decide se a posição está dentro da cerca.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ponto.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'erro' => 'Método não suportado.'], 405);
}

if (!Auth::logado()) {
    responder_json(['ok' => false, 'erro' => 'Sessão expirada. Entre novamente.', 'sessao' => false], 401);
}

if (!csrf_valido()) {
    responder_json(['ok' => false, 'erro' => 'Token de segurança inválido. Recarregue a página.'], 419);
}

$funcionarioId = Auth::funcionarioId();
if (!$funcionarioId) {
    responder_json([
        'ok'  => false,
        'erro' => 'Sua conta ainda não está vinculada a um cadastro de funcionário. '
                . 'Peça ao administrador da empresa para concluir o cadastro.',
    ], 409);
}

// Aceita tanto JSON quanto formulário.
$corpo = [];
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $corpo = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
} else {
    $corpo = $_POST;
}

$resultado = Ponto::registrar($funcionarioId, [
    'tipo'      => $corpo['tipo'] ?? '',
    'latitude'  => $corpo['latitude'] ?? null,
    'longitude' => $corpo['longitude'] ?? null,
    'precisao'  => $corpo['precisao'] ?? null,
    'endereco'  => $corpo['endereco'] ?? null,
    'token'     => $corpo['token'] ?? null,
    'origem'    => $corpo['origem'] ?? 'web',
]);

if (!$resultado['ok']) {
    responder_json($resultado, 422);
}

$resumo = Ponto::resumoDoDia($funcionarioId);
$ponto  = $resultado['ponto'];

responder_json([
    'ok'        => true,
    'duplicado' => $resultado['duplicado'] ?? false,
    'mensagem'  => ($resultado['duplicado'] ?? false)
        ? 'Esta batida já estava registrada.'
        : Ponto::ROTULOS[$ponto['tipo']] . ' registrada às ' . date('H:i', strtotime($ponto['data_hora'])) . '.',
    'ponto' => [
        'id'           => (int) $ponto['id'],
        'tipo'         => $ponto['tipo'],
        'hora'         => date('H:i', strtotime($ponto['data_hora'])),
        'status'       => $ponto['status'],
        'dentro_cerca' => $ponto['dentro_cerca'] === null ? null : (bool) $ponto['dentro_cerca'],
        'distancia'    => $ponto['distancia_metros'] !== null ? (float) $ponto['distancia_metros'] : null,
    ],
    'resumo' => [
        'proximo'    => $resumo['proximo'],
        'permitidos' => $resumo['permitidos'],
        'trabalhado' => $resumo['formatado'],
        'encerrado'  => $resumo['encerrado'],
    ],
]);
