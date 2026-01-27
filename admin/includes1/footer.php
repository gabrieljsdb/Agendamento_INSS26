<?php
session_start();
require '../conexao.php';

// A verificação de login agora está no header.php, mas mantemos aqui por segurança
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}

// Filtros da URL
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$status_filtro = $_GET['status'] ?? 'Todos';

$sql = "SELECT
            a.id, a.data_agendamento, a.hora_inicio, a.status, a.motivo, a.observacao, a.motivo_cancelamento,
            u.nome, u.email, u.oab, u.cpf_oab
        FROM agendamentos a
        JOIN usuarios u ON a.id_usuario = u.id";

$where_clauses = [];
$params = [];

if ($data_inicio && $data_fim) {
    $where_clauses[] = "a.data_agendamento BETWEEN ? AND ?";
    $params[] = $data_inicio;
    $params[] = $data_fim;
}

if ($status_filtro && $status_filtro !== 'Todos') {
    $where_clauses[] = "a.status = ?";
    $params[] = $status_filtro;
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY a.data_agendamento DESC, a.hora_inicio DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$todos_agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inclui o cabeçalho do template
require 'includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h3 class="title-5 m-b-35">Monitoramento de Agendamentos</h3>
        
        <div class="card">
            <div class="card-header">Filtros e Ações</div>
            <div class="card-body">
                <form method="GET" action="dashboard.php" class="form-inline">
                    <div class="form-group mx-sm-3 mb-2">
                        <label for="data_inicio" class="mr-2">De:</label>
                        <input type="date" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>" class="form-control">
                    </div>
                    <div class="form-group mx-sm-3 mb-2">
                        <label for="data_fim" class="mr-2">Até:</label>
                        <input type="date" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>" class="form-control">
                    </div>
                    <div class="form-group mx-sm-3 mb-2">
                        <label for="status" class="mr-2">Status:</label>
                        <select name="status" id="status" class="form-control">
                            <option value="Todos" <?php if ($status_filtro === 'Todos') echo 'selected'; ?>>Todos</option>
                            <option value="Confirmado" <?php if ($status_filtro === 'Confirmado') echo 'selected'; ?>>Confirmado</option>
                            <option value="Cancelado" <?php if ($status_filtro === 'Cancelado') echo 'selected'; ?>>Cancelado</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary mb-2">Filtrar</button>
                    <button type="submit" form="form-export" class="btn btn-info mb-2 ml-2">Exportar para CSV</button>
                </form>
                
                <form id="form-export" method="GET" action="exportar.php" target="_blank" class="d-none">
                    <input type="hidden" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>">
                    <input type="hidden" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filtro); ?>">
                </form>
            </div>
        </div>

        <div class="table-responsive table-responsive-data2 mt-4">
            <table class="table table-data2">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>OAB</th>
                        <th>Email</th>
                        <th>Motivo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todos_agendamentos as $ag): ?>
                    <tr class="tr-shadow">
                        <td><?php echo date('d/m/Y H:i', strtotime($ag['data_agendamento'] . ' ' . $ag['hora_inicio'])); ?></td>
                        <td><?php echo htmlspecialchars($ag['nome']); ?></td>
                        <td><?php echo htmlspecialchars($ag['cpf_oab']); ?></td>
                        <td><?php echo htmlspecialchars($ag['oab']); ?></td>
                        <td><?php echo htmlspecialchars($ag['email']); ?></td>
                        <td><?php echo htmlspecialchars($ag['motivo']); ?></td>
                        <td>
                            <span class="<?php echo $ag['status'] === 'Confirmado' ? 'status--process' : 'status--denied'; ?>">
                                <?php echo $ag['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <tr class="spacer"></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Inclui o rodapé do template
require 'includes/footer.php';
?>