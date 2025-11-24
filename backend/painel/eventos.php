<?php
/**
 * Página de Eventos - Painel de Analytics
 * Hub de Links - Sistema de Analytics
 */

// Importa headers de segurança
require_once 'security-headers.php';

// Verifica status de sessão
require_once 'verificar-sessao.php';
exigirAutenticacao();

// Importa proteção CSRF
require_once 'csrf-protection.php';

// Configuração de banco de dados
require_once '../api/configuracao-banco.php';

$pdo = obterConexaoBanco();

// Parâmetros de filtro
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
$tipoFiltro = $_GET['tipo'] ?? 'todos';
$paginaAtual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$itensPorPagina = 30;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Obter lista de eventos com paginação
try {
    // Monta query base
    $sqlBase = "
        FROM tb_eventos e
        INNER JOIN tb_visitas v ON e.uuid_visita = v.uuid_visita
        WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio AND :data_fim
    ";
    
    // Adiciona filtro por tipo se necessário
    if ($tipoFiltro !== 'todos') {
        $sqlBase .= " AND e.tipo_evento = :tipo_evento";
    }
    
    // Conta total de eventos no período
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) as total " . $sqlBase);
    $stmtTotal->bindParam(':data_inicio', $dataInicio);
    $stmtTotal->bindParam(':data_fim', $dataFim);
    if ($tipoFiltro !== 'todos') {
        $stmtTotal->bindParam(':tipo_evento', $tipoFiltro);
    }
    $stmtTotal->execute();
    $totalEventos = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
    
    $totalPaginas = ceil($totalEventos / $itensPorPagina);
    
    // Busca eventos do período com paginação
    $stmtEventos = $pdo->prepare("
        SELECT 
            e.id_evento,
            e.uuid_visita,
            e.tipo_evento,
            e.nome_evento,
            e.valor_evento,
            e.dados_adicionais,
            e.data_hora_evento,
            v.tipo_dispositivo,
            v.navegador,
            v.sistema_operacional,
            v.pais,
            v.cidade
        " . $sqlBase . "
        ORDER BY e.data_hora_evento DESC
        LIMIT :limit OFFSET :offset
    ");
    
    $stmtEventos->bindParam(':data_inicio', $dataInicio);
    $stmtEventos->bindParam(':data_fim', $dataFim);
    if ($tipoFiltro !== 'todos') {
        $stmtEventos->bindParam(':tipo_evento', $tipoFiltro);
    }
    $stmtEventos->bindValue(':limit', $itensPorPagina, PDO::PARAM_INT);
    $stmtEventos->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtEventos->execute();
    
    $eventos = $stmtEventos->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas por tipo de evento
    $stmtTipos = $pdo->prepare("
        SELECT 
            e.tipo_evento,
            COUNT(*) as total
        FROM tb_eventos e
        INNER JOIN tb_visitas v ON e.uuid_visita = v.uuid_visita
        WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio AND :data_fim
        GROUP BY e.tipo_evento
        ORDER BY total DESC
    ");
    $stmtTipos->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $estatisticasTipos = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);
    
    // Eventos mais frequentes
    $stmtFrequentes = $pdo->prepare("
        SELECT 
            e.nome_evento,
            e.tipo_evento,
            COUNT(*) as total
        FROM tb_eventos e
        INNER JOIN tb_visitas v ON e.uuid_visita = v.uuid_visita
        WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio AND :data_fim
        AND e.nome_evento IS NOT NULL AND e.nome_evento != ''
        GROUP BY e.nome_evento, e.tipo_evento
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmtFrequentes->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $eventosFrequentes = $stmtFrequentes->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $erro = "Erro ao carregar eventos: " . $e->getMessage();
    $eventos = [];
    $estatisticasTipos = [];
    $eventosFrequentes = [];
}

/**
 * Retorna ícone e cor para cada tipo de evento
 */
function obterEstiloTipoEvento($tipo) {
    $estilos = [
        'clique_link' => ['icone' => 'link-outline', 'cor' => '#D62454'],
        'visualizacao_secao' => ['icone' => 'eye-outline', 'cor' => '#04d361'],
        'busca_portfolio' => ['icone' => 'search-outline', 'cor' => '#ffc107'],
        'scroll' => ['icone' => 'arrow-down-outline', 'cor' => '#00bcd4'],
        'outro' => ['icone' => 'radio-button-on-outline', 'cor' => '#9c27b0']
    ];
    
    return $estilos[$tipo] ?? $estilos['outro'];
}

/**
 * Formata tipo de evento para exibição
 */
function formatarTipoEvento($tipo) {
    $tipos = [
        'clique_link' => 'Clique em Link',
        'visualizacao_secao' => 'Visualização de Seção',
        'busca_portfolio' => 'Busca no Portfólio',
        'scroll' => 'Rolagem',
        'outro' => 'Outro'
    ];
    
    return $tipos[$tipo] ?? ucfirst($tipo);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - Painel de Analytics</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="estilo-painel.css?v=3">
    
    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
    <style>
        /* ========================================
           AJUSTES VISUAIS - CORES SECUNDÁRIAS EM CINZA
           (Mesmos ajustes do dashboard.php e visitas.php)
           ======================================== */
        
        /* Ícone do filtro de período - CINZA */
        .icone-filtro {
            color: #6b7280 !important;
        }
        
        /* Ícones das seções de detalhes - CINZA */
        .titulo-secao ion-icon {
            color: #6b7280 !important;
        }
        
        /* Background dos ícones dos cards principais - CINZA CLARO */
        .stat-card-evento ion-icon {
            background: rgba(107, 114, 128, 0.1) !important;
        }
        
        /* Botão "Últimos 30 dias" - CINZA */
        .botao-limpar-filtro {
            background-color: #f3f4f6 !important;
            color: #374151 !important;
            border: 2px solid #d1d5db !important;
        }
        
        .tema-escuro .botao-limpar-filtro {
            background-color: #374151 !important;
            color: #f3f4f6 !important;
            border: 2px solid #4b5563 !important;
        }
        
        .botao-limpar-filtro:hover {
            background-color: #e5e7eb !important;
            border-color: #9ca3af !important;
            transform: translateY(-2px);
        }
        
        .tema-escuro .botao-limpar-filtro:hover {
            background-color: #4b5563 !important;
            border-color: #6b7280 !important;
        }
        
        /* Labels dos ícones do input de data - CINZA */
        .label-data ion-icon {
            color: #6b7280 !important;
        }
        
        /* Valores do ranking (lado direito) - CINZA */
        .valor-ranking-evento {
            color: #6b7280 !important;
        }
        
        /* Estilos específicos da página de eventos */
        .stats-eventos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .stat-card-evento {
            background-color: var(--fundo-card);
            border: 2px solid var(--borda);
            border-radius: var(--raio-borda);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--sombra);
        }
        
        .stat-card-evento ion-icon {
            font-size: 32px;
            color: var(--cor-primaria);
        }
        
        .stat-info-evento {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .stat-label-evento {
            font-size: 12px;
            color: var(--texto-secundario);
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .stat-valor-evento {
            font-size: 22px;
            font-weight: 900;
            color: var(--texto-primario);
        }
        
        /* Seletor de tipo de evento */
        .filtro-tipo-evento {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--borda);
        }
        
        .botao-tipo-evento {
            padding: 10px 18px;
            background-color: var(--fundo-secundario);
            border: 2px solid var(--borda);
            border-radius: 20px;
            color: var(--texto-secundario);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transicao);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .botao-tipo-evento:hover {
            border-color: var(--cor-primaria);
            color: var(--texto-primario);
            transform: translateY(-2px);
        }
        
        .botao-tipo-evento.ativo {
            background-color: var(--cor-primaria);
            border-color: var(--cor-primaria);
            color: #ffffff;
        }
        
        .botao-tipo-evento ion-icon {
            font-size: 18px;
        }
        
        /* Tabela de eventos */
        .tabela-eventos {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: var(--fundo-card);
            border: 2px solid var(--borda);
            border-radius: var(--raio-borda);
            overflow: hidden;
            box-shadow: var(--sombra);
        }
        
        .tabela-eventos thead {
            background-color: var(--fundo-secundario);
            border-bottom: 2px solid var(--borda);
        }
        
        .tabela-eventos th {
            padding: 18px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 13px;
            color: var(--texto-primario);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .tabela-eventos tbody tr {
            border-bottom: 1px solid var(--borda);
            transition: var(--transicao);
            cursor: pointer;
        }
        
        .tabela-eventos tbody tr:last-child {
            border-bottom: none;
        }
        
        .tabela-eventos tbody tr:hover {
            background-color: rgba(214, 36, 84, 0.05);
        }
        
        .tabela-eventos td {
            padding: 16px;
            font-size: 14px;
            color: var(--texto-secundario);
        }
        
        .celula-data {
            font-weight: 600;
            color: var(--texto-primario);
            white-space: nowrap;
        }
        
        .badge-tipo-evento {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        
        .badge-tipo-evento ion-icon {
            font-size: 16px;
        }
        
        .celula-nome-evento {
            font-weight: 600;
            color: var(--texto-primario);
        }
        
        .celula-contexto {
            font-size: 13px;
            color: var(--texto-secundario);
        }
        
        .container-tabela {
            overflow-x: auto;
            margin-top: 24px;
        }
        
        .mensagem-vazio-tabela {
            text-align: center;
            padding: 48px 24px;
            color: var(--texto-secundario);
            font-size: 16px;
        }
        
        /* Paginação */
        .paginacao {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin-top: 32px;
            padding: 24px;
        }
        
        .botao-paginacao {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            background-color: var(--fundo-card);
            border: 2px solid var(--borda);
            border-radius: var(--raio-borda);
            color: var(--texto-primario);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transicao);
            text-decoration: none;
            font-size: 14px;
            min-width: 44px;
        }
        
        .botao-paginacao:hover:not(.desabilitado) {
            border-color: var(--cor-primaria);
            color: var(--cor-primaria);
            transform: translateY(-2px);
        }
        
        .botao-paginacao.ativo {
            background-color: var(--cor-primaria);
            border-color: var(--cor-primaria);
            color: #ffffff;
        }
        
        .botao-paginacao.desabilitado {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .info-paginacao {
            color: var(--texto-secundario);
            font-size: 14px;
            font-weight: 600;
        }
        
        /* Modal de detalhes */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-overlay.ativo {
            display: flex;
        }
        
        .modal-conteudo {
            background-color: var(--fundo-card);
            border-radius: var(--raio-borda);
            padding: 32px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--sombra-hover);
            border: 2px solid var(--borda);
            position: relative;
        }
        
        .modal-cabecalho {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--borda);
        }
        
        .modal-titulo {
            font-size: 24px;
            font-weight: 900;
            color: var(--texto-primario);
        }
        
        .botao-fechar-modal {
            background: none;
            border: none;
            color: var(--texto-secundario);
            font-size: 32px;
            cursor: pointer;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transicao);
        }
        
        .botao-fechar-modal:hover {
            background-color: rgba(247, 90, 104, 0.1);
            color: var(--cor-erro);
        }
        
        .detalhes-grade {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .detalhe-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 16px;
            background-color: var(--fundo-secundario);
            border: 2px solid var(--borda);
            border-radius: var(--raio-borda);
        }
        
        .detalhe-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--texto-secundario);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detalhe-valor {
            font-size: 15px;
            font-weight: 600;
            color: var(--texto-primario);
            word-break: break-word;
        }
        
        .detalhe-json {
            background-color: var(--fundo-primario);
            border: 2px solid var(--borda);
            border-radius: 8px;
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            color: var(--texto-primario);
        }
        
        /* Ranking de eventos frequentes */
        .item-ranking-evento {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background-color: var(--fundo-secundario);
            border: 2px solid var(--borda);
            border-radius: var(--raio-borda);
            transition: var(--transicao);
        }
        
        .item-ranking-evento:hover {
            border-color: var(--cor-primaria);
            transform: translateX(4px);
        }
        
        .posicao-ranking-evento {
            font-weight: 900;
            font-size: 22px;
            color: var(--cor-primaria);
            min-width: 36px;
        }
        
        .info-ranking-evento {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .nome-ranking-evento {
            font-weight: 700;
            font-size: 15px;
            color: var(--texto-primario);
        }
        
        .tipo-ranking-evento {
            font-size: 12px;
            color: var(--texto-secundario);
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .valor-ranking-evento {
            font-weight: 900;
            font-size: 20px;
            color: #6b7280; /* CINZA ao invés de verde */
        }
        
        /* Responsividade */
        @media (max-width: 1200px) {
            .conteudo-principal-painel {
                padding: 24px;
            }
            
            .tabela-eventos {
                font-size: 13px;
            }
            
            .tabela-eventos th,
            .tabela-eventos td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 768px) {
            .conteudo-principal-painel {
                margin-left: 0;
                padding: 20px;
            }
            
            .tabela-eventos {
                display: block;
                overflow-x: auto;
            }
            
            .detalhes-grade {
                grid-template-columns: 1fr;
            }
            
            .filtro-tipo-evento {
                flex-direction: column;
            }
            
            .paginacao {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body class="pagina-dashboard">
    <!-- Barra Lateral -->
    <aside class="barra-lateral">
        <div class="logo-painel">
            <h1>Hub Analytics</h1>
            <button id="toggle-tema" class="botao-toggle-tema" aria-label="Alternar tema">
                <ion-icon name="moon-outline" class="icone-lua"></ion-icon>
                <ion-icon name="sunny-outline" class="icone-sol" style="display: none;"></ion-icon>
            </button>
        </div>
        
        <nav class="menu-navegacao">
            <a href="dashboard.php" class="item-menu">
                <ion-icon name="analytics-outline" class="icone-menu"></ion-icon>
                <span>Dashboard</span>
            </a>
            <a href="visitas.php" class="item-menu">
                <ion-icon name="people-outline" class="icone-menu"></ion-icon>
                <span>Visitas</span>
            </a>
            <a href="eventos.php" class="item-menu ativo">
                <ion-icon name="radio-button-on-outline" class="icone-menu"></ion-icon>
                <span>Eventos</span>
            </a>
            <a href="links-clicados.php" class="item-menu">
                <ion-icon name="link-outline" class="icone-menu"></ion-icon>
                <span>Links Clicados</span>
            </a>
            <a href="relatorios.php" class="item-menu">
                <ion-icon name="bar-chart-outline" class="icone-menu"></ion-icon>
                <span>Relatórios</span>
            </a>
        </nav>
        
        <div class="rodape-barra-lateral">
            <div class="info-usuario">
                <span class="nome-usuario"><?php echo htmlspecialchars(obterNomeAdminLogado()); ?></span>
                <span class="email-usuario"><?php echo htmlspecialchars($_SESSION['admin_usuario']); ?></span>
            </div>
            <form method="POST" action="logout.php">
                <?php echo CSRFProtection::campoToken(); ?>
                <button type="submit" class="botao-logout">
                    <ion-icon name="log-out-outline"></ion-icon>
                    Sair
                </button>
            </form>
        </div>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="conteudo-principal-painel">
        <!-- Cabeçalho -->
        <header class="cabecalho-painel">
            <div class="titulo-container">
                <h2 class="titulo-pagina">Eventos</h2>
                <p class="subtitulo-pagina">Análise detalhada de todas as interações capturadas</p>
            </div>
        </header>

        <!-- Filtro de Período -->
        <section class="secao-filtro-periodo">
            <div class="card-filtro">
                <div class="cabecalho-filtro">
                    <ion-icon name="calendar-outline" class="icone-filtro"></ion-icon>
                    <h3 class="titulo-filtro">Período de Análise</h3>
                </div>
                
                <form method="GET" action="eventos.php" class="formulario-filtro">
                    <div class="grupo-datas">
                        <div class="campo-data">
                            <label for="data_inicio" class="label-data">
                                <ion-icon name="calendar-number-outline"></ion-icon>
                                Data Inicial
                            </label>
                            <input 
                                type="date" 
                                id="data_inicio" 
                                name="data_inicio" 
                                value="<?php echo htmlspecialchars($dataInicio); ?>"
                                max="<?php echo date('Y-m-d'); ?>"
                                class="input-data"
                                required>
                        </div>
                        
                        <div class="campo-data">
                            <label for="data_fim" class="label-data">
                                <ion-icon name="calendar-clear-outline"></ion-icon>
                                Data Final
                            </label>
                            <input 
                                type="date" 
                                id="data_fim" 
                                name="data_fim" 
                                value="<?php echo htmlspecialchars($dataFim); ?>"
                                max="<?php echo date('Y-m-d'); ?>"
                                class="input-data"
                                required>
                        </div>
                    </div>
                    
                    <div class="botoes-filtro">
                        <button type="submit" class="botao-aplicar-filtro">
                            <ion-icon name="funnel-outline"></ion-icon>
                            Aplicar Filtro
                        </button>
                        <button type="button" id="botao-limpar-filtro" class="botao-limpar-filtro">
                            <ion-icon name="refresh-outline"></ion-icon>
                            Últimos 30 dias
                        </button>
                    </div>
                    
                    <!-- Filtro por Tipo de Evento -->
                    <div class="filtro-tipo-evento">
                        <a href="?data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>&tipo=todos" 
                           class="botao-tipo-evento <?php echo $tipoFiltro === 'todos' ? 'ativo' : ''; ?>">
                            <ion-icon name="apps-outline"></ion-icon>
                            Todos
                        </a>
                        <a href="?data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>&tipo=clique_link" 
                           class="botao-tipo-evento <?php echo $tipoFiltro === 'clique_link' ? 'ativo' : ''; ?>">
                            <ion-icon name="link-outline"></ion-icon>
                            Cliques em Link
                        </a>
                        <a href="?data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>&tipo=visualizacao_secao" 
                           class="botao-tipo-evento <?php echo $tipoFiltro === 'visualizacao_secao' ? 'ativo' : ''; ?>">
                            <ion-icon name="eye-outline"></ion-icon>
                            Visualizações
                        </a>
                        <a href="?data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>&tipo=busca_portfolio" 
                           class="botao-tipo-evento <?php echo $tipoFiltro === 'busca_portfolio' ? 'ativo' : ''; ?>">
                            <ion-icon name="search-outline"></ion-icon>
                            Buscas
                        </a>
                        <a href="?data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>&tipo=scroll" 
                           class="botao-tipo-evento <?php echo $tipoFiltro === 'scroll' ? 'ativo' : ''; ?>">
                            <ion-icon name="arrow-down-outline"></ion-icon>
                            Scroll
                        </a>
                    </div>
                </form>
            </div>
        </section>

        <!-- Estatísticas por Tipo -->
        <?php if (!empty($estatisticasTipos)): ?>
            <div class="stats-eventos">
                <?php foreach ($estatisticasTipos as $tipo): ?>
                    <?php $estilo = obterEstiloTipoEvento($tipo['tipo_evento']); ?>
                    <div class="stat-card-evento">
                        <ion-icon name="<?php echo $estilo['icone']; ?>"></ion-icon>
                        <div class="stat-info-evento">
                            <span class="stat-label-evento"><?php echo formatarTipoEvento($tipo['tipo_evento']); ?></span>
                            <span class="stat-valor-evento"><?php echo number_format($tipo['total'], 0, ',', '.'); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Eventos Mais Frequentes -->
        <?php if (!empty($eventosFrequentes)): ?>
            <section class="secao-detalhes" style="margin-bottom: 32px;">
                <h3 class="titulo-secao">
                    <ion-icon name="flame-outline"></ion-icon>
                    Eventos Mais Frequentes
                </h3>
                <div class="lista-ranking">
                    <?php foreach ($eventosFrequentes as $index => $evento): ?>
                        <div class="item-ranking-evento">
                            <span class="posicao-ranking-evento">#<?php echo $index + 1; ?></span>
                            <div class="info-ranking-evento">
                                <span class="nome-ranking-evento"><?php echo htmlspecialchars($evento['nome_evento']); ?></span>
                                <span class="tipo-ranking-evento"><?php echo formatarTipoEvento($evento['tipo_evento']); ?></span>
                            </div>
                            <span class="valor-ranking-evento"><?php echo number_format($evento['total'], 0, ',', '.'); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Tabela de Eventos -->
        <section class="secao-detalhes">
            <h3 class="titulo-secao">
                <ion-icon name="list-outline"></ion-icon>
                Registro de Eventos (<?php echo number_format($totalEventos, 0, ',', '.'); ?> total)
            </h3>
            
            <?php if (!empty($eventos)): ?>
                <div class="container-tabela">
                    <table class="tabela-eventos">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Tipo</th>
                                <th>Nome do Evento</th>
                                <th>Valor</th>
                                <th>Contexto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventos as $evento): ?>
                                <?php $estilo = obterEstiloTipoEvento($evento['tipo_evento']); ?>
                                <tr onclick="mostrarDetalhes(<?php echo $evento['id_evento']; ?>)">
                                    <td class="celula-data">
                                        <?php echo date('d/m/Y H:i:s', strtotime($evento['data_hora_evento'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge-tipo-evento" style="background-color: <?php echo $estilo['cor']; ?>20; color: <?php echo $estilo['cor']; ?>; border: 2px solid <?php echo $estilo['cor']; ?>;">
                                            <ion-icon name="<?php echo $estilo['icone']; ?>"></ion-icon>
                                            <?php echo formatarTipoEvento($evento['tipo_evento']); ?>
                                        </span>
                                    </td>
                                    <td class="celula-nome-evento">
                                        <?php echo htmlspecialchars($evento['nome_evento'] ?: 'Sem nome'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($evento['valor_evento'] ?: '-'); ?>
                                    </td>
                                    <td class="celula-contexto">
                                        <?php 
                                        $contexto = [];
                                        if ($evento['tipo_dispositivo']) $contexto[] = ucfirst($evento['tipo_dispositivo']);
                                        if ($evento['navegador']) $contexto[] = $evento['navegador'];
                                        if ($evento['cidade']) $contexto[] = $evento['cidade'];
                                        echo htmlspecialchars(implode(' • ', $contexto) ?: 'N/A');
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <?php if ($totalPaginas > 1): ?>
                    <div class="paginacao">
                        <?php if ($paginaAtual > 1): ?>
                            <a href="?pagina=<?php echo $paginaAtual - 1; ?>&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>&tipo=<?php echo $tipoFiltro; ?>" 
                               class="botao-paginacao">
                                <ion-icon name="chevron-back-outline"></ion-icon>
                            </a>
                        <?php else: ?>
                            <span class="botao-paginacao desabilitado">
                                <ion-icon name="chevron-back-outline"></ion-icon>
                            </span>
                        <?php endif; ?>
                        
                        <span class="info-paginacao">
                            Página <?php echo $paginaAtual; ?> de <?php echo $totalPaginas; ?>
                        </span>
                        
                        <?php if ($paginaAtual < $totalPaginas): ?>
                            <a href="?pagina=<?php echo $paginaAtual + 1; ?>&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>&tipo=<?php echo $tipoFiltro; ?>" 
                               class="botao-paginacao">
                                <ion-icon name="chevron-forward-outline"></ion-icon>
                            </a>
                        <?php else: ?>
                            <span class="botao-paginacao desabilitado">
                                <ion-icon name="chevron-forward-outline"></ion-icon>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <p class="mensagem-vazio-tabela">
                    <ion-icon name="sad-outline" style="font-size: 48px; color: var(--texto-secundario);"></ion-icon><br>
                    Nenhum evento registrado no período selecionado.
                </p>
            <?php endif; ?>
        </section>
    </main>

    <!-- Modal de Detalhes -->
    <div class="modal-overlay" id="modal-detalhes">
        <div class="modal-conteudo">
            <div class="modal-cabecalho">
                <h3 class="modal-titulo">Detalhes do Evento</h3>
                <button class="botao-fechar-modal" onclick="fecharModal()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <div id="modal-corpo" class="detalhes-grade">
                <!-- Conteúdo será inserido via JavaScript -->
            </div>
        </div>
    </div>

    <script src="script-painel.js?v=3"></script>
    <script>
        // Dados dos eventos para o modal (formato JSON)
        const eventosData = <?php echo json_encode($eventos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        // Função para mostrar detalhes do evento
        function mostrarDetalhes(idEvento) {
            const evento = eventosData.find(e => e.id_evento == idEvento);
            if (!evento) return;
            
            const modalCorpo = document.getElementById('modal-corpo');
            
            let dadosAdicionaisHTML = '';
            if (evento.dados_adicionais) {
                try {
                    const dados = JSON.parse(evento.dados_adicionais);
                    dadosAdicionaisHTML = `
                        <div class="detalhe-item" style="grid-column: 1 / -1;">
                            <span class="detalhe-label">Dados Adicionais (JSON)</span>
                            <pre class="detalhe-json">${JSON.stringify(dados, null, 2)}</pre>
                        </div>
                    `;
                } catch (e) {
                    dadosAdicionaisHTML = `
                        <div class="detalhe-item" style="grid-column: 1 / -1;">
                            <span class="detalhe-label">Dados Adicionais (Texto)</span>
                            <span class="detalhe-valor">${evento.dados_adicionais}</span>
                        </div>
                    `;
                }
            }
            
            modalCorpo.innerHTML = `
                <div class="detalhe-item">
                    <span class="detalhe-label">ID do Evento</span>
                    <span class="detalhe-valor">${evento.id_evento}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">UUID da Visita</span>
                    <span class="detalhe-valor" style="font-size: 12px;">${evento.uuid_visita}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Data/Hora</span>
                    <span class="detalhe-valor">${formatarDataHora(evento.data_hora_evento)}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Tipo de Evento</span>
                    <span class="detalhe-valor">${evento.tipo_evento}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Nome do Evento</span>
                    <span class="detalhe-valor">${evento.nome_evento || 'Não informado'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Valor do Evento</span>
                    <span class="detalhe-valor">${evento.valor_evento || 'Não informado'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Tipo de Dispositivo</span>
                    <span class="detalhe-valor">${evento.tipo_dispositivo || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Navegador</span>
                    <span class="detalhe-valor">${evento.navegador || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Sistema Operacional</span>
                    <span class="detalhe-valor">${evento.sistema_operacional || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">País</span>
                    <span class="detalhe-valor">${evento.pais || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Cidade</span>
                    <span class="detalhe-valor">${evento.cidade || 'Não disponível'}</span>
                </div>
                ${dadosAdicionaisHTML}
            `;
            
            document.getElementById('modal-detalhes').classList.add('ativo');
        }
        
        // Função para fechar modal
        function fecharModal() {
            document.getElementById('modal-detalhes').classList.remove('ativo');
        }
        
        // Fechar modal ao clicar fora
        document.getElementById('modal-detalhes').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });
        
        // Função para formatar data/hora
        function formatarDataHora(datetime) {
            if (!datetime) return 'Não disponível';
            const data = new Date(datetime);
            return data.toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }
        
        // Script para o botão de limpar filtro
        document.getElementById('botao-limpar-filtro').addEventListener('click', function() {
            const hoje = new Date().toISOString().split('T')[0];
            const trintaDiasAtras = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            
            document.getElementById('data_inicio').value = trintaDiasAtras;
            document.getElementById('data_fim').value = hoje;
            
            // Preserva o tipo de evento ao limpar
            const tipoAtual = new URLSearchParams(window.location.search).get('tipo') || 'todos';
            window.location.href = `?data_inicio=${trintaDiasAtras}&data_fim=${hoje}&tipo=${tipoAtual}`;
        });
    </script>
</body>
</html>