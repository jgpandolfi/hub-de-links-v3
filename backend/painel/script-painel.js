/**
 * Script do Painel de Analytics
 * Hub de Links - Sistema de Tracking
 * Sincronizado com o frontend
 */

// ==================== GERENCIADOR DE TEMA ====================
const GerenciadorTema = (() => {
    // Usar a MESMA chave do frontend para sincronização
    const CHAVE_TEMA = 'hubJota_modo_tema';
    
    /**
     * Obtém o tema salvo ou o padrão
     */
    function obterTemaSalvo() {
        const temaSalvo = localStorage.getItem(CHAVE_TEMA);
        console.log('Tema salvo encontrado:', temaSalvo);
        return temaSalvo || 'claro';
    }
    
    /**
     * Aplica o tema ao body
     */
    function aplicarTema(tema) {
        console.log('Aplicando tema:', tema);
        
        if (tema === 'escuro') {
            document.body.classList.add('tema-escuro');
        } else {
            document.body.classList.remove('tema-escuro');
        }
    }
    
    /**
     * Atualiza os ícones do botão toggle
     */
    function atualizarIcones(tema) {
        const iconesLua = document.querySelectorAll('.icone-lua');
        const iconesSol = document.querySelectorAll('.icone-sol');
        
        console.log('Atualizando ícones para tema:', tema);
        console.log('Ícones lua encontrados:', iconesLua.length);
        console.log('Ícones sol encontrados:', iconesSol.length);
        
        // Modo claro: mostrar lua (para mudar para escuro)
        // Modo escuro: mostrar sol (para mudar para claro)
        iconesLua.forEach(icone => {
            if (tema === 'claro') {
                icone.style.display = 'block';
            } else {
                icone.style.display = 'none';
            }
        });
        
        iconesSol.forEach(icone => {
            if (tema === 'escuro') {
                icone.style.display = 'block';
            } else {
                icone.style.display = 'none';
            }
        });
    }
    
    /**
     * Alterna entre temas
     */
    function alternarTema() {
        const temaAtual = obterTemaSalvo();
        const novoTema = temaAtual === 'claro' ? 'escuro' : 'claro';
        
        console.log('Alternando tema de', temaAtual, 'para', novoTema);
        
        // Salvar no localStorage
        localStorage.setItem(CHAVE_TEMA, novoTema);
        
        // Aplicar tema
        aplicarTema(novoTema);
        
        // Atualizar ícones
        atualizarIcones(novoTema);
    }
    
    /**
     * Inicializa o gerenciador de tema
     */
    function inicializar() {
        console.log('Inicializando Gerenciador de Tema do Painel');
        
        // Obter tema salvo
        const temaSalvo = obterTemaSalvo();
        console.log('Tema inicial:', temaSalvo);
        
        // Aplicar tema
        aplicarTema(temaSalvo);
        
        // Atualizar ícones
        atualizarIcones(temaSalvo);
        
        // Botão no dashboard
        const botaoToggle = document.getElementById('toggle-tema');
        if (botaoToggle) {
            console.log('Botão toggle dashboard encontrado');
            botaoToggle.addEventListener('click', (e) => {
                e.preventDefault();
                alternarTema();
            });
        } else {
            console.warn('Botão toggle dashboard NÃO encontrado');
        }
        
        // Botão no login
        const botaoToggleLogin = document.getElementById('toggle-tema-login');
        if (botaoToggleLogin) {
            console.log('Botão toggle login encontrado');
            botaoToggleLogin.addEventListener('click', (e) => {
                e.preventDefault();
                alternarTema();
            });
        } else {
            console.warn('Botão toggle login NÃO encontrado');
        }
        
        console.log('✅ Gerenciador de Tema inicializado com sucesso');
    }
    
    return {
        inicializar
    };
})();

// ==================== INICIALIZAÇÃO ====================
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM carregado - Iniciando sistema do painel');
    GerenciadorTema.inicializar();
    console.log('✅ Painel de Analytics carregado com sucesso');
});