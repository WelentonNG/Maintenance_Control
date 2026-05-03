<?php
/**
 * Gestão de Peças e Materiais
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
    
    // Processar formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = sanitize($_POST['code'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $unit = sanitize($_POST['unit'] ?? 'un');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $min_quantity = (int)($_POST['min_quantity'] ?? 5);
        $unit_price = !empty($_POST['unit_price']) ? (float)$_POST['unit_price'] : null;
        $supplier = sanitize($_POST['supplier'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        
        if (empty($name) || empty($code)) {
            $error = 'Nome e código são obrigatórios.';
        } else {
            if ($action === 'edit' && $id > 0) {
                $sql = "UPDATE parts SET 
                        code = ?, name = ?, description = ?, category = ?, unit = ?,
                        quantity = ?, min_quantity = ?, unit_price = ?, supplier = ?, location = ?
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $code, $name, $description, $category, $unit,
                    $quantity, $min_quantity, $unit_price, $supplier, $location, $id
                ]);
                
                logAction('update', 'parts', $id);
                setFlashMessage('success', 'Peça atualizada com sucesso!');
            } else {
                // Verificar código duplicado
                $stmt = $db->prepare("SELECT id FROM parts WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetch()) {
                    $error = 'Este código já está em uso.';
                } else {
                    $sql = "INSERT INTO parts 
                            (code, name, description, category, unit, quantity, min_quantity, unit_price, supplier, location)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        $code, $name, $description, $category, $unit,
                        $quantity, $min_quantity, $unit_price, $supplier, $location
                    ]);
                    
                    $newId = $db->lastInsertId();
                    logAction('create', 'parts', $newId);
                    setFlashMessage('success', 'Peça cadastrada com sucesso!');
                }
            }
            
            if (empty($error)) {
                redirect('parts.php');
            }
        }
    }
    
    // Excluir
    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM parts WHERE id = ?");
        $stmt->execute([$id]);
        logAction('delete', 'parts', $id);
        setFlashMessage('success', 'Peça excluída com sucesso!');
        redirect('parts.php');
    }
    
    // Carregar peça para edição
    $part = null;
    if ($action === 'edit' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM parts WHERE id = ?");
        $stmt->execute([$id]);
        $part = $stmt->fetch();
        
        if (!$part) {
            setFlashMessage('error', 'Peça não encontrada.');
            redirect('parts.php');
        }
    }
    
    // Listar peças
    $search = sanitize($_GET['search'] ?? '');
    $filterLowStock = isset($_GET['low_stock']);
    
    $sql = "SELECT * FROM parts WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (code LIKE ? OR name LIKE ? OR supplier LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($filterLowStock) {
        $sql .= " AND quantity <= min_quantity";
    }
    
    $sql .= " ORDER BY name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $parts = $stmt->fetchAll();
    
    // Contar peças com estoque baixo
    $lowStockCount = $db->query("SELECT COUNT(*) FROM parts WHERE quantity <= min_quantity")->fetchColumn();
    
} catch (PDOException $e) {
    $error = 'Erro ao acessar banco de dados.';
    $parts = [];
    $lowStockCount = 0;
}

// Agora incluir o header após processar todas as ações
$page_title = 'Peças e Materiais';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<div class="page-header">
    <h1>Peças e Materiais</h1>
    <div class="page-actions">
        <?php if ($lowStockCount > 0): ?>
        <a href="parts.php?low_stock=1" class="btn btn-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $lowStockCount; ?> em Estoque Baixo
        </a>
        <?php endif; ?>
        <a href="parts.php?action=new" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Nova Peça
        </a>
    </div>
</div>

<div class="filters-bar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Buscar peças..." value="<?php echo htmlspecialchars($search); ?>" 
               onkeypress="if(event.key==='Enter') window.location.href='parts.php?search='+this.value">
    </div>
    
    <label class="form-check" style="margin-left: auto;">
        <input type="checkbox" class="form-check-input" <?php echo $filterLowStock ? 'checked' : ''; ?>
               onchange="window.location.href=this.checked?'parts.php?low_stock=1':'parts.php'">
        <span class="form-check-label">Apenas estoque baixo</span>
    </label>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($parts)): ?>
        <div class="empty-state">
            <div style="font-size: 6rem; color: #94a3b8; margin-bottom: 1.5rem; line-height: 1;">
                <i class="fas fa-box"></i>
            </div>
            <h3 style="font-size: 1.5rem; color: #475569; margin-bottom: 0.5rem;">Nenhuma peça encontrada</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Cadastre sua primeira peça ou material.</p>
            <a href="parts.php?action=new" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nova Peça
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
                        <th>Quantidade</th>
                        <th>Unidade</th>
                        <th>Preço Unit.</th>
                        <th>Fornecedor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parts as $p): ?>
                    <tr class="<?php echo $p['quantity'] <= $p['min_quantity'] ? 'table-warning' : ''; ?>">
                        <td><strong><?php echo htmlspecialchars($p['code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                        <td><?php echo htmlspecialchars($p['category'] ?? '-'); ?></td>
                        <td>
                            <?php if ($p['quantity'] <= $p['min_quantity']): ?>
                            <span class="badge badge-danger"><?php echo $p['quantity']; ?></span>
                            <?php else: ?>
                            <?php echo $p['quantity']; ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['unit']); ?></td>
                        <td><?php echo $p['unit_price'] ? formatCurrency($p['unit_price']) : '-'; ?></td>
                        <td><?php echo htmlspecialchars($p['supplier'] ?? '-'); ?></td>
                        <td>
                            <div class="actions">
                                <a href="parts.php?action=edit&id=<?php echo $p['id']; ?>" class="action-btn edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="parts.php?action=delete&id=<?php echo $p['id']; ?>" class="action-btn delete" onclick="return confirm('Tem certeza?')">
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
    <h1><?php echo $action === 'edit' ? 'Editar Peça' : 'Nova Peça'; ?></h1>
    <div class="page-actions">
        <a href="parts.php" class="btn btn-outline">
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
                <div class="col-3">
                    <div class="form-group">
                        <label class="form-label">Código *</label>
                        <input type="text" name="code" class="form-control" required
                               value="<?php echo htmlspecialchars($part['code'] ?? 'PC-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT)); ?>">
                    </div>
                </div>
                <div class="col">
                    <div class="form-group">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?php echo htmlspecialchars($part['name'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($part['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <input type="text" name="category" class="form-control" 
                               value="<?php echo htmlspecialchars($part['category'] ?? ''); ?>"
                               placeholder="Ex: Elétrica, Hidráulica...">
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Unidade</label>
                        <select name="unit" class="form-control form-select">
                            <option value="un" <?php echo ($part['unit'] ?? 'un') === 'un' ? 'selected' : ''; ?>>Unidade</option>
                            <option value="pç" <?php echo ($part['unit'] ?? '') === 'pç' ? 'selected' : ''; ?>>Peça</option>
                            <option value="m" <?php echo ($part['unit'] ?? '') === 'm' ? 'selected' : ''; ?>>Metro</option>
                            <option value="kg" <?php echo ($part['unit'] ?? '') === 'kg' ? 'selected' : ''; ?>>Quilograma</option>
                            <option value="l" <?php echo ($part['unit'] ?? '') === 'l' ? 'selected' : ''; ?>>Litro</option>
                            <option value="cx" <?php echo ($part['unit'] ?? '') === 'cx' ? 'selected' : ''; ?>>Caixa</option>
                            <option value="pc" <?php echo ($part['unit'] ?? '') === 'pc' ? 'selected' : ''; ?>>Pacote</option>
                        </select>
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Preço Unitário (R$)</label>
                        <input type="number" name="unit_price" class="form-control" step="0.01" min="0"
                               value="<?php echo $part['unit_price'] ?? ''; ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Quantidade em Estoque</label>
                        <input type="number" name="quantity" class="form-control" min="0"
                               value="<?php echo $part['quantity'] ?? 0; ?>">
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Quantidade Mínima</label>
                        <input type="number" name="min_quantity" class="form-control" min="0"
                               value="<?php echo $part['min_quantity'] ?? 5; ?>">
                    </div>
                </div>
                <div class="col-4">
                    <div class="form-group">
                        <label class="form-label">Localização no Estoque</label>
                        <input type="text" name="location" class="form-control" 
                               value="<?php echo htmlspecialchars($part['location'] ?? ''); ?>"
                               placeholder="Ex: Prateleira A-1">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Fornecedor</label>
                <input type="text" name="supplier" class="form-control" 
                       value="<?php echo htmlspecialchars($part['supplier'] ?? ''); ?>">
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $action === 'edit' ? 'Salvar Alterações' : 'Cadastrar'; ?>
                </button>
                <a href="parts.php" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
