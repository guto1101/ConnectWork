<?php
/**
 * ConnectWork — Roteador de perfil
 *
 * Encaminha cada usuário para a área correspondente ao seu nível de
 * acesso. Existe para que qualquer link genérico para o "painel" funcione
 * independentemente do perfil de quem clicou.
 */
require_once __DIR__ . '/includes/auth.php';

Auth::exigirLogin();
header('Location: ' . Auth::paginaInicial());
exit;
