<?php
/**
 * Logout do Painel Administrativo
 * Hub de Links - Sistema de Analytics
 */

// Importa headers de segurança
require_once 'security-headers.php';

// Inicia a sessão
session_start();

// IMPORTA classe CSRF
require_once 'csrf-protection.php';

// Valida CSRF
CSRFProtection::validarRequisicao('dashboard.php');

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Destruir o cookie de sessão
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Destruir a sessão
session_destroy();

// Redirecionar para login
header('Location: index.php');
exit;