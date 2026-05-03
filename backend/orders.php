<?php
/**
 * Gestão de Ordens de Manutenção
 * Sistema de Controle de Manutenção
 */

require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

try {
    $db = getDB();
    
    // Carregar dados auxiliares
    $equipmentList = $db->query("SELECT id, code, name FROM equipment ORDER BY name")->fetchAll();
    $techniciansList = $db->query("
        SELECT t.id, u.name, t.specialization 
        FROM technicians t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY u.name
    ")->fetchAll();
    
    // Processar formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $order_number = sanitize($_POST['order_number'] ?? '');
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
        $technician_id = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
        $type = sanitize($_POST['type'] ?? 'corrective');
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $status = sanitize($_POST['status'] ?? 'pending');
        $scheduled_date = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
        $estimated_hours = !empty($_POST['estimated_hours']) ? (float)$_POST['estimated_hours'] : null;
        $cost = !empty($_POST['cost']) ? (float)$_POST['cost'] : 0;
        $solution = sanitize($_POST['solution'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if (empty($title)) {
            $error = 'O título é obrigatório.';
        } else {
            if ($action === 'edit' && $id > 0) {
                // Atualizar
                $sql = "UPDATE maintenance_orders SET 
                        title = ?, description = ?, equipment_id = ?, technician_id = ?,
                        type = ?, priority = ?, status = ?, scheduled_date = ?,
                        estimated_hours = ?, cost = ?, solution = ?, notes = ?
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $title, $description, $equipment_id, $technician_id,
                    $type, $priority, $status, $scheduled_date,
                    $estimated_hours, $cost, $solution, $notes, $id
                ]);
                
                // Atualizar datas baseado no status
                if ($status === 'in_progress') {
                    $db->prepare("UPDATE maintenance_orders SET start_date = NOW() WHERE id = ? AND start_date IS NULL")->execute([$id]);
                } elseif ($status === 'completed') {
                    $db->prepare("UPDATE maintenance_orders SET end_date = NOW() WHERE id = ?")->execute([$id]);
                    
                    // Notificar o solicitante que a ordem foi concluída
                    $stmt = $db->prepare("SELECT requester_id, order_number FROM maintenance_orders WHERE id = ?");
                    $stmt->execute([$id]);
                    $order_data = $stmt->fetch();
                    
                    if ($order_data && $order_data['requester_id']) {
                        require_once 'notifications.php';
                        createNotification(
                            $order_data['requester_id'],
                            'success',
                            'Ordem Concluída',
                            "A ordem #{$order_data['order_number']} foi concluída",
                            "orders.php?id={$id}"
                        );
                    }
                }
                
                logAction('update', 'maintenance_orders', $id);
                setFlashMessage('success', 'Ordem atualizada com sucesso!');
            } else {
                // Criar nova
                if (empty($order_number)) {
                    $prefix = getSetting('orders_prefix', 'OM');
                    // Garantir que o prefixo não seja vazio
                    if (empty($prefix)) $prefix = 'OM';
                    $order_number = $prefix . '-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                }
                
                $sql = "INSERT INTO maintenance_orders 
                        (order_number, title, description, equipment_id, technician_id, requester_id,
                         type, priority, status, scheduled_date, estimated_hours, cost, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $order_number, $title, $description, $equipment_id, $technician_id, $_SESSION['user_id'],
                    $type, $priority, $status, $scheduled_date, $estimated_hours, $cost, $notes
                ]);
                
                $newId = $db->lastInsertId();
                logAction('create', 'maintenance_orders', $newId);
                
                // Criar notificação para o técnico
                if ($technician_id) {
                    $stmt = $db->prepare("SELECT user_id FROM technicians WHERE id = ?");
                    $stmt->execute([$technician_id]);
                    $tech_user_id = $stmt->fetchColumn();
                    
                    if ($tech_user_id) {
                        require_once 'notifications.php';
                        $notif_type = $priority === 'urgent' ? 'warning' : 'maintenance';
                        createNotification(
                            $tech_user_id,
                            $notif_type,
                            'Nova Ordem de Serviço',
                            "Nova ordem atribuída: {$title}",
                            "orders.php?id={$newId}"
                        );
                    }
                }
                
                setFlashMessage('success', 'Ordem criada com sucesso!');
            }
            
            redirect('orders.php');
        }
    }
    
    // Excluir
    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM maintenance_orders WHERE id = ?");
        $stmt->execute([$id]);
        logAction('delete', 'maintenance_orders', $id);
        setFlashMessage('success', 'Ordem excluída com sucesso!');
        redirect('orders.php');
    }
    
    // Carregar ordem para edição
    $order = null;
    if (($action === 'edit' || $action === 'view') && $id > 0) {
        $stmt = $db->prepare("
            SELECT mo.*, e.name as equipment_name, u.name as requester_name,
                   CONCAT(tu.name, ' - ', t.specialization) as technician_info
            FROM maintenance_orders mo
            LEFT JOIN equipment e ON mo.equipment_id = e.id
            LEFT JOIN users u ON mo.requester_id = u.id
            LEFT JOIN technicians t ON mo.technician_id = t.id
            LEFT JOIN users tu ON t.user_id = tu.id
            WHERE mo.id = ?
        ");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            setFlashMessage('error', 'Ordem não encontrada.');
            redirect('orders.php');
        }
    }
    
    // Listar ordens
    $search = sanitize($_GET['search'] ?? '');
    $filterStatus = sanitize($_GET['status'] ?? '');
    $filterType = sanitize($_GET['type'] ?? '');
    $filterPriority = sanitize($_GET['priority'] ?? '');
    
    $sql = "SELECT mo.*, e.name as equipment_name, 
                   tu.name as technician_name
            FROM maintenance_orders mo
            LEFT JOIN equipment e ON mo.equipment_id = e.id
            LEFT JOIN technicians t ON mo.technician_id = t.id
            LEFT JOIN users tu ON t.user_id = tu.id
            WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (mo.order_number LIKE ? OR mo.title LIKE ? OR e.name LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($filterStatus) {
        $sql .= " AND mo.status = ?";
        $params[] = $filterStatus;
    }
    
    if ($filterType) {
        $sql .= " AND mo.type = ?";
        $params[] = $filterType;
    }
    
    if ($filterPriority) {
        $sql .= " AND mo.priority = ?";
        $params[] = $filterPriority;
    }
    
    $sql .= " ORDER BY mo.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao acessar banco de dados.';
    $orders = [];
    $equipmentList = [];
    $techniciansList = [];
}

// Agora incluir o header após processar todas as ações
$page_title = 'Ordens de Serviço';
require_once __DIR__ . '/../includes/header.php';

// Arrays de tradução
$typeLabels = [
    'preventive' => 'Preventiva',
    'corrective' => 'Corretiva',
    'predictive' => 'Preditiva',
    'emergency' => 'Emergência'
];

$priorityLabels = [
    'low' => 'Baixa',
    'medium' => 'Média',
    'high' => 'Alta',
    'urgent' => 'Urgente'
];

$statusLabels = [
    'pending' => 'Pendente',
    'in_progress' => 'Em Andamento',
    'waiting_parts' => 'Aguardando Peças',
    'completed' => 'Concluída',
    'cancelled' => 'Cancelada'
];
?>

<?php if ($action === 'list'): ?>
<!-- Lista de Ordens -->
<div class="page-header">
    <h1>Ordens de Serviço</h1>
    <div class="page-actions">
        <a href="orders.php?action=new" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Nova Ordem
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="filters-bar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Buscar ordens..." value="<?php echo htmlspecialchars($search); ?>" 
               onkeypress="if(event.key==='Enter') window.location.href='orders.php?search='+this.value">
    </div>
    
    <select class="form-control filter-select" onchange="window.location.href='orders.php?status='+this.value+'&search=<?php echo urlencode($search); ?>'">
        <option value="">Todos os Status</option>
        <?php foreach ($statusLabels as $key => $label): ?>
        <option value="<?php echo $key; ?>" <?php echo $filterStatus === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
    </select>
    
    <select class="form-control filter-select" onchange="window.location.href='orders.php?type='+this.value+'&search=<?php echo urlencode($search); ?>'">
        <option value="">Todos os Tipos</option>
        <?php foreach ($typeLabels as $key => $label): ?>
        <option value="<?php echo $key; ?>" <?php echo $filterType === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
    </select>
    
    <select class="form-control filter-select" onchange="window.location.href='orders.php?priority='+this.value+'&search=<?php echo urlencode($search); ?>'">
        <option value="">Todas Prioridades</option>
        <?php foreach ($priorityLabels as $key => $label): ?>
        <option value="<?php echo $key; ?>" <?php echo $filterPriority === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Tabela -->
<div class="card">
    <div class="card-body">
        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <div style="font-size: 6rem; color: #94a3b8; margin-bottom: 1.5rem; line-height: 1;">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <h3 style="font-size: 1.5rem; color: #475569; margin-bottom: 0.5rem;">Nenhuma ordem encontrada</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Crie sua primeira ordem de manutenção.</p>
            <a href="orders.php?action=new" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nova Ordem
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Título</th>
                        <th>Equipamento</th>
                        <th>Técnico</th>
                        <th>Tipo</th>
                        <th>Prioridade</th>
                        <th>Status</th>
                        <th>Custo</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($o['order_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($o['title']); ?></td>
                        <td><?php echo htmlspecialchars($o['equipment_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($o['technician_name'] ?? '-'); ?></td>
                        <td>
                            <span class="badge badge-info"><?php echo $typeLabels[$o['type']] ?? $o['type']; ?></span>
                        </td>
                        <td>
                            <span class="priority <?php echo $o['priority']; ?>">
                                <?php echo $priorityLabels[$o['priority']] ?? $o['priority']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="order-status <?php echo $o['status']; ?>">
                                <?php echo $statusLabels[$o['status']] ?? $o['status']; ?>
                            </span>
                        </td>
                        <td><strong><?php echo formatCurrency($o['cost'] ?? 0); ?></strong></td>
                        <td><?php echo formatDate($o['created_at']); ?></td>
                        <td>
                            <div class="actions">
                                <a href="orders.php?action=view&id=<?php echo $o['id']; ?>" class="action-btn view" title="Visualizar">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="orders.php?action=edit&id=<?php echo $o['id']; ?>" class="action-btn edit" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="orders.php?action=delete&id=<?php echo $o['id']; ?>" class="action-btn delete" title="Excluir" onclick="return confirm('Tem certeza?')">
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

<?php elseif ($action === 'new' || $action === 'edit'): ?>
<!-- Formulário -->
<div class="page-header">
    <h1><?php echo $action === 'edit' ? 'Editar Ordem' : 'Nova Ordem de Serviço'; ?></h1>
    <div class="page-actions">
        <a href="orders.php" class="btn btn-outline">
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
                        <label class="form-label">Número da Ordem</label>
                        <?php 
                        if (!isset($order['order_number'])) {
                            $prefix = getSetting('orders_prefix', 'OM');
                            if (empty($prefix)) $prefix = 'OM';
                            $defaultOrderNumber = $prefix . '-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                        } else {
                            $defaultOrderNumber = $order['order_number'];
                        }
                        ?>
                        <input type="text" name="order_number" class="form-control" 
                               value="<?php echo htmlspecialchars($defaultOrderNumber); ?>" 
                               <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Título *</label>
                        <input type="text" name="title" class="form-control" required
                               value="<?php echo htmlspecialchars($order['title'] ?? ''); ?>"
                               placeholder="Descrição breve do serviço">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea name="description" class="form-control" rows="3" 
                          placeholder="Descrição detalhada do problema ou serviço"><?php echo htmlspecialchars($order['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Equipamento</label>
                        <select name="equipment_id" class="form-control form-select">
                            <option value="">Selecione um equipamento</option>
                            <?php foreach ($equipmentList as $eq): ?>
                            <option value="<?php echo $eq['id']; ?>" <?php echo ($order['equipment_id'] ?? '') == $eq['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($eq['code'] . ' - ' . $eq['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Técnico Responsável</label>
                        <select name="technician_id" class="form-control form-select">
                            <option value="">Selecione um técnico</option>
                            <?php foreach ($techniciansList as $tech): ?>
                            <option value="<?php echo $tech['id']; ?>" <?php echo ($order['technician_id'] ?? '') == $tech['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tech['name'] . ' - ' . $tech['specialization']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-3">
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select name="type" class="form-control form-select">
                            <?php foreach ($typeLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($order['type'] ?? 'corrective') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-3">
                    <div class="form-group">
                        <label class="form-label">Prioridade</label>
                        <select name="priority" class="form-control form-select">
                            <?php foreach ($priorityLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($order['priority'] ?? 'medium') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-3">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control form-select">
                            <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($order['status'] ?? 'pending') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-3">
                    <div class="form-group">
                        <label class="form-label">Data Agendada</label>
                        <input type="date" name="scheduled_date" class="form-control" 
                               value="<?php echo $order['scheduled_date'] ?? ''; ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Horas Estimadas</label>
                        <input type="number" name="estimated_hours" class="form-control" step="0.5" min="0"
                               value="<?php echo $order['estimated_hours'] ?? ''; ?>" placeholder="Ex: 2.5">
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Custo (R$)</label>
                        <input type="number" name="cost" class="form-control" step="0.01" min="0"
                               value="<?php echo $order['cost'] ?? ''; ?>" placeholder="Ex: 150.00">
                    </div>
                </div>
            </div>
            
            <?php if ($action === 'edit'): ?>
            <div class="form-group">
                <label class="form-label">Solução</label>
                <textarea name="solution" class="form-control" rows="3" 
                          placeholder="Descreva a solução aplicada"><?php echo htmlspecialchars($order['solution'] ?? ''); ?></textarea>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label class="form-label">Observações</label>
                <textarea name="notes" class="form-control" rows="2" 
                          placeholder="Observações adicionais"><?php echo htmlspecialchars($order['notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $action === 'edit' ? 'Salvar Alterações' : 'Criar Ordem'; ?>
                </button>
                <a href="orders.php" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'view' && $order): ?>
<!-- Visualização -->
<div class="page-header">
    <h1>Ordem <?php echo htmlspecialchars($order['order_number']); ?></h1>
    <div class="page-actions">
        <button onclick="window.print()" class="btn btn-success">
            <i class="fas fa-print"></i>
            Imprimir
        </button>
        <a href="orders.php?action=edit&id=<?php echo $id; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i>
            Editar
        </a>
        <a href="orders.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Voltar
        </a>
    </div>
</div>

<div class="print-content">
<div class="print-header">
    <div class="print-logo">
        <h1><?php echo SITE_NAME; ?></h1>
        <p>Ordem de Serviço</p>
    </div>
    <div class="print-order-number">
        <h2><?php echo htmlspecialchars($order['order_number']); ?></h2>
        <p><?php echo formatDate($order['created_at'], 'd/m/Y'); ?></p>
    </div>
</div>

<div class="row">
    <div class="col" style="flex: 2;">
        <div class="card">
            <div class="card-header">
                <h3><?php echo htmlspecialchars($order['title']); ?></h3>
                <span class="order-status <?php echo $order['status']; ?>">
                    <?php echo $statusLabels[$order['status']] ?? $order['status']; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-6">
                        <p><strong>Tipo:</strong> <?php echo $typeLabels[$order['type']] ?? $order['type']; ?></p>
                        <p><strong>Prioridade:</strong> <span class="priority <?php echo $order['priority']; ?>"><?php echo $priorityLabels[$order['priority']] ?? $order['priority']; ?></span></p>
                        <p><strong>Equipamento:</strong> <?php echo htmlspecialchars($order['equipment_name'] ?? '-'); ?></p>
                    </div>
                    <div class="col-6">
                        <p><strong>Técnico:</strong> <?php echo htmlspecialchars($order['technician_info'] ?? '-'); ?></p>
                        <p><strong>Solicitante:</strong> <?php echo htmlspecialchars($order['requester_name'] ?? '-'); ?></p>
                        <p><strong>Data Agendada:</strong> <?php echo $order['scheduled_date'] ? formatDate($order['scheduled_date']) : '-'; ?></p>
                    </div>
                </div>
                
                <h4>Descrição</h4>
                <p><?php echo nl2br(htmlspecialchars($order['description'] ?? 'Sem descrição.')); ?></p>
                
                <?php if ($order['solution']): ?>
                <h4 class="mt-4">Solução</h4>
                <p><?php echo nl2br(htmlspecialchars($order['solution'])); ?></p>
                <?php endif; ?>
                
                <?php if ($order['notes']): ?>
                <h4 class="mt-4">Observações</h4>
                <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col">
        <div class="card">
            <div class="card-header">
                <h3>Informações</h3>
            </div>
            <div class="card-body">
                <p><strong>Criado em:</strong><br><?php echo formatDate($order['created_at'], 'd/m/Y H:i'); ?></p>
                <p><strong>Atualizado em:</strong><br><?php echo formatDate($order['updated_at'], 'd/m/Y H:i'); ?></p>
                <?php if ($order['start_date']): ?>
                <p><strong>Iniciado em:</strong><br><?php echo formatDate($order['start_date'], 'd/m/Y H:i'); ?></p>
                <?php endif; ?>
                <?php if ($order['end_date']): ?>
                <p><strong>Concluído em:</strong><br><?php echo formatDate($order['end_date'], 'd/m/Y H:i'); ?></p>
                <?php endif; ?>
                <p><strong>Horas Estimadas:</strong> <?php echo $order['estimated_hours'] ?? '-'; ?></p>
                <p><strong>Horas Reais:</strong> <?php echo $order['actual_hours'] ?? '-'; ?></p>
                <p><strong>Custo:</strong> <?php echo formatCurrency($order['cost'] ?? 0); ?></p>
            </div>
        </div>
    </div>
</div>
</div>
<?php endif; ?>

<style>
@media print {
    .sidebar, .page-header, .navbar, .actions, .filters-bar, footer {
        display: none !important;
    }
    
    @page {
        size: A4;
        margin: 15mm;
    }
    
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    body {
        background: white !important;
        margin: 0;
        padding: 0;
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 10pt;
        color: #1a1a1a;
        line-height: 1.5;
    }
    
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    /* Cabeçalho Simples e Elegante */
    .print-header {
        display: flex !important;
        justify-content: space-between;
        align-items: center;
        padding: 18px 0;
        margin-bottom: 20px;
        border-bottom: 3px solid #2563eb;
    }
    
    .print-logo h1 {
        margin: 0;
        font-size: 18pt;
        color: #2563eb !important;
        font-weight: 700;
    }
    
    .print-logo p {
        margin: 4px 0 0 0;
        font-size: 10pt;
        color: #666 !important;
    }
    
    .print-order-number {
        text-align: right;
    }
    
    .print-order-number h2 {
        margin: 0;
        font-size: 16pt;
        color: #1a1a1a !important;
        font-weight: 700;
    }
    
    .print-order-number p {
        margin: 4px 0 0 0;
        font-size: 9pt;
        color: #888 !important;
    }
    
    /* Layout em Grid */
    .row {
        display: grid;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .row:not(.mb-4) {
        grid-template-columns: 1.6fr 1fr;
    }
    
    .row.mb-4 {
        grid-template-columns: 1fr 1fr;
        margin-bottom: 12px !important;
    }
    
    /* Cards Limpos */
    .card {
        border: 1px solid #e0e0e0 !important;
        border-radius: 8px !important;
        overflow: hidden;
        background: white;
        break-inside: avoid;
    }
    
    .card-header {
        background: #f8f9fa !important;
        border-bottom: 2px solid #e0e0e0 !important;
        padding: 12px 16px !important;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .card-header h3 {
        margin: 0;
        font-size: 12pt;
        color: #1a1a1a !important;
        font-weight: 600;
    }
    
    .card-body {
        padding: 16px !important;
    }
    
    .card-body p {
        margin: 0 0 10px 0;
        display: flex;
        gap: 10px;
    }
    
    .card-body p strong {
        color: #4a4a4a;
        min-width: 120px;
        font-weight: 600;
        flex-shrink: 0;
    }
    
    .card-body h4 {
        margin: 16px 0 10px 0;
        font-size: 11pt;
        color: #2563eb !important;
        font-weight: 600;
        padding-bottom: 6px;
        border-bottom: 2px solid #e0e0e0;
    }
    
    /* Badges Modernos */
    .order-status, .priority, .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 9pt;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .order-status.pending { 
        background: #fef3c7 !important; 
        color: #92400e !important;
        border: 1px solid #fbbf24 !important;
    }
    .order-status.in_progress { 
        background: #dbeafe !important; 
        color: #1e40af !important;
        border: 1px solid #3b82f6 !important;
    }
    .order-status.completed { 
        background: #d1fae5 !important; 
        color: #065f46 !important;
        border: 1px solid #10b981 !important;
    }
    .order-status.cancelled { 
        background: #fee2e2 !important; 
        color: #991b1b !important;
        border: 1px solid #ef4444 !important;
    }
    
    .priority.low { 
        background: #dbeafe !important; 
        color: #1e40af !important;
        border: 1px solid #3b82f6 !important;
    }
    .priority.medium { 
        background: #fef3c7 !important; 
        color: #92400e !important;
        border: 1px solid #fbbf24 !important;
    }
    .priority.high { 
        background: #fed7aa !important; 
        color: #9a3412 !important;
        border: 1px solid #fb923c !important;
    }
    .priority.urgent { 
        background: #fee2e2 !important; 
        color: #991b1b !important;
        border: 1px solid #ef4444 !important;
    }
    
    .badge-info {
        background: #cffafe !important;
        color: #0e7490 !important;
        border: 1px solid #06b6d4 !important;
    }
    
    /* Caixas de Informação */
    .col-6 {
        background: #fafafa;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid #e0e0e0;
    }
    
    .col-6 p {
        margin-bottom: 8px;
    }
    
    /* Textos */
    .card-body > p:not(:has(strong)) {
        text-align: justify;
        line-height: 1.6;
        padding: 12px;
        background: #fafafa;
        border-radius: 6px;
        border: 1px solid #e0e0e0;
        display: block;
        margin: 10px 0;
    }
    
    .mt-4 {
        margin-top: 10px !important;
    }
    
    /* Rodapé */
    .print-content::after {
        content: "Gerado em <?php echo date('d/m/Y H:i'); ?> • Sistema de Controle de Manutenção";
        display: block;
        text-align: center;
        font-size: 8pt;
        color: #999;
        margin-top: 25px;
        padding-top: 15px;
        border-top: 1px solid #e0e0e0;
    }
}

@media screen {
    .print-header {
        display: none;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
