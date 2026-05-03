<?php
/**
 * Página de Recuperação de Senha
 * Sistema de Controle de Manutenção
 */

require_once __DIR__ . '/../config/config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Por favor, digite seu email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, digite um email válido.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Em um ambiente real, aqui seria enviado um email com link de recuperação
                // Por simplicidade, apenas mostramos uma mensagem
                $success = 'Se este email estiver cadastrado, você receberá instruções para recuperar sua senha.';
            } else {
                // Mensagem genérica por segurança
                $success = 'Se este email estiver cadastrado, você receberá instruções para recuperar sua senha.';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao processar solicitação. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="../assets/img/CM.png">
    
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
                    <h1>Recuperar Senha</h1>
                    <p>Digite seu email para receber instruções</p>
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
                    <?php else: ?>
                    <form class="auth-form" method="POST" action="">
                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <div class="input-icon">
                                <i class="fas fa-envelope"></i>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-control" 
                                    placeholder="seu@email.com"
                                    required
                                >
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-auth">
                            <i class="fas fa-paper-plane"></i>
                            Enviar Instruções
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                
                <div class="auth-footer">
                    <p>Lembrou a senha? <a href="login.php">Fazer login</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/app.js"></script>
</body>
</html>
