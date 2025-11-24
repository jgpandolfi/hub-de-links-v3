<?php
/**
 * Rate Limiter - Proteção Contra Brute-Force
 * Hub de Links - Painel de Analytics
 * 
 * Implementa limite de 5 tentativas de login em 30 minutos por IP
 */

class RateLimiter {
    // Configurações
    const MAX_TENTATIVAS = 5;          // Máximo de tentativas permitidas
    const TEMPO_BLOQUEIO = 1800;       // 30 minutos em segundos
    const TEMPO_EXPIRACAO = 3600;      // 60 minutos para limpar arquivos antigos
    
    /**
     * Verifica se IP está bloqueado
     * @param string $ip Endereço IP do usuário
     * @return bool True se bloqueado, False se liberado
     */
    public static function verificarBloqueio($ip) {
        $arquivo = self::obterArquivoTentativas($ip);
        
        // Se não existe arquivo, não está bloqueado
        if (!file_exists($arquivo)) {
            return false;
        }
        
        $dados = json_decode(file_get_contents($arquivo), true);
        
        // Verifica se ainda está no período de bloqueio
        if (isset($dados['bloqueado_ate']) && $dados['bloqueado_ate'] > time()) {
            return true;
        }
        
        // Se o bloqueio expirou, limpa os dados
        if (isset($dados['bloqueado_ate']) && $dados['bloqueado_ate'] <= time()) {
            self::limparTentativas($ip);
            return false;
        }
        
        return false;
    }
    
    /**
     * Registra tentativa de login (sucesso ou falha)
     * @param string $ip Endereço IP
     * @param bool $sucesso Se o login foi bem-sucedido
     */
    public static function registrarTentativa($ip, $sucesso = false) {
        $arquivo = self::obterArquivoTentativas($ip);
        
        // Se login bem-sucedido, limpa tentativas
        if ($sucesso) {
            self::limparTentativas($ip);
            return;
        }
        
        // Login falhou - registra tentativa
        $dados = [
            'tentativas' => 0,
            'bloqueado_ate' => 0,
            'primeira_tentativa' => time(),
            'ultima_tentativa' => time()
        ];
        
        // Se já existe arquivo, carrega dados
        if (file_exists($arquivo)) {
            $dadosExistentes = json_decode(file_get_contents($arquivo), true);
            if (is_array($dadosExistentes)) {
                $dados = array_merge($dados, $dadosExistentes);
            }
        }
        
        // Incrementa contador
        $dados['tentativas']++;
        $dados['ultima_tentativa'] = time();
        
        // Se atingiu o limite, bloqueia
        if ($dados['tentativas'] >= self::MAX_TENTATIVAS) {
            $dados['bloqueado_ate'] = time() + self::TEMPO_BLOQUEIO;
        }
        
        // Salva arquivo
        file_put_contents($arquivo, json_encode($dados));
    }
    
    /**
     * Obtém número de tentativas restantes
     * @param string $ip Endereço IP
     * @return int Tentativas restantes
     */
    public static function obterTentativasRestantes($ip) {
        $arquivo = self::obterArquivoTentativas($ip);
        
        if (!file_exists($arquivo)) {
            return self::MAX_TENTATIVAS;
        }
        
        $dados = json_decode(file_get_contents($arquivo), true);
        
        if (!isset($dados['tentativas'])) {
            return self::MAX_TENTATIVAS;
        }
        
        return max(0, self::MAX_TENTATIVAS - $dados['tentativas']);
    }
    
    /**
     * Obtém tempo restante de bloqueio (em segundos)
     * @param string $ip Endereço IP
     * @return int Segundos restantes de bloqueio
     */
    public static function obterTempoBloqueio($ip) {
        $arquivo = self::obterArquivoTentativas($ip);
        
        if (!file_exists($arquivo)) {
            return 0;
        }
        
        $dados = json_decode(file_get_contents($arquivo), true);
        
        if (!isset($dados['bloqueado_ate'])) {
            return 0;
        }
        
        return max(0, $dados['bloqueado_ate'] - time());
    }
    
    /**
     * Limpa tentativas de um IP (após login bem-sucedido)
     * @param string $ip Endereço IP
     */
    private static function limparTentativas($ip) {
        $arquivo = self::obterArquivoTentativas($ip);
        
        if (file_exists($arquivo)) {
            unlink($arquivo);
        }
    }
    
    /**
     * Obtém caminho do arquivo de tentativas
     * @param string $ip Endereço IP
     * @return string Caminho completo do arquivo
     */
    private static function obterArquivoTentativas($ip) {
        $dir = __DIR__ . '/../../temp/rate-limit';
        
        // Cria diretório se não existir
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Hash do IP para privacidade (+ salt)
        $salt = 'hub_security_2025';
        $ipHash = hash('sha256', $ip . $salt);
        
        return $dir . '/' . $ipHash . '.json';
    }
    
    /**
     * Limpa arquivos antigos (maintenance)
     * Deve ser chamado periodicamente
     */
    public static function limparArquivosAntigos() {
        $dir = __DIR__ . '/../../temp/rate-limit';
        
        if (!is_dir($dir)) {
            return;
        }
        
        $agora = time();
        $arquivos = glob($dir . '/*.json');
        
        foreach ($arquivos as $arquivo) {
            $modificado = filemtime($arquivo);
            
            // Remove arquivos com mais de 30 minutos
            if (($agora - $modificado) > self::TEMPO_EXPIRACAO) {
                unlink($arquivo);
            }
        }
    }
}