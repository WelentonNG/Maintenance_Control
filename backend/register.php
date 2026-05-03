<?php
/**
 * Página de Registro
 * Sistema de Controle de Manutenção
 */

require_once __DIR__ . '/../config/config.php';

// Redirecionar se já estiver logado
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';
$formData = [];

// Processar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $formData = ['username' => $username, 'name' => $name, 'email' => $email, 'phone' => $phone];
    
    // Validações
    if (empty($username) || empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Por favor, preencha todos os campos obrigatórios.';
    } elseif (strlen($username) < 3) {
        $error = 'O nome de usuário deve ter pelo menos 3 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        $error = 'O nome de usuário pode conter apenas letras, números, pontos, traços e underscores.';
    } elseif (strlen($name) < 3) {
        $error = 'O nome deve ter pelo menos 3 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, digite um email válido.';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($password !== $confirm_password) {
        $error = 'As senhas não coincidem.';
    } else {
        try {
            $db = getDB();
            
            // Verificar se username já existe
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = 'Este nome de usuário já está em uso.';
            } else {
                // Verificar se email já existe
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $error = 'Este email já está cadastrado.';
                } else {
                    // Criar usuário
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $sql = "INSERT INTO users (username, name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, 'user', 'active')";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$username, $name, $email, $phone, $hashedPassword]);
                    
                    setFlashMessage('success', 'Conta criada com sucesso! Faça login para continuar.');
                    redirect('login.php');
                }
            }
        } catch (PDOException $e) {
            $error = 'Erro ao criar conta. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="../assets/img/CM.png">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <div class="auth-logo">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h1>Criar Conta</h1>
                    <p>Cadastre-se para acessar o sistema</p>
                </div>
                
                <div class="auth-body">
                    <?php if ($error): ?>
                    <div class="auth-alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form class="auth-form" method="POST" action="">
                        <div class="form-group">
                            <label class="form-label" for="username">Nome de Usuário *</label>
                            <div class="input-icon">
                                <i class="fas fa-user-circle"></i>
                                <input 
                                    type="text" 
                                    id="username" 
                                    name="username" 
                                    class="form-control" 
                                    placeholder="seu.usuario"
                                    value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>"
                                    required
                                    minlength="3"
                                    pattern="[a-zA-Z0-9._-]+"
                                    title="Apenas letras, números, pontos, traços e underscores"
                                >
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="name">Nome Completo *</label>
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                                <input 
                                    type="text" 
                                    id="name" 
                                    name="name" 
                                    class="form-control" 
                                    placeholder="Seu nome completo"
                                    value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>"
                                    required
                                    minlength="3"
                                >
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="email">Email *</label>
                            <div class="input-icon">
                                <i class="fas fa-envelope"></i>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-control" 
                                    placeholder="seu@email.com"
                                    value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                    required
                                >
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="phone">Telefone</label>
                            <div class="input-icon">
                                <i class="fas fa-phone"></i>
                                <input 
                                    type="tel" 
                                    id="phone" 
                                    name="phone" 
                                    class="form-control" 
                                    placeholder="(00) 00000-0000"
                                    value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                                >
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="password">Senha *</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="form-control" 
                                    placeholder="Mínimo 6 caracteres"
                                    required
                                    minlength="6"
                                >
                                <button type="button" class="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirmar Senha *</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="form-control" 
                                    placeholder="Repita a senha"
                                    required
                                >
                                <button type="button" class="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-auth">
                            <i class="fas fa-user-plus"></i>
                            Criar Conta
                        </button>
                    </form>
                </div>
                
                <div class="auth-footer">
                    <p>Já tem uma conta? <a href="login.php">Fazer login</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/app.js"></script>
</body>
</html>
