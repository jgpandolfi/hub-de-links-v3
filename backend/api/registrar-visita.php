<?php
/**
 * API: Registrar Nova Visita
 * Hub de Links - Sistema de Analytics
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Apenas requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

require_once 'configuracao-banco.php';
require_once 'utilitarios-seguranca.php';

try {
    // Lê dados JSON da requisição
    $dadosJson = file_get_contents('php://input');
    $dados = json_decode($dadosJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dados JSON inválidos');
    }
    
    // Valida e sanitiza dados obrigatórios
    $uuidVisita = isset($dados['uuid_visita']) && validarUuid($dados['uuid_visita']) 
        ? $dados['uuid_visita'] 
        : gerarUuid();
    
    $consentimentoAceito = isset($dados['consentimento_aceito']) 
        ? filter_var($dados['consentimento_aceito'], FILTER_VALIDATE_BOOLEAN)
        : false;
    
    // Não registra se não houver consentimento
    if (!$consentimentoAceito) {
        http_response_code(200);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Consentimento não fornecido'
        ]);
        exit;
    }
    
    // Conecta ao banco
    $pdo = obterConexaoBanco();
    
    // Verifica se o UUID já existe
    $sqlCheck = "SELECT uuid_visita FROM tb_visitas WHERE uuid_visita = :uuid_visita";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->bindParam(':uuid_visita', $uuidVisita);
    $stmtCheck->execute();
    
    if ($stmtCheck->rowCount() > 0) {
        // UUID já existe - retorna sucesso sem inserir novamente
        http_response_code(200);
        echo json_encode([
            'sucesso' => true,
            'uuid_visita' => $uuidVisita,
            'mensagem' => 'Visita já registrada anteriormente'
        ]);
        exit;
    }
    
    // Se chegou aqui, UUID não existe - prossegue com INSERT
    
    // Obtém IP do visitante
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    $ipHash = gerarHashIp($ip);
    
    // Sanitiza dados
    $userAgent = sanitizarString($_SERVER['HTTP_USER_AGENT'] ?? '');
    $navegador = sanitizarString($dados['navegador'] ?? '');
    $versaoNavegador = sanitizarString($dados['versao_navegador'] ?? '');
    $sistemaOperacional = sanitizarString($dados['sistema_operacional'] ?? '');
    $versaoSistema = sanitizarString($dados['versao_sistema'] ?? '');
    $tipoDispositivo = in_array($dados['tipo_dispositivo'] ?? '', ['desktop', 'mobile', 'tablet']) 
        ? $dados['tipo_dispositivo'] 
        : 'outro';
    $resolucaoTela = sanitizarString($dados['resolucao_tela'] ?? '');
    $idiomaNavegador = sanitizarString($dados['idioma_navegador'] ?? '');
    $urlReferencia = sanitizarUrl($dados['url_referencia'] ?? '');
    $utmSource = sanitizarString($dados['utm_source'] ?? '');
    $utmMedium = sanitizarString($dados['utm_medium'] ?? '');
    $utmCampaign = sanitizarString($dados['utm_campaign'] ?? '');
    $utmTerm = sanitizarString($dados['utm_term'] ?? '');
    $utmContent = sanitizarString($dados['utm_content'] ?? '');
    $origemTrafego = sanitizarString($dados['origem_trafego'] ?? '');
    $pais = sanitizarString($dados['pais'] ?? '');
    $regiao = sanitizarString($dados['regiao'] ?? '');
    $cidade = sanitizarString($dados['cidade'] ?? '');
    
    // Prepara query de INSERT
    $sql = "INSERT INTO tb_visitas (
        uuid_visita, ip_hash, user_agent, navegador, versao_navegador,
        sistema_operacional, versao_sistema, tipo_dispositivo, resolucao_tela,
        idioma_navegador, url_referencia, utm_source, utm_medium, utm_campaign,
        utm_term, utm_content, origem_trafego, pais, regiao, cidade,
        consentimento_aceito, data_consentimento
    ) VALUES (
        :uuid_visita, :ip_hash, :user_agent, :navegador, :versao_navegador,
        :sistema_operacional, :versao_sistema, :tipo_dispositivo, :resolucao_tela,
        :idioma_navegador, :url_referencia, :utm_source, :utm_medium, :utm_campaign,
        :utm_term, :utm_content, :origem_trafego, :pais, :regiao, :cidade,
        :consentimento_aceito, NOW()
    )";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind dos parâmetros
    $stmt->bindParam(':uuid_visita', $uuidVisita);
    $stmt->bindParam(':ip_hash', $ipHash);
    $stmt->bindParam(':user_agent', $userAgent);
    $stmt->bindParam(':navegador', $navegador);
    $stmt->bindParam(':versao_navegador', $versaoNavegador);
    $stmt->bindParam(':sistema_operacional', $sistemaOperacional);
    $stmt->bindParam(':versao_sistema', $versaoSistema);
    $stmt->bindParam(':tipo_dispositivo', $tipoDispositivo);
    $stmt->bindParam(':resolucao_tela', $resolucaoTela);
    $stmt->bindParam(':idioma_navegador', $idiomaNavegador);
    $stmt->bindParam(':url_referencia', $urlReferencia);
    $stmt->bindParam(':utm_source', $utmSource);
    $stmt->bindParam(':utm_medium', $utmMedium);
    $stmt->bindParam(':utm_campaign', $utmCampaign);
    $stmt->bindParam(':utm_term', $utmTerm);
    $stmt->bindParam(':utm_content', $utmContent);
    $stmt->bindParam(':origem_trafego', $origemTrafego);
    $stmt->bindParam(':pais', $pais);
    $stmt->bindParam(':regiao', $regiao);
    $stmt->bindParam(':cidade', $cidade);
    $stmt->bindParam(':consentimento_aceito', $consentimentoAceito, PDO::PARAM_BOOL);
    
    // Executa
    $stmt->execute();
    
    http_response_code(201);
    echo json_encode([
        'sucesso' => true,
        'uuid_visita' => $uuidVisita,
        'mensagem' => 'Visita registrada com sucesso'
    ]);
    
} catch (Exception $erro) {
    error_log('Erro ao registrar visita: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro ao processar requisição'
    ]);
}