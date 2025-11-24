<?php
/**
 * Utilitários de Segurança e Sanitização
 * Hub de Links - Sistema de Analytics
 */

/**
 * Sanitiza uma string removendo caracteres perigosos
 * @param string $valor
 * @return string
 */
function sanitizarString($valor) {
    if (!is_string($valor)) {
        return '';
    }
    
    // Remove tags HTML e PHP
    $valor = strip_tags($valor);
    
    // Remove caracteres nulos
    $valor = str_replace("\0", '', $valor);
    
    // Limita o tamanho
    $valor = mb_substr($valor, 0, 500);
    
    return trim($valor);
}

/**
 * Sanitiza URL
 * @param string $url
 * @return string|null
 */
function sanitizarUrl($url) {
    if (empty($url)) {
        return null;
    }
    
    $url = filter_var($url, FILTER_SANITIZE_URL);
    
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return mb_substr($url, 0, 500);
    }
    
    return null;
}

/**
 * Gera hash seguro do IP para privacidade
 * @param string $ip
 * @return string
 */
function gerarHashIp($ip) {
    // Adiciona salt para aumentar segurança
    $salt = 'seu_salt_secreto_aqui_' . date('Y-m-d');
    return hash('sha256', $ip . $salt);
}

/**
 * Valida UUID v4
 * @param string $uuid
 * @return bool
 */
function validarUuid($uuid) {
    $padrao = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    return preg_match($padrao, $uuid) === 1;
}

/**
 * Gera UUID v4
 * @return string
 */
function gerarUuid() {
    $dados = random_bytes(16);
    $dados[6] = chr(ord($dados[6]) & 0x0f | 0x40);
    $dados[8] = chr(ord($dados[8]) & 0x3f | 0x80);
    
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($dados), 4));
}

/**
 * Sanitiza dados JSON
 * @param mixed $dados
 * @return string|null
 */
function sanitizarJson($dados) {
    if (empty($dados)) {
        return null;
    }
    
    $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($json === false) {
        return null;
    }
    
    return mb_substr($json, 0, 5000);
}

/**
 * Valida booleano
 * @param mixed $valor
 * @return bool
 */
function validarBooleano($valor) {
    return filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null;
}