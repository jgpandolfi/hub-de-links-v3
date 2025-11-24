<?php
/**
 * API: Atualizar Fim de Visita
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
    
    $duracaoSessao = filter_var($dados['duracao_sessao_segundos'] ?? 0, FILTER_VALIDATE_INT);
    if ($duracaoSessao === false || $duracaoSessao < 0) {
        $duracaoSessao = 0;
    }
    
    $pdo = obterConexaoBanco();
    
    $sql = "UPDATE tb_visitas 
            SET data_hora_fim = NOW(), 
                duracao_sessao_segundos = :duracao
            WHERE uuid_visita = :uuid_visita";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':duracao', $duracaoSessao, PDO::PARAM_INT);
    $stmt->bindParam(':uuid_visita', $uuidVisita);
    
    $stmt->execute();
    
    http_response_code(200);
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Visita atualizada com sucesso'
    ]);
    
} catch (Exception $erro) {
    error_log('Erro ao atualizar visita: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro ao processar requisição'
    ]);
}