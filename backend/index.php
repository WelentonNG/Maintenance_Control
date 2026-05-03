<?php
/**
 * Dashboard Principal
 * Sistema de Controle de Manutenção
 */

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

// Estatísticas
try {
    $db = getDB();
    
    // Total de ordens
    $stmt = $db->query("SELECT COUNT(*) FROM maintenance_orders");
    $totalOrders = $stmt->fetchColumn();
    
    // Ordens pendentes
    $stmt = $db->query("SELECT COUNT(*) FROM maintenance_orders WHERE status = 'pending'");
    $pendingOrders = $stmt->fetchColumn();
    
    // Ordens em andamento
    $stmt = $db->query("SELECT COUNT(*) FROM maintenance_orders WHERE status = 'in_progress'");
    $inProgressOrders = $stmt->fetchColumn();
    
    // Ordens concluídas este mês
    $stmt = $db->query("SELECT COUNT(*) FROM maintenance_orders WHERE status = 'completed' AND MONTH(end_date) = MONTH(CURRENT_DATE()) AND YEAR(end_date) = YEAR(CURRENT_DATE())");
    $completedThisMonth = $stmt->fetchColumn();
    
    // Total de equipamentos
    $stmt = $db->query("SELECT COUNT(*) FROM equipment");
    $totalEquipment = $stmt->fetchColumn();
    
    // Equipamentos em manutenção
    $stmt = $db->query("SELECT COUNT(*) FROM equipment WHERE status = 'maintenance'");
    $equipmentInMaintenance = $stmt->fetchColumn();
    
    // Total de técnicos
    $stmt = $db->query("SELECT COUNT(*) FROM technicians");
    $totalTechnicians = $stmt->fetchColumn();
    
    // Técnicos disponíveis
    $stmt = $db->query("SELECT COUNT(*) FROM technicians WHERE status = 'available'");
    $availableTechnicians = $stmt->fetchColumn();
    
    // Últimas ordens
    $stmt = $db->query("
        SELECT mo.*, e.name as equipment_name, u.name as requester_name
        FROM maintenance_orders mo
        LEFT JOIN equipment e ON mo.equipment_id = e.id
        LEFT JOIN users u ON mo.requester_id = u.id
        ORDER BY mo.created_at DESC
        LIMIT 5
    ");
    $recentOrders = $stmt->fetchAll();
    
    // Ordens por tipo (para gráfico)
    $stmt = $db->query("SELECT type, COUNT(*) as count FROM maintenance_orders GROUP BY type");
    $ordersByType = $stmt->fetchAll();
    
    // Ordens por status (para gráfico)
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM maintenance_orders GROUP BY status");
    $ordersByStatus = $stmt->fetchAll();
    
    // Manutenções por mês (últimos 6 meses)
    $stmt = $db->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM maintenance_orders
        WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $ordersByMonth = $stmt->fetchAll();
    
    // Próximas manutenções preventivas
    $stmt = $db->query("
        SELECT ps.*, e.name as equipment_name
        FROM preventive_schedules ps
        LEFT JOIN equipment e ON ps.equipment_id = e.id
        WHERE ps.status = 'active' AND ps.next_execution >= CURRENT_DATE()
        ORDER BY ps.next_execution
        LIMIT 5
    ");
    $upcomingPreventive = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Valores padrão em caso de erro
    $totalOrders = $pendingOrders = $inProgressOrders = $completedThisMonth = 0;
    $totalEquipment = $equipmentInMaintenance = 0;
    $totalTechnicians = $availableTechnicians = 0;
    $recentOrders = [];
    $ordersByType = [];
    $ordersByStatus = [];
    $ordersByMonth = [];
    $upcomingPreventive = [];
}

// Preparar dados para gráficos
$chartOrdersByType = json_encode($ordersByType);
$chartOrdersByStatus = json_encode($ordersByStatus);
$chartOrdersByMonth = json_encode($ordersByMonth);
?>

<!-- Dashboard Content -->
<div class="page-header">
    <h1>Dashboard</h1>
    <div class="page-actions">
        <a href="orders.php?action=new" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Nova Ordem
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalOrders; ?></h3>
            <p>Total de Ordens</p>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up"></i>
                <?php echo $completedThisMonth; ?> este mês
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $pendingOrders; ?></h3>
            <p>Ordens Pendentes</p>
            <div class="stat-change">
                <i class="fas fa-hourglass-half"></i>
                <?php echo $inProgressOrders; ?> em andamento
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-cogs"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalEquipment; ?></h3>
            <p>Equipamentos</p>
            <div class="stat-change">
                <i class="fas fa-wrench"></i>
                <?php echo $equipmentInMaintenance; ?> em manutenção
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalTechnicians; ?></h3>
            <p>Técnicos</p>
            <div class="stat-change positive">
                <?php echo $availableTechnicians; ?> disponíveis
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <a href="orders.php?action=new" class="quick-action-card">
        <i class="fas fa-plus-circle"></i>
        <h4>Nova Ordem</h4>
    </a>
    <a href="equipment.php?action=new" class="quick-action-card">
        <i class="fas fa-cog"></i>
        <h4>Novo Equipamento</h4>
    </a>
    <a href="technicians.php" class="quick-action-card">
        <i class="fas fa-user-cog"></i>
        <h4>Ver Técnicos</h4>
    </a>
    <a href="reports.php" class="quick-action-card">
        <i class="fas fa-chart-pie"></i>
        <h4>Relatórios</h4>
    </a>
</div>

<!-- Charts -->
<div class="charts-grid">
    <div class="chart-card">
        <div class="chart-header">
            <h3>Manutenções por Mês</h3>
        </div>
        <div class="chart-container">
            <canvas id="ordersChart"></canvas>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="chart-header">
            <h3>Por Status</h3>
        </div>
        <div class="chart-container">
            <canvas id="statusChart"></canvas>
        </div>
    </div>
</div>

<!-- Recent Orders & Upcoming Preventive -->
<div class="row">
    <div class="col" style="flex: 2;">
        <div class="card recent-orders">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list"></i> Ordens Recentes</h3>
                <a href="orders.php" class="btn btn-sm btn-outline">Ver Todas</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentOrders)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard"></i>
                    <h3>Nenhuma ordem encontrada</h3>
                    <p>Crie sua primeira ordem de manutenção.</p>
                    <a href="orders.php?action=new" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nova Ordem
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Título</th>
                                <th>Equipamento</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($order['title']); ?></td>
                                <td><?php echo htmlspecialchars($order['equipment_name'] ?? '-'); ?></td>
                                <td>
                                    <span class="priority <?php echo $order['priority']; ?>">
                                        <?php 
                                        $priorities = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'urgent' => 'Urgente'];
                                        echo $priorities[$order['priority']] ?? $order['priority'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="order-status <?php echo $order['status']; ?>">
                                        <?php 
                                        $statuses = [
                                            'pending' => 'Pendente',
                                            'in_progress' => 'Em Andamento',
                                            'waiting_parts' => 'Aguardando Peças',
                                            'completed' => 'Concluída',
                                            'cancelled' => 'Cancelada'
                                        ];
                                        echo $statuses[$order['status']] ?? $order['status'];
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($order['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-check"></i> Preventivas</h3>
                <a href="preventive.php" class="btn btn-sm btn-outline">Ver Todas</a>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingPreventive)): ?>
                <div class="empty-state" style="padding: 2rem;">
                    <i class="fas fa-calendar" style="font-size: 2rem;"></i>
                    <p>Nenhuma preventiva agendada</p>
                </div>
                <?php else: ?>
                <div class="activity-timeline">
                    <?php foreach ($upcomingPreventive as $preventive): ?>
                    <div class="activity-item">
                        <div class="activity-content">
                            <p><strong><?php echo htmlspecialchars($preventive['title']); ?></strong></p>
                            <p><?php echo htmlspecialchars($preventive['equipment_name']); ?></p>
                            <span class="time">
                                <i class="fas fa-calendar"></i>
                                <?php echo formatDate($preventive['next_execution']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = <<<SCRIPTS
<script>
// Dados dos gráficos
const ordersByMonth = {$chartOrdersByMonth};
const ordersByStatus = {$chartOrdersByStatus};

// Gráfico de Ordens por Mês
const ordersCtx = document.getElementById('ordersChart').getContext('2d');
new Chart(ordersCtx, {
    type: 'line',
    data: {
        labels: ordersByMonth.map(item => {
            const [year, month] = item.month.split('-');
            const months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            return months[parseInt(month) - 1] + '/' + year.slice(2);
        }),
        datasets: [{
            label: 'Total',
            data: ordersByMonth.map(item => item.total),
            borderColor: '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Concluídas',
            data: ordersByMonth.map(item => item.completed),
            borderColor: '#27ae60',
            backgroundColor: 'rgba(39, 174, 96, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Gráfico de Status
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusColors = {
    pending: '#f39c12',
    in_progress: '#3498db',
    waiting_parts: '#9b59b6',
    completed: '#27ae60',
    cancelled: '#e74c3c'
};
const statusLabels = {
    pending: 'Pendente',
    in_progress: 'Em Andamento',
    waiting_parts: 'Aguardando Peças',
    completed: 'Concluída',
    cancelled: 'Cancelada'
};

new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ordersByStatus.map(item => statusLabels[item.status] || item.status),
        datasets: [{
            data: ordersByStatus.map(item => item.count),
            backgroundColor: ordersByStatus.map(item => statusColors[item.status] || '#6c757d'),
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            }
        },
        cutout: '60%'
    }
});
</script>
SCRIPTS;

require_once __DIR__ . '/../includes/footer.php';
?>
