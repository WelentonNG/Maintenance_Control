<?php
/**
 * Header do Sistema
 * Sistema de Controle de Manutenção
 */

require_once __DIR__ . '/../config/config.php';

// Verificar login para páginas protegidas
$public_pages = ['login.php', 'register.php', 'forgot-password.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if (!in_array($current_page, $public_pages) && !isLoggedIn()) {
    redirect('login.php');
}

// Obter dados do usuário logado
$user_name = $_SESSION['user_name'] ?? 'Usuário';
$user_role = $_SESSION['user_role'] ?? 'user';
$user_avatar = $_SESSION['user_avatar'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

// Carregar notificações do usuário
$notifications = [];
$unread_count = 0;
if ($user_id && !in_array($current_page, $public_pages)) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, type, title, message, link, is_read, created_at 
            FROM notifications 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([':user_id' => $user_id]);
        $notifications = $stmt->fetchAll();
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute([':user_id' => $user_id]);
        $unread_count = $stmt->fetch()['count'];
    } catch (PDOException $e) {
        error_log("Error loading notifications: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="../assets/img/CM.png">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php if (isLoggedIn() && !in_array($current_page, $public_pages)): ?>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-tools"></i>
                <span>Manutenção</span>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <nav class="sidebar-nav">
            <ul>
                <li class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                    <a href="index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="<?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
                    <a href="orders.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Ordens de Serviço</span>
                    </a>
                </li>
                <li class="<?php echo $current_page === 'equipment.php' ? 'active' : ''; ?>">
                    <a href="equipment.php">
                        <i class="fas fa-cogs"></i>
                        <span>Equipamentos</span>
                    </a>
                </li>
                <li class="<?php echo $current_page === 'technicians.php' ? 'active' : ''; ?>">
                    <a href="technicians.php">
                         <i class="fas fa-user-cog"></i>
                        <span>Técnicos</span>
                    </a>
                </li>
                <li class="<?php echo $current_page === 'parts.php' ? 'active' : ''; ?>">
                    <a href="parts.php">
                        <i class="fas fa-box"></i>
                        <span>Peças e Materiais</span>
                    </a>
                </li>
                <li class="<?php echo $current_page === 'preventive.php' ? 'active' : ''; ?>">
                    <a href="preventive.php">
                        <i class="fas fa-calendar-check"></i>
                        <span>Preventivas</span>
                    </a>
                </li>
                
                <?php if (hasPermission('manager')): ?>
                <li class="nav-divider">
                    <span>Administração</span>
                </li>
                <li class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Relatórios</span>
                    </a>
                </li>
                <li class="<?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
                    <a href="categories.php">
                        <i class="fas fa-tags"></i>
                        <span>Categorias</span>
                    </a>
                </li>
                <li class="<?php echo $current_page === 'locations.php' ? 'active' : ''; ?>">
                    <a href="locations.php">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Localizações</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasPermission('admin')): ?>
                <li class="<?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                    <a href="users.php">
                        <i class="fas fa-users"></i>
                        <span>Usuários</span>
                    </a>
                </li>
                <li class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                    <a href="settings.php">
                        <i class="fas fa-cog"></i>
                        <span>Configurações</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if ($user_avatar): ?>
                        <img src="assets/uploads/avatars/<?php echo $user_avatar; ?>" alt="Avatar">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="user-role"><?php echo ucfirst($user_role); ?></span>
                </div>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Bar -->
        <header class="top-bar">
            <div class="top-bar-left">
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="breadcrumb">
                    <span><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></span>
                </div>
            </div>
            
            <div class="top-bar-right">
                <div class="notifications dropdown">
                    <button class="btn-icon" id="notificationsBtn">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu" id="notificationsMenu">
                        <div class="dropdown-header">
                            <h4>Notificações</h4>
                            <?php if ($unread_count > 0): ?>
                            <a href="#" id="markAllRead">Marcar todas como lidas</a>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <?php if (empty($notifications)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <p>Nenhuma notificação</p>
                                </div>
                            </div>
                            <?php else: ?>
                            <?php foreach ($notifications as $notif): 
                                $icon_map = [
                                    'warning' => 'fa-exclamation-circle text-warning',
                                    'success' => 'fa-check-circle text-success',
                                    'info' => 'fa-info-circle text-info',
                                    'error' => 'fa-times-circle text-danger',
                                    'maintenance' => 'fa-wrench text-warning',
                                    'schedule' => 'fa-calendar text-info'
                                ];
                                $icon_class = $icon_map[$notif['type']] ?? 'fa-bell text-info';
                                $time_ago = timeAgo($notif['created_at']);
                            ?>
                            <a href="<?php echo $notif['link'] ?? '#'; ?>" 
                               class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>"
                               data-id="<?php echo $notif['id']; ?>">
                                <i class="fas <?php echo $icon_class; ?>"></i>
                                <div class="notification-content">
                                    <p><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <span class="time"><?php echo $time_ago; ?></span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-footer">
                            <a href="notifications.php">Ver todas as notificações</a>
                        </div>
                    </div>
                </div>
                
                <div class="user-menu dropdown">
                    <button class="btn-user" id="userMenuBtn">
                        <div class="user-avatar-small">
                            <?php if ($user_avatar): ?>
                                <img src="assets/uploads/avatars/<?php echo $user_avatar; ?>" alt="Avatar">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <span class="user-name-short"><?php echo htmlspecialchars($user_name); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="userMenu">
                        <a href="profile.php">
                            <i class="fas fa-user"></i>
                            Meu Perfil
                        </a>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i>
                            Configurações
                        </a>
                        <div class="divider"></div>
                        <a href="logout.php" class="text-danger">
                            <i class="fas fa-sign-out-alt"></i>
                            Sair
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Flash Messages -->
        <?php if ($flash = getFlashMessage()): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>" id="flashMessage">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
            <span><?php echo $flash['message']; ?></span>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Page Content -->
        <div class="page-content">
    <?php endif; ?>