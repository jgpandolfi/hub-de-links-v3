<?php
/**
 * Dashboard Principal - Painel de Analytics
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

// Parâmetros de filtro de data
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');

// Obter estatísticas gerais com filtro de período
try {
    // Total de visitas no período
    $stmtVisitas = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM tb_visitas 
        WHERE DATE(data_hora_inicio) BETWEEN :data_inicio AND :data_fim
    ");
    $stmtVisitas->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $totalVisitas = $stmtVisitas->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de eventos no período
    $stmtEventos = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM tb_eventos e
        INNER JOIN tb_visitas v ON e.uuid_visita = v.uuid_visita
        WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio AND :data_fim
    ");
    $stmtEventos->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $totalEventos = $stmtEventos->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de cliques em links no período
    $stmtCliques = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM tb_links_clicados lc
        INNER JOIN tb_visitas v ON lc.uuid_visita = v.uuid_visita
        WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio AND :data_fim
    ");
    $stmtCliques->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $totalCliques = $stmtCliques->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Links mais clicados no período (Top 5)
    $stmtTopLinks = $pdo->prepare("
        SELECT lc.nome_link, lc.url_destino, COUNT(*) as total_cliques
        FROM tb_links_clicados lc
        INNER JOIN tb_visitas v ON lc.uuid_visita = v.uuid_visita
        WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio AND :data_fim
        GROUP BY lc.nome_link, lc.url_destino
        ORDER BY total_cliques DESC
        LIMIT 5
    ");
    $stmtTopLinks->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $topLinks = $stmtTopLinks->fetchAll(PDO::FETCH_ASSOC);
    
    // Dispositivos mais usados no período
    $stmtDispositivos = $pdo->prepare("
        SELECT tipo_dispositivo, COUNT(*) as total
        FROM tb_visitas
        WHERE DATE(data_hora_inicio) BETWEEN :data_inicio AND :data_fim
        GROUP BY tipo_dispositivo
        ORDER BY total DESC
    ");
    $stmtDispositivos->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $dispositivos = $stmtDispositivos->fetchAll(PDO::FETCH_ASSOC);
    
    // Navegadores mais usados no período
    $stmtNavegadores = $pdo->prepare("
        SELECT navegador, COUNT(*) as total
        FROM tb_visitas
        WHERE DATE(data_hora_inicio) BETWEEN :data_inicio AND :data_fim
        AND navegador IS NOT NULL AND navegador != ''
        GROUP BY navegador
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmtNavegadores->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    $navegadores = $stmtNavegadores->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $erro = "Erro ao carregar estatísticas: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Painel de Analytics</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="estilo-painel.css?v=3">
    
    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
    <style>
        /* ========================================
           AJUSTES VISUAIS - CORES SECUNDÁRIAS EM CINZA
           ======================================== */
        
        /* Ícone do filtro de período - CINZA */
        .icone-filtro {
            color: #6b7280 !important; /* Cinza médio */
        }
        
        /* Ícones das seções de detalhes - CINZA */
        .titulo-secao ion-icon {
            color: #6b7280 !important; /* Cinza médio */
        }
        
        /* Background dos ícones dos cards principais - CINZA CLARO */
        .icone-card {
            background: rgba(107, 114, 128, 0.1) !important; /* Fundo cinza muito claro */
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
        
        /* Valores dos contadores nas seções laterais - CINZA */
        .valor-stat {
            color: #6b7280 !important; /* Cinza médio */
        }
        
        /* Valores do ranking (lado direito) - CINZA para harmonia */
        .valor-ranking {
            color: #6b7280 !important; /* Cinza médio ao invés de verde */
        }
        
        /* Labels dos ícones do input de data - CINZA */
        .label-data ion-icon {
            color: #6b7280 !important;
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
            <a href="dashboard.php" class="item-menu ativo">
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
                <h2 class="titulo-pagina">Dashboard</h2>
                <p class="subtitulo-pagina">Visão geral do seu hub de links</p>
            </div>
        </header>

        <!-- Filtro de Período -->
        <section class="secao-filtro-periodo">
            <div class="card-filtro">
                <div class="cabecalho-filtro">
                    <ion-icon name="calendar-outline" class="icone-filtro"></ion-icon>
                    <h3 class="titulo-filtro">Período de Análise</h3>
                </div>
                
                <form method="GET" action="dashboard.php" class="formulario-filtro">
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

        <!-- Cards de Estatísticas -->
        <section class="secao-cards-stats">
            <div class="card-stat">
                <ion-icon name="analytics-outline" class="icone-card"></ion-icon>
                <div class="info-card">
                    <span class="label-card">Visitas</span>
                    <span class="valor-card"><?php echo number_format($totalVisitas, 0, ',', '.'); ?></span>
                </div>
            </div>

            <div class="card-stat">
                <ion-icon name="radio-button-on-outline" class="icone-card"></ion-icon>
                <div class="info-card">
                    <span class="label-card">Eventos</span>
                    <span class="valor-card"><?php echo number_format($totalEventos, 0, ',', '.'); ?></span>
                </div>
            </div>

            <div class="card-stat">
                <ion-icon name="link-outline" class="icone-card"></ion-icon>
                <div class="info-card">
                    <span class="label-card">Cliques</span>
                    <span class="valor-card"><?php echo number_format($totalCliques, 0, ',', '.'); ?></span>
                </div>
            </div>
        </section>

        <!-- Seções de Detalhes -->
        <div class="grade-secoes">
            <!-- Links Mais Clicados -->
            <section class="secao-detalhes">
                <h3 class="titulo-secao">
                    <ion-icon name="trophy-outline"></ion-icon>
                    Links Mais Clicados
                </h3>
                <div class="lista-ranking">
                    <?php if (!empty($topLinks)): ?>
                        <?php foreach ($topLinks as $index => $link): ?>
                            <div class="item-ranking">
                                <span class="posicao-ranking">#<?php echo $index + 1; ?></span>
                                <div class="info-ranking">
                                    <span class="nome-ranking"><?php echo htmlspecialchars($link['nome_link']); ?></span>
                                    <span class="detalhe-ranking"><?php echo htmlspecialchars($link['url_destino']); ?></span>
                                </div>
                                <span class="valor-ranking"><?php echo number_format($link['total_cliques'], 0, ',', '.'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="mensagem-vazio">Nenhum clique registrado no período selecionado.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Dispositivos -->
            <section class="secao-detalhes">
                <h3 class="titulo-secao">
                    <ion-icon name="phone-portrait-outline"></ion-icon>
                    Dispositivos
                </h3>
                <div class="lista-stats">
                    <?php if (!empty($dispositivos)): ?>
                        <?php foreach ($dispositivos as $dispositivo): ?>
                            <div class="item-stat">
                                <span class="label-stat"><?php echo ucfirst(htmlspecialchars($dispositivo['tipo_dispositivo'])); ?></span>
                                <span class="valor-stat"><?php echo number_format($dispositivo['total'], 0, ',', '.'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="mensagem-vazio">Nenhum dado disponível no período selecionado.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Navegadores -->
            <section class="secao-detalhes">
                <h3 class="titulo-secao">
                    <ion-icon name="globe-outline"></ion-icon>
                    Navegadores
                </h3>
                <div class="lista-stats">
                    <?php if (!empty($navegadores)): ?>
                        <?php foreach ($navegadores as $navegador): ?>
                            <div class="item-stat">
                                <span class="label-stat"><?php echo htmlspecialchars($navegador['navegador']); ?></span>
                                <span class="valor-stat"><?php echo number_format($navegador['total'], 0, ',', '.'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="mensagem-vazio">Nenhum dado disponível no período selecionado.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <script src="script-painel.js?v=3"></script>
    <script>
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