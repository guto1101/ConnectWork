<?php
/**
 * ConnectWork — Espelho de ponto da empresa (administrador)
 */
require_once __DIR__ . '/../includes/espelho_ponto.php';

Auth::exigirNivel(['admin']);

render_espelho_ponto('admin/ponto.php', 'ponto', 'Ponto',
    'Espelho de ponto de toda a empresa, com conferência das batidas marcadas.');
