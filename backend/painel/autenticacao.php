<?php
/**
 * Autenticação de Administradores
 * Hub de Links - Painel de Analytics
 */

// Importa headers de segurança
require_once 'security-headers.php';

// Configurações de segurança da sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// Iniciar a sessão
session_start();

// Importa e valida CSRF
require_once 'csrf-protection.php';
CSRFProtection::validarRequisicao('index.php');

// Importa Rate Limiter (proteção contra ataques bruteforce)
require_once 'rate-limiter.php';

// Apenas requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_once '../api/configuracao-banco.php';

try {
    // Obter IP do usuário
    $ipUsuario = $_SERVER['REMOTE_ADDR'];
    
    // Se está atrás de proxy/Cloudflare, obter IP real
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ipUsuario = trim($ips[0]);
    } elseif (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ipUsuario = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    
    // Verificar se o usuário está bloqueado
    if (RateLimiter::verificarBloqueio($ipUsuario)) {
        $tempoBloqueio = RateLimiter::obterTempoBloqueio($ipUsuario);
        $minutos = ceil($tempoBloqueio / 60);
        
        $_SESSION['erro_login'] = "Muitas tentativas de login. Tente novamente em {$minutos} minutos.";
        header('Location: index.php');
        exit;
    }
    
    // Validar dados inseridos pelo usuário
    $usuario = filter_var($_POST['usuario'] ?? '', FILTER_SANITIZE_STRING);
    $senha = $_POST['senha'] ?? '';
    
    if (empty($usuario) || empty($senha)) {
        throw new Exception('Usuário e senha são obrigatórios');
    }
    
    // Buscar administrador no banco de dados MySQL
    $pdo = obterConexaoBanco();
    
    $stmt = $pdo->prepare("
        SELECT id_admin, nome_completo, usuario, senha_hash, ativo 
        FROM tb_administradores 
        WHERE usuario = :usuario
        LIMIT 1
    ");
    
    $stmt->execute(['usuario' => $usuario]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        // Registrar falha (caso credencial de usuário inserida não exista)
        RateLimiter::registrarTentativa($ipUsuario, false);
        
        $tentativasRestantes = RateLimiter::obterTentativasRestantes($ipUsuario);
        throw new Exception("Usuário ou senha incorretos. Tentativas restantes: {$tentativasRestantes}");
    }
    
    // Verificar se a conta de administrador está com status "ativo"
    if (!$admin['ativo']) {
        throw new Exception('Conta desativada. Contate o administrador.');
    }
    
    // Verificar senha inserida
    if (!password_verify($senha, $admin['senha_hash'])) {
        // Registrar falha (senha inserida está incorreta)
        RateLimiter::registrarTentativa($ipUsuario, false);
        
        $tentativasRestantes = RateLimiter::obterTentativasRestantes($ipUsuario);
        throw new Exception("Usuário ou senha incorretos. Tentativas restantes: {$tentativasRestantes}");
    }
    
    // Login bem-sucedido
    
    // Limpa/reseta todas as tentativas de login 
    RateLimiter::registrarTentativa($ipUsuario, true);
    
    // Regenera ID da sessão (previne session fixation)
    session_regenerate_id(true);
    
    // Define dados da sessão
    $_SESSION['admin_logado'] = true;
    $_SESSION['admin_id'] = $admin['id_admin'];
    $_SESSION['admin_nome'] = $admin['nome_completo'];
    $_SESSION['admin_usuario'] = $admin['usuario'];
    $_SESSION['ultimo_acesso'] = time();
    
    // Atualiza último acesso no banco
    $stmtUpdate = $pdo->prepare("
        UPDATE tb_administradores 
        SET ultimo_acesso = NOW() 
        WHERE id_admin = :id
    ");
    $stmtUpdate->execute(['id' => $admin['id_admin']]);
    
    // Redireciona o usuário para dashboard (painel)
    header('Location: dashboard.php');
    exit;
    
} catch (Exception $erro) {
    $_SESSION['erro_login'] = $erro->getMessage();
    header('Location: index.php');
    exit;
}