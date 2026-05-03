<?php
/**
 * Manutenções Preventivas
 * Sistema de Controle de Manutenção
 */

require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

try {
    $db = getDB();
    
    $equipmentList = $db->query("SELECT id, code, name FROM equipment ORDER BY name")->fetchAll();
    $techniciansList = $db->query("
        SELECT t.id, u.name, t.specialization 
        FROM technicians t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY u.name
    ")->fetchAll();
    
    // Processar formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $equipment_id = (int)$_POST['equipment_id'];
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $frequency = sanitize($_POST['frequency'] ?? 'monthly');
        $next_execution = $_POST['next_execution'] ?? null;
        $assigned_technician = !empty($_POST['assigned_technician']) ? (int)$_POST['assigned_technician'] : null;
        $status = sanitize($_POST['status'] ?? 'active');
        
        if (empty($title) || empty($equipment_id)) {
            $error = 'Título e equipamento são obrigatórios.';
        } else {
            if ($action === 'edit' && $id > 0) {
                $sql = "UPDATE preventive_schedules SET 
                        equipment_id = ?, title = ?, description = ?, frequency = ?,
                        next_execution = ?, assigned_technician = ?, status = ?
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$equipment_id, $title, $description, $frequency, $next_execution, $assigned_technician, $status, $id]);
                logAction('update', 'preventive_schedules', $id);
                setFlashMessage('success', 'Programação atualizada com sucesso!');
            } else {
                $sql = "INSERT INTO preventive_schedules 
                        (equipment_id, title, description, frequency, next_execution, assigned_technician, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$equipment_id, $title, $description, $frequency, $next_execution, $assigned_technician, $status]);
                $newId = $db->lastInsertId();
                logAction('create', 'preventive_schedules', $newId);
                setFlashMessage('success', 'Programação criada com sucesso!');
            }
            redirect('preventive.php');
        }
    }
    
    // Excluir
    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM preventive_schedules WHERE id = ?");
        $stmt->execute([$id]);
        logAction('delete', 'preventive_schedules', $id);
        setFlashMessage('success', 'Programação excluída com sucesso!');
        redirect('preventive.php');
    }
    
    // Executar preventiva (criar ordem)
    if ($action === 'execute' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM preventive_schedules WHERE id = ?");
        $stmt->execute([$id]);
        $schedule = $stmt->fetch();
        
        if ($schedule) {
            // Criar ordem de manutenção
            $orderNumber = 'OM-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $sql = "INSERT INTO maintenance_orders 
                    (order_number, equipment_id, technician_id, requester_id, type, priority, status, title, description)
                    VALUES (?, ?, ?, ?, 'preventive', 'medium', 'pending', ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $orderNumber,
                $schedule['equipment_id'],
                $schedule['assigned_technician'],
                $_SESSION['user_id'],
                $schedule['title'],
                $schedule['description']
            ]);
            
            // Atualizar datas da programação
            $intervals = [
                'daily' => '+1 day',
                'weekly' => '+1 week',
                'biweekly' => '+2 weeks',
                'monthly' => '+1 month',
                'quarterly' => '+3 months',
                'semiannual' => '+6 months',
                'annual' => '+1 year'
            ];
            $nextDate = date('Y-m-d', strtotime($intervals[$schedule['frequency']] ?? '+1 month'));
            
            $db->prepare("UPDATE preventive_schedules SET last_execution = CURRENT_DATE(), next_execution = ? WHERE id = ?")->execute([$nextDate, $id]);
            
            setFlashMessage('success', 'Ordem de serviço criada: ' . $orderNumber);
        }
        redirect('preventive.php');
    }
    
    // Carregar para edição
    $schedule = null;
    if ($action === 'edit' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM preventive_schedules WHERE id = ?");
        $stmt->execute([$id]);
        $schedule = $stmt->fetch();
    }
    
    // Listar
    $filterStatus = sanitize($_GET['status'] ?? '');
    
    $sql = "SELECT ps.*, e.code as equipment_code, e.name as equipment_name,
                   u.name as technician_name
            FROM preventive_schedules ps
            LEFT JOIN equipment e ON ps.equipment_id = e.id
            LEFT JOIN technicians t ON ps.assigned_technician = t.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE 1=1";
    $params = [];
    
    if ($filterStatus) {
        $sql .= " AND ps.status = ?";
        $params[] = $filterStatus;
    }
    
    $sql .= " ORDER BY ps.next_execution";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao acessar banco de dados.';
    $schedules = [];
    $equipmentList = [];
    $techniciansList = [];
}

// Agora incluir o header após processar todas as ações
$page_title = 'Manutenções Preventivas';
require_once __DIR__ . '/../includes/header.php';

$frequencyLabels = [
    'daily' => 'Diária',
    'weekly' => 'Semanal',
    'biweekly' => 'Quinzenal',
    'monthly' => 'Mensal',
    'quarterly' => 'Trimestral',
    'semiannual' => 'Semestral',
    'annual' => 'Anual'
];
?>

<?php if ($action === 'list'): ?>
<div class="page-header">
    <h1>Manutenções Preventivas</h1>
    <div class="page-actions">
        <a href="preventive.php?action=new" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Nova Programação
        </a>
    </div>
</div>

<div class="filters-bar">
    <select class="form-control filter-select" onchange="window.location.href='preventive.php?status='+this.value">
        <option value="">Todos os Status</option>
        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Ativas</option>
        <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inativas</option>
    </select>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($schedules)): ?>
        <div class="empty-state">
            <div style="font-size: 6rem; color: #94a3b8; margin-bottom: 1.5rem; line-height: 1;">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h3 style="font-size: 1.5rem; color: #475569; margin-bottom: 0.5rem;">Nenhuma programação encontrada</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Crie sua primeira programação de manutenção preventiva.</p>
            <a href="preventive.php?action=new" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nova Programação
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Equipamento</th>
                        <th>Título</th>
                        <th>Frequência</th>
                        <th>Próxima Execução</th>
                        <th>Técnico</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $s): ?>
                    <?php 
                    $isOverdue = $s['next_execution'] && strtotime($s['next_execution']) < time();
                    ?>
                    <tr class="<?php echo $isOverdue && $s['status'] === 'active' ? 'table-warning' : ''; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($s['equipment_code']); ?></strong><br>
                            <small><?php echo htmlspecialchars($s['equipment_name']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($s['title']); ?></td>
                        <td><?php echo $frequencyLabels[$s['frequency']] ?? $s['frequency']; ?></td>
                        <td>
                            <?php if ($s['next_execution']): ?>
                                <?php if ($isOverdue): ?>
                                <span class="badge badge-danger"><?php echo formatDate($s['next_execution']); ?></span>
                                <?php else: ?>
                                <?php echo formatDate($s['next_execution']); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($s['technician_name'] ?? '-'); ?></td>
                        <td>
                            <span class="badge <?php echo $s['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $s['status'] === 'active' ? 'Ativa' : 'Inativa'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <?php if ($s['status'] === 'active'): ?>
                                <a href="preventive.php?action=execute&id=<?php echo $s['id']; ?>" class="action-btn view" title="Executar" onclick="return confirm('Criar ordem de serviço?')">
                                    <i class="fas fa-play"></i>
                                </a>
                                <?php endif; ?>
                                <a href="preventive.php?action=edit&id=<?php echo $s['id']; ?>" class="action-btn edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="preventive.php?action=delete&id=<?php echo $s['id']; ?>" class="action-btn delete" onclick="return confirm('Tem certeza?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- Formulário -->
<div class="page-header">
    <h1><?php echo $action === 'edit' ? 'Editar Programação' : 'Nova Programação'; ?></h1>
    <div class="page-actions">
        <a href="preventive.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Voltar
        </a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <span><?php echo $error; ?></span>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Equipamento *</label>
                        <select name="equipment_id" class="form-control form-select" required>
                            <option value="">Selecione</option>
                            <?php foreach ($equipmentList as $eq): ?>
                            <option value="<?php echo $eq['id']; ?>" <?php echo ($schedule['equipment_id'] ?? '') == $eq['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($eq['code'] . ' - ' . $eq['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Título *</label>
                        <input type="text" name="title" class="form-control" required
                               value="<?php echo htmlspecialchars($schedule['title'] ?? ''); ?>"
                               placeholder="Ex: Inspeção mensal">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($schedule['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Frequência</label>
                        <select name="frequency" class="form-control form-select">
                            <?php foreach ($frequencyLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($schedule['frequency'] ?? 'monthly') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Próxima Execução</label>
                        <input type="date" name="next_execution" class="form-control" 
                               value="<?php echo $schedule['next_execution'] ?? date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control form-select">
                            <option value="active" <?php echo ($schedule['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Ativa</option>
                            <option value="inactive" <?php echo ($schedule['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inativa</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Técnico Responsável</label>
                <select name="assigned_technician" class="form-control form-select">
                    <option value="">Nenhum</option>
                    <?php foreach ($techniciansList as $tech): ?>
                    <option value="<?php echo $tech['id']; ?>" <?php echo ($schedule['assigned_technician'] ?? '') == $tech['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tech['name'] . ' - ' . $tech['specialization']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $action === 'edit' ? 'Salvar Alterações' : 'Criar Programação'; ?>
                </button>
                <a href="preventive.php" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
