<?php
/**
 * API: Registrar Evento de Interação
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
    
    // Valida UUID da visita
    $uuidVisita = $dados['uuid_visita'] ?? '';
    if (!validarUuid($uuidVisita)) {
        throw new Exception('UUID de visita inválido');
    }
    
    // Valida tipo de evento
    $tiposPermitidos = ['clique_link', 'visualizacao_secao', 'busca_portfolio', 'scroll', 'outro'];
    $tipoEvento = $dados['tipo_evento'] ?? '';
    if (!in_array($tipoEvento, $tiposPermitidos)) {
        throw new Exception('Tipo de evento inválido');
    }
    
    // Sanitiza dados
    $nomeEvento = sanitizarString($dados['nome_evento'] ?? '');
    $valorEvento = sanitizarString($dados['valor_evento'] ?? '');
    $dadosAdicionais = sanitizarJson($dados['dados_adicionais'] ?? null);
    
    $pdo = obterConexaoBanco();
    
    $sql = "INSERT INTO tb_eventos (
        uuid_visita, tipo_evento, nome_evento, valor_evento, dados_adicionais
    ) VALUES (
        :uuid_visita, :tipo_evento, :nome_evento, :valor_evento, :dados_adicionais
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':uuid_visita', $uuidVisita);
    $stmt->bindParam(':tipo_evento', $tipoEvento);
    $stmt->bindParam(':nome_evento', $nomeEvento);
    $stmt->bindParam(':valor_evento', $valorEvento);
    $stmt->bindParam(':dados_adicionais', $dadosAdicionais);
    
    $stmt->execute();
    
    http_response_code(201);
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Evento registrado com sucesso'
    ]);
    
} catch (Exception $erro) {
    error_log('Erro ao registrar evento: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro ao processar requisição'
    ]);
}