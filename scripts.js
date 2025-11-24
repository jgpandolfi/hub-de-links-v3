'use strict';

/**
 * Hub de Links do Jota
 * https://hub.agenciam2a.com.br/
 * 
 * Desenvolvido por Jota / José Guilherme Pandolfi - Agência m2a
 * www.agenciam2a.com.br
 */

// ==================== UTILITÁRIOS ====================

const Utilitarios = (() => {
    const DEBUG = false;

    function registro(...argumentos) {
        if (DEBUG) console.log(...argumentos);
    }

    function erro(...argumentos) {
        if (DEBUG) console.error(...argumentos);
    }

    function obterAnoAtual() {
        return new Date().getFullYear();
    }

    function detectarModoPreferido() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
            return 'claro';
        }
        return 'escuro';
    }

    return {
        registro,
        erro,
        obterAnoAtual,
        detectarModoPreferido
    };
})();

// ==================== GERENCIADOR DE MODO (CLARO/ESCURO) ====================

const GerenciadorModo = (() => {
    const CHAVE_ARMAZENAMENTO = 'hubJota_modo';
    const CLASSE_MODO_CLARO = 'modo-claro';
    const CLASSE_MODO_ESCURO = 'modo-escuro';
    const botaoToggle = document.getElementById('toggleModo');

    function obterModoSalvo() {
        return localStorage.getItem(CHAVE_ARMAZENAMENTO);
    }

    function salvarModo(modo) {
        localStorage.setItem(CHAVE_ARMAZENAMENTO, modo);
    }

    function aplicarModo(modo) {
        const corpo = document.body;
        
        if (modo === 'claro') {
            corpo.classList.remove(CLASSE_MODO_ESCURO);
            corpo.classList.add(CLASSE_MODO_CLARO);
            atualizarIconoBotao(true);
        } else {
            corpo.classList.remove(CLASSE_MODO_CLARO);
            corpo.classList.add(CLASSE_MODO_ESCURO);
            atualizarIconoBotao(false);
        }
        
        salvarModo(modo);
    }

    function atualizarIconoBotao(ehModoClaro) {
        if (!botaoToggle) return;
        
        const icone = botaoToggle.querySelector('i');
        if (icone) {
            if (ehModoClaro) {
                icone.className = 'icon ion-ios-sunny';
            } else {
                icone.className = 'icon ion-ios-moon';
            }
        }
    }

    function alternarModo() {
        const modoAtual = document.body.classList.contains(CLASSE_MODO_CLARO) ? 'claro' : 'escuro';
        const novoModo = modoAtual === 'claro' ? 'escuro' : 'claro';
        aplicarModo(novoModo);
    }

    function inicializar() {
        let modoPreferido = obterModoSalvo();
        
        if (!modoPreferido) {
            modoPreferido = Utilitarios.detectarModoPreferido();
        }
        
        aplicarModo(modoPreferido);
        
        if (botaoToggle) {
            botaoToggle.addEventListener('click', alternarModo);
        }

        // Detectar mudança de preferência do sistema
        if (window.matchMedia) {
            const consultaModoEscuro = window.matchMedia('(prefers-color-scheme: dark)');
            consultaModoEscuro.addEventListener('change', (evento) => {
                if (!obterModoSalvo()) {
                    const novoModo = evento.matches ? 'escuro' : 'claro';
                    aplicarModo(novoModo);
                }
            });
        }
    }

    return {
        inicializar,
        alternarModo
    };
})();

// ==================== CURSOR PERSONALIZADO ====================

const CursorPersonalizado = (() => {
    const SELETORES_CLICAVEIS = [
        'a',
        'button',
        '.botao-link',
        '.botao-toggle-modo',
        '.img-clicavel-modal',
        '[role="button"]'
    ].join(', ');

    const SELETORES_TEXTO = [
        'input',
        'textarea'
    ].join(', ');

    function verificarDispositivoTouch() {
        return (
            'ontouchstart' in window ||
            navigator.maxTouchPoints > 0 ||
            navigator.msMaxTouchPoints > 0
        );
    }

    function verificarSeEstaEmElementoClicavel(elemento) {
        // Verificar se o elemento atual é clicável
        if (elemento.matches(SELETORES_CLICAVEIS)) {
            return true;
        }
        
        // Verificar se algum pai é clicável (isso resolve o problema dos filhos)
        const elementoClicavel = elemento.closest(SELETORES_CLICAVEIS);
        return elementoClicavel !== null;
    }

    function verificarSeEstaEmElementoTexto(elemento) {
        // Verificar se o elemento atual é de texto
        if (elemento.matches(SELETORES_TEXTO)) {
            return true;
        }
        
        // Verificar se algum pai é de texto
        const elementoTexto = elemento.closest(SELETORES_TEXTO);
        return elementoTexto !== null;
    }

    function inicializar() {
        if (verificarDispositivoTouch()) {
            document.body.classList.add('dispositivo-touch');
            return;
        }

        const cursorPrincipal = document.querySelector('.cursor-principal');
        if (!cursorPrincipal) {
            Utilitarios.registro('Cursor principal não encontrado no DOM');
            return;
        }

        document.body.classList.add('cursor-ativo');

        document.addEventListener('mousemove', (evento) => {
            cursorPrincipal.style.left = `${evento.clientX}px`;
            cursorPrincipal.style.top = `${evento.clientY}px`;
        });

        document.body.addEventListener('mouseover', (evento) => {
            const elemento = evento.target;
            
            // Verificar se está sobre um elemento clicável (incluindo pais)
            if (verificarSeEstaEmElementoClicavel(elemento)) {
                cursorPrincipal.classList.add('sobre-clicavel', 'hover');
                document.body.classList.remove('cursor-ativo');
            } 
            // Verificar se está sobre um elemento de texto (incluindo pais)
            else if (verificarSeEstaEmElementoTexto(elemento)) {
                cursorPrincipal.classList.add('sobre-clicavel', 'texto');
                document.body.classList.remove('cursor-ativo');
            }
        }, true);

        document.body.addEventListener('mouseout', () => {
            cursorPrincipal.classList.remove('sobre-clicavel', 'hover', 'texto');
            document.body.classList.add('cursor-ativo');
        }, true);

        Utilitarios.registro('Cursor personalizado inicializado');
    }

    return {
        inicializar
    };
})();

// ==================== LOADER PRINCIPAL ====================

const GerenciadorLoader = (() => {
    const loader = document.getElementById('loader-principal');
    const barraProgresso = document.getElementById('loader-barra-progresso');
    
    let progresso = 0;
    let intervaloProgresso = null;
    let loaderFinalizado = false;

    /**
     * Atualiza a barra de progresso
     */
    function atualizarProgresso(valor) {
        progresso = Math.min(valor, 100);
        if (barraProgresso) {
            barraProgresso.style.width = `${progresso}%`;
        }
    }

    /**
     * Simula progresso gradual
     */
    function simularProgresso() {
        intervaloProgresso = setInterval(() => {
            if (progresso < 90) {
                progresso += Math.random() * 15;
                atualizarProgresso(progresso);
            } else {
                clearInterval(intervaloProgresso);
            }
        }, 300);
    }

    /**
     * Finaliza o loader e remove da tela
     */
    function finalizar() {
        if (loaderFinalizado || !loader) return;
        
        loaderFinalizado = true;
        
        // Completa a barra
        atualizarProgresso(100);
        
        // Aguarda animação da barra e remove
        setTimeout(() => {
            loader.classList.add('oculto');
            
            // Remove do DOM após transição
            setTimeout(() => {
                loader.remove();
            }, 600);
        }, 400);
        
        Utilitarios.registro('Loader finalizado');
    }

    /**
     * Inicializa o loader
     */
    function inicializar() {
        if (!loader) {
            Utilitarios.erro('Loader não encontrado no DOM');
            return;
        }

        // Inicia simulação de progresso
        simularProgresso();

        // Detecta quando tudo carregou
        if (document.readyState === 'complete') {
            finalizar();
        } else {
            window.addEventListener('load', finalizar);
        }

        Utilitarios.registro('Loader inicializado');
    }

    return {
        inicializar,
        finalizar,
        atualizarProgresso
    };
})();

// ==================== ANIMAÇÕES E TRANSIÇÕES ====================

const Animacoes = (() => {
    function aplicarAnimacaoEntrada() {
        const listaLinks = document.querySelector('.lista-links');
        if (!listaLinks) return;

        const links = listaLinks.querySelectorAll('.botao-link');
        links.forEach((link, indice) => {
            link.style.animation = `none`;
            setTimeout(() => {
                link.style.animation = `deslizamentoEntrada 0.6s ease-out ${indice * 0.1}s forwards`;
            }, 10);
        });
    }

    function inicializar() {
        // Adicionar animação ao CSS dinamicamente
        const estilo = document.createElement('style');
        estilo.textContent = `
            @keyframes deslizamentoEntrada {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(estilo);

        // Aplicar quando o documento carregar
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', aplicarAnimacaoEntrada);
        } else {
            aplicarAnimacaoEntrada();
        }
    }

    return {
        inicializar
    };
})();

// ==================== PREENCHIMENTO DE DATA ====================

const Datas = (() => {
    function preencherAnoAtual() {
        const elementoAno = document.getElementById('anoAtual');
        if (elementoAno) {
            elementoAno.textContent = Utilitarios.obterAnoAtual();
        }
    }

    function inicializar() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', preencherAnoAtual);
        } else {
            preencherAnoAtual();
        }
    }

    return {
        inicializar
    };
})();

// ==================== ACESSIBILIDADE ====================

const Acessibilidade = (() => {
    function configurarSkipLink() {
        const linkPular = document.createElement('a');
        linkPular.href = '#lista-links-principal';
        linkPular.textContent = 'Pular para conteúdo principal';
        linkPular.style.cssText = `
            position: absolute;
            top: -60px;
            left: 0;
            background: #000;
            color: #fff;
            padding: 8px;
            text-decoration: none;
            z-index: 100;
        `;
        linkPular.addEventListener('focus', () => {
            linkPular.style.top = '0';
        });
        linkPular.addEventListener('blur', () => {
            linkPular.style.top = '-40px';
        });
        document.body.insertBefore(linkPular, document.body.firstChild);
    }

    function melhorarContrasteLeitura() {
        const listaLinks = document.querySelector('.lista-links');
        if (listaLinks) {
            listaLinks.setAttribute('role', 'navigation');
            listaLinks.setAttribute('aria-label', 'Lista de links principais');
        }
    }

    function inicializar() {
        configurarSkipLink();
        melhorarContrasteLeitura();
    }

    return {
        inicializar
    };
})();

// ==================== SUPORTE A COMPARTILHAMENTO (Web Share API) ====================

const Compartilhamento = (() => {
    function adicionarBotoesCompartilhamento() {
        if (!navigator.share) return;

        const botoes = document.querySelectorAll('.botao-link');
        botoes.forEach((botao) => {
            botao.addEventListener('auxclick', (evento) => {
                if (evento.button === 1) {
                    evento.preventDefault();
                    compartilharLink(botao);
                }
            });
        });
    }

    function compartilharLink(botao) {
        const titulo = botao.querySelector('.titulo-link')?.textContent || 'Hub do Jota';
        const descricao = botao.querySelector('.descricao-link')?.textContent || '';
        const url = botao.href;

        navigator.share({
            title: titulo,
            text: descricao,
            url: url
        }).catch((erro) => {
            Utilitarios.erro('Erro ao compartilhar:', erro);
        });
    }

    function inicializar() {
        adicionarBotoesCompartilhamento();
    }

    return {
        inicializar
    };
})();

// ================================================
// BARRA DE CARREGAMENTO
// ================================================

const BarraCarregamento = {
    container: null,
    barra: null,
    
    inicializar() {
        this.container = document.getElementById('barra-carregamento-container');
        this.barra = document.getElementById('barra-carregamento');
        
        if (!this.container || !this.barra) {
            console.warn('Elementos da barra de carregamento não encontrados');
        }
    },
    
    mostrar() {
        if (this.container && this.barra) {
            this.container.classList.add('visivel');
            this.barra.style.width = '5%';
        }
    },
    
    atualizar(porcentagem) {
        if (this.barra) {
            const progresso = Math.max(5, Math.min(100, porcentagem));
            this.barra.style.width = progresso + '%';
        }
    },
    
    esconder() {
        if (this.container && this.barra) {
            this.barra.style.width = '100%';
            
            setTimeout(() => {
                this.container.classList.remove('visivel');
                setTimeout(() => {
                    this.barra.style.width = '0%';
                }, 300);
            }, 200);
        }
    },
    
    resetar() {
        if (this.container && this.barra) {
            this.container.classList.remove('visivel');
            this.barra.style.width = '0%';
        }
    }
};

// ==================== PORTFÓLIO DE IMAGENS ====================

const PortfolioImagens = (() => {
    const CHAVE_PORTFOLIO = 'hubJota_portfolio_visivel';
    
    // Elementos do DOM
    const botaoPortfolio = document.getElementById('botao-portfolio-imagens');
    const containerPortfolio = document.getElementById('container-portfolio');
    const containerLista = document.querySelector('.container-conteudo');
    const listaLinks = document.querySelector('.lista-links');
    const barraBusca = document.getElementById('barra-busca-portfolio');
    const btnVoltar = document.getElementById('btn-voltar-portfolio');
    const imgsPortfolio = document.querySelectorAll('.img-portfolio');

    function mostrarPortfolio() {
        if (!containerPortfolio) {
            console.error('Container do portfólio não encontrado');
            return;
        }
        
        // Inicializa e mostra a barra de carregamento
        BarraCarregamento.inicializar();
        BarraCarregamento.mostrar();
        
        // Oculta a lista de links e mostra o portfólio
        listaLinks.style.display = 'none';
        containerPortfolio.classList.add('ativo');
        localStorage.setItem(CHAVE_PORTFOLIO, 'true');
        
        // Obtém todos os itens do portfólio
        const itens = containerPortfolio.querySelectorAll('.item-portfolio');
        
        // Variáveis de controle
        let imagensCarregadas = 0;
        const totalImagens = itens.length;
        
        // Função chamada quando cada imagem carrega
        function imagemCarregada() {
            imagensCarregadas++;
            const progresso = (imagensCarregadas / totalImagens) * 100;
            BarraCarregamento.atualizar(progresso);
            
            if (imagensCarregadas === totalImagens) {
                BarraCarregamento.esconder();
            }
        }
        
        // Processa cada item do portfólio
        itens.forEach((item, indice) => {
            // Animação de entrada
            item.style.animation = 'none';
            setTimeout(() => {
                item.style.animation = `fadeIn 0.3s ease ${indice * 0.05}s forwards`;
            }, 10);
            
            // Busca a imagem
            const img = item.querySelector('.img-portfolio');
            
            if (img) {
                // Se já está carregada
                if (img.complete && img.naturalHeight !== 0) {
                    imagemCarregada();
                } else {
                    // Adiciona listeners
                    img.addEventListener('load', imagemCarregada, { once: true });
                    img.addEventListener('error', () => {
                        console.warn('Erro ao carregar imagem:', img.src);
                        imagemCarregada();
                    }, { once: true });
                }
            } else {
                imagemCarregada();
            }
        });
        
        // Se não houver itens
        if (totalImagens === 0) {
            BarraCarregamento.esconder();
        }
        
        Utilitarios.registro('Portfólio de imagens exibido');
    }

    function ocultarPortfolio() {
        if (!containerPortfolio) return;
        
        listaLinks.style.display = 'flex';
        containerPortfolio.classList.remove('ativo');
        localStorage.setItem(CHAVE_PORTFOLIO, 'false');
        barraBusca.value = '';
        filtrarImagens('');
        
        Utilitarios.registro('Portfólio de imagens oculto');
    }

    function filtrarImagens(termo) {
        const termoLower = termo.toLowerCase().trim();
        const todosOsItens = document.querySelectorAll('.item-portfolio');
        
        Utilitarios.registro(`Filtrando por: "${termo}"`);
        Utilitarios.registro(`Total de itens encontrados: ${todosOsItens.length}`);
        
        let countVisíveis = 0;
        
        todosOsItens.forEach((item, indice) => {
            const img = item.querySelector('.img-portfolio');
            
            if (!img) {
                Utilitarios.erro(`Imagem não encontrada no item ${indice}`);
                return;
            }
            
            const alt = img.getAttribute('alt') || '';
            const title = img.getAttribute('title') || '';
            const altLower = alt.toLowerCase();
            const titleLower = title.toLowerCase();
            
            const match = termoLower === '' || 
                        altLower.includes(termoLower) || 
                        titleLower.includes(termoLower);
            
            item.style.display = match ? 'block' : 'none';
            
            if (match && termoLower !== '') {
                countVisíveis++;
                Utilitarios.registro(`✓ Match encontrado: ${alt}`);
            }
        });
        
        Utilitarios.registro(`Resultado: ${countVisíveis} imagens visíveis`);
    }

    function inicializar() {
        if (!botaoPortfolio || !containerPortfolio) {
            Utilitarios.registro('Elementos do portfólio não encontrados');
            return;
        }

        // Event listeners
        botaoPortfolio.addEventListener('click', mostrarPortfolio);
        btnVoltar.addEventListener('click', ocultarPortfolio);
        barraBusca.addEventListener('keyup', (e) => {
            filtrarImagens(e.target.value);
        });

        // Restaurar estado anterior
        const estadoAnterior = localStorage.getItem(CHAVE_PORTFOLIO);
        if (estadoAnterior === 'true') {
            mostrarPortfolio();
        }

        Utilitarios.registro('Portfólio de imagens inicializado');
    }

    return {
        inicializar,
        mostrarPortfolio,
        ocultarPortfolio,
        filtrarImagens
    };
})();

// ==================== MODAL DE VISUALIZAÇÃO EXPANDIDA ====================

const ModalPortfolio = (() => {
    const CHAVE_MODAL = 'hubJota_modal_visivel';
    
    // Elementos do DOM
    const modal = document.getElementById('modal-portfolio');
    const btnFechar = document.getElementById('btn-fechar-modal-portfolio');
    const btnAnterior = document.getElementById('btn-imagem-anterior-modal');
    const btnProxima = document.getElementById('btn-imagem-proxima-modal');
    const imagemModal = document.getElementById('imagem-modal');
    const sourceModal = document.getElementById('source-modal');
    const legendaModal = document.getElementById('legenda-modal');
    const contadorModal = document.getElementById('contador-modal');
    const fundoModal = document.querySelector('.modal-fundo');
    const imagensClikaveis = document.querySelectorAll('.img-clicavel-modal');
    
    let imagemAtualIndex = 0;
    let toqueInicial = 0;
    let tocufeFinal = 0;
    
    /**
     * Abre o modal com a imagem especificada
     * @param {number} index - Índice da imagem no array
     */
    function abrirModal(index) {
        imagemAtualIndex = index;
        const img = imagensClikaveis[index];
        
        // Definir fontes da imagem
        sourceModal.srcset = img.src.replace('.jpg', '.webp');
        imagemModal.src = img.src;
        imagemModal.alt = img.alt;
        
        // Definir legenda
        legendaModal.textContent = img.alt;
        
        // Atualizar contador
        atualizarContador();
        
        // Mostrar modal
        modal.classList.add('ativo');
        document.body.classList.add('modal-aberto');
        
        // Atualizar botões
        atualizarBotoes();
        
        // Adicionar event listeners
        document.addEventListener('keydown', tratarTeclas);
        modal.addEventListener('touchstart', tratarToqueinicio);
        modal.addEventListener('touchend', tratarToqueOfim);
        
        Utilitarios.registro(`Modal aberto - Imagem ${imagemAtualIndex + 1}`);
    }
    
    /**
     * Fecha o modal
     */
    function fecharModal() {
        modal.classList.remove('ativo');
        document.body.classList.remove('modal-aberto');
        
        // Remover event listeners
        document.removeEventListener('keydown', tratarTeclas);
        modal.removeEventListener('touchstart', tratarToqueinicio);
        modal.removeEventListener('touchend', tratarToqueOfim);
        
        Utilitarios.registro('Modal fechado');
    }
    
    /**
     * Navega para imagem anterior
     */
    function irParaAnterior() {
        if (imagemAtualIndex > 0) {
            abrirModal(imagemAtualIndex - 1);
        }
    }
    
    /**
     * Navega para próxima imagem
     */
    function irParaProxima() {
        if (imagemAtualIndex < imagensClikaveis.length - 1) {
            abrirModal(imagemAtualIndex + 1);
        }
    }
    
    /**
     * Atualiza estado dos botões baseado na posição atual
     */
    function atualizarBotoes() {
        btnAnterior.disabled = imagemAtualIndex === 0;
        btnProxima.disabled = imagemAtualIndex === imagensClikaveis.length - 1;
    }
    
    /**
     * Atualiza o contador de imagens
     */
    function atualizarContador() {
        contadorModal.textContent = `${imagemAtualIndex + 1} / ${imagensClikaveis.length}`;
    }
    
    /**
     * Trata navegação via teclado
     * @param {KeyboardEvent} evento
     */
    function tratarTeclas(evento) {
        if (!modal.classList.contains('ativo')) return;
        
        switch (evento.key) {
            case 'Escape':
                fecharModal();
                break;
            case 'ArrowLeft':
                if (imagemAtualIndex > 0) irParaAnterior();
                break;
            case 'ArrowRight':
                if (imagemAtualIndex < imagensClikaveis.length - 1) irParaProxima();
                break;
        }
    }
    
    /**
     * Trata início do toque
     * @param {TouchEvent} evento
     */
    function tratarToqueinicio(evento) {
        toqueInicial = evento.touches[0].clientX;
    }
    
    /**
     * Trata fim do toque e detecta direção
     * @param {TouchEvent} evento
     */
    function tratarToqueOfim(evento) {
        tocufeFinal = evento.changedTouches[0].clientX;
        const diferenca = tocufeFinal - toqueInicial;
        
        // Limiar mínimo de 50px para considerar um swipe
        if (Math.abs(diferenca) > 50) {
            if (diferenca < 0 && imagemAtualIndex < imagensClikaveis.length - 1) {
                // Swipe para esquerda = próxima imagem
                irParaProxima();
            } else if (diferenca > 0 && imagemAtualIndex > 0) {
                // Swipe para direita = imagem anterior
                irParaAnterior();
            }
        }
    }
    
    /**
     * Inicializa o módulo
     */
    function inicializar() {
        if (!modal || imagensClikaveis.length === 0) {
            Utilitarios.registro('Modal de portfólio não encontrado ou sem imagens');
            return;
        }
        
        // Event listeners dos botões
        btnFechar.addEventListener('click', fecharModal);
        btnAnterior.addEventListener('click', irParaAnterior);
        btnProxima.addEventListener('click', irParaProxima);
        
        // Fechar ao clicar no fundo
        fundoModal.addEventListener('click', fecharModal);
        
        // Evitar fechar ao clicar no conteúdo
        document.querySelector('.modal-conteudo').addEventListener('click', (e) => {
            e.stopPropagation();
        });
        
        // Adicionar listeners para cada imagem
        imagensClikaveis.forEach((img, index) => {
            img.addEventListener('click', () => abrirModal(index));
            img.style.cursor = 'pointer';
        });
        
        Utilitarios.registro('Modal de portfólio inicializado');
    }
    
    return {
        inicializar,
        abrirModal,
        fecharModal,
        irParaAnterior,
        irParaProxima
    };
})();

// ==================== ARTIGOS PUBLICADOS ====================

const Artigos = (() => {
    const CHAVE_ARTIGOS = 'hubJota_artigos_visivel';
    
    // Elementos do DOM
    const botaoArtigos = document.getElementById('botao-artigos');
    const containerArtigos = document.getElementById('container-artigos');
    const listaLinks = document.querySelector('.lista-links');
    const btnVoltarArtigos = document.getElementById('btn-voltar-de-artigos-para-links');
    
    /**
     * Abre a seção de artigos
     */
    function mostrarArtigos() {
        if (!containerArtigos) return;
        
        listaLinks.style.display = 'none';
        containerArtigos.classList.add('ativo');
        localStorage.setItem(CHAVE_ARTIGOS, 'true');
        
        // Scroll suave para o topo
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
        
        Utilitarios.registro('Seção de artigos aberta');
    }
    
    /**
     * Fecha a seção de artigos e volta para os links
     */
    function ocultarArtigos() {
        if (!containerArtigos) return;
        
        listaLinks.style.display = 'flex';
        containerArtigos.classList.remove('ativo');
        localStorage.setItem(CHAVE_ARTIGOS, 'false');
        
        // Scroll suave para o topo
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
        
        Utilitarios.registro('Seção de artigos fechada');
    }
    
    /**
     * Inicializa o módulo
     */
    function inicializar() {
        if (!botaoArtigos || !containerArtigos) {
            Utilitarios.registro('Elementos de artigos não encontrados');
            return;
        }
        
        // Event listeners
        botaoArtigos.addEventListener('click', (e) => {
            e.preventDefault();
            mostrarArtigos();
        });
        
        btnVoltarArtigos.addEventListener('click', ocultarArtigos);
        
        // Restaurar estado anterior
        const estadoAnterior = localStorage.getItem(CHAVE_ARTIGOS);
        if (estadoAnterior === 'true') {
            mostrarArtigos();
        }
        
        Utilitarios.registro('Módulo de artigos inicializado');
    }
    
    return {
        inicializar,
        mostrarArtigos,
        ocultarArtigos
    };
})();

// ==================== INFORMAÇÕES DO VISITANTE ====================

const InformacoesVisitante = (() => {
    const eleIP = document.getElementById('ip');
    const eleISP = document.getElementById('ISP');
    const eleLoc = document.getElementById('loc');
    const eleSistema = document.getElementById('sistema');
    const eleBrowser = document.getElementById('browser');
    const eleUTM = document.getElementById('UTM');

    /**
     * Obtém todas as informações do visitante via ipwhois.io
     * API gratuita com HTTPS que suporta CORS nativamente (10k req/mês)
     */
    async function obterInformacoesCompletas() {
        try {
            // ipwhois.io - HTTPS gratuito, sem necessidade de token
            const response = await fetch('https://ipwhois.app/json/');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            // Verifica se a API retornou sucesso
            if (data.success !== true) {
                throw new Error('Erro ao obter dados da API');
            }
            
            // Atualiza IP
            eleIP.textContent = data.ip || 'Indisponível';
            
            // Atualiza ISP (provedor de internet)
            eleISP.textContent = data.isp || data.org || 'Indisponível';
            
            // Atualiza Localização (cidade, estado, país)
            if (data.city && data.region && data.country) {
                eleLoc.textContent = `${data.city}, ${data.region}, ${data.country}`;
            } else if (data.city && data.country) {
                eleLoc.textContent = `${data.city}, ${data.country}`;
            } else if (data.country) {
                eleLoc.textContent = data.country;
            } else {
                eleLoc.textContent = 'Indisponível';
            }
            
            Utilitarios.registro('Informações do visitante obtidas via ipwhois.io:', data);
            return true;
            
        } catch (erro) {
            console.error('Erro ao obter informações via ipwhois.io:', erro);
            
            // Define valores padrão em caso de erro
            eleIP.textContent = 'Indisponível';
            eleISP.textContent = 'Indisponível';
            eleLoc.textContent = 'Indisponível';
            
            return false;
        }
    }

    /**
     * Detecta o sistema operacional do visitante
     */
    function obterSistemaOperacional() {
        const userAgent = navigator.userAgent;
        let so = 'Desconhecido';

        if (userAgent.indexOf('Windows NT 10.0') > -1) {
            so = 'Windows 10/11';
        } else if (userAgent.indexOf('Windows NT') > -1) {
            so = 'Windows';
        } else if (userAgent.indexOf('Mac OS X') > -1) {
            so = 'macOS';
        } else if (userAgent.indexOf('Linux') > -1) {
            so = 'Linux';
        } else if (userAgent.indexOf('Android') > -1) {
            so = 'Android';
        } else if (userAgent.indexOf('iPhone') > -1 || userAgent.indexOf('iPad') > -1) {
            so = 'iOS';
        }

        eleSistema.textContent = so;
    }

    /**
     * Detecta o navegador web do visitante
     */
    function obterNavegador() {
        const userAgent = navigator.userAgent;
        let browser = 'Desconhecido';

        if (userAgent.indexOf('Firefox') > -1) {
            browser = 'Firefox';
        } else if (userAgent.indexOf('Edg') > -1) {
            browser = 'Edge';
        } else if (userAgent.indexOf('Chrome') > -1) {
            browser = 'Chrome';
        } else if (userAgent.indexOf('Safari') > -1) {
            browser = 'Safari';
        } else if (userAgent.indexOf('Opera') > -1 || userAgent.indexOf('OPR') > -1) {
            browser = 'Opera';
        }

        eleBrowser.textContent = browser;
    }

    /**
     * Obtém a origem do tráfego considerando UTM Source e Referrer
     */
    function obterOrigemTrafego() {
        try {
            // Tenta obter UTM Source primeiro
            const urlParams = new URLSearchParams(window.location.search);
            const utmSource = urlParams.get('utm_source');

            if (utmSource) {
                eleUTM.textContent = utmSource.charAt(0).toUpperCase() + utmSource.slice(1);
                return;
            }

            // Se não tem UTM, verifica o Referrer
            const referrer = document.referrer;

            if (!referrer) {
                eleUTM.textContent = 'Acesso Direto';
                return;
            }

            try {
                const urlReferrer = new URL(referrer);
                const dominioReferrer = urlReferrer.hostname;
                const dominioAtual = window.location.hostname;

                if (dominioReferrer === dominioAtual) {
                    eleUTM.textContent = 'Navegação Interna';
                    return;
                }

                // Verifica redes sociais
                const redesSociais = {
                    'facebook.com': 'Facebook',
                    'instagram.com': 'Instagram',
                    'twitter.com': 'Twitter',
                    'x.com': 'X (Twitter)',
                    'linkedin.com': 'LinkedIn',
                    'youtube.com': 'YouTube',
                    'tiktok.com': 'TikTok',
                    'pinterest.com': 'Pinterest',
                    'reddit.com': 'Reddit',
                    'whatsapp.com': 'WhatsApp',
                    't.co': 'Twitter'
                };

                for (const [dominio, nome] of Object.entries(redesSociais)) {
                    if (dominioReferrer.includes(dominio)) {
                        eleUTM.textContent = nome;
                        return;
                    }
                }

                // Verifica buscadores
                const buscadores = {
                    'google': 'Google',
                    'bing': 'Bing',
                    'yahoo': 'Yahoo',
                    'duckduckgo': 'DuckDuckGo',
                    'baidu': 'Baidu',
                    'yandex': 'Yandex'
                };

                for (const [busca, nome] of Object.entries(buscadores)) {
                    if (dominioReferrer.includes(busca)) {
                        eleUTM.textContent = `${nome} (Busca)`;
                        return;
                    }
                }

                const dominioSimplificado = dominioReferrer.replace('www.', '');
                eleUTM.textContent = `Referência: ${dominioSimplificado}`;

            } catch (erroUrl) {
                console.error('Erro ao processar Referrer:', erroUrl);
                eleUTM.textContent = 'Referência Externa';
            }

        } catch (erro) {
            console.error('Erro ao obter origem do tráfego:', erro);
            eleUTM.textContent = 'Acesso Direto';
        }
    }

    /**
     * Inicializa o módulo de informações do visitante
     */
    async function inicializar() {
        const temConsentimento = GerenciadorLGPD.verificarConsentimentoExistente();
        
        if (!temConsentimento) {
            Utilitarios.registro('Consentimento não fornecido - InformaçõesVisitante desativado');
            return;
        }
        
        if (!eleIP || !eleISP || !eleLoc || !eleSistema || !eleBrowser || !eleUTM) {
            Utilitarios.registro('Elementos de informações do visitante não encontrados no DOM');
            return;
        }

        obterSistemaOperacional();
        obterNavegador();
        obterOrigemTrafego();
        await obterInformacoesCompletas();

        Utilitarios.registro('Módulo de informações do visitante inicializado com sucesso');
    }

    return {
        inicializar,
        obterOrigemTrafego,
        obterNavegador,
        obterSistemaOperacional,
        obterInformacoesCompletas
    };
})();

// ==================== SISTEMA DE ANALYTICS E TRACKING ====================

const SistemaAnalytics = (() => {
    const CONFIG = {
        API_BASE_URL: './backend/api',
        UUID_STORAGE_KEY: 'hubJota_uuid_visita',
        CONSENTIMENTO_KEY: 'hubJota_consentimento',
        VISITA_REGISTRADA_KEY: 'hubJota_visita_registrada'
    };

    let uuidVisita = null;
    let tempoInicioSessao = null;
    let consentimentoAceito = false;
    let visitaJaRegistrada = false; // ✅ NOVA FLAG

    /**
     * Gera UUID v4
     */
    function gerarUuidV4() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Obtém ou cria UUID da visita
     */
    function obterUuidVisita() {
        let uuid = sessionStorage.getItem(CONFIG.UUID_STORAGE_KEY);
        
        if (!uuid) {
            uuid = gerarUuidV4();
            sessionStorage.setItem(CONFIG.UUID_STORAGE_KEY, uuid);
            Utilitarios.registro('Analytics: Novo UUID gerado - ' + uuid);
        }
        
        return uuid;
    }

    /**
     * Verifica se usuário aceitou consentimento
     */
    function verificarConsentimento() {
        const consentimento = localStorage.getItem(CONFIG.CONSENTIMENTO_KEY);
        return consentimento === 'aceito';
    }

    /**
     * Detecta informações do navegador
     */
    function detectarNavegador() {
        const ua = navigator.userAgent;
        let navegador = 'Desconhecido';
        let versao = '';

        if (ua.indexOf('Firefox') > -1) {
            navegador = 'Firefox';
            versao = ua.match(/Firefox\/(\d+\.\d+)/)?.[1] || '';
        } else if (ua.indexOf('Chrome') > -1 && ua.indexOf('Edg') === -1) {
            navegador = 'Chrome';
            versao = ua.match(/Chrome\/(\d+\.\d+)/)?.[1] || '';
        } else if (ua.indexOf('Safari') > -1 && ua.indexOf('Chrome') === -1) {
            navegador = 'Safari';
            versao = ua.match(/Version\/(\d+\.\d+)/)?.[1] || '';
        } else if (ua.indexOf('Edg') > -1) {
            navegador = 'Edge';
            versao = ua.match(/Edg\/(\d+\.\d+)/)?.[1] || '';
        }

        return { navegador, versao };
    }

    /**
     * Detecta sistema operacional
     */
    function detectarSistemaOperacional() {
        const ua = navigator.userAgent;
        let sistema = 'Desconhecido';
        let versao = '';

        if (ua.indexOf('Windows NT 10.0') > -1) {
            sistema = 'Windows';
            versao = '10/11';
        } else if (ua.indexOf('Windows NT') > -1) {
            sistema = 'Windows';
            versao = ua.match(/Windows NT (\d+\.\d+)/)?.[1] || '';
        } else if (ua.indexOf('Mac OS X') > -1) {
            sistema = 'macOS';
            versao = ua.match(/Mac OS X (\d+[._]\d+)/)?.[1].replace('_', '.') || '';
        } else if (ua.indexOf('Linux') > -1) {
            sistema = 'Linux';
        } else if (ua.indexOf('Android') > -1) {
            sistema = 'Android';
            versao = ua.match(/Android (\d+\.\d+)/)?.[1] || '';
        } else if (ua.indexOf('iOS') > -1 || ua.indexOf('iPhone') > -1) {
            sistema = 'iOS';
            versao = ua.match(/OS (\d+_\d+)/)?.[1].replace('_', '.') || '';
        }

        return { sistema, versao };
    }

    /**
     * Detecta tipo de dispositivo
     */
    function detectarTipoDispositivo() {
        const ua = navigator.userAgent;
        
        if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
            return 'tablet';
        }
        if (/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    /**
     * Detecta origem do tráfego
     */
    function detectarOrigemTrafego() {
        const referrer = document.referrer;
        const params = new URLSearchParams(window.location.search);
        
        if (!referrer) {
            return 'Direto';
        }
        
        if (params.has('utm_source')) {
            return 'Campanha';
        }
        
        try {
            const dominioReferrer = new URL(referrer).hostname;
            const dominioAtual = window.location.hostname;
            
            if (dominioReferrer === dominioAtual) {
                return 'Interno';
            }
            
            const redesSociais = ['facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'tiktok'];
            if (redesSociais.some(rede => dominioReferrer.includes(rede))) {
                return 'Social';
            }
            
            const buscadores = ['google', 'bing', 'yahoo', 'duckduckgo'];
            if (buscadores.some(buscador => dominioReferrer.includes(buscador))) {
                return 'Orgânico';
            }
        } catch (e) {
            console.error('Erro ao detectar origem:', e);
        }
        
        return 'Referência';
    }

    /**
     * Envia dados para API
     */
    async function enviarParaAPI(endpoint, dados) {
        try {
            const resposta = await fetch(`${CONFIG.API_BASE_URL}/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(dados)
            });
            
            const resultado = await resposta.json();
            
            if (!resposta.ok) {
                throw new Error(`Erro HTTP ${resposta.status}: ${resultado.erro || resultado.mensagem}`);
            }
            
            return resultado.sucesso;
        } catch (erro) {
            console.error(`Erro ao enviar para ${endpoint}:`, erro);
            return false;
        }
    }

    /**
     * Registra nova visita no backend
     */
    async function registrarVisita() {
        // Não registra se já foi registrada anteriormente
        if (visitaJaRegistrada) {
            Utilitarios.registro('Analytics: Visita já foi registrada nesta sessão');
            return;
        }

        if (!consentimentoAceito) {
            Utilitarios.registro('Analytics: Consentimento não aceito');
            return;
        }

        const infoNavegador = detectarNavegador();
        const infoSO = detectarSistemaOperacional();
        const tipoDispositivo = detectarTipoDispositivo();

        const dadosVisita = {
            uuid_visita: uuidVisita,
            consentimento_aceito: consentimentoAceito,
            navegador: infoNavegador.navegador,
            versao_navegador: infoNavegador.versao,
            sistema_operacional: infoSO.sistema,
            versao_sistema: infoSO.versao,
            tipo_dispositivo: tipoDispositivo,
            resolucao_tela: `${screen.width}x${screen.height}`,
            idioma_navegador: navigator.language,
            url_referencia: document.referrer || null,
            origem_trafego: detectarOrigemTrafego()
        };

        Utilitarios.registro('Analytics: Tentando registrar visita...', dadosVisita);
        
        const sucesso = await enviarParaAPI('registrar-visita.php', dadosVisita);
        
        // Marca como registrada
        if (sucesso) {
            visitaJaRegistrada = true;
            sessionStorage.setItem(CONFIG.VISITA_REGISTRADA_KEY, 'true');
            Utilitarios.registro('Analytics: Visita registrada com sucesso - UUID: ' + uuidVisita);
        } else {
            Utilitarios.registro('Analytics: Falha ao registrar visita', 'erro');
        }
    }

    /**
     * Registra clique em link
     */
    async function registrarCliqueLink(nomeLink, urlDestino, posicao = null) {
        if (!consentimentoAceito || !uuidVisita) return;

        const dadosClique = {
            uuid_visita: uuidVisita,
            nome_link: nomeLink,
            url_destino: urlDestino,
            posicao_lista: posicao
        };

        await enviarParaAPI('registrar-clique-link.php', dadosClique);
    }

    /**
     * Registra evento genérico
     */
    async function registrarEvento(tipoEvento, nomeEvento, valorEvento = '', dadosAdicionais = null) {
        if (!consentimentoAceito || !uuidVisita) return;

        const dadosEvento = {
            uuid_visita: uuidVisita,
            tipo_evento: tipoEvento,
            nome_evento: nomeEvento,
            valor_evento: valorEvento,
            dados_adicionais: dadosAdicionais
        };

        await enviarParaAPI('registrar-evento.php', dadosEvento);
    }

    /**
     * Atualiza fim da visita
     */
    async function atualizarFimVisita() {
        if (!consentimentoAceito || !uuidVisita || !tempoInicioSessao) return;

        const duracaoSegundos = Math.floor((Date.now() - tempoInicioSessao) / 1000);

        const dadosAtualizacao = {
            uuid_visita: uuidVisita,
            duracao_sessao_segundos: duracaoSegundos
        };

        await enviarParaAPI('atualizar-fim-visita.php', dadosAtualizacao);
    }

    /**
     * Configura rastreamento de links
     */
    function configurarRastreamentoLinks() {
        document.querySelectorAll('a[href]').forEach((link, index) => {
            link.addEventListener('click', () => {
                const nome = link.textContent.trim() || link.getAttribute('aria-label') || 'Link sem nome';
                const url = link.href;
                registrarCliqueLink(nome, url, index + 1);
            });
        });
    }

    /**
     * Configura rastreamento de seções visíveis
     */
    function configurarRastreamentoSecoes() {
        const secoes = document.querySelectorAll('section[id]');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const nomeSecao = entry.target.id;
                    registrarEvento('visualizacao_secao', 'Seção Visualizada', nomeSecao);
                }
            });
        }, { threshold: 0.5 });

        secoes.forEach(secao => observer.observe(secao));
    }

    /**
     * Configura eventos de saída
     */
    function configurarEventosSaida() {
        window.addEventListener('beforeunload', () => {
            atualizarFimVisita();
        });

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                atualizarFimVisita();
            }
        });
    }

    /**
     * Aguarda consentimento do usuário
     */
    function aguardarConsentimento() {
        const verificarConsentimentoInterval = setInterval(() => {
            if (verificarConsentimento()) {
                consentimentoAceito = true;
                clearInterval(verificarConsentimentoInterval);
                Utilitarios.registro('Analytics: Consentimento aceito, tracking iniciado');
                iniciarTracking();
            }
        }, 500);

        setTimeout(() => {
            clearInterval(verificarConsentimentoInterval);
        }, 30000);
    }

    /**
     * Inicia tracking após consentimento
     */
    function iniciarTracking() {
        // Verifica se já foi registrada uma visita inicial
        const jaRegistrada = sessionStorage.getItem(CONFIG.VISITA_REGISTRADA_KEY);
        if (jaRegistrada === 'true') {
            visitaJaRegistrada = true;
            Utilitarios.registro('Analytics: Visita já registrada anteriormente nesta sessão');
            
            // Continua configurando rastreamento mas não registra novamente
            uuidVisita = obterUuidVisita();
            configurarRastreamentoLinks();
            configurarRastreamentoSecoes();
            configurarEventosSaida();
            return;
        }

        uuidVisita = obterUuidVisita();
        tempoInicioSessao = Date.now();
        
        registrarVisita();
        configurarRastreamentoLinks();
        configurarRastreamentoSecoes();
        configurarEventosSaida();
    }

    /**
     * Inicializa o sistema
     */
    function inicializar() {
        consentimentoAceito = verificarConsentimento();

        if (consentimentoAceito) {
            iniciarTracking();
            Utilitarios.registro('Analytics: Sistema inicializado com consentimento existente');
        } else {
            aguardarConsentimento();
            Utilitarios.registro('Analytics: Aguardando consentimento do usuário');
        }
    }

    return {
        inicializar,
        registrarEvento,
        registrarCliqueLink
    };
})();

// ==================== INTEGRAÇÕES COM TRACKING EXTERNOS (CLARITY & GOOGLE ANALYTICS) ====================

const IntegracoesExternas = (() => {
    const CONFIG = {
        CLARITY_ID: 'li7zcfg6p7',
        GOOGLE_ANALYTICS_ID: 'G-LCJRWY87HC',
        CONSENTIMENTO_KEY: 'hubJota_consentimento'
    };

    let clarityCarregado = false;
    let googleAnalyticsCarregado = false;

    /**
     * Inicializa Microsoft Clarity
     */
    function inicializarClarity() {
        if (clarityCarregado) {
            Utilitarios.registro('Clarity: Já está carregado');
            return;
        }

        try {
            (function(c,l,a,r,i,t,y){
                c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
                t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
                y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
            })(window, document, "clarity", "script", CONFIG.CLARITY_ID);

            clarityCarregado = true;
            Utilitarios.registro('Clarity: Inicializado com sucesso');
        } catch (erro) {
            console.error('Erro ao carregar Microsoft Clarity:', erro);
        }
    }

    /**
     * Inicializa Google Analytics (gtag.js)
     */
    function inicializarGoogleAnalytics() {
        if (googleAnalyticsCarregado) {
            Utilitarios.registro('Google Analytics: Já está carregado');
            return;
        }

        try {
            // Cria script do gtag.js
            const scriptGtag = document.createElement('script');
            scriptGtag.async = true;
            scriptGtag.src = `https://www.googletagmanager.com/gtag/js?id=${CONFIG.GOOGLE_ANALYTICS_ID}`;
            document.head.appendChild(scriptGtag);

            // Inicializa dataLayer e gtag
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            window.gtag = gtag;
            
            gtag('js', new Date());
            gtag('config', CONFIG.GOOGLE_ANALYTICS_ID, {
                'anonymize_ip': false,
                'cookie_flags': 'SameSite=None;Secure'
            });

            googleAnalyticsCarregado = true;
            Utilitarios.registro('Google Analytics: Inicializado com sucesso');
        } catch (erro) {
            console.error('Erro ao carregar Google Analytics:', erro);
        }
    }

    /**
     * Inicializa TODAS as ferramentas externas
     */
    function inicializarTodasFerramentas() {
        const consentimento = localStorage.getItem(CONFIG.CONSENTIMENTO_KEY);
        
        if (consentimento !== 'aceito') {
            Utilitarios.registro('Integrações Externas: Aguardando consentimento');
            return false;
        }

        Utilitarios.registro('Integrações Externas: Carregando ferramentas...');
        
        inicializarClarity();
        inicializarGoogleAnalytics();

        return true;
    }

    /**
     * Envia evento personalizado para Google Analytics
     */
    function enviarEventoGA(nomeEvento, parametros = {}) {
        if (!googleAnalyticsCarregado || typeof window.gtag !== 'function') {
            return;
        }

        try {
            window.gtag('event', nomeEvento, parametros);
            Utilitarios.registro(`GA Event enviado: ${nomeEvento}`, parametros);
        } catch (erro) {
            console.error('Erro ao enviar evento GA:', erro);
        }
    }

    /**
     * Envia evento personalizado para Clarity
     */
    function enviarEventoClarity(nomeEvento, valor = '') {
        if (!clarityCarregado || typeof window.clarity !== 'function') {
            return;
        }

        try {
            window.clarity('set', nomeEvento, valor);
            Utilitarios.registro(`Clarity Event enviado: ${nomeEvento}`, valor);
        } catch (erro) {
            console.error('Erro ao enviar evento Clarity:', erro);
        }
    }

    /**
     * Público: Inicializa integrações
     */
    function inicializar() {
        return inicializarTodasFerramentas();
    }

    return {
        inicializar,
        inicializarTodasFerramentas,
        enviarEventoGA,
        enviarEventoClarity
    };
})();

// ==================== GERENCIADOR LGPD E CONSENTIMENTO ====================

const GerenciadorLGPD = (() => {
    const CHAVE_CONSENTIMENTO = 'hubJota_consentimento';
    const CHAVE_DATA_CONSENTIMENTO = 'hubJota_lgpd_data';
    
    // Elementos do DOM
    const modal = document.getElementById('lgpd-modal');
    const botaoAceitar = document.getElementById('lgpd-aceitar');
    const botaoRejeitar = document.getElementById('lgpd-rejeitar');
    const botaoReconsiderar = document.getElementById('lgpd-reconsiderar');
    const linkPolitica = document.getElementById('link-politica');
    const termoExpandido = document.getElementById('termos-expandidos');
    const textareTermos = document.getElementById('textarea-termos');
    const mensagemRejeicao = document.getElementById('lgpd-mensagem-rejeicao');
    
    /**
     * Verifica se o usuário já aceitou os termos
     */
    function verificarConsentimentoExistente() {
        try {
            return localStorage.getItem(CHAVE_CONSENTIMENTO) === 'aceito';
        } catch (erro) {
            Utilitarios.registro('Erro ao verificar consentimento: ' + erro, 'erro');
            return false;
        }
    }
    
    /**
     * Salva o consentimento no localStorage
     */
    function salvarConsentimento() {
        try {
            localStorage.setItem(CHAVE_CONSENTIMENTO, 'aceito');
            localStorage.setItem(CHAVE_DATA_CONSENTIMENTO, new Date().toISOString());
            return true;
        } catch (erro) {
            Utilitarios.registro('Erro ao salvar consentimento: ' + erro, 'erro');
            return false;
        }
    }
    
    /**
     * Remove o consentimento do localStorage
     */
    function removerConsentimento() {
        try {
            localStorage.removeItem(CHAVE_CONSENTIMENTO);
            localStorage.removeItem(CHAVE_DATA_CONSENTIMENTO);
            return true;
        } catch (erro) {
            Utilitarios.registro('Erro ao remover consentimento: ' + erro, 'erro');
            return false;
        }
    }
    
    /**
     * Exibe o modal de consentimento
     */
    function exibirModal() {
        if (modal) {
            modal.classList.add('ativo');
            document.body.style.overflow = 'hidden';
        }
    }
    
    /**
     * Oculta o modal de consentimento
     */
    function ocultarModal() {
        if (modal) {
            modal.classList.remove('ativo');
            document.body.style.overflow = 'auto';
        }
    }
    
    /**
     * Processa o aceite do usuário
     */
    function aceitarTermos() {
        salvarConsentimento();

        // Define explicitamente o valor esperado pelo sistema de analytics
        localStorage.setItem('hubJota_consentimento', 'aceito');

        ocultarModal();
        
        // Ativa as funcionalidades internas de tracking
        if (InformacoesVisitante) {
            InformacoesVisitante.inicializar();
        }

        // Ativa ferramentas externas de tracking (Microsoft Clarity e Google Analytics)
        if (IntegracoesExternas) {
            IntegracoesExternas.inicializarTodasFerramentas();
        }
        
        Utilitarios.registro('Termos aceitos - Tracking ativado', 'info');
    }
    
    /**
     * Processa a rejeição do usuário
     */
    function rejeitarTermos() {
        removerConsentimento();
        
        // Oculta os botões de aceitar/rejeitar
        document.getElementById('lgpd-aceitar').style.display = 'none';
        document.getElementById('lgpd-rejeitar').style.display = 'none';
        
        // Exibe a mensagem de rejeição
        mensagemRejeicao.style.display = 'block';
        
        // Oculta o container de informações do visitante
        const containerInfos = document.getElementById('container-suas-infos');
        if (containerInfos) {
            containerInfos.style.display = 'none';
        }
        
        Utilitarios.registro('Termos rejeitados - Tracking desativado', 'info');
    }
    
    /**
     * Permite ao usuário reconsiderar a rejeição
     */
    function reconsiderarRejeicao() {
        // Restaura os botões
        document.getElementById('lgpd-aceitar').style.display = '';
        document.getElementById('lgpd-rejeitar').style.display = '';
        
        // Oculta a mensagem de rejeição
        mensagemRejeicao.style.display = 'none';
        
        // Limpa os termos expandidos
        termoExpandido.style.display = 'none';
        
        Utilitarios.registro('Usuário reconsidera rejeição', 'info');
    }
    
    /**
     * Carrega e exibe os termos de privacidade
     */
    async function carregarTermosPolitica() {
        try {
            // Tenta carregar um arquivo de termos (você pode criar este arquivo)
            const termosPadrao = `POLÍTICA DE PRIVACIDADE E COOKIES
Hub de Links - José Guilherme Pandolfi

1. INTRODUÇÃO
Este site coleta dados dos visitantes para melhorar a experiência e análise de uso.

2. DADOS COLETADOS
- Endereço IP
- Localização geográfica aproximada
- Sistema operacional
- Navegador web
- Marca do dispositivo
- Duração da sessão
- Cliques e interações

3. FINALIDADE DO TRATAMENTO
Os dados são coletados para:
- Melhorar a experiência do usuário
- Realizar análises de comportamento
- Aumentar a segurança
- Cumprir obrigações legais

4. ARMAZENAMENTO
Os dados são armazenados de forma segura e apenas pelo tempo necessário.

5. COOKIES
Utilizamos cookies essenciais para o funcionamento do site e cookies analíticos para melhorar sua experiência.

6. DIREITOS DO USUÁRIO
Você pode solicitar acesso, correção ou exclusão de seus dados a qualquer momento.

7. CONSENTIMENTO
Você pode revogar seu consentimento a qualquer momento limpando os cookies do navegador.

Última atualização: ${new Date().toLocaleDateString('pt-BR')}`;
            
            textareTermos.value = termosPadrao;
        } catch (erro) {
            Utilitarios.registro('Erro ao carregar termos: ' + erro, 'erro');
            textareTermos.value = 'Erro ao carregar os termos. Por favor, entre em contato.';
        }
    }
    
    /**
     Configura os event listeners
     */
    function configurarEventos() {
        // Botão Aceitar
        if (botaoAceitar) {
            botaoAceitar.addEventListener('click', aceitarTermos);
        }
        
        // Botão Rejeitar
        if (botaoRejeitar) {
            botaoRejeitar.addEventListener('click', rejeitarTermos);
        }
        
        // Botão Reconsiderar
        if (botaoReconsiderar) {
            botaoReconsiderar.addEventListener('click', reconsiderarRejeicao);
        }
        
        // Link Política
        if (linkPolitica) {
            linkPolitica.addEventListener('click', (e) => {
                e.preventDefault();
                
                if (termoExpandido.style.display === 'none') {
                    carregarTermosPolitica();
                    termoExpandido.style.display = 'block';
                } else {
                    termoExpandido.style.display = 'none';
                }
            });
        }
        
        Utilitarios.registro('Eventos LGPD configurados', 'info');
    }
    
    /**
     * Inicializa o gerenciador LGPD
     */
    function inicializar() {
        if (!modal) {
            Utilitarios.registro('Modal LGPD não encontrado no DOM', 'erro');
            return false;
        }
        
        configurarEventos();
        
        // Verificar se já existe consentimento
        const temConsentimento = verificarConsentimentoExistente();
        
        if (!temConsentimento) {
            // Exibir modal após meio segundo
            setTimeout(() => {
                exibirModal();
            }, 500);
        } else {
            // Se já tem consentimento, ativar funcionalidades de tracking internas imediatamente
            if (InformacoesVisitante) {
                InformacoesVisitante.inicializar();
            }
            // Também ativa imediatamente as ferramentas externas de tracking
            if (IntegracoesExternas) {
            IntegracoesExternas.inicializarTodasFerramentas();
            }
        }
        
        Utilitarios.registro('Gerenciador LGPD inicializado', 'info');
        return temConsentimento;
    }
    
    return {
        inicializar,
        exibirModal,
        ocultarModal,
        verificarConsentimentoExistente,
        salvarConsentimento,
        removerConsentimento
    };
})();

// ==================== INICIALIZAÇÃO PRINCIPAL ====================

function inicializarAplicacao() {
    Utilitarios.registro('Iniciando Hub de Links do Jota...');
    
    GerenciadorModo.inicializar();
    CursorPersonalizado.inicializar();
    BarraCarregamento.inicializar();
    GerenciadorLoader.inicializar();
    Animacoes.inicializar();
    Datas.inicializar();
    Acessibilidade.inicializar();
    Compartilhamento.inicializar();
    PortfolioImagens.inicializar();
    Artigos.inicializar();
    ModalPortfolio.inicializar();
    GerenciadorLGPD.inicializar();
    InformacoesVisitante.inicializar();
    SistemaAnalytics.inicializar();
    
    Utilitarios.registro('Hub de Links do Jota carregado com sucesso!');
}

// Executar quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarAplicacao);
} else {
    inicializarAplicacao();
}

// Service Worker (se suportado)
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then((registros) => {
        registros.forEach((registro) => {
            registro.unregister();
        });
    });
}