<?php
/**
 * Logout
 * Sistema de Controle de Manutenção
 */

require_once __DIR__ . '/../config/config.php';

// Log de logout
if (isLoggedIn()) {
    logAction('logout', 'users', $_SESSION['user_id']);
}

// Destruir sessão
session_unset();
session_destroy();

// Remover cookie de lembrar
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirecionar para login
header("Location: login.php");
exit;
