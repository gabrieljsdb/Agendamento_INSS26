<?php
// admin/dashboard_graficos.php
session_start();
require '../conexao.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}

// Pega o ID da agenda selecionada
$id_agenda_selecionada = $_SESSION['admin_id_agenda_selecionada'] ?? 1;

// Busca agendas disponíveis para o filtro
$agendas_disponiveis = $pdo->query("SELECT id, nome FROM agendas WHERE ativa = 1 ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$pagina_atual_menu = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard Administrativo - Gráficos</title>
    
    <!-- CSS do Painel CoolAdmin -->
    <link href="../css/font-face.css" rel="stylesheet" media="all">
    <link href="../vendor/font-awesome-5/css/fontawesome-all.min.css" rel="stylesheet" media="all">
    <link href="../vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">
    <link href="../vendor/bootstrap-4.1/bootstrap.min.css" rel="stylesheet" media="all">
    <link href="../vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="../css/theme.css" rel="stylesheet" media="all">
    
    <!-- Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .dashboard-stats {
            margin-bottom: 30px;
        }
        
        .stat-card {
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        
        .stat-card-header {
            padding: 15px 20px;
            color: white;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .stat-card-body {
            padding: 20px;
            background-color: white;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-description {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .chart-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        
        .chart-title {
            margin-bottom: 15px;
            font-weight: bold;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .stats-row {
            margin-bottom: 30px;
        }
        
        .filter-controls {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .chart-controls {
            margin-top: 15px;
            display: flex;
            justify-content: flex-end;
        }
        
        .chart-controls button {
            margin-left: 10px;
        }
        
        .animated-number {
            display: inline-block;
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.8;
        }
        
        .stat-trend {
            font-size: 0.9rem;
            margin-left: 10px;
        }
        
        .trend-up {
            color: #28a745;
        }
        
        .trend-down {
            color: #dc3545;
        }
        
        .trend-neutral {
            color: #6c757d;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease forwards;
        }
        
        .chart-card {
            opacity: 0;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
    </style>
</head>

<body class="animsition">
    <div class="page-wrapper">
        <!-- MENU LATERAL -->
        <aside class="menu-sidebar d-none d-lg-block">
            <div class="logo">
                <a href="dashboard.php"><img src="../images/icon/logo.png" alt="CoolAdmin" /></a>
            </div>
            <div class="menu-sidebar__content js-scrollbar1">
                <nav class="navbar-sidebar">
                    <ul class="navbar__list">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                        <li class="active"><a href="dashboard_graficos.php"><i class="fas fa-chart-bar"></i>Gráficos</a></li>
                        <li><a href="atendimentos_hoje.php"><i class="fas fa-clipboard-list"></i>Atendimentos do Dia</a></li>
                        <li><a href="bloqueios.php"><i class="fas fa-ban"></i>Gerenciar Bloqueios</a></li>
                        <?php if (isset($_SESSION['admin_cargo']) && $_SESSION['admin_cargo'] === 'SuperAdmin'): ?>
                            <li><a href="gerenciar_admins.php"><i class="fas fa-users-cog"></i>Gerenciar Admins</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="page-container">
            <!-- HEADER -->
            <header class="header-desktop">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">
                        <div class="header-wrap">
                            <form class="form-header"></form>
                            <div class="header-button">
                                <div class="account-wrap">
                                    <div class="account-item clearfix js-item-menu">
                                        <div class="content">
                                            <a class="js-acc-btn" href="#"><?php echo htmlspecialchars($_SESSION['admin_usuario']); ?></a>
                                        </div>
                                        <div class="account-dropdown js-dropdown">
                                            <div class="account-dropdown__footer">
                                                <a href="logout.php"><i class="zmdi zmdi-power"></i>Sair</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- CONTEÚDO PRINCIPAL -->
            <div class="main-content">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-md-12">
                                <h3 class="title-5 m-b-35">Dashboard Analítico</h3>
                                
                                <!-- Filtros -->
                                <div class="filter-controls">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="periodo">Período</label>
                                                <select class="form-control" id="periodo">
                                                    <option value="7">Últimos 7 dias</option>
                                                    <option value="30" selected>Últimos 30 dias</option>
                                                    <option value="90">Últimos 3 meses</option>
                                                    <option value="365">Último ano</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="agenda">Agenda</label>
                                                <select class="form-control" id="agenda">
                                                    <option value="todas" selected>Todas as agendas</option>
                                                    <?php foreach ($agendas_disponiveis as $agenda): ?>
                                                        <option value="<?php echo $agenda['id']; ?>"><?php echo htmlspecialchars($agenda['nome']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="tipo_grafico">Tipo de Visualização</label>
                                                <select class="form-control" id="tipo_grafico">
                                                    <option value="bar" selected>Barras</option>
                                                    <option value="line">Linhas</option>
                                                    <option value="pie">Pizza</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label>&nbsp;</label>
                                            <button id="aplicar-filtros" class="btn btn-primary btn-block">Aplicar Filtros</button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Cards de Estatísticas -->
                                <div class="row stats-row">
                                    <div class="col-md-3">
                                        <div class="stat-card fade-in-up" style="animation-delay: 0.1s">
                                            <div class="stat-card-header" style="background-color: #28a745;">
                                                <span>Agendamentos Confirmados</span>
                                                <i class="fas fa-calendar-check stat-icon"></i>
                                            </div>
                                            <div class="stat-card-body">
                                                <div class="stat-number" id="stat-confirmados">
                                                    <div class="loading">Carregando...</div>
                                                </div>
                                                <div class="stat-description">
                                                    <span id="trend-confirmados" class="stat-trend">Carregando...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card fade-in-up" style="animation-delay: 0.2s">
                                            <div class="stat-card-header" style="background-color: #dc3545;">
                                                <span>Agendamentos Cancelados</span>
                                                <i class="fas fa-calendar-times stat-icon"></i>
                                            </div>
                                            <div class="stat-card-body">
                                                <div class="stat-number" id="stat-cancelados">
                                                    <div class="loading">Carregando...</div>
                                                </div>
                                                <div class="stat-description">
                                                    <span id="trend-cancelados" class="stat-trend">Carregando...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card fade-in-up" style="animation-delay: 0.3s">
                                            <div class="stat-card-header" style="background-color: #007bff;">
                                                <span>Taxa de Comparecimento</span>
                                                <i class="fas fa-user-check stat-icon"></i>
                                            </div>
                                            <div class="stat-card-body">
                                                <div class="stat-number" id="stat-comparecimento">
                                                    <div class="loading">Carregando...</div>
                                                </div>
                                                <div class="stat-description">
                                                    <span id="trend-comparecimento" class="stat-trend">Carregando...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card fade-in-up" style="animation-delay: 0.4s">
                                            <div class="stat-card-header" style="background-color: #6f42c1;">
                                                <span>Média Diária</span>
                                                <i class="fas fa-chart-line stat-icon"></i>
                                            </div>
                                            <div class="stat-card-body">
                                                <div class="stat-number" id="stat-media-diaria">
                                                    <div class="loading">Carregando...</div>
                                                </div>
                                                <div class="stat-description">
                                                    <span id="trend-media" class="stat-trend">Carregando...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Gráficos -->
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="chart-card fade-in-up" style="animation-delay: 0.5s">
                                            <div class="chart-container">
                                                <div class="chart-title">Agendamentos por Dia</div>
                                                <div class="loading" id="loading-agendamentos">Carregando gráfico...</div>
                                                <canvas id="agendamentosChart" style="display: none;"></canvas>
                                                <div class="chart-controls">
                                                    <button class="btn btn-sm btn-outline-primary" id="toggle-agendamentos">Alternar para Linhas</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="chart-card fade-in-up" style="animation-delay: 0.6s">
                                            <div class="chart-container">
                                                <div class="chart-title">Distribuição por Motivo</div>
                                                <div class="loading" id="loading-motivos">Carregando gráfico...</div>
                                                <canvas id="motivosChart" style="display: none;"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="chart-card fade-in-up" style="animation-delay: 0.7s">
                                            <div class="chart-container">
                                                <div class="chart-title">Status dos Atendimentos</div>
                                                <div class="loading" id="loading-status">Carregando gráfico...</div>
                                                <canvas id="statusAtendimentosChart" style="display: none;"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="chart-card fade-in-up" style="animation-delay: 0.8s">
                                            <div class="chart-container">
                                                <div class="chart-title">Horários Mais Populares</div>
                                                <div class="loading" id="loading-horarios">Carregando gráfico...</div>
                                                <canvas id="horariosChart" style="display: none;"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Scripts -->
<script src="../vendor/jquery-3.2.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
<script src="../vendor/bootstrap-4.1/bootstrap.min.js"></script>
<script src="../vendor/animsition/animsition.min.js"></script>
<script src="../js/main.js"></script>

<script>
// Aguardar o jQuery carregar antes de executar o código
function waitForjQuery() {
    if (window.jQuery) {
        initializeDashboard();
    } else {
        setTimeout(waitForjQuery, 100);
    }
}

// Inicializar dashboard quando a página e jQuery carregarem
function initializeDashboard() {
    console.log('jQuery carregado, inicializando dashboard...');
    
    // Carregar dados iniciais
    carregarDadosDashboard();
    
    // Configurar eventos
    $('#aplicar-filtros').on('click', aplicarFiltros);
    $('#toggle-agendamentos').on('click', toggleVisualizacaoAgendamentos);
}

// Variáveis globais para os gráficos
let agendamentosChart, motivosChart, statusAtendimentosChart, horariosChart;

// Função para carregar todos os dados do dashboard
function carregarDadosDashboard() {
    const periodo = $('#periodo').val();
    const agenda = $('#agenda').val();
    
    console.log('Carregando dados - Periodo:', periodo, 'Agenda:', agenda);
    
    // Carregar estatísticas
    carregarEstatisticas(periodo, agenda);
    
    // Carregar gráficos
    carregarGraficoAgendamentos(periodo, agenda);
    carregarGraficoMotivos(periodo, agenda);
    carregarGraficoStatusAtendimentos(periodo, agenda);
    carregarGraficoHorarios(periodo, agenda);
}

// Função para carregar estatísticas
function carregarEstatisticas(periodo, agenda) {
    console.log('Carregando estatísticas...');
    
    $.ajax({
        url: 'ajax_estatisticas.php',
        type: 'GET',
        data: {
            periodo: periodo,
            agenda: agenda
        },
        dataType: 'json',
        success: function(dados) {
            console.log('Estatísticas carregadas:', dados);
            
            if (dados.error) {
                mostrarErro('estatisticas', dados.error);
                return;
            }
            
            atualizarEstatisticas(dados);
            animarNumeros();
        },
        error: function(xhr, status, error) {
            console.error('Erro ao carregar estatísticas:', error);
            mostrarErro('estatisticas', 'Erro de conexão: ' + error);
        }
    });
}

// Função para carregar gráfico de agendamentos por dia
function carregarGraficoAgendamentos(periodo, agenda) {
    $.ajax({
        url: 'ajax_agendamentos_dia.php',
        type: 'GET',
        data: {
            periodo: periodo,
            agenda: agenda
        },
        dataType: 'json',
        success: function(dados) {
            console.log('Dados agendamentos/dia:', dados);
            
            if (dados.error) {
                mostrarErro('agendamentos', dados.error);
                return;
            }
            
            criarGraficoAgendamentos(dados);
            $('#loading-agendamentos').hide();
            $('#agendamentosChart').show();
        },
        error: function(xhr, status, error) {
            console.error('Erro ao carregar agendamentos/dia:', error);
            mostrarErro('agendamentos', 'Erro de conexão');
        }
    });
}

// Função para carregar gráfico de distribuição por motivo
function carregarGraficoMotivos(periodo, agenda) {
    $.ajax({
        url: 'ajax_motivos.php',
        type: 'GET',
        data: {
            periodo: periodo,
            agenda: agenda
        },
        dataType: 'json',
        success: function(dados) {
            console.log('Dados motivos:', dados);
            
            if (dados.error) {
                mostrarErro('motivos', dados.error);
                return;
            }
            
            criarGraficoMotivos(dados);
            $('#loading-motivos').hide();
            $('#motivosChart').show();
        },
        error: function(xhr, status, error) {
            console.error('Erro ao carregar motivos:', error);
            mostrarErro('motivos', 'Erro de conexão');
        }
    });
}

// Função para carregar gráfico de status dos atendimentos
function carregarGraficoStatusAtendimentos(periodo, agenda) {
    $.ajax({
        url: 'ajax_status_atendimentos.php',
        type: 'GET',
        data: {
            periodo: periodo,
            agenda: agenda
        },
        dataType: 'json',
        success: function(dados) {
            console.log('Dados status:', dados);
            
            if (dados.error) {
                mostrarErro('status', dados.error);
                return;
            }
            
            criarGraficoStatusAtendimentos(dados);
            $('#loading-status').hide();
            $('#statusAtendimentosChart').show();
        },
        error: function(xhr, status, error) {
            console.error('Erro ao carregar status:', error);
            mostrarErro('status', 'Erro de conexão');
        }
    });
}

// Função para carregar gráfico de horários populares
function carregarGraficoHorarios(periodo, agenda) {
    $.ajax({
        url: 'ajax_horarios.php',
        type: 'GET',
        data: {
            periodo: periodo,
            agenda: agenda
        },
        dataType: 'json',
        success: function(dados) {
            console.log('Dados horarios:', dados);
            
            if (dados.error) {
                mostrarErro('horarios', dados.error);
                return;
            }
            
            criarGraficoHorarios(dados);
            $('#loading-horarios').hide();
            $('#horariosChart').show();
        },
        error: function(xhr, status, error) {
            console.error('Erro ao carregar horarios:', error);
            mostrarErro('horarios', 'Erro de conexão');
        }
    });
}

// Função para mostrar erros
function mostrarErro(tipo, mensagem) {
    const elementos = {
        'estatisticas': ['stat-confirmados', 'stat-cancelados', 'stat-comparecimento', 'stat-media-diaria'],
        'agendamentos': ['loading-agendamentos'],
        'motivos': ['loading-motivos'],
        'status': ['loading-status'],
        'horarios': ['loading-horarios']
    };
    
    if (elementos[tipo]) {
        elementos[tipo].forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.innerHTML = `<div style="color: #dc3545; text-align: center;">Erro: ${mensagem}</div>`;
            }
        });
    }
}

// Função para atualizar as estatísticas
function atualizarEstatisticas(dados) {
    console.log('Atualizando estatísticas com:', dados);
    
    // Limpar conteúdos anteriores
    $('#stat-confirmados').html('<span class="animated-number">0</span>');
    $('#stat-cancelados').html('<span class="animated-number">0</span>');
    $('#stat-comparecimento').html('<span class="animated-number">0%</span>');
    $('#stat-media-diaria').html('<span class="animated-number">0</span>');
    
    // Atualizar com novos dados
    $('#stat-confirmados').html('<span class="animated-number">' + dados.confirmados + '</span>');
    $('#stat-cancelados').html('<span class="animated-number">' + dados.cancelados + '</span>');
    $('#stat-comparecimento').html('<span class="animated-number">' + dados.comparecimento + '%</span>');
    $('#stat-media-diaria').html('<span class="animated-number">' + dados.mediaDiaria + '</span>');
    
    // Atualizar tendências
    $('#trend-confirmados').text(dados.trendConfirmados);
    $('#trend-cancelados').text(dados.trendCancelados);
    $('#trend-comparecimento').text(dados.trendComparecimento);
    $('#trend-media').text(dados.trendMedia);
    
    // Aplicar classes de cor para as tendências
    aplicarClasseTrend('trend-confirmados', dados.trendConfirmados);
    aplicarClasseTrend('trend-cancelados', dados.trendCancelados);
    aplicarClasseTrend('trend-comparecimento', dados.trendComparecimento);
    aplicarClasseTrend('trend-media', dados.trendMedia);
}

// Função para aplicar classe de cor baseada na tendência
function aplicarClasseTrend(elementId, trendText) {
    const element = document.getElementById(elementId);
    element.className = 'stat-trend';
    
    if (trendText.includes('+')) {
        element.classList.add('trend-up');
    } else if (trendText.includes('-')) {
        element.classList.add('trend-down');
    } else {
        element.classList.add('trend-neutral');
    }
}

// Função para animar números
function animarNumeros() {
    const elementos = document.querySelectorAll('.animated-number');
    
    elementos.forEach(elemento => {
        const valorFinal = parseInt(elemento.textContent);
        let valorAtual = 0;
        const incremento = valorFinal / 50;
        const duracao = 1000;
        const intervalo = duracao / 50;
        
        const timer = setInterval(() => {
            valorAtual += incremento;
            if (valorAtual >= valorFinal) {
                elemento.textContent = valorFinal;
                clearInterval(timer);
            } else {
                elemento.textContent = Math.round(valorAtual);
            }
        }, intervalo);
    });
}

// Função para criar gráfico de agendamentos por dia
function criarGraficoAgendamentos(dados) {
    const ctx = document.getElementById('agendamentosChart').getContext('2d');
    
    if (agendamentosChart) {
        agendamentosChart.destroy();
    }
    
    agendamentosChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dados.labels,
            datasets: [
                {
                    label: 'Confirmados',
                    data: dados.confirmados,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Cancelados',
                    data: dados.cancelados,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });
}

// Função para criar gráfico de distribuição por motivo
function criarGraficoMotivos(dados) {
    const ctx = document.getElementById('motivosChart').getContext('2d');
    
    if (motivosChart) {
        motivosChart.destroy();
    }
    
    motivosChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: dados.labels,
            datasets: [{
                data: dados.data,
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)',
                    'rgba(255, 193, 7, 0.7)',
                    'rgba(108, 117, 125, 0.7)',
                    'rgba(0, 123, 255, 0.7)'
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(108, 117, 125, 1)',
                    'rgba(0, 123, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1000
            }
        }
    });
}

// Função para criar gráfico de status dos atendimentos
function criarGraficoStatusAtendimentos(dados) {
    const ctx = document.getElementById('statusAtendimentosChart').getContext('2d');
    
    if (statusAtendimentosChart) {
        statusAtendimentosChart.destroy();
    }
    
    statusAtendimentosChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: dados.labels,
            datasets: [{
                data: dados.data,
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)',
                    'rgba(220, 53, 69, 0.7)',
                    'rgba(23, 162, 184, 0.7)',
                    'rgba(255, 193, 7, 0.7)'
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(220, 53, 69, 1)',
                    'rgba(23, 162, 184, 1)',
                    'rgba(255, 193, 7, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1000
            }
        }
    });
}

// Função para criar gráfico de horários populares
function criarGraficoHorarios(dados) {
    const ctx = document.getElementById('horariosChart').getContext('2d');
    
    if (horariosChart) {
        horariosChart.destroy();
    }
    
    horariosChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dados.labels,
            datasets: [{
                label: 'Agendamentos',
                data: dados.data,
                backgroundColor: 'rgba(0, 123, 255, 0.7)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });
}

// Função para aplicar filtros
function aplicarFiltros() {
    const botao = $('#aplicar-filtros');
    const textoOriginal = botao.html();
    
    // Mostrar indicador de carregamento
    botao.html('<i class="fas fa-spinner fa-spin"></i> Aplicando...');
    botao.prop('disabled', true);
    
    // Recarregar todos os dados com os novos filtros
    carregarDadosDashboard();
    
    // Restaurar botão após um tempo
    setTimeout(() => {
        botao.html(textoOriginal);
        botao.prop('disabled', false);
    }, 2000);
}

// Função para alternar visualização do gráfico de agendamentos
function toggleVisualizacaoAgendamentos() {
    if (!agendamentosChart) return;
    
    const tipoAtual = agendamentosChart.config.type;
    const novoTipo = tipoAtual === 'bar' ? 'line' : 'bar';
    
    // Atualizar o tipo do gráfico
    agendamentosChart.config.type = novoTipo;
    
    // Atualizar as opções de dataset para o novo tipo
    agendamentosChart.data.datasets.forEach(dataset => {
        if (novoTipo === 'line') {
            dataset.backgroundColor = 'transparent';
            dataset.fill = true;
        } else {
            dataset.backgroundColor = dataset.label === 'Confirmados' 
                ? 'rgba(40, 167, 69, 0.7)' 
                : 'rgba(220, 53, 69, 0.7)';
            dataset.fill = false;
        }
    });
    
    agendamentosChart.update();
    
    // Atualizar texto do botão
    $('#toggle-agendamentos').text(
        novoTipo === 'bar' ? 'Alternar para Linhas' : 'Alternar para Barras'
    );
}

// Iniciar a verificação do jQuery quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    waitForjQuery();
});
</script>
</body>
</html>