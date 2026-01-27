<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel de Agendamentos - <?php echo config('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css' rel='stylesheet' />
    <style>
        body { background-color: #f4f7f6; }
        .sidebar { background: #2c3e50; color: #fff; min-height: 100vh; padding: 20px; }
        .sidebar a { color: #bdc3c7; text-decoration: none; display: block; padding: 10px; border-radius: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #34495e; color: #fff; }
        .main-content { padding: 30px; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        #calendar { background: #fff; padding: 20px; border-radius: 10px; }
        .modal-header { background: #3498db; color: #fff; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="text-center mb-4">
                    <img src="images/logo1.png" alt="Logo" style="max-width: 120px;" onerror="this.src='https://via.placeholder.com/120x40?text=OAB/SC'">
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item"><a href="index.php" class="active"><i class="fas fa-calendar-alt me-2"></i> Painel</a></li>
                    <li class="nav-item"><a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sair</a></li>
                </ul>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Painel de Agendamentos</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="me-3">Olá, <strong><?php echo sanitize($usuario['nome']); ?></strong></span>
                    </div>
                </div>

                <?php if (isset($_GET['status'])): ?>
                    <?php if ($_GET['status'] === 'sucesso'): ?><div class="alert alert-success">Agendamento realizado com sucesso!</div><?php endif; ?>
                    <?php if ($_GET['status'] === 'horario_ocupado'): ?><div class="alert alert-danger">Este horário já foi ocupado.</div><?php endif; ?>
                    <?php if ($_GET['status'] === 'limite_excedido'): ?><div class="alert alert-warning">Você atingiu o limite mensal de agendamentos.</div><?php endif; ?>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-9">
                        <div class="card">
                            <div class="card-body">
                                <div id="calendar"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card mb-4">
                            <div class="card-header bg-white fw-bold">Próximos Agendamentos</div>
                            <div class="card-body">
                                <?php if (empty($proximos)): ?>
                                    <p class="text-muted">Nenhum agendamento futuro.</p>
                                <?php else: ?>
                                    <?php foreach ($proximos as $ag): ?>
                                        <div class="border-start border-primary border-4 p-2 mb-2 bg-light">
                                            <div class="fw-bold"><?php echo formatDate($ag['data_agendamento']); ?></div>
                                            <small><?php echo formatTime($ag['hora_inicio']); ?> - <?php echo sanitize($ag['motivo']); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Agendamento -->
    <div class="modal fade" id="agendamentoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="agendar.php" method="POST">
                    <?php echo \App\Middleware\CsrfMiddleware::field(); ?>
                    <input type="hidden" name="data_agendamento" id="modal_data">
                    <input type="hidden" name="hora_inicio" id="modal_hora">
                    <div class="modal-header">
                        <h5 class="modal-title">Novo Agendamento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Data: <strong id="display_data"></strong> às <strong id="display_hora"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">Motivo</label>
                            <select name="motivo" class="form-select" required>
                                <option value="Atendimento">Atendimento</option>
                                <option value="Problemas com Senha">Problemas com Senha</option>
                                <option value="Outros">Outros</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Telefone de Contato</label>
                            <input type="tel" name="telefone_contato" class="form-control" value="<?php echo sanitize($usuario['telefone']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observação</label>
                            <textarea name="observacao" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar Agendamento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var modal = new bootstrap.Modal(document.getElementById('agendamentoModal'));
            
            var calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'pt-br',
                initialView: 'timeGridWeek',
                slotMinTime: '08:00:00',
                slotMaxTime: '12:30:00',
                allDaySlot: false,
                weekends: false,
                selectable: true,
                events: 'api_eventos.php',
                select: function(info) {
                    var data = info.startStr.split('T')[0];
                    var hora = info.startStr.split('T')[1].substring(0, 8);
                    
                    document.getElementById('modal_data').value = data;
                    document.getElementById('modal_hora').value = hora;
                    document.getElementById('display_data').innerText = data.split('-').reverse().join('/');
                    document.getElementById('display_hora').innerText = hora.substring(0, 5);
                    
                    modal.show();
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>
