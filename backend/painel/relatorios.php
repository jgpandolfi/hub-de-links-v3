<?php
/**
 * Página de Relatórios - Painel de Analytics
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

// Obter estatísticas para preview
try {
    $stmtStats = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM tb_visitas 
             WHERE DATE(data_hora_inicio) BETWEEN :data_inicio1 AND :data_fim1) as total_visitas,
            (SELECT COUNT(*) FROM tb_eventos e 
             INNER JOIN tb_visitas v ON e.uuid_visita = v.uuid_visita 
             WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio2 AND :data_fim2) as total_eventos,
            (SELECT COUNT(*) FROM tb_links_clicados lc 
             INNER JOIN tb_visitas v ON lc.uuid_visita = v.uuid_visita 
             WHERE DATE(v.data_hora_inicio) BETWEEN :data_inicio3 AND :data_fim3) as total_cliques
    ");
    
    $stmtStats->execute([
        'data_inicio1' => $dataInicio,
        'data_fim1' => $dataFim,
        'data_inicio2' => $dataInicio,
        'data_fim2' => $dataFim,
        'data_inicio3' => $dataInicio,
        'data_fim3' => $dataFim
    ]);
    
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = [
        'total_visitas' => 0,
        'total_eventos' => 0,
        'total_cliques' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Painel de Analytics</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="estilo-painel.css?v=3">
    
    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
    <style>
        /* ========================================
           AJUSTES VISUAIS - CORES SECUNDÁRIAS EM CINZA
           (Mesmos ajustes aplicados em todas as páginas)
           ======================================== */
        
        /* Ícone do filtro de período - CINZA */
        .icone-filtro {
            color: #6b7280 !important;
        }
        
        /* Ícones das seções de detalhes - CINZA */
        .titulo-secao ion-icon {
            color: #6b7280 !important;
        }
        
        /* Ícone do preview - CINZA */
        .titulo-preview ion-icon {
            color: #6b7280 !important;
        }
        
        /* Ícones dos cards de formato - CINZA */
        .icone-formato {
            background: rgba(107, 114, 128, 0.1) !important;
            color: #6b7280 !important;
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
        
        /* Estilos da página de relatórios */
        .preview-dados {
            background-color: var(--fundo-card);
            border: 2px solid var(--borda);
            border-radius: var(--raio-borda);
            padding: 28px;
            margin-bottom: 32px;
            box-shadow: var(--sombra);
        }
        
        .titulo-preview {
            font-size: 20px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 3px solid var(--borda);
            font-weight: 900;
            color: var(--texto-primario);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .titulo-preview ion-icon {
            font-size: 26px;
            color: var(--cor-primaria);
        }
        
        .grid-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        
        .item-preview {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 20px;
            background-color: var(--fundo-secundario);
            border: 2px solid var(--borda);
            border-radius: var(--raio-borda);
        }
        
        .label-preview {
            font-size: 13px;
            color: var(--texto-secundario);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .valor-preview {
            font-size: 32px;
            font-weight: 900;
            color: var(--texto-primario);
        }
        
        /* Grid de formatos */
        .grid-formatos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }
        
        .card-formato {
            background-color: var(--fundo-secundario);
            border: 2px solid var(--borda);
            border-radius: var(--raio-borda);
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            transition: var(--transicao);
        }
        
        .card-formato:hover {
            border-color: var(--cor-primaria);
            transform: translateY(-4px);
            box-shadow: var(--sombra-hover);
        }
        
        .icone-formato {
            font-size: 48px;
            color: var(--cor-primaria);
            width: 72px;
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(214, 36, 84, 0.1);
            border-radius: 14px;
            margin-bottom: 8px;
        }
        
        .nome-formato {
            font-size: 20px;
            font-weight: 900;
            color: var(--texto-primario);
            margin-bottom: 4px;
        }
        
        .extensao-formato {
            font-size: 14px;
            color: var(--texto-secundario);
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .descricao-formato {
            font-size: 14px;
            color: var(--texto-secundario);
            line-height: 1.6;
            flex: 1;
        }
        
        .botao-download-formato {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px;
            background-color: var(--cor-primaria);
            color: #ffffff;
            border: none;
            border-radius: var(--raio-borda);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transicao);
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .botao-download-formato:hover {
            background-color: var(--cor-primaria-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(214, 36, 84, 0.3);
        }
        
        .botao-download-formato ion-icon {
            font-size: 20px;
        }
        
        /* Responsividade */
        @media (max-width: 1200px) {
            .conteudo-principal-painel {
                padding: 24px;
            }
            
            .grid-formatos {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .conteudo-principal-painel {
                margin-left: 0;
                padding: 20px;
            }
            
            .grid-formatos {
                grid-template-columns: 1fr;
            }
            
            .grid-preview {
                grid-template-columns: 1fr;
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
            <a href="links-clicados.php" class="item-menu">
                <ion-icon name="link-outline" class="icone-menu"></ion-icon>
                <span>Links Clicados</span>
            </a>
            <a href="relatorios.php" class="item-menu ativo">
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
                <h2 class="titulo-pagina">Relatórios</h2>
                <p class="subtitulo-pagina">Exporte dados completos em múltiplos formatos</p>
            </div>
        </header>

        <!-- Filtro de Período -->
        <section class="secao-filtro-periodo">
            <div class="card-filtro">
                <div class="cabecalho-filtro">
                    <ion-icon name="calendar-outline" class="icone-filtro"></ion-icon>
                    <h3 class="titulo-filtro">Período de Análise</h3>
                </div>
                
                <form method="GET" action="relatorios.php" class="formulario-filtro">
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

        <!-- Preview dos Dados -->
        <div class="preview-dados">
            <h3 class="titulo-preview">
                <ion-icon name="eye-outline"></ion-icon>
                Preview dos Dados
            </h3>
            <div class="grid-preview">
                <div class="item-preview">
                    <span class="label-preview">Total de Visitas</span>
                    <span class="valor-preview"><?php echo number_format($stats['total_visitas'], 0, ',', '.'); ?></span>
                </div>
                <div class="item-preview">
                    <span class="label-preview">Total de Eventos</span>
                    <span class="valor-preview"><?php echo number_format($stats['total_eventos'], 0, ',', '.'); ?></span>
                </div>
                <div class="item-preview">
                    <span class="label-preview">Total de Cliques</span>
                    <span class="valor-preview"><?php echo number_format($stats['total_cliques'], 0, ',', '.'); ?></span>
                </div>
            </div>
        </div>

        <!-- Formatos de Exportação -->
        <section class="secao-detalhes">
            <h3 class="titulo-secao">
                <ion-icon name="download-outline"></ion-icon>
                Formatos Disponíveis
            </h3>
            
            <div class="grid-formatos">
                <!-- Excel -->
                <div class="card-formato">
                    <ion-icon name="document-outline" class="icone-formato"></ion-icon>
                    <div>
                        <h4 class="nome-formato">Microsoft Excel</h4>
                        <p class="extensao-formato">.XLSX</p>
                    </div>
                    <p class="descricao-formato">
                        Planilha completa com múltiplas abas (Visitas, Eventos, Links). Formatação profissional, filtros e tabelas.
                    </p>
                    <a href="gerar-relatorio.php?formato=xlsx&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>" 
                       class="botao-download-formato">
                        <ion-icon name="download-outline"></ion-icon>
                        Baixar XLSX
                    </a>
                </div>

                <!-- CSV -->
                <div class="card-formato">
                    <ion-icon name="document-text-outline" class="icone-formato"></ion-icon>
                    <div>
                        <h4 class="nome-formato">CSV</h4>
                        <p class="extensao-formato">.CSV</p>
                    </div>
                    <p class="descricao-formato">
                        Formato universal compatível com Excel, Google Sheets e bancos de dados. Ideal para importação e análise.
                    </p>
                    <a href="gerar-relatorio.php?formato=csv&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>" 
                       class="botao-download-formato">
                        <ion-icon name="download-outline"></ion-icon>
                        Baixar CSV
                    </a>
                </div>

                <!-- PDF -->
                <div class="card-formato">
                    <ion-icon name="newspaper-outline" class="icone-formato"></ion-icon>
                    <div>
                        <h4 class="nome-formato">Adobe PDF</h4>
                        <p class="extensao-formato">.PDF</p>
                    </div>
                    <p class="descricao-formato">
                        Relatório visual formatado com gráficos e estatísticas. Perfeito para apresentações e impressão.
                    </p>
                    <a href="gerar-relatorio.php?formato=pdf&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>" 
                       class="botao-download-formato">
                        <ion-icon name="download-outline"></ion-icon>
                        Baixar PDF
                    </a>
                </div>

                <!-- TXT -->
                <div class="card-formato">
                    <ion-icon name="reader-outline" class="icone-formato"></ion-icon>
                    <div>
                        <h4 class="nome-formato">Texto Simples</h4>
                        <p class="extensao-formato">.TXT</p>
                    </div>
                    <p class="descricao-formato">
                        Arquivo de texto puro com dados tabulados. Compatível com qualquer editor e sistema operacional.
                    </p>
                    <a href="gerar-relatorio.php?formato=txt&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>" 
                       class="botao-download-formato">
                        <ion-icon name="download-outline"></ion-icon>
                        Baixar TXT
                    </a>
                </div>

                <!-- SQL -->
                <div class="card-formato">
                    <ion-icon name="server-outline" class="icone-formato"></ion-icon>
                    <div>
                        <h4 class="nome-formato">SQL Dump</h4>
                        <p class="extensao-formato">.SQL</p>
                    </div>
                    <p class="descricao-formato">
                        Script SQL com INSERT statements. Ideal para backup, migração e restauração de dados em bancos MySQL.
                    </p>
                    <a href="gerar-relatorio.php?formato=sql&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>" 
                       class="botao-download-formato">
                        <ion-icon name="download-outline"></ion-icon>
                        Baixar SQL
                    </a>
                </div>
            </div>
        </section>
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