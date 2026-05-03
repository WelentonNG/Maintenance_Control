<?php
/**
 * Configurações Gerais do Sistema
 * Sistema de Controle de Manutenção
 */

// Iniciar sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Definir caminho base
define('BASE_PATH', dirname(__DIR__)); // Aponta para raiz do projeto (Maintenance_Control)
define('BACKEND_PATH', BASE_PATH . '/backend/'); // Aponta para pasta backend
define('BASE_URL', '/Maintenance_Control/backend/');

// Incluir conexão com banco de dados
require_once BASE_PATH . '/config/database.php';

// Configurações do sistema
define('SITE_NAME', 'Sistema de Controle de Manutenção');
define('SITE_VERSION', '3.0.0');

// Configurações de upload
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Função para verificar se usuário está logado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Função para verificar permissão
function hasPermission($required_role) {
    if (!isLoggedIn()) return false;
    
    $roles = ['user' => 1, 'technician' => 2, 'manager' => 3, 'admin' => 4];
    $user_role = $_SESSION['user_role'] ?? 'user';
    
    return ($roles[$user_role] ?? 0) >= ($roles[$required_role] ?? 0);
}

// Função para redirecionar
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

// Função para exibir mensagens flash
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Função para sanitizar input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Função para gerar código único
function generateCode($prefix, $length = 6) {
    return $prefix . str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Função para formatar data
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

// Função para formatar moeda
function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Função para calcular tempo decorrido
function timeAgo($datetime) {
    if (empty($datetime)) return '-';
    
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' ano' . ($diff->y > 1 ? 's' : '') . ' atrás';
    if ($diff->m > 0) return $diff->m . ' mês' . ($diff->m > 1 ? 'es' : '') . ' atrás';
    if ($diff->d > 0) return $diff->d . ' dia' . ($diff->d > 1 ? 's' : '') . ' atrás';
    if ($diff->h > 0) return $diff->h . ' hora' . ($diff->h > 1 ? 's' : '') . ' atrás';
    if ($diff->i > 0) return $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '') . ' atrás';
    return 'agora';
}

// Função para buscar configuração do sistema
function getSetting($key, $default = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        // Retorna o valor do banco se existir e não for vazio, senão retorna o default
        return ($result !== false && $result !== '' && $result !== null) ? $result : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Função para log do sistema
function logAction($action, $table = null, $record_id = null, $old_values = null, $new_values = null) {
    if (!isLoggedIn()) return;
    
    try {
        $db = getDB();
        $sql = "INSERT INTO system_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $table,
            $record_id,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Silenciar erros de log
    }
}