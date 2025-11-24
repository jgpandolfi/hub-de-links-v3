<?php
/**
 * Página de Visitas - Painel de Analytics
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
$itensPorPagina = 20;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Obter lista de visitas com paginação
try {
    // Conta total de visitas no período
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM tb_visitas 
        WHERE DATE(data_hora_inicio) BETWEEN :data_inicio AND :data_fim
    ");
    $stmtTotal->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $totalVisitas = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
    
    $totalPaginas = ceil($totalVisitas / $itensPorPagina);
    
    // Busca visitas do período com paginação
    $stmtVisitas = $pdo->prepare("
        SELECT 
            uuid_visita,
            ip_hash,
            navegador,
            versao_navegador,
            sistema_operacional,
            versao_sistema,
            tipo_dispositivo,
            resolucao_tela,
            idioma_navegador,
            url_referencia,
            origem_trafego,
            pais,
            regiao,
            cidade,
            data_hora_inicio,
            data_hora_fim,
            duracao_sessao_segundos,
            utm_source,
            utm_medium,
            utm_campaign
        FROM tb_visitas 
        WHERE DATE(data_hora_inicio) BETWEEN :data_inicio AND :data_fim
        ORDER BY data_hora_inicio DESC
        LIMIT :limit OFFSET :offset
    ");
    
    $stmtVisitas->bindParam(':data_inicio', $dataInicio);
    $stmtVisitas->bindParam(':data_fim', $dataFim);
    $stmtVisitas->bindValue(':limit', $itensPorPagina, PDO::PARAM_INT);
    $stmtVisitas->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtVisitas->execute();
    
    $visitas = $stmtVisitas->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas rápidas do período
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_visitas,
            AVG(duracao_sessao_segundos) as duracao_media,
            SUM(CASE WHEN tipo_dispositivo = 'mobile' THEN 1 ELSE 0 END) as mobile,
            SUM(CASE WHEN tipo_dispositivo = 'desktop' THEN 1 ELSE 0 END) as desktop,
            SUM(CASE WHEN tipo_dispositivo = 'tablet' THEN 1 ELSE 0 END) as tablet
        FROM tb_visitas 
        WHERE DATE(data_hora_inicio) BETWEEN :data_inicio AND :data_fim
    ");
    $stmtStats->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $erro = "Erro ao carregar visitas: " . $e->getMessage();
    $visitas = [];
    $stats = [
        'total_visitas' => 0,
        'duracao_media' => 0,
        'mobile' => 0,
        'desktop' => 0,
        'tablet' => 0
    ];
}

/**
 * Formata duração em segundos para formato legível
 */
function formatarDuracao($segundos) {
    if ($segundos < 60) {
        return $segundos . 's';
    } elseif ($segundos < 3600) {
        $minutos = floor($segundos / 60);
        $segs = $segundos % 60;
        return $minutos . 'm ' . $segs . 's';
    } else {
        $horas = floor($segundos / 3600);
        $minutos = floor(($segundos % 3600) / 60);
        return $horas . 'h ' . $minutos . 'm';
    }
}

/**
 * Obtém ícone do dispositivo
 */
function iconeDispositivo($tipo) {
    switch ($tipo) {
        case 'mobile': return 'phone-portrait-outline';
        case 'tablet': return 'tablet-portrait-outline';
        case 'desktop': return 'desktop-outline';
        default: return 'help-outline';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitas - Painel de Analytics</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="estilo-painel.css?v=3">
    
    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
    <style>
        /* ========================================
           AJUSTES VISUAIS - CORES SECUNDÁRIAS EM CINZA
           (Mesmos ajustes do dashboard.php)
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
        
        /* Badge de dispositivo - mantém ícone mas suaviza */
        .badge-dispositivo ion-icon {
            color: #6b7280 !important;
        }
        
        /* Estilos específicos da página de visitas */
        .stats-rapidos {
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
        
        .tabela-visitas {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: var(--fundo-card);
            border: 2px solid var(--borda);
            border-radius: var(--raio-borda);
            overflow: hidden;
            box-shadow: var(--sombra);
        }
        
        .tabela-visitas thead {
            background-color: var(--fundo-secundario);
            border-bottom: 2px solid var(--borda);
        }
        
        .tabela-visitas th {
            padding: 18px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 13px;
            color: var(--texto-primario);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .tabela-visitas tbody tr {
            border-bottom: 1px solid var(--borda);
            transition: var(--transicao);
            cursor: pointer;
        }
        
        .tabela-visitas tbody tr:last-child {
            border-bottom: none;
        }
        
        .tabela-visitas tbody tr:hover {
            background-color: rgba(214, 36, 84, 0.05);
        }
        
        .tabela-visitas td {
            padding: 16px;
            font-size: 14px;
            color: var(--texto-secundario);
        }
        
        .celula-data {
            font-weight: 600;
            color: var(--texto-primario);
            white-space: nowrap;
        }
        
        .badge-dispositivo {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background-color: var(--fundo-secundario);
            border: 2px solid var(--borda);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-dispositivo ion-icon {
            font-size: 16px;
        }
        
        .celula-localizacao {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .celula-duracao {
            font-weight: 600;
            color: var(--texto-primario);
            white-space: nowrap;
        }
        
        .tag-utm {
            display: inline-block;
            padding: 4px 10px;
            background-color: rgba(4, 211, 97, 0.1);
            color: var(--cor-sucesso);
            border: 2px solid var(--cor-sucesso);
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
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
        
        /* Responsividade */
        @media (max-width: 1200px) {
            .conteudo-principal-painel {
                padding: 24px;
            }
            
            .tabela-visitas {
                font-size: 13px;
            }
            
            .tabela-visitas th,
            .tabela-visitas td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 768px) {
            .conteudo-principal-painel {
                margin-left: 0;
                padding: 20px;
            }
            
            .tabela-visitas {
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
            <a href="visitas.php" class="item-menu ativo">
                <ion-icon name="people-outline" class="icone-menu"></ion-icon>
                <span>Visitas</span>
            </a>
            <a href="eventos.php" class="item-menu">
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
                <h2 class="titulo-pagina">Visitas</h2>
                <p class="subtitulo-pagina">Análise detalhada de todas as visitas ao hub</p>
            </div>
        </header>

        <!-- Filtro de Período -->
        <section class="secao-filtro-periodo">
            <div class="card-filtro">
                <div class="cabecalho-filtro">
                    <ion-icon name="calendar-outline" class="icone-filtro"></ion-icon>
                    <h3 class="titulo-filtro">Período de Análise</h3>
                </div>
                
                <form method="GET" action="visitas.php" class="formulario-filtro">
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
        <div class="stats-rapidos">
            <div class="stat-card-mini">
                <ion-icon name="people-outline"></ion-icon>
                <div class="stat-info-mini">
                    <span class="stat-label-mini">Total de Visitas</span>
                    <span class="stat-valor-mini"><?php echo number_format($stats['total_visitas'], 0, ',', '.'); ?></span>
                </div>
            </div>
            
            <div class="stat-card-mini">
                <ion-icon name="time-outline"></ion-icon>
                <div class="stat-info-mini">
                    <span class="stat-label-mini">Duração Média</span>
                    <span class="stat-valor-mini"><?php echo formatarDuracao(round($stats['duracao_media'])); ?></span>
                </div>
            </div>
            
            <div class="stat-card-mini">
                <ion-icon name="phone-portrait-outline"></ion-icon>
                <div class="stat-info-mini">
                    <span class="stat-label-mini">Mobile</span>
                    <span class="stat-valor-mini"><?php echo number_format($stats['mobile'], 0, ',', '.'); ?></span>
                </div>
            </div>
            
            <div class="stat-card-mini">
                <ion-icon name="desktop-outline"></ion-icon>
                <div class="stat-info-mini">
                    <span class="stat-label-mini">Desktop</span>
                    <span class="stat-valor-mini"><?php echo number_format($stats['desktop'], 0, ',', '.'); ?></span>
                </div>
            </div>
        </div>

        <!-- Tabela de Visitas -->
        <section class="secao-detalhes">
            <h3 class="titulo-secao">
                <ion-icon name="list-outline"></ion-icon>
                Registro de Visitas (<?php echo number_format($totalVisitas, 0, ',', '.'); ?> total)
            </h3>
            
            <?php if (!empty($visitas)): ?>
                <div class="container-tabela">
                    <table class="tabela-visitas">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Dispositivo</th>
                                <th>Navegador</th>
                                <th>Sistema</th>
                                <th>Localização</th>
                                <th>Duração</th>
                                <th>Origem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visitas as $visita): ?>
                                <tr onclick="mostrarDetalhes('<?php echo htmlspecialchars($visita['uuid_visita'], ENT_QUOTES); ?>')">
                                    <td class="celula-data">
                                        <?php echo date('d/m/Y H:i', strtotime($visita['data_hora_inicio'])); ?>
                                    </td>
                                    <td>
                                        <div class="badge-dispositivo">
                                            <ion-icon name="<?php echo iconeDispositivo($visita['tipo_dispositivo']); ?>"></ion-icon>
                                            <?php echo ucfirst($visita['tipo_dispositivo'] ?? 'Desconhecido'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $nav = $visita['navegador'] ?? 'Desconhecido';
                                        $ver = $visita['versao_navegador'] ? ' ' . $visita['versao_navegador'] : '';
                                        echo htmlspecialchars($nav . $ver);
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $so = $visita['sistema_operacional'] ?? 'Desconhecido';
                                        echo htmlspecialchars($so);
                                        ?>
                                    </td>
                                    <td class="celula-localizacao">
                                        <?php 
                                        $loc = [];
                                        if ($visita['cidade']) $loc[] = $visita['cidade'];
                                        if ($visita['regiao']) $loc[] = $visita['regiao'];
                                        if ($visita['pais']) $loc[] = $visita['pais'];
                                        echo htmlspecialchars(implode(', ', $loc) ?: 'Não disponível');
                                        ?>
                                    </td>
                                    <td class="celula-duracao">
                                        <?php echo formatarDuracao($visita['duracao_sessao_segundos'] ?? 0); ?>
                                    </td>
                                    <td class="celula-utm">
                                        <?php 
                                        if ($visita['utm_source']) {
                                            echo '<span class="tag-utm">' . htmlspecialchars($visita['utm_source']) . '</span>';
                                        } elseif ($visita['origem_trafego']) {
                                            echo htmlspecialchars($visita['origem_trafego']);
                                        } else {
                                            echo '<span style="color: var(--texto-secundario);">Direto</span>';
                                        }
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
                    Nenhuma visita registrada no período selecionado.
                </p>
            <?php endif; ?>
        </section>
    </main>

    <!-- Modal de Detalhes -->
    <div class="modal-overlay" id="modal-detalhes">
        <div class="modal-conteudo">
            <div class="modal-cabecalho">
                <h3 class="modal-titulo">Detalhes da Visita</h3>
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
        // Dados das visitas para o modal (formato JSON)
        const visitasData = <?php echo json_encode($visitas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        // Função para mostrar detalhes da visita
        function mostrarDetalhes(uuid) {
            const visita = visitasData.find(v => v.uuid_visita === uuid);
            if (!visita) return;
            
            const modalCorpo = document.getElementById('modal-corpo');
            modalCorpo.innerHTML = `
                <div class="detalhe-item">
                    <span class="detalhe-label">UUID da Visita</span>
                    <span class="detalhe-valor">${visita.uuid_visita}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Data/Hora Início</span>
                    <span class="detalhe-valor">${formatarDataHora(visita.data_hora_inicio)}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Data/Hora Fim</span>
                    <span class="detalhe-valor">${visita.data_hora_fim ? formatarDataHora(visita.data_hora_fim) : 'Em andamento'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Duração da Sessão</span>
                    <span class="detalhe-valor">${formatarDuracao(visita.duracao_sessao_segundos || 0)}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Tipo de Dispositivo</span>
                    <span class="detalhe-valor">${visita.tipo_dispositivo || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Resolução de Tela</span>
                    <span class="detalhe-valor">${visita.resolucao_tela || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Navegador</span>
                    <span class="detalhe-valor">${visita.navegador || 'Desconhecido'} ${visita.versao_navegador || ''}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Sistema Operacional</span>
                    <span class="detalhe-valor">${visita.sistema_operacional || 'Desconhecido'} ${visita.versao_sistema || ''}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Idioma do Navegador</span>
                    <span class="detalhe-valor">${visita.idioma_navegador || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">País</span>
                    <span class="detalhe-valor">${visita.pais || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Região/Estado</span>
                    <span class="detalhe-valor">${visita.regiao || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Cidade</span>
                    <span class="detalhe-valor">${visita.cidade || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">URL de Referência</span>
                    <span class="detalhe-valor">${visita.url_referencia || 'Acesso direto'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">Origem do Tráfego</span>
                    <span class="detalhe-valor">${visita.origem_trafego || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">UTM Source</span>
                    <span class="detalhe-valor">${visita.utm_source || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">UTM Medium</span>
                    <span class="detalhe-valor">${visita.utm_medium || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">UTM Campaign</span>
                    <span class="detalhe-valor">${visita.utm_campaign || 'Não disponível'}</span>
                </div>
                <div class="detalhe-item">
                    <span class="detalhe-label">IP Hash (SHA-256)</span>
                    <span class="detalhe-valor" style="font-size: 11px;">${visita.ip_hash || 'Não disponível'}</span>
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
        
        // Função para formatar duração
        function formatarDuracao(segundos) {
            if (!segundos) return '0s';
            if (segundos < 60) return segundos + 's';
            if (segundos < 3600) {
                const mins = Math.floor(segundos / 60);
                const secs = segundos % 60;
                return mins + 'm ' + secs + 's';
            }
            const horas = Math.floor(segundos / 3600);
            const mins = Math.floor((segundos % 3600) / 60);
            return horas + 'h ' + mins + 'm';
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