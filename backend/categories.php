<?php
/**
 * Gestão de Categorias
 * Sistema de Controle de Manutenção
 */

require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (!hasPermission('manager')) {
    setFlashMessage('error', 'Você não tem permissão para acessar esta página.');
    redirect('index.php');
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

try {
    $db = getDB();
    
    // Processar formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $color = sanitize($_POST['color'] ?? '#3498db');
        $icon = sanitize($_POST['icon'] ?? 'fa-cog');
        
        if (empty($name)) {
            $error = 'O nome é obrigatório.';
        } else {
            if ($action === 'edit' && $id > 0) {
                $sql = "UPDATE categories SET name = ?, description = ?, color = ?, icon = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $description, $color, $icon, $id]);
                logAction('update', 'categories', $id);
                setFlashMessage('success', 'Categoria atualizada com sucesso!');
            } else {
                $sql = "INSERT INTO categories (name, description, color, icon) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $description, $color, $icon]);
                $newId = $db->lastInsertId();
                logAction('create', 'categories', $newId);
                setFlashMessage('success', 'Categoria criada com sucesso!');
            }
            redirect('categories.php');
        }
    }
    
    // Excluir
    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        logAction('delete', 'categories', $id);
        setFlashMessage('success', 'Categoria excluída com sucesso!');
        redirect('categories.php');
    }
    
    // Carregar para edição
    $category = null;
    if ($action === 'edit' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
    }
    
    // Listar
    $categories = $db->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM equipment WHERE category_id = c.id) as equipment_count
        FROM categories c 
        ORDER BY c.name
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao acessar banco de dados.';
    $categories = [];
}

$icons = ['fa-cog', 'fa-bolt', 'fa-cogs', 'fa-tint', 'fa-snowflake', 'fa-laptop', 'fa-shield-alt', 'fa-wrench', 'fa-tools', 'fa-fan', 'fa-pump-soap', 'fa-fire'];

// Agora incluir o header após processar todas as ações
$page_title = 'Categorias';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Categorias de Equipamentos</h1>
    <div class="page-actions">
        <button class="btn btn-primary" data-modal="categoryModal" onclick="openCategoryModal()">
            <i class="fas fa-plus"></i>
            Nova Categoria
        </button>
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
        <?php if (empty($categories)): ?>
        <div class="empty-state">
            <div style="font-size: 6rem; color: #94a3b8; margin-bottom: 1.5rem; line-height: 1;">
                <i class="fas fa-tags"></i>
            </div>
            <h3 style="font-size: 1.5rem; color: #475569; margin-bottom: 0.5rem;">Nenhuma categoria encontrada</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Crie sua primeira categoria.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cor</th>
                        <th>Nome</th>
                        <th>Descrição</th>
                        <th>Equipamentos</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td>
                            <span style="display: inline-block; width: 30px; height: 30px; border-radius: 50%; background-color: <?php echo $cat['color']; ?>; text-align: center; line-height: 30px; color: white;">
                                <i class="fas <?php echo $cat['icon']; ?>"></i>
                            </span>
                        </td>
                        <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($cat['description'] ?? '-'); ?></td>
                        <td><?php echo $cat['equipment_count']; ?></td>
                        <td>
                            <div class="actions">
                                <button class="action-btn edit" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="categories.php?action=delete&id=<?php echo $cat['id']; ?>" class="action-btn delete" onclick="return confirm('Tem certeza?')">
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

<!-- Modal -->
<div class="modal-overlay" id="categoryModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Nova Categoria</h3>
            <button class="modal-close" onclick="closeModal('categoryModal')">&times;</button>
        </div>
        <form method="POST" action="categories.php" id="categoryForm">
            <input type="hidden" name="id" id="categoryId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="name" id="categoryName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" id="categoryDescription" class="form-control" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Cor</label>
                            <input type="color" name="color" id="categoryColor" class="form-control" value="#3498db" style="height: 45px;">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Ícone</label>
                            <select name="icon" id="categoryIcon" class="form-control form-select">
                                <?php foreach ($icons as $icon): ?>
                                <option value="<?php echo $icon; ?>"><?php echo $icon; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('categoryModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCategoryModal() {
    document.getElementById('modalTitle').textContent = 'Nova Categoria';
    document.getElementById('categoryForm').action = 'categories.php?action=new';
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryName').value = '';
    document.getElementById('categoryDescription').value = '';
    document.getElementById('categoryColor').value = '#3498db';
    document.getElementById('categoryIcon').value = 'fa-cog';
    openModal('categoryModal');
}

function editCategory(cat) {
    document.getElementById('modalTitle').textContent = 'Editar Categoria';
    document.getElementById('categoryForm').action = 'categories.php?action=edit&id=' + cat.id;
    document.getElementById('categoryId').value = cat.id;
    document.getElementById('categoryName').value = cat.name;
    document.getElementById('categoryDescription').value = cat.description || '';
    document.getElementById('categoryColor').value = cat.color || '#3498db';
    document.getElementById('categoryIcon').value = cat.icon || 'fa-cog';
    openModal('categoryModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
