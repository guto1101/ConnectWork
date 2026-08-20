<?php
/**
 * ConnectWork — Encerrar sessão
 */
require_once __DIR__ . '/includes/auth.php';

Auth::sair();
header('Location: ' . url('index.php'));
exit;
