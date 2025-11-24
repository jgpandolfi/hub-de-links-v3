<?php
/**
 * Página de Links Clicados - Painel de Analytics
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
$paginaAtual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$itensPorPagina = 30;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Obter lista de cliques com paginação
try {
    // Conta total de cliques no período
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM tb_links_clicados lc
        INNER JOIN tb_visitas v ON lc.uuid_visita = v.uuid_visita
        WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio AND :data_fim
    ");
    $stmtTotal->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $totalCliques = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
    
    $totalPaginas = ceil($totalCliques / $itensPorPagina);
    
    // Busca cliques do período com paginação
    $stmtCliques = $pdo->prepare("
        SELECT 
            lc.id_clique,
            lc.uuid_visita,
            lc.nome_link,
            lc.url_destino,
            lc.posicao_lista,
            lc.data_hora_clique,
            v.tipo_dispositivo,
            v.navegador,
            v.sistema_operacional,
            v.pais,
            v.regiao,
            v.cidade
        FROM tb_links_clicados lc
        INNER JOIN tb_visitas v ON lc.uuid_visita = v.uuid_visita
        WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio AND :data_fim
        ORDER BY lc.data_hora_clique DESC
        LIMIT :limit OFFSET :offset
    ");
    
    $stmtCliques->bindParam(':data_inicio', $dataInicio);
    $stmtCliques->bindParam(':data_fim', $dataFim);
    $stmtCliques->bindValue(':limit', $itensPorPagina, PDO::PARAM_INT);
    $stmtCliques->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtCliques->execute();
    
    $cliques = $stmtCliques->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 10 links mais clicados
    $stmtTopLinks = $pdo->prepare("
        SELECT 
            lc.nome_link,
            lc.url_destino,
            COUNT(*) as total_cliques,
            COUNT(DISTINCT lc.uuid_visita) as visitantes_unicos
        FROM tb_links_clicados lc
        INNER JOIN tb_visitas v ON lc.uuid_visita = v.uuid_visita
        WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio AND :data_fim
        GROUP BY lc.nome_link, lc.url_destino
        ORDER BY total_cliques DESC
        LIMIT 10
    ");
    $stmtTopLinks->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $topLinks = $stmtTopLinks->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas por posição
    $stmtPosicoes = $pdo->prepare("
        SELECT 
            lc.posicao_lista,
            COUNT(*) as total
        FROM tb_links_clicados lc
        INNER JOIN tb_visitas v ON lc.uuid_visita = v.uuid_visita
        WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio AND :data_fim
        AND lc.posicao_lista IS NOT NULL
        GROUP BY lc.posicao_lista
        ORDER BY lc.posicao_lista ASC
    ");
    $stmtPosicoes->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $estatisticasPosicoes = $stmtPosicoes->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas por dispositivo
    $stmtDispositivos = $pdo->prepare("
        SELECT 
            v.tipo_dispositivo,
            COUNT(*) as total
        FROM tb_links_clicados lc
        INNER JOIN tb_visitas v ON lc.uuid_visita = v.uuid_visita
        WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio AND :data_fim
        GROUP BY v.tipo_dispositivo
        ORDER BY total DESC
    ");
    $stmtDispositivos->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $estatisticasDispositivos = $stmtDispositivos->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $erro = "Erro ao carregar cliques: " . $e->getMessage();
    $cliques = [];
    $topLinks = [];
    $estatisticasPosicoes = [];
    $estatisticasDispositivos = [];
}

/**
 * Extrai domínio de uma URL
 */
function extrairDominio($url) {
    $parsed = parse_url($url);
    $host = $parsed['host'] ?? $url;
    return str_replace('www.', '', $host);
}

/**
 * Retorna ícone da plataforma baseado na URL
 */
function obterIconePlataforma($url) {
    $url = strtolower($url);
    
    if (strpos($url, 'instagram.com') !== false) return 'logo-instagram';
    if (strpos($url, 'linkedin.com') !== false) return 'logo-linkedin';
    if (strpos($url, 'github.com') !== false) return 'logo-github';
    if (strpos($url, 'twitter.com') !== false || strpos($url, 'x.com') !== false) return 'logo-twitter';
    if (strpos($url, 'youtube.com') !== false) return 'logo-youtube';
    if (strpos($url, 'facebook.com') !== false) return 'logo-facebook';
    if (strpos($url, 'whatsapp.com') !== false || strpos($url, 'wa.me') !== false) return 'logo-whatsapp';
    if (strpos($url, 'tiktok.com') !== false) return 'logo-tiktok';
    if (strpos($url, 'behance.net') !== false) return 'logo-behance';
    if (strpos($url, 'dribbble.com') !== false) return 'logo-dribbble';
    
    return 'link-outline';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Links Clicados - Painel de Analytics</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="estilo-painel.css?v=3">
    
    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
    <style>
        /* ========================================
           AJUSTES VISUAIS - CORES SECUNDÁRIAS EM CINZA
           (Mesmos ajustes do dashboard.php, visitas.php e eventos.php)
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
        .stat-card-mini ion-icon {
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
        .valor-ranking {
            color: #6b7280 !important;
        }
        
        /* Ícones de plataforma - CINZA */
        .icone-plataforma {
            background: rgba(107, 114, 128, 0.1) !important;
            color: #6b7280 !important;
        }
        
        /* Valores dos stats laterais - CINZA */
        .valor-stat {
            color: #6b7280 !important;
        }
        
        /* Estilos específicos da página de links clicados */
        .stats-cliques {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .stat-card-mini {
            background-color: var(--fundo-card);
            border: 2px solid var(--borda);
            border-radius: var(--raio-borda);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--sombra);
        }
        
        .stat-card-mini ion-icon {
            font-size: 32px;
            color: var(--cor-primaria);
        }
        
        .stat-info-mini {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .stat-label-mini {
            font-size: 12px;
            color: var(--texto-secundario);
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .stat-valor-mini {
            font-size: 22px;
            font-weight: 900;
            color: var(--texto-primario);
        }
        
        .top-links-secao {
            margin-bottom: 32px;
        }
        
        /* Tabela de cliques */
        .tabela-cliques {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: var(--fundo-card);
            border: 2px solid var(--borda);
            border-radius: var(--raio-borda);
            overflow: hidden;
            box-shadow: var(--sombra);
        }
        
        .tabela-cliques thead {
            background-color: var(--fundo-secundario);
            border-bottom: 2px solid var(--borda);
        }
        
        .tabela-cliques th {
            padding: 18px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 13px;
            color: var(--texto-primario);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .tabela-cliques tbody tr {
            border-bottom: 1px solid var(--borda);
            transition: var(--transicao);
            cursor: pointer;
        }
        
        .tabela-cliques tbody tr:last-child {
            border-bottom: none;
        }
        
        .tabela-cliques tbody tr:hover {
            background-color: rgba(214, 36, 84, 0.05);
        }
        
        .tabela-cliques td {
            padding: 16px;
            font-size: 14px;
            color: var(--texto-secundario);
        }
        
        .celula-data {
            font-weight: 600;
            color: var(--texto-primario);
            white-space: nowrap;
        }
        
        .celula-link {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .icone-plataforma {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(214, 36, 84, 0.1);
            border-radius: 10px;
            color: var(--cor-primaria);
            font-size: 22px;
            flex-shrink: 0;
        }
        
        .info-link {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }
        
        .nome-link {
            font-weight: 600;
            color: var(--texto-primario);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .url-link {
            font-size: 12px;
            color: var(--texto-secundario);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .badge-posicao {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background-color: var(--cor-primaria);
            color: #ffffff;
            border-radius: 8px;
            font-weight: 900;
            font-size: 14px;
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
        
        .detalhe-item.detalhe-link-completo {
            grid-column: 1 / -1;
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
        
        .detalhe-valor[href] {
            color: var(--cor-primaria);
            text-decoration: none;
            transition: var(--transicao);
        }
        
        .detalhe-valor[href]:hover {
            text-decoration: underline;
        }
        
        /* Responsividade */
        @media (max-width: 1200px) {
            .conteudo-principal-painel {
                padding: 24px;
            }
            
            .tabela-cliques {
                font-size: 13px;
            }
            
            .tabela-cliques th,
            .tabela-cliques td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 768px) {
            .conteudo-principal-painel {
                margin-left: 0;
                padding: 20px;
            }
            
            .tabela-cliques {
                display: block;
                overflow-x: auto;
            }
            
            .detalhes-grade {
                grid-template-columns: 1fr;
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
            <a href="eventos.php" class="item-menu">
                <ion-icon name="radio-button-on-outline" class="icone-menu"></ion-icon>
                <span>Eventos</span>
            </a>
            <a href="links-clicados.php" class="item-menu ativo">
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
                <h2 class="titulo-pagina">Links Clicados</h2>
                <p class="subtitulo-pagina">Análise completa dos cliques nos links do hub</p>
            </div>
        </header>

        <!-- Filtro de Período -->
        <section class="secao-filtro-periodo">
            <div class="card-filtro">
                <div class="cabecalho-filtro">
                    <ion-icon name="calendar-outline" class="icone-filtro"></ion-icon>
                    <h3 class="titulo-filtro">Período de Análise</h3>
                </div>
                
                <form method="GET" action="links-clicados.php" class="formulario-filtro">
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
                </form>
            </div>
        </section>

        <!-- Estatísticas Rápidas -->
        <div class="stats-cliques">
            <div class="stat-card-mini">
                <ion-icon name="link-outline"></ion-icon>
                <div class="stat-info-mini">
                    <span class="stat-label-mini">Total de Cliques</span>
                    <span class="stat-valor-mini"><?php echo number_format($totalCliques, 0, ',', '.'); ?></span>
                </div>
            </div>
            
            <div class="stat-card-mini">
                <ion-icon name="layers-outline"></ion-icon>
                <div class="stat-info-mini">
                    <span class="stat-label-mini">Links Únicos</span>
                    <span class="stat-valor-mini"><?php echo number_format(count($topLinks), 0, ',', '.'); ?></span>
                </div>
            </div>
            
            <?php if (!empty($estatisticasDispositivos)): ?>
                <?php $dispositivoDominante = $estatisticasDispositivos[0]; ?>
                <div class="stat-card-mini">
                    <ion-icon name="<?php echo $dispositivoDominante['tipo_dispositivo'] === 'mobile' ? 'phone-portrait-outline' : 'desktop-outline'; ?>"></ion-icon>
                    <div class="stat-info-mini">
                        <span class="stat-label-mini">Dispositivo Principal</span>
                        <span class="stat-valor-mini"><?php echo ucfirst($dispositivoDominante['tipo_dispositivo']); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top 10 Links Mais Clicados -->
        <?php if (!empty($topLinks)): ?>
            <section class="secao-detalhes top-links-secao">
                <h3 class="titulo-secao">
                    <ion-icon name="trophy-outline"></ion-icon>
                    Top 10 Links Mais Clicados
                </h3>
                <div class="lista-ranking">
                    <?php foreach ($topLinks as $index => $link): ?>
                        <div class="item-ranking">
                            <span class="posicao-ranking">#<?php echo $index + 1; ?></span>
                            <div class="info-ranking">
                                <span class="nome-ranking"><?php echo htmlspecialchars($link['nome_link']); ?></span>
                                <span class="detalhe-ranking"><?php echo htmlspecialchars(extrairDominio($link['url_destino'])); ?> • <?php echo number_format($link['visitantes_unicos'], 0, ',', '.'); ?> visitantes únicos</span>
                            </div>
                            <span class="valor-ranking"><?php echo number_format($link['total_cliques'], 0, ',', '.'); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Estatísticas por Posição -->
        <?php if (!empty($estatisticasPosicoes)): ?>
            <section class="secao-detalhes" style="margin-bottom: 32px;">
                <h3 class="titulo-secao">
                    <ion-icon name="list-outline"></ion-icon>
                    Cliques por Posição na Lista
                </h3>
                <div class="lista-stats">
                    <?php foreach ($estatisticasPosicoes as $pos): ?>
                        <div class="item-stat">
                            <span class="label-stat">Posição <?php echo $pos['posicao_lista']; ?></span>
                            <span class="valor-stat"><?php echo number_format($pos['total'], 0, ',', '.'); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Tabela de Cliques -->
        <section class="secao-detalhes">
            <h3 class="titulo-secao">
                <ion-icon name="list-outline"></ion-icon>
                Registro Completo de Cliques (<?php echo number_format($totalCliques, 0, ',', '.'); ?> total)
            </h3>
            
            <?php if (!empty($cliques)): ?>
                <div class="container-tabela">
                    <table class="tabela-cliques">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Link</th>
                                <th>Posição</th>
                                <th>Dispositivo</th>
                                <th>Localização</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cliques as $clique): ?>
                                <tr onclick="mostrarDetalhes(<?php echo $clique['id_clique']; ?>)">
                                    <td class="celula-data">
                                        <?php echo date('d/m/Y H:i:s', strtotime($clique['data_hora_clique'])); ?>
                                    </td>
                                    <td>
                                        <div class="celula-link">
                                            <div class="icone-plataforma">
                                                <ion-icon name="<?php echo obterIconePlataforma($clique['url_destino']); ?>"></ion-icon>
                                            </div>
                                            <div class="info-link">
                                                <span class="nome-link"><?php echo htmlspecialchars($clique['nome_link']); ?></span>
                                                <span class="url-link"><?php echo htmlspecialchars(extrairDominio($clique['url_destino'])); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($clique['posicao_lista']): ?>
                                            <span class="badge-posicao"><?php echo $clique['posicao_lista']; ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--texto-secundario);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo ucfirst($clique['tipo_dispositivo'] ?? 'Desconhecido'); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $loc = [];
                                        if ($clique['cidade']) $loc[] = $clique['cidade'];
                                        if ($clique['regiao']) $loc[] = $clique['regiao'];
                                        if ($clique['pais']) $loc[] = $clique['pais'];
                                        echo htmlspecialchars(implode(', ', $loc) ?: 'Não disponível');
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
                            <a href="?pagina=<?php echo $paginaAtual - 1; ?>&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>" 
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
                            <a href="?pagina=<?php echo $paginaAtual + 1; ?>&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>" 
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
                    Nenhum clique registrado no período selecionado.
                </p>
            <?php endif; ?>
        </section>
    </main>

    <!-- Modal de Detalhes -->
    <div class="modal-overlay" id="modal-detalhes">
        <div class="modal-conteudo">
            <div class="modal-cabecalho">
                <h3 class="modal-titulo">Detalhes do Clique</h3>
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
        // Dados dos cliques para o modal (formato JSON)
        const cliquesData = <?php echo json_encode($cliques, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        // Função para mostrar detalhes do clique
        function mostrarDetalhes(idClique) {
            const clique = cliquesData.find(c => c.id_clique == idClique);
            if (!clique) return;
            
            const modalCorpo = document.getElementById('modal-corpo');
            modalCorpo.innerHTML = `
                <div class="detalhe-item">
                    <span class="detalhe-label">ID do Clique</span>
                    <span class="detalhe-valor">${clique.id_clique}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">UUID da Visita</span>
                    <span class="detalhe-valor" style="font-size: 12px;">${clique.uuid_visita}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Data/Hora</span>
                    <span class="detalhe-valor">${formatarDataHora(clique.data_hora_clique)}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Nome do Link</span>
                    <span class="detalhe-valor">${clique.nome_link}</span>
                </div>
                <div class="detalhe-item detalhe-link-completo">
                    <span class="detalhe-label">URL de Destino</span>
                    <a href="${clique.url_destino}" target="_blank" rel="noopener" class="detalhe-valor">
                        ${clique.url_destino}
                    </a>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Posição na Lista</span>
                    <span class="detalhe-valor">${clique.posicao_lista || 'Não especificada'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Tipo de Dispositivo</span>
                    <span class="detalhe-valor">${clique.tipo_dispositivo || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Navegador</span>
                    <span class="detalhe-valor">${clique.navegador || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Sistema Operacional</span>
                    <span class="detalhe-valor">${clique.sistema_operacional || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">País</span>
                    <span class="detalhe-valor">${clique.pais || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Região/Estado</span>
                    <span class="detalhe-valor">${clique.regiao || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Cidade</span>
                    <span class="detalhe-valor">${clique.cidade || 'Não disponível'}</span>
                </div>
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
            
            document.querySelector('.formulario-filtro').submit();
        });
    </script>
</body>
</html>