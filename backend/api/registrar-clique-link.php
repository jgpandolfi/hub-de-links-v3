<?php
/**
 * API: Registrar Clique em Link
 * Hub de Links - Sistema de Analytics
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

require_once 'configuracao-banco.php';
require_once 'utilitarios-seguranca.php';

try {
    $dadosJson = file_get_contents('php://input');
    $dados = json_decode($dadosJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dados JSON inválidos');
    }
    
    $uuidVisita = $dados['uuid_visita'] ?? '';
    if (!validarUuid($uuidVisita)) {
        throw new Exception('UUID de visita inválido');
    }
    
    $nomeLink = sanitizarString($dados['nome_link'] ?? '');
    $urlDestino = sanitizarUrl($dados['url_destino'] ?? '');
    $posicaoLista = filter_var($dados['posicao_lista'] ?? null, FILTER_VALIDATE_INT);
    
    if (empty($nomeLink) || empty($urlDestino)) {
        throw new Exception('Nome do link e URL são obrigatórios');
    }
    
    $pdo = obterConexaoBanco();
    
    $sql = "INSERT INTO tb_links_clicados (
        uuid_visita, nome_link, url_destino, posicao_lista
    ) VALUES (
        :uuid_visita, :nome_link, :url_destino, :posicao_lista
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':uuid_visita', $uuidVisita);
    $stmt->bindParam(':nome_link', $nomeLink);
    $stmt->bindParam(':url_destino', $urlDestino);
    $stmt->bindParam(':posicao_lista', $posicaoLista, PDO::PARAM_INT);
    
    $stmt->execute();
    
    http_response_code(201);
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Clique registrado com sucesso'
    ]);
    
} catch (Exception $erro) {
    error_log('Erro ao registrar clique: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro ao processar requisição'
    ]);
}