/**
 * Sistema de Controle de Manutenção - Modern JavaScript
 * Main Application Script
 */

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initDropdowns();
    initNotifications();
    initAnimations();
});

/**
 * Sidebar functionality
 */
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Desktop toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            
            // Save state
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            
            // Update icon
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
            } else {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-left');
            }
        });
    }
    
    // Mobile toggle
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.add('active');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }
    
    // Close sidebar on overlay click
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    // Restore sidebar state
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && sidebar && window.innerWidth > 992) {
        sidebar.classList.add('collapsed');
        if (sidebarToggle) {
            const icon = sidebarToggle.querySelector('i');
            icon.classList.remove('fa-chevron-left');
            icon.classList.add('fa-chevron-right');
        }
    }
}

/**
 * Dropdown menus
 */
function initDropdowns() {
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        const notifications = document.getElementById('notifications');
        const userMenu = document.getElementById('userMenu');
        
        if (notifications && !notifications.contains(e.target)) {
            notifications.classList.remove('active');
        }
        
        if (userMenu && !userMenu.contains(e.target)) {
            userMenu.classList.remove('active');
        }
    });
}

/**
 * Toggle notifications dropdown
 */
function toggleNotifications() {
    const notifications = document.getElementById('notifications');
    const userMenu = document.getElementById('userMenu');
    
    // Close user menu if open
    if (userMenu) userMenu.classList.remove('active');
    
    // Toggle notifications
    if (notifications) notifications.classList.toggle('active');
}

/**
 * Toggle user menu dropdown
 */
function toggleUserMenu() {
    const notifications = document.getElementById('notifications');
    const userMenu = document.getElementById('userMenu');
    
    // Close notifications if open
    if (notifications) notifications.classList.remove('active');
    
    // Toggle user menu
    if (userMenu) userMenu.classList.toggle('active');
}

/**
 * Initialize notifications
 */
function initNotifications() {
    // This would connect to a real backend in production
    console.log('Notifications initialized');
    
    // Mark all notifications as read
    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            markAllNotificationsAsRead();
        });
    }
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead() {
    const notificationItems = document.querySelectorAll('.notification-item.unread');
    const badge = document.querySelector('.notifications .badge');
    
    // Remove unread class from all notifications
    notificationItems.forEach(item => {
        item.classList.remove('unread');
    });
    
    // Update or hide badge
    if (badge) {
        badge.style.display = 'none';
        badge.textContent = '0';
    }
    
    // Show success message
    showNotification('Todas as notificações foram marcadas como lidas', 'success');
}

/**
 * Initialize animations
 */
function initAnimations() {
    // Add animation class to elements as they come into view
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-slide-up');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1
    });
    
    // Observe stat cards
    document.querySelectorAll('.stat-card').forEach(card => {
        observer.observe(card);
    });
    
    // Observe quick action cards
    document.querySelectorAll('.quick-action-card').forEach(card => {
        observer.observe(card);
    });
}

/**
 * Show notification toast
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    notification.innerHTML = `
        <i class="fas ${icons[type] || icons.info}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; margin-left: auto;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

/**
 * Toggle password visibility
 */
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.parentElement.querySelector('.toggle-password');
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

/**
 * Format date
 */
function formatDate(date, format = 'DD/MM/YYYY') {
    const d = new Date(date);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    
    return format
        .replace('DD', day)
        .replace('MM', month)
        .replace('YYYY', year);
}

/**
 * Format currency
 */
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Modal functionality
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

/**
 * Confirm dialog
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Table row selection
 */
function initTableSelection(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const checkboxes = table.querySelectorAll('input[type="checkbox"]');
    const selectAll = table.querySelector('.select-all');
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                if (cb !== selectAll) {
                    cb.checked = selectAll.checked;
                }
            });
        });
    }
}

/**
 * Search functionality
 */
function initSearch(inputId, containerId) {
    const input = document.getElementById(inputId);
    const container = document.getElementById(containerId);
    
    if (!input || !container) return;
    
    const search = debounce(function(query) {
        const items = container.querySelectorAll('[data-search]');
        const lowerQuery = query.toLowerCase();
        
        items.forEach(item => {
            const text = item.getAttribute('data-search').toLowerCase();
            if (text.includes(lowerQuery) || query === '') {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }, 300);
    
    input.addEventListener('input', function() {
        search(this.value);
    });
}

/**
 * Form validation
 */
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const inputs = form.querySelectorAll('[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
        
        // Email validation
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                input.classList.add('is-invalid');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

/**
 * Logout
 */
function logout() {
    // Clear any session data
    localStorage.removeItem('user');
    sessionStorage.clear();
    
    // Show notification
    showNotification('Você foi desconectado com sucesso!', 'success');
    
    // Redirect to login
    setTimeout(() => {
        window.location.href = 'login.html';
    }, 1000);
}

/**
 * Sample data for demo purposes
 */
const sampleData = {
    orders: [
        { id: 1, number: 'OM-001256', title: 'Manutenção preventiva mensal', equipment: 'Compressor AR-001', priority: 'medium', status: 'pending', date: '2026-01-21' },
        { id: 2, number: 'OM-001255', title: 'Correção de vazamento', equipment: 'Bomba Hidráulica BH-003', priority: 'high', status: 'in-progress', date: '2026-01-20' },
        { id: 3, number: 'OM-001254', title: 'Troca de filtros', equipment: 'Ar Condicionado AC-012', priority: 'low', status: 'completed', date: '2026-01-19' }
    ],
    equipment: [
        { id: 1, code: 'AR-001', name: 'Compressor Industrial', category: 'Compressores', location: 'Galpão A', status: 'operational' },
        { id: 2, code: 'BH-003', name: 'Bomba Hidráulica', category: 'Bombas', location: 'Galpão B', status: 'maintenance' },
        { id: 3, code: 'AC-012', name: 'Ar Condicionado Split', category: 'HVAC', location: 'Escritório', status: 'operational' }
    ],
    technicians: [
        { id: 1, name: 'João Silva', specialty: 'Elétrica', status: 'available', orders: 15 },
        { id: 2, name: 'Maria Santos', specialty: 'Mecânica', status: 'busy', orders: 12 },
        { id: 3, name: 'Pedro Costa', specialty: 'Hidráulica', status: 'available', orders: 8 }
    ]
};

// Export functions for global access
window.toggleNotifications = toggleNotifications;
window.toggleUserMenu = toggleUserMenu;
window.showNotification = showNotification;
window.togglePassword = togglePassword;
window.openModal = openModal;
window.closeModal = closeModal;
window.confirmAction = confirmAction;
window.logout = logout;
