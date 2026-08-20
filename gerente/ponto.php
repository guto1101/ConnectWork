<?php
/**
 * ConnectWork — Espelho de ponto da equipe (gerente)
 *
 * Mesma tela do administrador, com o alcance reduzido à equipe pelo
 * Auth::equipeVisivel().
 */
require_once __DIR__ . '/../includes/espelho_ponto.php';

Auth::exigirNivel(['gerente']);

render_espelho_ponto('gerente/ponto.php', 'ponto', 'Ponto da equipe',
    'Registros dos funcionários que têm você como gestor.');
