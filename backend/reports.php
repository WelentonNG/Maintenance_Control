<?php
/**
 * Relatórios
 * Sistema de Controle de Manutenção
 */

$page_title = 'Relatórios';
require_once __DIR__ . '/../includes/header.php';

if (!hasPermission('manager')) {
    setFlashMessage('error', 'Você não tem permissão para acessar esta página.');
    redirect('index.php');
}

try {
    $db = getDB();
    
    // Período
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    
    // Estatísticas gerais do período
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN type = 'preventive' THEN 1 ELSE 0 END) as preventive,
            SUM(CASE WHEN type = 'corrective' THEN 1 ELSE 0 END) as corrective,
            SUM(cost) as total_cost,
            AVG(actual_hours) as avg_hours
        FROM maintenance_orders 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $stats = $stmt->fetch();
    
    // Top equipamentos com mais manutenções
    $stmt = $db->prepare("
        SELECT e.code, e.name, COUNT(mo.id) as order_count, SUM(mo.cost) as total_cost
        FROM maintenance_orders mo
        JOIN equipment e ON mo.equipment_id = e.id
        WHERE mo.created_at BETWEEN ? AND ?
        GROUP BY e.id
        ORDER BY order_count DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $topEquipment = $stmt->fetchAll();
    
    // Performance dos técnicos
    $stmt = $db->prepare("
        SELECT u.name, 
               COUNT(mo.id) as total_orders,
               SUM(CASE WHEN mo.status = 'completed' THEN 1 ELSE 0 END) as completed,
               AVG(mo.actual_hours) as avg_hours
        FROM maintenance_orders mo
        JOIN technicians t ON mo.technician_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE mo.created_at BETWEEN ? AND ?
        GROUP BY t.id
        ORDER BY completed DESC
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $techPerformance = $stmt->fetchAll();
    
    // Ordens por mês
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(cost) as cost
        FROM maintenance_orders
        WHERE created_at >= DATE_SUB(?, INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$endDate]);
    $monthlyData = $stmt->fetchAll();
    
    // Custo por categoria
    $stmt = $db->prepare("
        SELECT c.name, SUM(mo.cost) as total_cost, COUNT(mo.id) as order_count
        FROM maintenance_orders mo
        JOIN equipment e ON mo.equipment_id = e.id
        JOIN categories c ON e.category_id = c.id
        WHERE mo.created_at BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY total_cost DESC
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $costByCategory = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $stats = ['total' => 0, 'completed' => 0, 'pending' => 0, 'in_progress' => 0, 'cancelled' => 0, 'preventive' => 0, 'corrective' => 0, 'total_cost' => 0, 'avg_hours' => 0];
    $topEquipment = [];
    $techPerformance = [];
    $monthlyData = [];
    $costByCategory = [];
}

$monthlyDataJson = json_encode($monthlyData);
$costByCategoryJson = json_encode($costByCategory);
?>

<div class="page-header">
    <h1>Relatórios</h1>
    <div class="page-actions">
        <button class="btn btn-outline" onclick="window.print()">
            <i class="fas fa-print"></i>
            Imprimir
        </button>
    </div>
</div>

<!-- Filtro de período -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="d-flex gap-3 align-items-center">
            <div class="form-group mb-0">
                <label class="form-label">Data Início</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Data Fim</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top: 24px;">
                <i class="fas fa-filter"></i>
                Filtrar
            </button>
        </form>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['total'] ?? 0; ?></h3>
            <p>Total de Ordens</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['completed'] ?? 0; ?></h3>
            <p>Concluídas</p>
            <div class="stat-change">
                <?php 
                $rate = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) : 0;
                echo $rate . '% de conclusão';
                ?>
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo round($stats['avg_hours'] ?? 0, 1); ?>h</h3>
            <p>Tempo Médio</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo formatCurrency($stats['total_cost'] ?? 0); ?></h3>
            <p>Custo Total</p>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div class="charts-grid">
    <div class="chart-card">
        <div class="chart-header">
            <h3>Evolução Mensal</h3>
        </div>
        <div class="chart-container">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="chart-header">
            <h3>Custo por Categoria</h3>
        </div>
        <div class="chart-container">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
</div>

<!-- Tabelas -->
<div class="row">
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3>Top Equipamentos</h3>
            </div>
            <div class="card-body">
                <?php if (empty($topEquipment)): ?>
                <p class="text-muted text-center">Sem dados no período</p>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Equipamento</th>
                            <th>Ordens</th>
                            <th>Custo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topEquipment as $eq): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($eq['code']); ?></strong><br>
                                <small><?php echo htmlspecialchars($eq['name']); ?></small>
                            </td>
                            <td><?php echo $eq['order_count']; ?></td>
                            <td><?php echo formatCurrency($eq['total_cost'] ?? 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3>Performance dos Técnicos</h3>
            </div>
            <div class="card-body">
                <?php if (empty($techPerformance)): ?>
                <p class="text-muted text-center">Sem dados no período</p>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Técnico</th>
                            <th>Total</th>
                            <th>Concluídas</th>
                            <th>Tempo Médio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($techPerformance as $tech): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tech['name']); ?></td>
                            <td><?php echo $tech['total_orders']; ?></td>
                            <td><?php echo $tech['completed']; ?></td>
                            <td><?php echo round($tech['avg_hours'] ?? 0, 1); ?>h</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Resumo por tipo -->
<div class="card mt-4">
    <div class="card-header">
        <h3>Resumo por Tipo de Manutenção</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-3 text-center">
                <h2 class="text-primary"><?php echo $stats['preventive'] ?? 0; ?></h2>
                <p>Preventivas</p>
            </div>
            <div class="col-3 text-center">
                <h2 class="text-warning"><?php echo $stats['corrective'] ?? 0; ?></h2>
                <p>Corretivas</p>
            </div>
            <div class="col-3 text-center">
                <h2 class="text-success"><?php echo $stats['completed'] ?? 0; ?></h2>
                <p>Concluídas</p>
            </div>
            <div class="col-3 text-center">
                <h2 class="text-danger"><?php echo $stats['cancelled'] ?? 0; ?></h2>
                <p>Canceladas</p>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = <<<SCRIPTS
<script>
const monthlyData = {$monthlyDataJson};
const costByCategory = {$costByCategoryJson};

// Gráfico Mensal
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: monthlyData.map(item => {
            const [year, month] = item.month.split('-');
            const months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            return months[parseInt(month) - 1] + '/' + year.slice(2);
        }),
        datasets: [{
            label: 'Total',
            data: monthlyData.map(item => item.total),
            backgroundColor: 'rgba(52, 152, 219, 0.8)'
        }, {
            label: 'Concluídas',
            data: monthlyData.map(item => item.completed),
            backgroundColor: 'rgba(39, 174, 96, 0.8)'
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
                beginAtZero: true
            }
        }
    }
});

// Gráfico de Categoria
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryColors = ['#3498db', '#27ae60', '#f39c12', '#e74c3c', '#9b59b6', '#1abc9c'];
new Chart(categoryCtx, {
    type: 'pie',
    data: {
        labels: costByCategory.map(item => item.name),
        datasets: [{
            data: costByCategory.map(item => item.total_cost),
            backgroundColor: categoryColors.slice(0, costByCategory.length)
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            }
        }
    }
});
</script>
SCRIPTS;

require_once __DIR__ . '/../includes/footer.php';
?>
