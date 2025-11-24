<?php
/**
 * Verificação de Sessão de Administrador
 * Hub de Links - Painel de Analytics
 */

// Se a sessão não estiver iniciada, configura e inicia
if (session_status() === PHP_SESSION_NONE) {
    // Configura parâmetros de segurança da sessão antes de iniciá-la
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    
    // Inicia a sessão
    session_start();
}

/**
 * Verifica se usuário está autenticado
 * @return bool
 */
function verificarAutenticacao() {
    // Verificar se as variáveis de sessão existem
    if (!isset($_SESSION['admin_logado']) || 
        !isset($_SESSION['admin_id']) || 
        !isset($_SESSION['admin_usuario'])) {
        return false;
    }
    
    // Verificar se está autenticado
    if ($_SESSION['admin_logado'] !== true) {
        return false;
    }
    
    // Verificar tempo de inatividade (30 minutos)
    if (isset($_SESSION['ultimo_acesso'])) {
        $tempoInativo = time() - $_SESSION['ultimo_acesso'];
        
        if ($tempoInativo > 1800) { // 30 minutos
            return false;
        }
    }
    
    // Atualizar último acesso
    $_SESSION['ultimo_acesso'] = time();
    
    return true;
}

/**
 * Redireciona para login se não autenticado
 */
function exigirAutenticacao() {
    if (!verificarAutenticacao()) {
        // Destruir sessão
        session_unset();
        session_destroy();
        
        // Redirecionar para login
        header('Location: index.php');
        exit;
    }
}

/**
 * Obtém ID do admin logado
 * @return int|null
 */
function obterIdAdminLogado() {
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Obtém usuário do admin logado
 * @return string|null
 */
function obterUsuarioAdminLogado() {
    return $_SESSION['admin_usuario'] ?? null;
}

/**
 * Obtém nome do admin logado
 * @return string|null
 */
function obterNomeAdminLogado() {
    return $_SESSION['admin_nome'] ?? null;
}