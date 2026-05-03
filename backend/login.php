<?php
/**
 * Página de Login
 * Sistema de Controle de Manutenção
 */

require_once __DIR__ . '/../config/config.php';

// Redirecionar se já estiver logado
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        try {
            $db = getDB();
            $sql = "SELECT id, username, name, email, password, role, avatar, status FROM users WHERE username = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Sua conta está inativa. Entre em contato com o administrador.';
                } else {
                    // Login bem sucedido
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_avatar'] = $user['avatar'];
                    
                    // Log de login
                    logAction('login', 'users', $user['id']);
                    
                    // Cookie de "lembrar-me"
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
                    }
                    
                    redirect('index.php');
                }
            } else {
                $error = 'Nome de usuário ou senha incorretos.';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao conectar com o banco de dados. Verifique se o MySQL está rodando.';
        }
    }
}

// Verificar mensagens de flash
if ($flash = getFlashMessage()) {
    if ($flash['type'] === 'success') {
        $success = $flash['message'];
    } else {
        $error = $flash['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
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
                        <img src="../assets/img/CM.png" alt="Logo">
                    </div>
                    <p>Faça login para continuar</p>
                </div>
                
                <div class="auth-body">
                    <?php if ($error): ?>
                    <div class="auth-alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="auth-alert success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form class="auth-form" method="POST" action="">
                        <div class="form-group">
                            <label class="form-label" for="username">Nome de Usuário</label>
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                                <input 
                                    type="text" 
                                    id="username" 
                                    name="username" 
                                    class="form-control" 
                                    placeholder="seu.usuario"
                                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                    required
                                >
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="password">Senha</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="form-control" 
                                    placeholder="Digite sua senha"
                                    required
                                >
                                <button type="button" class="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="remember-forgot">
                            <div class="remember-me">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Lembrar-me</label>
                            </div>
                            <a href="forgot-password.php" class="forgot-password">Esqueceu a senha?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-auth">
                            <i class="fas fa-sign-in-alt"></i>
                            Entrar
                        </button>
                    </form>
                </div>
                
                <div class="auth-footer">
                    <p>Não tem uma conta? <a href="register.php">Cadastre-se</a></p>
                </div>
            </div>
            
        </div>
    </div>
    
    <script src="../assets/js/app.js"></script>
</body>
</html>