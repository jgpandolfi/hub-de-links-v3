<?php
/**
 * Página de Login do Painel Administrativo
 * Hub de Links - Sistema de Analytics
 */

// Importa headers de segurança
require_once 'security-headers.php';

// Configurações de segurança da sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// Inicia a sessão
session_start();

// Importa proteção CSRF
require_once 'csrf-protection.php';

// Se já estiver logado, redirecionar para dashboard
if (isset($_SESSION['admin_autenticado']) && $_SESSION['admin_autenticado'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Mensagem de erro (se houver)
$erro = $_SESSION['erro_login'] ?? '';
unset($_SESSION['erro_login']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel de Analytics</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="estilo-painel.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    
    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body class="pagina-login">
    <!-- Botão Toggle Tema -->
    <button id="toggle-tema-login" class="botao-toggle-tema-login" aria-label="Alternar tema">
        <ion-icon name="moon-outline" class="icone-lua"></ion-icon>
        <ion-icon name="sunny-outline" class="icone-sol" style="display: none;"></ion-icon>
    </button>

    <div class="container-login">
        <div class="card-login">
            <!-- Logo/Título -->
            <div class="cabecalho-login">
                <h1 class="titulo-login">Hub de Links</h1>
                <p class="subtitulo-login">Painel de Analytics</p>
            </div>

            <!-- Mensagem de Erro -->
            <?php if ($erro): ?>
                <div class="alerta-erro" role="alert">
                    <ion-icon name="warning-outline" class="icone-erro"></ion-icon>
                    <span><?php echo htmlspecialchars($erro); ?></span>
                </div>
            <?php endif; ?>

            <!-- Formulário de Login -->
            <form action="autenticacao.php" method="POST" class="formulario-login">
                <!-- Token CSRF -->
                <?php echo CSRFProtection::campoToken(); ?>

                <!-- Campo Usuário -->
                <div class="grupo-campo">
                    <label for="usuario" class="label-campo">Usuário</label>
                    <input 
                        type="text" 
                        id="usuario" 
                        name="usuario" 
                        class="campo-entrada"
                        required
                        autocomplete="username"
                        placeholder="Digite seu usuário"
                        autofocus>
                </div>

                <!-- Campo Senha -->
                <div class="grupo-campo">
                    <label for="senha" class="label-campo">Senha</label>
                    <input 
                        type="password" 
                        id="senha" 
                        name="senha" 
                        class="campo-entrada"
                        required
                        autocomplete="current-password"
                        placeholder="Digite sua senha">
                </div>

                <!-- Botão Entrar -->
                <button type="submit" class="botao-entrar">
                    Entrar no Painel
                </button>
            </form>

            <!-- Rodapé -->
            <div class="rodape-login">
                <p>© <?php echo date('Y'); ?> José Guilherme Pandolfi</p>
            </div>
        </div>
    </div>

    <script src="script-painel.js"></script>
</body>
</html>