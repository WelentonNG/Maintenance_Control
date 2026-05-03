<?php
/**
 * Gestão de Usuários
 * Sistema de Controle de Manutenção
 */

require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (!hasPermission('admin')) {
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
        $username = sanitize($_POST['username'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $role = sanitize($_POST['role'] ?? 'user');
        $status = sanitize($_POST['status'] ?? 'active');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($name)) {
            $error = 'Nome de usuário e nome são obrigatórios.';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido.';
        } elseif (strlen($username) < 3) {
            $error = 'O nome de usuário deve ter pelo menos 3 caracteres.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            $error = 'O nome de usuário pode conter apenas letras, números, pontos, traços e underscores.';
        } else {
            if ($action === 'edit' && $id > 0) {
                // Verificar username duplicado
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $id]);
                if ($stmt->fetch()) {
                    $error = 'Este nome de usuário já está em uso.';
                } else {
                    // Verificar email duplicado
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $id]);
                    if ($stmt->fetch()) {
                        $error = 'Este email já está em uso.';
                    } else {
                        if (!empty($password)) {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $sql = "UPDATE users SET username = ?, name = ?, email = ?, phone = ?, role = ?, status = ?, password = ? WHERE id = ?";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$username, $name, $email, $phone, $role, $status, $hashedPassword, $id]);
                        } else {
                            $sql = "UPDATE users SET username = ?, name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$username, $name, $email, $phone, $role, $status, $id]);
                        }
                        logAction('update', 'users', $id);
                        setFlashMessage('success', 'Usuário atualizado com sucesso!');
                    }
                }
            } else {
                if (empty($password)) {
                    $error = 'A senha é obrigatória para novos usuários.';
                } else {
                    // Verificar username duplicado
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetch()) {
                        $error = 'Este nome de usuário já está em uso.';
                    } else {
                        // Verificar email duplicado
                        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetch()) {
                            $error = 'Este email já está em uso.';
                        } else {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $sql = "INSERT INTO users (username, name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$username, $name, $email, $phone, $hashedPassword, $role, $status]);
                            $newId = $db->lastInsertId();
                            logAction('create', 'users', $newId);
                            setFlashMessage('success', 'Usuário criado com sucesso!');
                        }
                    }
                }
            }
            
            if (empty($error)) {
                redirect('users.php');
            }
        }
    }
    
    // Excluir
    if ($action === 'delete' && $id > 0) {
        if ($id == $_SESSION['user_id']) {
            setFlashMessage('error', 'Você não pode excluir seu próprio usuário.');
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            logAction('delete', 'users', $id);
            setFlashMessage('success', 'Usuário excluído com sucesso!');
        }
        redirect('users.php');
    }
    
    // Carregar para edição
    $user = null;
    if ($action === 'edit' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
    }
    
    // Listar
    $search = sanitize($_GET['search'] ?? '');
    $filterRole = sanitize($_GET['role'] ?? '');
    
    $sql = "SELECT * FROM users WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (username LIKE ? OR name LIKE ? OR email LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($filterRole) {
        $sql .= " AND role = ?";
        $params[] = $filterRole;
    }
    
    $sql .= " ORDER BY name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao acessar banco de dados.';
    $users = [];
}

// Agora incluir o header após processar todas as ações
$page_title = 'Usuários';
require_once __DIR__ . '/../includes/header.php';

$roleLabels = [
    'admin' => 'Administrador',
    'manager' => 'Gerente',
    'technician' => 'Técnico',
    'user' => 'Usuário'
];
?>

<?php if ($action === 'list'): ?>
<div class="page-header">
    <h1>Usuários</h1>
    <div class="page-actions">
        <a href="users.php?action=new" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Novo Usuário
        </a>
    </div>
</div>

<div class="filters-bar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Buscar usuários..." value="<?php echo htmlspecialchars($search); ?>" 
               onkeypress="if(event.key==='Enter') window.location.href='users.php?search='+this.value">
    </div>
    
    <select class="form-control filter-select" onchange="window.location.href='users.php?role='+this.value">
        <option value="">Todos os Perfis</option>
        <?php foreach ($roleLabels as $key => $label): ?>
        <option value="<?php echo $key; ?>" <?php echo $filterRole === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($u['username'] ?? ''); ?></strong></td>
                        <td><?php echo htmlspecialchars($u['name']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'manager' ? 'warning' : 'info'); ?>">
                                <?php echo $roleLabels[$u['role']] ?? $u['role']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $u['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $u['status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td><?php echo formatDate($u['created_at']); ?></td>
                        <td>
                            <div class="actions">
                                <a href="users.php?action=edit&id=<?php echo $u['id']; ?>" class="action-btn edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <a href="users.php?action=delete&id=<?php echo $u['id']; ?>" class="action-btn delete" onclick="return confirm('Tem certeza?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Formulário -->
<div class="page-header">
    <h1><?php echo $action === 'edit' ? 'Editar Usuário' : 'Novo Usuário'; ?></h1>
    <div class="page-actions">
        <a href="users.php" class="btn btn-outline">
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
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" required
                               pattern="[a-zA-Z0-9._-]+" 
                               minlength="3"
                               title="Use apenas letras, números, pontos, traços e underscores"
                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
                        <small class="form-text">Mínimo 3 caracteres. Use apenas letras, números, pontos, traços e underscores.</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Senha <?php echo $action === 'new' ? '*' : '(deixe em branco para manter)'; ?></label>
                        <input type="password" name="password" class="form-control" 
                               minlength="6"
                               <?php echo $action === 'new' ? 'required' : ''; ?>>
                        <small class="form-text">Mínimo 6 caracteres.</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Perfil</label>
                        <select name="role" class="form-control form-select">
                            <?php foreach ($roleLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($user['role'] ?? 'user') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control form-select">
                            <option value="active" <?php echo ($user['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inactive" <?php echo ($user['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $action === 'edit' ? 'Salvar Alterações' : 'Criar Usuário'; ?>
                </button>
                <a href="users.php" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
