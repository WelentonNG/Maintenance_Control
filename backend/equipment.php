<?php
/**
 * Gestão de Equipamentos
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
    
    // Carregar categorias e localizações
    $categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
    $locations = $db->query("SELECT id, name, building, department FROM locations ORDER BY name")->fetchAll();
    
    // Processar formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = sanitize($_POST['code'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        $brand = sanitize($_POST['brand'] ?? '');
        $model = sanitize($_POST['model'] ?? '');
        $serial_number = sanitize($_POST['serial_number'] ?? '');
        $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
        $warranty_expiry = !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null;
        $status = sanitize($_POST['status'] ?? 'operational');
        $criticality = sanitize($_POST['criticality'] ?? 'medium');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if (empty($name) || empty($code)) {
            $error = 'Nome e código são obrigatórios.';
        } else {
            if ($action === 'edit' && $id > 0) {
                $sql = "UPDATE equipment SET 
                        code = ?, name = ?, description = ?, category_id = ?, location_id = ?,
                        brand = ?, model = ?, serial_number = ?, purchase_date = ?, warranty_expiry = ?,
                        status = ?, criticality = ?, notes = ?
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $code, $name, $description, $category_id, $location_id,
                    $brand, $model, $serial_number, $purchase_date, $warranty_expiry,
                    $status, $criticality, $notes, $id
                ]);
                
                logAction('update', 'equipment', $id);
                setFlashMessage('success', 'Equipamento atualizado com sucesso!');
            } else {
                // Verificar código duplicado
                $stmt = $db->prepare("SELECT id FROM equipment WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetch()) {
                    $error = 'Este código já está em uso.';
                } else {
                    $sql = "INSERT INTO equipment 
                            (code, name, description, category_id, location_id, brand, model, 
                             serial_number, purchase_date, warranty_expiry, status, criticality, notes)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        $code, $name, $description, $category_id, $location_id,
                        $brand, $model, $serial_number, $purchase_date, $warranty_expiry,
                        $status, $criticality, $notes
                    ]);
                    
                    $newId = $db->lastInsertId();
                    logAction('create', 'equipment', $newId);
                    setFlashMessage('success', 'Equipamento cadastrado com sucesso!');
                }
            }
            
            if (empty($error)) {
                redirect('equipment.php');
            }
        }
    }
    
    // Excluir
    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM equipment WHERE id = ?");
        $stmt->execute([$id]);
        logAction('delete', 'equipment', $id);
        setFlashMessage('success', 'Equipamento excluído com sucesso!');
        redirect('equipment.php');
    }
    
    // Carregar equipamento para edição
    $equipment = null;
    if (($action === 'edit' || $action === 'view') && $id > 0) {
        $stmt = $db->prepare("
            SELECT e.*, c.name as category_name, l.name as location_name,
                   l.building, l.department
            FROM equipment e
            LEFT JOIN categories c ON e.category_id = c.id
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $equipment = $stmt->fetch();
        
        if (!$equipment) {
            setFlashMessage('error', 'Equipamento não encontrado.');
            redirect('equipment.php');
        }
    }
    
    // Listar equipamentos
    $search = sanitize($_GET['search'] ?? '');
    $filterStatus = sanitize($_GET['status'] ?? '');
    $filterCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    
    $sql = "SELECT e.*, c.name as category_name, l.name as location_name
            FROM equipment e
            LEFT JOIN categories c ON e.category_id = c.id
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (e.code LIKE ? OR e.name LIKE ? OR e.brand LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($filterStatus) {
        $sql .= " AND e.status = ?";
        $params[] = $filterStatus;
    }
    
    if ($filterCategory) {
        $sql .= " AND e.category_id = ?";
        $params[] = $filterCategory;
    }
    
    $sql .= " ORDER BY e.name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $equipmentList = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao acessar banco de dados.';
    $equipmentList = [];
    $categories = [];
    $locations = [];
}

// Agora incluir o header após processar todas as ações
$page_title = 'Equipamentos';
require_once __DIR__ . '/../includes/header.php';

$statusLabels = [
    'operational' => 'Operacional',
    'maintenance' => 'Em Manutenção',
    'broken' => 'Avariado',
    'retired' => 'Desativado'
];

$criticalityLabels = [
    'low' => 'Baixa',
    'medium' => 'Média',
    'high' => 'Alta',
    'critical' => 'Crítica'
];
?>

<?php if ($action === 'list'): ?>
<!-- Lista -->
<div class="page-header">
    <h1>Equipamentos</h1>
    <div class="page-actions">
        <a href="equipment.php?action=new" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Novo Equipamento
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="filters-bar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Buscar equipamentos..." value="<?php echo htmlspecialchars($search); ?>" 
               onkeypress="if(event.key==='Enter') window.location.href='equipment.php?search='+this.value">
    </div>
    
    <select class="form-control filter-select" onchange="window.location.href='equipment.php?status='+this.value">
        <option value="">Todos os Status</option>
        <?php foreach ($statusLabels as $key => $label): ?>
        <option value="<?php echo $key; ?>" <?php echo $filterStatus === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
    </select>
    
    <select class="form-control filter-select" onchange="window.location.href='equipment.php?category='+this.value">
        <option value="">Todas Categorias</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?php echo $cat['id']; ?>" <?php echo $filterCategory == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Tabela -->
<div class="card">
    <div class="card-body">
        <?php if (empty($equipmentList)): ?>
        <div class="empty-state">
            <div style="font-size: 6rem; color: #94a3b8; margin-bottom: 1.5rem; line-height: 1;">
                <i class="fas fa-cogs"></i>
            </div>
            <h3 style="font-size: 1.5rem; color: #475569; margin-bottom: 0.5rem;">Nenhum equipamento encontrado</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Cadastre seu primeiro equipamento.</p>
            <a href="equipment.php?action=new" class="btn btn-primary">
                <i class="fas fa-plus"></i> Novo Equipamento
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Localização</th>
                        <th>Marca/Modelo</th>
                        <th>Status</th>
                        <th>Criticidade</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipmentList as $eq): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($eq['code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($eq['name']); ?></td>
                        <td><?php echo htmlspecialchars($eq['category_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($eq['location_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars(($eq['brand'] ?? '') . ' ' . ($eq['model'] ?? '')); ?></td>
                        <td>
                            <span class="equipment-status <?php echo $eq['status']; ?>">
                                <?php echo $statusLabels[$eq['status']] ?? $eq['status']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="priority <?php echo $eq['criticality']; ?>">
                                <?php echo $criticalityLabels[$eq['criticality']] ?? $eq['criticality']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="equipment.php?action=view&id=<?php echo $eq['id']; ?>" class="action-btn view" title="Visualizar">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="equipment.php?action=edit&id=<?php echo $eq['id']; ?>" class="action-btn edit" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="equipment.php?action=delete&id=<?php echo $eq['id']; ?>" class="action-btn delete" title="Excluir" onclick="return confirm('Tem certeza?')">
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
    <h1><?php echo $action === 'edit' ? 'Editar Equipamento' : 'Novo Equipamento'; ?></h1>
    <div class="page-actions">
        <a href="equipment.php" class="btn btn-outline">
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
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Código *</label>
                        <?php 
                        if (!isset($equipment['code'])) {
                            $prefix = getSetting('equipment_prefix', 'EQ');
                            if (empty($prefix)) $prefix = 'EQ';
                            $defaultCode = $prefix . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                        } else {
                            $defaultCode = $equipment['code'];
                        }
                        ?>
                        <input type="text" name="code" class="form-control" required
                               value="<?php echo htmlspecialchars($defaultCode); ?>"
                               <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                    </div>
                </div>
                <div class="col">
                    <div class="form-group">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?php echo htmlspecialchars($equipment['name'] ?? ''); ?>"
                               placeholder="Nome do equipamento">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($equipment['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <select name="category_id" class="form-control form-select">
                            <option value="">Selecione</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($equipment['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Localização</label>
                        <select name="location_id" class="form-control form-select">
                            <option value="">Selecione</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo $loc['id']; ?>" <?php echo ($equipment['location_id'] ?? '') == $loc['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['name'] . ' - ' . $loc['building']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Marca</label>
                        <input type="text" name="brand" class="form-control" 
                               value="<?php echo htmlspecialchars($equipment['brand'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Modelo</label>
                        <input type="text" name="model" class="form-control" 
                               value="<?php echo htmlspecialchars($equipment['model'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Número de Série</label>
                        <input type="text" name="serial_number" class="form-control" 
                               value="<?php echo htmlspecialchars($equipment['serial_number'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-3">
                    <div class="form-group">
                        <label class="form-label">Data de Compra</label>
                        <input type="date" name="purchase_date" class="form-control" 
                               value="<?php echo $equipment['purchase_date'] ?? ''; ?>">
                    </div>
                </div>
                <div class="col-3">
                    <div class="form-group">
                        <label class="form-label">Garantia até</label>
                        <input type="date" name="warranty_expiry" class="form-control" 
                               value="<?php echo $equipment['warranty_expiry'] ?? ''; ?>">
                    </div>
                </div>
                <div class="col-3">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control form-select">
                            <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($equipment['status'] ?? 'operational') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-3">
                    <div class="form-group">
                        <label class="form-label">Criticidade</label>
                        <select name="criticality" class="form-control form-select">
                            <?php foreach ($criticalityLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($equipment['criticality'] ?? 'medium') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Observações</label>
                <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($equipment['notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $action === 'edit' ? 'Salvar Alterações' : 'Cadastrar'; ?>
                </button>
                <a href="equipment.php" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'view' && $equipment): ?>
<!-- Visualização -->
<div class="page-header">
    <h1><?php echo htmlspecialchars($equipment['code'] . ' - ' . $equipment['name']); ?></h1>
    <div class="page-actions">
        <a href="orders.php?action=new&equipment_id=<?php echo $id; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i>
            Nova Ordem
        </a>
        <a href="equipment.php?action=edit&id=<?php echo $id; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i>
            Editar
        </a>
        <a href="equipment.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Voltar
        </a>
    </div>
</div>

<div class="row">
    <div class="col" style="flex: 2;">
        <div class="card">
            <div class="card-header">
                <h3>Informações do Equipamento</h3>
                <span class="equipment-status <?php echo $equipment['status']; ?>">
                    <?php echo $statusLabels[$equipment['status']] ?? $equipment['status']; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-6">
                        <p><strong>Código:</strong> <?php echo htmlspecialchars($equipment['code']); ?></p>
                        <p><strong>Nome:</strong> <?php echo htmlspecialchars($equipment['name']); ?></p>
                        <p><strong>Categoria:</strong> <?php echo htmlspecialchars($equipment['category_name'] ?? '-'); ?></p>
                        <p><strong>Localização:</strong> <?php echo htmlspecialchars($equipment['location_name'] ?? '-'); ?></p>
                    </div>
                    <div class="col-6">
                        <p><strong>Marca:</strong> <?php echo htmlspecialchars($equipment['brand'] ?? '-'); ?></p>
                        <p><strong>Modelo:</strong> <?php echo htmlspecialchars($equipment['model'] ?? '-'); ?></p>
                        <p><strong>Nº Série:</strong> <?php echo htmlspecialchars($equipment['serial_number'] ?? '-'); ?></p>
                        <p><strong>Criticidade:</strong> <span class="priority <?php echo $equipment['criticality']; ?>"><?php echo $criticalityLabels[$equipment['criticality']] ?? $equipment['criticality']; ?></span></p>
                    </div>
                </div>
                
                <?php if ($equipment['description']): ?>
                <h4>Descrição</h4>
                <p><?php echo nl2br(htmlspecialchars($equipment['description'])); ?></p>
                <?php endif; ?>
                
                <?php if ($equipment['notes']): ?>
                <h4 class="mt-4">Observações</h4>
                <p><?php echo nl2br(htmlspecialchars($equipment['notes'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col">
        <div class="card">
            <div class="card-header">
                <h3>Datas e Garantia</h3>
            </div>
            <div class="card-body">
                <p><strong>Data de Compra:</strong><br><?php echo $equipment['purchase_date'] ? formatDate($equipment['purchase_date']) : '-'; ?></p>
                <p><strong>Garantia até:</strong><br><?php echo $equipment['warranty_expiry'] ? formatDate($equipment['warranty_expiry']) : '-'; ?></p>
                <p><strong>Cadastrado em:</strong><br><?php echo formatDate($equipment['created_at'], 'd/m/Y H:i'); ?></p>
                <p><strong>Atualizado em:</strong><br><?php echo formatDate($equipment['updated_at'], 'd/m/Y H:i'); ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
