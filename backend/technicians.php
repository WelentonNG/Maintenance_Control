<?php
/**
 * Gestão de Técnicos
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
    
    // Carregar usuários disponíveis para vincular
    $availableUsers = $db->query("
        SELECT u.id, u.name, u.email 
        FROM users u 
        LEFT JOIN technicians t ON u.id = t.user_id
        WHERE t.id IS NULL
        ORDER BY u.name
    ")->fetchAll();
    
    // Processar formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $employee_code = sanitize($_POST['employee_code'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');
        $certification = sanitize($_POST['certification'] ?? '');
        $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
        $hourly_rate = !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null;
        $status = sanitize($_POST['status'] ?? 'available');
        
        // Para novo técnico, criar usuário também se necessário
        if ($action === 'new' && empty($user_id)) {
            $user_name = sanitize($_POST['user_name'] ?? '');
            $user_email = sanitize($_POST['user_email'] ?? '');
            $user_phone = sanitize($_POST['user_phone'] ?? '');
            $user_password = $_POST['user_password'] ?? '';
            
            if (empty($user_name) || empty($user_email) || empty($user_password)) {
                $error = 'Nome, email e senha são obrigatórios para criar novo técnico.';
            } else {
                // Verificar email duplicado
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$user_email]);
                if ($stmt->fetch()) {
                    $error = 'Este email já está cadastrado.';
                } else {
                    // Criar usuário
                    $hashedPassword = password_hash($user_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'technician', 'active')");
                    $stmt->execute([$user_name, $user_email, $user_phone, $hashedPassword]);
                    $user_id = $db->lastInsertId();
                }
            }
        }
        
        if (empty($error) && $user_id) {
            if (empty($employee_code)) {
                $employee_code = 'TEC-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            if ($action === 'edit' && $id > 0) {
                $sql = "UPDATE technicians SET 
                        specialization = ?, certification = ?, hire_date = ?, 
                        hourly_rate = ?, status = ?
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$specialization, $certification, $hire_date, $hourly_rate, $status, $id]);
                
                logAction('update', 'technicians', $id);
                setFlashMessage('success', 'Técnico atualizado com sucesso!');
            } else {
                $sql = "INSERT INTO technicians 
                        (user_id, employee_code, specialization, certification, hire_date, hourly_rate, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$user_id, $employee_code, $specialization, $certification, $hire_date, $hourly_rate, $status]);
                
                $newId = $db->lastInsertId();
                logAction('create', 'technicians', $newId);
                setFlashMessage('success', 'Técnico cadastrado com sucesso!');
            }
            
            redirect('technicians.php');
        }
    }
    
    // Excluir
    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM technicians WHERE id = ?");
        $stmt->execute([$id]);
        logAction('delete', 'technicians', $id);
        setFlashMessage('success', 'Técnico excluído com sucesso!');
        redirect('technicians.php');
    }
    
    // Carregar técnico para edição
    $technician = null;
    if (($action === 'edit' || $action === 'view') && $id > 0) {
        $stmt = $db->prepare("
            SELECT t.*, u.name, u.email, u.phone
            FROM technicians t
            JOIN users u ON t.user_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $technician = $stmt->fetch();
        
        if (!$technician) {
            setFlashMessage('error', 'Técnico não encontrado.');
            redirect('technicians.php');
        }
    }
    
    // Listar técnicos
    $search = sanitize($_GET['search'] ?? '');
    $filterStatus = sanitize($_GET['status'] ?? '');
    
    $sql = "SELECT t.*, u.name, u.email, u.phone,
                   (SELECT COUNT(*) FROM maintenance_orders WHERE technician_id = t.id AND status IN ('pending', 'in_progress')) as active_orders
            FROM technicians t
            JOIN users u ON t.user_id = u.id
            WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (u.name LIKE ? OR t.employee_code LIKE ? OR t.specialization LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($filterStatus) {
        $sql .= " AND t.status = ?";
        $params[] = $filterStatus;
    }
    
    $sql .= " ORDER BY u.name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $technicians = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao acessar banco de dados.';
    $technicians = [];
    $availableUsers = [];
}

// Agora incluir o header após processar todas as ações
$page_title = 'Técnicos';
require_once __DIR__ . '/../includes/header.php';

$statusLabels = [
    'available' => 'Disponível',
    'busy' => 'Ocupado',
    'on_leave' => 'Ausente',
    'inactive' => 'Inativo'
];
?>

<?php if ($action === 'list'): ?>
<div class="page-header">
    <h1>Técnicos</h1>
    <div class="page-actions">
        <a href="technicians.php?action=new" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Novo Técnico
        </a>
    </div>
</div>

<div class="filters-bar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Buscar técnicos..." value="<?php echo htmlspecialchars($search); ?>" 
               onkeypress="if(event.key==='Enter') window.location.href='technicians.php?search='+this.value">
    </div>
    
    <select class="form-control filter-select" onchange="window.location.href='technicians.php?status='+this.value">
        <option value="">Todos os Status</option>
        <?php foreach ($statusLabels as $key => $label): ?>
        <option value="<?php echo $key; ?>" <?php echo $filterStatus === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($technicians)): ?>
        <div class="empty-state">
            <div style="font-size: 6rem; color: #94a3b8; margin-bottom: 1.5rem; line-height: 1;">
                <i class="fas fa-user-gear"></i>
            </div>
            <h3 style="font-size: 1.5rem; color: #475569; margin-bottom: 0.5rem;">Nenhum técnico encontrado</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Cadastre seu primeiro técnico.</p>
            <a href="technicians.php?action=new" class="btn btn-primary">
                <i class="fas fa-plus"></i> Novo Técnico
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Especialização</th>
                        <th>Contato</th>
                        <th>Ordens Ativas</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($technicians as $tech): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($tech['employee_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($tech['name']); ?></td>
                        <td><?php echo htmlspecialchars($tech['specialization'] ?? '-'); ?></td>
                        <td>
                            <?php echo htmlspecialchars($tech['email']); ?>
                            <?php if ($tech['phone']): ?>
                            <br><small><?php echo htmlspecialchars($tech['phone']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $tech['active_orders'] > 0 ? 'badge-warning' : 'badge-secondary'; ?>">
                                <?php echo $tech['active_orders']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="technician-status <?php echo $tech['status']; ?>">
                                <?php echo $statusLabels[$tech['status']] ?? $tech['status']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="technicians.php?action=view&id=<?php echo $tech['id']; ?>" class="action-btn view">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="technicians.php?action=edit&id=<?php echo $tech['id']; ?>" class="action-btn edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="technicians.php?action=delete&id=<?php echo $tech['id']; ?>" class="action-btn delete" onclick="return confirm('Tem certeza?')">
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
<div class="page-header">
    <h1><?php echo $action === 'edit' ? 'Editar Técnico' : 'Novo Técnico'; ?></h1>
    <div class="page-actions">
        <a href="technicians.php" class="btn btn-outline">
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
            <?php if ($action === 'new'): ?>
            <h4 class="mb-3">Dados do Usuário</h4>
            
            <?php if (!empty($availableUsers)): ?>
            <div class="form-group">
                <label class="form-label">Vincular a usuário existente</label>
                <select name="user_id" class="form-control form-select" id="existingUser">
                    <option value="">Criar novo usuário</option>
                    <?php foreach ($availableUsers as $user): ?>
                    <option value="<?php echo $user['id']; ?>">
                        <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div id="newUserFields">
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="user_name" class="form-control" placeholder="Nome completo">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="user_email" class="form-control" placeholder="email@exemplo.com">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="user_phone" class="form-control" placeholder="(00) 00000-0000">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Senha *</label>
                            <input type="password" name="user_password" class="form-control" placeholder="Senha de acesso">
                        </div>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            <?php else: ?>
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle"></i>
                <span>Técnico: <strong><?php echo htmlspecialchars($technician['name']); ?></strong> (<?php echo htmlspecialchars($technician['email']); ?>)</span>
            </div>
            <?php endif; ?>
            
            <h4 class="mb-3">Dados Profissionais</h4>
            
            <div class="row">
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Código do Funcionário</label>
                        <input type="text" name="employee_code" class="form-control" 
                               value="<?php echo htmlspecialchars($technician['employee_code'] ?? 'TEC-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT)); ?>"
                               <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                    </div>
                </div>
                <div class="col-8">
                    <div class="form-group">
                        <label class="form-label">Especialização</label>
                        <input type="text" name="specialization" class="form-control" 
                               value="<?php echo htmlspecialchars($technician['specialization'] ?? ''); ?>"
                               placeholder="Ex: Elétrica, Mecânica, HVAC...">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Certificações</label>
                <textarea name="certification" class="form-control" rows="2" 
                          placeholder="Liste as certificações do técnico"><?php echo htmlspecialchars($technician['certification'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Data de Contratação</label>
                        <input type="date" name="hire_date" class="form-control" 
                               value="<?php echo $technician['hire_date'] ?? ''; ?>">
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Valor/Hora (R$)</label>
                        <input type="number" name="hourly_rate" class="form-control" step="0.01" min="0"
                               value="<?php echo $technician['hourly_rate'] ?? ''; ?>" placeholder="0.00">
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control form-select">
                            <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($technician['status'] ?? 'available') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $action === 'edit' ? 'Salvar Alterações' : 'Cadastrar'; ?>
                </button>
                <a href="technicians.php" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('existingUser')?.addEventListener('change', function() {
    const newUserFields = document.getElementById('newUserFields');
    if (this.value) {
        newUserFields.style.display = 'none';
        newUserFields.querySelectorAll('input').forEach(i => i.required = false);
    } else {
        newUserFields.style.display = 'block';
    }
});
</script>

<?php elseif ($action === 'view' && $technician): ?>
<div class="page-header">
    <h1><?php echo htmlspecialchars($technician['name']); ?></h1>
    <div class="page-actions">
        <a href="technicians.php?action=edit&id=<?php echo $id; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i>
            Editar
        </a>
        <a href="technicians.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Voltar
        </a>
    </div>
</div>

<div class="row">
    <div class="col" style="flex: 2;">
        <div class="card">
            <div class="card-header">
                <h3>Informações do Técnico</h3>
                <span class="technician-status <?php echo $technician['status']; ?>">
                    <?php echo $statusLabels[$technician['status']] ?? $technician['status']; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-6">
                        <p><strong>Código:</strong> <?php echo htmlspecialchars($technician['employee_code']); ?></p>
                        <p><strong>Nome:</strong> <?php echo htmlspecialchars($technician['name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($technician['email']); ?></p>
                        <p><strong>Telefone:</strong> <?php echo htmlspecialchars($technician['phone'] ?? '-'); ?></p>
                    </div>
                    <div class="col-6">
                        <p><strong>Especialização:</strong> <?php echo htmlspecialchars($technician['specialization'] ?? '-'); ?></p>
                        <p><strong>Data Contratação:</strong> <?php echo $technician['hire_date'] ? formatDate($technician['hire_date']) : '-'; ?></p>
                        <p><strong>Valor/Hora:</strong> <?php echo $technician['hourly_rate'] ? formatCurrency($technician['hourly_rate']) : '-'; ?></p>
                    </div>
                </div>
                
                <?php if ($technician['certification']): ?>
                <h4>Certificações</h4>
                <p><?php echo nl2br(htmlspecialchars($technician['certification'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col">
        <div class="card">
            <div class="card-header">
                <h3>Estatísticas</h3>
            </div>
            <div class="card-body">
                <?php
                $stmt = $db->prepare("SELECT COUNT(*) FROM maintenance_orders WHERE technician_id = ?");
                $stmt->execute([$id]);
                $totalOrders = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) FROM maintenance_orders WHERE technician_id = ? AND status = 'completed'");
                $stmt->execute([$id]);
                $completedOrders = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) FROM maintenance_orders WHERE technician_id = ? AND status IN ('pending', 'in_progress')");
                $stmt->execute([$id]);
                $activeOrders = $stmt->fetchColumn();
                ?>
                <p><strong>Total de Ordens:</strong> <?php echo $totalOrders; ?></p>
                <p><strong>Ordens Ativas:</strong> <?php echo $activeOrders; ?></p>
                <p><strong>Ordens Concluídas:</strong> <?php echo $completedOrders; ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
