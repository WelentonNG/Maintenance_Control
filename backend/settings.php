<?php
/**
 * Configurações do Sistema
 * Sistema de Controle de Manutenção
 */

$page_title = 'Configurações';
require_once __DIR__ . '/../includes/header.php';

if (!hasPermission('admin')) {
    setFlashMessage('error', 'Você não tem permissão para acessar esta página.');
    redirect('index.php');
}

$error = '';
$success = '';

try {
    $db = getDB();
    
    // Carregar configurações
    $stmt = $db->query("SELECT * FROM settings ORDER BY setting_key");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Processar formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($_POST['settings'] as $key => $value) {
            // Usar INSERT ON DUPLICATE KEY UPDATE para criar registro se não existir
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $sanitizedValue = sanitize($value);
            $stmt->execute([sanitize($key), $sanitizedValue, $sanitizedValue]);
            $settings[$key] = $value;
        }
        
        logAction('update', 'settings', null);
        $success = 'Configurações salvas com sucesso!';
    }
    
} catch (PDOException $e) {
    $error = 'Erro ao acessar banco de dados.';
    $settings = [];
}
?>

<div class="page-header">
    <h1>Configurações do Sistema</h1>
</div>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <span><?php echo $error; ?></span>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <span><?php echo $success; ?></span>
</div>
<?php endif; ?>

<form method="POST" action="">
    <div class="row">
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Dados da Empresa</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Nome da Empresa</label>
                        <input type="text" name="settings[company_name]" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email de Contato</label>
                        <input type="email" name="settings[company_email]" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-hashtag"></i> Prefixos</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Prefixo para Ordens</label>
                        <input type="text" name="settings[orders_prefix]" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['orders_prefix'] ?? 'OM'); ?>"
                               placeholder="Ex: OM">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Prefixo para Equipamentos</label>
                        <input type="text" name="settings[equipment_prefix]" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['equipment_prefix'] ?? 'EQ'); ?>"
                               placeholder="Ex: EQ">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-globe"></i> Localização</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Fuso Horário</label>
                        <select name="settings[timezone]" class="form-control form-select">
                            <option value="America/Sao_Paulo" <?php echo ($settings['timezone'] ?? '') === 'America/Sao_Paulo' ? 'selected' : ''; ?>>São Paulo (GMT-3)</option>
                            <option value="America/Manaus" <?php echo ($settings['timezone'] ?? '') === 'America/Manaus' ? 'selected' : ''; ?>>Manaus (GMT-4)</option>
                            <option value="America/Bahia" <?php echo ($settings['timezone'] ?? '') === 'America/Bahia' ? 'selected' : ''; ?>>Bahia (GMT-3)</option>
                            <option value="America/Fortaleza" <?php echo ($settings['timezone'] ?? '') === 'America/Fortaleza' ? 'selected' : ''; ?>>Fortaleza (GMT-3)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Moeda</label>
                        <select name="settings[currency]" class="form-control form-select">
                            <option value="BRL" <?php echo ($settings['currency'] ?? '') === 'BRL' ? 'selected' : ''; ?>>Real (R$)</option>
                            <option value="USD" <?php echo ($settings['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>Dólar ($)</option>
                            <option value="EUR" <?php echo ($settings['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Informações do Sistema</h3>
                </div>
                <div class="card-body">
                    <p><strong>Servidor:</strong> Xampp</p>
                    <p><strong>Versão:</strong> <?php echo SITE_VERSION; ?></p>
                    <p><strong>PHP:</strong> <?php echo phpversion(); ?></p>
                    <p><strong>MySQL:</strong> <?php 
                        try {
                            echo $db->query("SELECT VERSION()")->fetchColumn();
                        } catch (Exception $e) {
                            echo 'N/A';
                        }
                    ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i>
            Salvar Configurações
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
