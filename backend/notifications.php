<?php
/**
 * Sistema de Notificações
 * Sistema de Controle de Manutenção
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Buscar notificações do usuário
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            
            $sql = "SELECT id, type, title, message, link, is_read, created_at 
                    FROM notifications 
                    WHERE user_id = :user_id 
                    ORDER BY created_at DESC 
                    LIMIT :limit";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $notifications = $stmt->fetchAll();
            
            // Contar não lidas
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0");
            $stmt->execute([':user_id' => $user_id]);
            $unread_count = $stmt->fetch()['count'];
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unread_count
            ]);
            break;
            
        case 'count':
            // Apenas contar notificações não lidas
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0");
            $stmt->execute([':user_id' => $user_id]);
            $unread_count = $stmt->fetch()['count'];
            
            echo json_encode([
                'success' => true,
                'unread_count' => $unread_count
            ]);
            break;
            
        case 'mark_read':
            // Marcar como lida
            $notification_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            
            if ($notification_id > 0) {
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
                $stmt->execute([
                    ':id' => $notification_id,
                    ':user_id' => $user_id
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Notificação marcada como lida']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
            }
            break;
            
        case 'mark_all_read':
            // Marcar todas como lidas
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
            $stmt->execute([':user_id' => $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Todas as notificações foram marcadas como lidas']);
            break;
            
        case 'delete':
            // Deletar notificação
            $notification_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            
            if ($notification_id > 0) {
                $stmt = $db->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
                $stmt->execute([
                    ':id' => $notification_id,
                    ':user_id' => $user_id
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Notificação excluída']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} catch (PDOException $e) {
    error_log("Notification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar notificação']);
}

/**
 * Função auxiliar para criar notificações
 * Pode ser chamada de outros arquivos
 */
function createNotification($user_id, $type, $title, $message, $link = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, created_at) 
            VALUES (:user_id, :type, :title, :message, :link, NOW())
        ");
        
        return $stmt->execute([
            ':user_id' => $user_id,
            ':type' => $type,
            ':title' => $title,
            ':message' => $message,
            ':link' => $link
        ]);
    } catch (PDOException $e) {
        error_log("Create notification error: " . $e->getMessage());
        return false;
    }
}
?>
