<?php
/**
 * Perfil do Usuário
 * Sistema de Controle de Manutenção
 */

$page_title = 'Meu Perfil';
require_once __DIR__ . '/../includes/header.php';

$error = '';
$success = '';

try {
    $db = getDB();
    
    // Carregar dados do usuário
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Processar formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'profile';
        
        if ($action === 'profile') {
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            
            if (empty($name) || empty($email)) {
                $error = 'Nome e email são obrigatórios.';
            } else {
                // Verificar email duplicado
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $error = 'Este email já está em uso.';
                } else {
                    $sql = "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$name, $email, $phone, $_SESSION['user_id']]);
                    
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    
                    $success = 'Perfil atualizado com sucesso!';
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                }
            }
        } elseif ($action === 'password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'Preencha todos os campos de senha.';
            } elseif (strlen($new_password) < 6) {
                $error = 'A nova senha deve ter pelo menos 6 caracteres.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'As senhas não coincidem.';
            } elseif (!password_verify($current_password, $user['password'])) {
                $error = 'Senha atual incorreta.';
            } else {
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                
                $success = 'Senha alterada com sucesso!';
            }
        }
    }
    
} catch (PDOException $e) {
    $error = 'Erro ao acessar banco de dados.';
}
?>

<div class="page-header">
    <h1>Meu Perfil</h1>
</div>

<div class="row">
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Dados Pessoais</h3>
            </div>
            <div class="card-body">
                <?php if ($error && !isset($_POST['action']) || (isset($_POST['action']) && $_POST['action'] === 'profile')): ?>
                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($success && isset($_POST['action']) && $_POST['action'] === 'profile'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="profile">
                    
                    <div class="form-group">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?php echo htmlspecialchars($user['name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Alterações
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-lock"></i> Alterar Senha</h3>
            </div>
            <div class="card-body">
                <?php if ($error && isset($_POST['action']) && $_POST['action'] === 'password'): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($success && isset($_POST['action']) && $_POST['action'] === 'password'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="password">
                    
                    <div class="form-group">
                        <label class="form-label">Senha Atual *</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nova Senha *</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirmar Nova Senha *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i>
                        Alterar Senha
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Informações da Conta</h3>
            </div>
            <div class="card-body">
                <p><strong>Perfil:</strong> 
                    <span class="badge badge-info">
                        <?php 
                        $roles = ['admin' => 'Administrador', 'manager' => 'Gerente', 'technician' => 'Técnico', 'user' => 'Usuário'];
                        echo $roles[$user['role']] ?? $user['role'];
                        ?>
                    </span>
                </p>
                <p><strong>Status:</strong> 
                    <span class="badge badge-success">
                        <?php echo $user['status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                    </span>
                </p>
                <p><strong>Membro desde:</strong> <?php echo formatDate($user['created_at'], 'd/m/Y'); ?></p>
                <p><strong>Última atualização:</strong> <?php echo formatDate($user['updated_at'], 'd/m/Y H:i'); ?></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
