<?php
/**
 * Proteção CSRF (Cross-Site Request Forgery)
 * Hub de Links - Painel de Analytics
 * 
 * Implementa tokens CSRF para proteger formulários contra ataques
 * em que sites maliciosos forçam ações não autorizadas
 */

class CSRFProtection {
    
    /**
     * Nome da chave do token na sessão
     */
    const TOKEN_KEY = 'csrf_token';
    
    /**
     * Tempo de expiração do token (em segundos)
     * 2 horas = 7200 segundos
     */
    const TOKEN_LIFETIME = 7200;
    
    /**
     * Gera um novo token CSRF e armazena na sessão
     * @return string Token gerado
     */
    public static function gerarToken() {
        // Gera token criptograficamente seguro (32 bytes = 64 caracteres hex)
        $token = bin2hex(random_bytes(32));
        
        // Armazena na sessão com timestamp
        $_SESSION[self::TOKEN_KEY] = [
            'token' => $token,
            'criado_em' => time()
        ];
        
        return $token;
    }
    
    /**
     * Obtém o token CSRF atual (gera novo se não existir)
     * @return string Token atual
     */
    public static function obterToken() {
        // Se não existe ou expirou, gera novo
        if (!self::tokenExiste() || self::tokenExpirou()) {
            return self::gerarToken();
        }
        
        return $_SESSION[self::TOKEN_KEY]['token'];
    }
    
    /**
     * Valida token CSRF recebido em formulário
     * @param string $tokenRecebido Token do formulário
     * @return bool True se válido, False se inválido
     */
    public static function validarToken($tokenRecebido) {
        // Verifica se token existe na sessão
        if (!self::tokenExiste()) {
            return false;
        }
        
        // Verifica se token expirou
        if (self::tokenExpirou()) {
            return false;
        }
        
        // Verifica se token recebido está vazio
        if (empty($tokenRecebido)) {
            return false;
        }
        
        // Compara tokens usando função timing-safe
        // Previne timing attacks
        $tokenSessao = $_SESSION[self::TOKEN_KEY]['token'];
        return hash_equals($tokenSessao, $tokenRecebido);
    }
    
    /**
     * Gera campo hidden HTML com token CSRF
     * @return string HTML do campo hidden
     */
    public static function campoToken() {
        $token = self::obterToken();
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }
    
    /**
     * Valida requisição POST (verifica token e redireciona se inválido)
     * @param string $urlRedirecionamento URL para redirecionar se inválido
     */
    public static function validarRequisicao($urlRedirecionamento = 'index.php') {
        // Apenas para requisições POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Obtém token do formulário
        $tokenRecebido = $_POST['csrf_token'] ?? '';
        
        // Valida token
        if (!self::validarToken($tokenRecebido)) {
            // Token inválido - registra tentativa suspeita
            error_log('CSRF: Tentativa de ataque detectada - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'));
            
            // Destroi sessão por segurança
            session_unset();
            session_destroy();
            
            // Redireciona com mensagem de erro
            session_start();
            $_SESSION['erro_login'] = 'Requisição inválida. Por segurança, você foi deslogado.';
            header('Location: ' . $urlRedirecionamento);
            exit;
        }
        
        // Token válido - gera novo para próxima requisição
        self::gerarToken();
    }
    
    /**
     * Verifica se token existe na sessão
     * @return bool
     */
    private static function tokenExiste() {
        return isset($_SESSION[self::TOKEN_KEY]) && 
               isset($_SESSION[self::TOKEN_KEY]['token']);
    }
    
    /**
     * Verifica se token expirou
     * @return bool
     */
    private static function tokenExpirou() {
        if (!self::tokenExiste()) {
            return true;
        }
        
        $criadoEm = $_SESSION[self::TOKEN_KEY]['criado_em'] ?? 0;
        $tempoDecorrido = time() - $criadoEm;
        
        return $tempoDecorrido > self::TOKEN_LIFETIME;
    }
}