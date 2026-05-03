<?php
/**
 * Gestão de Localizações
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
        $building = sanitize($_POST['building'] ?? '');
        $floor = sanitize($_POST['floor'] ?? '');
        $department = sanitize($_POST['department'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if (empty($name)) {
            $error = 'O nome é obrigatório.';
        } else {
            if ($action === 'edit' && $id > 0) {
                $sql = "UPDATE locations SET name = ?, building = ?, floor = ?, department = ?, description = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $building, $floor, $department, $description, $id]);
                logAction('update', 'locations', $id);
                setFlashMessage('success', 'Localização atualizada com sucesso!');
            } else {
                $sql = "INSERT INTO locations (name, building, floor, department, description) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $building, $floor, $department, $description]);
                $newId = $db->lastInsertId();
                logAction('create', 'locations', $newId);
                setFlashMessage('success', 'Localização criada com sucesso!');
            }
            redirect('locations.php');
        }
    }
    
    // Excluir
    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM locations WHERE id = ?");
        $stmt->execute([$id]);
        logAction('delete', 'locations', $id);
        setFlashMessage('success', 'Localização excluída com sucesso!');
        redirect('locations.php');
    }
    
    // Carregar para edição
    $location = null;
    if ($action === 'edit' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM locations WHERE id = ?");
        $stmt->execute([$id]);
        $location = $stmt->fetch();
    }
    
    // Listar
    $locations = $db->query("
        SELECT l.*, 
               (SELECT COUNT(*) FROM equipment WHERE location_id = l.id) as equipment_count
        FROM locations l 
        ORDER BY l.building, l.name
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao acessar banco de dados.';
    $locations = [];
}

// Agora incluir o header após processar todas as ações
$page_title = 'Localizações';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Localizações</h1>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openLocationModal()">
            <i class="fas fa-plus"></i>
            Nova Localização
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
        <?php if (empty($locations)): ?>
        <div class="empty-state">
            <div style="font-size: 6rem; color: #94a3b8; margin-bottom: 1.5rem; line-height: 1;">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h3 style="font-size: 1.5rem; color: #475569; margin-bottom: 0.5rem;">Nenhuma localização encontrada</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Crie sua primeira localização.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Prédio</th>
                        <th>Andar</th>
                        <th>Departamento</th>
                        <th>Equipamentos</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $loc): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($loc['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($loc['building'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($loc['floor'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($loc['department'] ?? '-'); ?></td>
                        <td><?php echo $loc['equipment_count']; ?></td>
                        <td>
                            <div class="actions">
                                <button class="action-btn edit" onclick="editLocation(<?php echo htmlspecialchars(json_encode($loc)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="locations.php?action=delete&id=<?php echo $loc['id']; ?>" class="action-btn delete" onclick="return confirm('Tem certeza?')">
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
<div class="modal-overlay" id="locationModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Nova Localização</h3>
            <button class="modal-close" onclick="closeModal('locationModal')">&times;</button>
        </div>
        <form method="POST" action="locations.php" id="locationForm">
            <input type="hidden" name="id" id="locationId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="name" id="locationName" class="form-control" required placeholder="Ex: Sala de Servidores">
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Prédio</label>
                            <input type="text" name="building" id="locationBuilding" class="form-control" placeholder="Ex: Prédio Principal">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Andar</label>
                            <input type="text" name="floor" id="locationFloor" class="form-control" placeholder="Ex: 2º Andar">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Departamento</label>
                    <input type="text" name="department" id="locationDepartment" class="form-control" placeholder="Ex: TI">
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" id="locationDescription" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('locationModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openLocationModal() {
    document.getElementById('modalTitle').textContent = 'Nova Localização';
    document.getElementById('locationForm').action = 'locations.php?action=new';
    document.getElementById('locationId').value = '';
    document.getElementById('locationName').value = '';
    document.getElementById('locationBuilding').value = '';
    document.getElementById('locationFloor').value = '';
    document.getElementById('locationDepartment').value = '';
    document.getElementById('locationDescription').value = '';
    openModal('locationModal');
}

function editLocation(loc) {
    document.getElementById('modalTitle').textContent = 'Editar Localização';
    document.getElementById('locationForm').action = 'locations.php?action=edit&id=' + loc.id;
    document.getElementById('locationId').value = loc.id;
    document.getElementById('locationName').value = loc.name;
    document.getElementById('locationBuilding').value = loc.building || '';
    document.getElementById('locationFloor').value = loc.floor || '';
    document.getElementById('locationDepartment').value = loc.department || '';
    document.getElementById('locationDescription').value = loc.description || '';
    openModal('locationModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
