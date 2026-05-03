/**
 * Sistema de Controle de Manutenção
 * JavaScript Principal
 */

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes
    initSidebar();
    initDropdowns();
    initModals();
    initAlerts();
    initForms();
    initTables();
    initTooltips();
});

/**
 * Sidebar Toggle
 */
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileToggle');
    const mainContent = document.getElementById('mainContent');
    
    // Desktop toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
        
        // Restore sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
    }
    
    // Mobile toggle
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            toggleOverlay();
        });
    }
    
    // Close sidebar on overlay click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('sidebar-overlay')) {
            sidebar.classList.remove('active');
            toggleOverlay();
        }
    });
}

function toggleOverlay() {
    let overlay = document.querySelector('.sidebar-overlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }
    
    overlay.classList.toggle('active');
}

/**
 * Dropdown Menus
 */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('button');
        
        if (trigger) {
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // Close other dropdowns
                dropdowns.forEach(d => {
                    if (d !== dropdown) {
                        d.classList.remove('active');
                    }
                });
                
                dropdown.classList.toggle('active');
            });
        }
    });
    
    // Close dropdowns on outside click
    document.addEventListener('click', function() {
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    });
    
    // Mark all notifications as read
    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            markAllNotificationsAsRead();
        });
    }
    
    // Mark individual notification as read on click
    const notificationItems = document.querySelectorAll('.notification-item[data-id]');
    notificationItems.forEach(item => {
        item.addEventListener('click', function(e) {
            if (!this.classList.contains('unread')) return;
            
            const notifId = this.getAttribute('data-id');
            markNotificationAsRead(notifId);
        });
    });
}

/**
 * Mark notification as read
 */
function markNotificationAsRead(id) {
    fetch('notifications.php?action=mark_read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationBadge();
        }
    })
    .catch(error => console.error('Error marking notification as read:', error));
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead() {
    fetch('notifications.php?action=mark_all_read', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notificationItems = document.querySelectorAll('.notification-item.unread');
            notificationItems.forEach(item => {
                item.classList.remove('unread');
            });
            
            const badge = document.querySelector('.notifications .badge');
            if (badge) {
                badge.style.display = 'none';
            }
            
            const markAllBtn = document.getElementById('markAllRead');
            if (markAllBtn) {
                markAllBtn.style.display = 'none';
            }
            
            showToast('Todas as notificações foram marcadas como lidas', 'success');
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
        showToast('Erro ao marcar notificações como lidas', 'error');
    });
}

/**
 * Update notification badge count
 */
function updateNotificationBadge() {
    fetch('notifications.php?action=count')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector('.notifications .badge');
            if (badge) {
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }
    })
    .catch(error => console.error('Error updating notification badge:', error));
}

/**
 * Load fresh notifications
 */
function loadNotifications() {
    fetch('notifications.php?action=list&limit=5')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notificationList = document.getElementById('notificationList');
            if (notificationList) {
                renderNotifications(data.notifications, notificationList);
            }
            
            const badge = document.querySelector('.notifications .badge');
            if (badge && data.unread_count > 0) {
                badge.textContent = data.unread_count;
                badge.style.display = 'inline-block';
            } else if (badge) {
                badge.style.display = 'none';
            }
        }
    })
    .catch(error => console.error('Error loading notifications:', error));
}

/**
 * Render notifications in the list
 */
function renderNotifications(notifications, container) {
    if (notifications.length === 0) {
        container.innerHTML = `
            <div class="notification-item">
                <div class="notification-content">
                    <p>Nenhuma notificação</p>
                </div>
            </div>
        `;
        return;
    }
    
    const iconMap = {
        'warning': 'fa-exclamation-circle text-warning',
        'success': 'fa-check-circle text-success',
        'info': 'fa-info-circle text-info',
        'error': 'fa-times-circle text-danger',
        'maintenance': 'fa-wrench text-warning',
        'schedule': 'fa-calendar text-info'
    };
    
    container.innerHTML = notifications.map(notif => {
        const iconClass = iconMap[notif.type] || 'fa-bell text-info';
        const unreadClass = notif.is_read == 0 ? 'unread' : '';
        const link = notif.link || '#';
        
        return `
            <a href="${link}" class="notification-item ${unreadClass}" data-id="${notif.id}">
                <i class="fas ${iconClass}"></i>
                <div class="notification-content">
                    <p>${notif.message}</p>
                    <span class="time">${formatTimeAgo(notif.created_at)}</span>
                </div>
            </a>
        `;
    }).join('');
    
    // Reattach event listeners
    container.querySelectorAll('.notification-item[data-id]').forEach(item => {
        item.addEventListener('click', function(e) {
            if (!this.classList.contains('unread')) return;
            
            const notifId = this.getAttribute('data-id');
            markNotificationAsRead(notifId);
        });
    });
}

/**
 * Format time ago helper
 */
function formatTimeAgo(datetime) {
    const now = new Date();
    const past = new Date(datetime);
    const diff = Math.floor((now - past) / 1000); // seconds
    
    if (diff < 60) return 'agora';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutos atrás';
    if (diff < 86400) return Math.floor(diff / 3600) + ' horas atrás';
    if (diff < 2592000) return Math.floor(diff / 86400) + ' dias atrás';
    if (diff < 31536000) return Math.floor(diff / 2592000) + ' meses atrás';
    return Math.floor(diff / 31536000) + ' anos atrás';
}

// Auto-refresh notifications every 30 seconds
setInterval(function() {
    if (document.querySelector('.notifications')) {
        updateNotificationBadge();
    }
}, 30000);

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    // Remove existing toast
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    toast.innerHTML = `
        <i class="fas fa-${icons[type] || icons.info}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Modal Functions
 */
function initModals() {
    // Open modal buttons
    document.querySelectorAll('[data-modal]').forEach(button => {
        button.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            openModal(modalId);
        });
    });
    
    // Close modal buttons
    document.querySelectorAll('.modal-close, [data-dismiss="modal"]').forEach(button => {
        button.addEventListener('click', function() {
            const overlay = this.closest('.modal-overlay');
            if (overlay) {
                closeModal(overlay.id);
            }
        });
    });
    
    // Close on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-overlay.active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
        }
    });
}

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
 * Auto-dismiss Alerts
 */
function initAlerts() {
    const flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        setTimeout(() => {
            flashMessage.style.opacity = '0';
            setTimeout(() => {
                flashMessage.remove();
            }, 300);
        }, 5000);
    }
}

/**
 * Form Enhancements
 */
function initForms() {
    // Password toggle
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Form validation
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
    
    // Real-time validation
    document.querySelectorAll('[data-validate-input]').forEach(input => {
        input.addEventListener('blur', function() {
            validateInput(this);
        });
    });
}

function validateForm(form) {
    let isValid = true;
    
    form.querySelectorAll('[required]').forEach(input => {
        if (!validateInput(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateInput(input) {
    const value = input.value.trim();
    const type = input.type;
    let isValid = true;
    let errorMessage = '';
    
    // Required check
    if (input.hasAttribute('required') && !value) {
        isValid = false;
        errorMessage = 'Este campo é obrigatório';
    }
    
    // Email check
    if (isValid && type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Digite um email válido';
        }
    }
    
    // Min length check
    if (isValid && input.hasAttribute('minlength')) {
        const minLength = parseInt(input.getAttribute('minlength'));
        if (value.length < minLength) {
            isValid = false;
            errorMessage = `Mínimo de ${minLength} caracteres`;
        }
    }
    
    // Update UI
    const formGroup = input.closest('.form-group');
    if (formGroup) {
        const existingError = formGroup.querySelector('.invalid-feedback');
        if (existingError) {
            existingError.remove();
        }
        
        if (!isValid) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            errorDiv.textContent = errorMessage;
            formGroup.appendChild(errorDiv);
        } else {
            input.classList.remove('is-invalid');
            if (value) {
                input.classList.add('is-valid');
            }
        }
    }
    
    return isValid;
}

/**
 * Table Enhancements
 */
function initTables() {
    // Select all checkbox
    document.querySelectorAll('.select-all').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const table = this.closest('table');
            const checkboxes = table.querySelectorAll('tbody input[type="checkbox"]');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });
    });
    
    // Delete confirmation
    document.querySelectorAll('.action-btn.delete').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Tem certeza que deseja excluir este item?')) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Tooltips
 */
function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', function() {
            const text = this.getAttribute('data-tooltip');
            
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = text;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + 'px';
            tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
            
            tooltip.classList.add('active');
        });
        
        element.addEventListener('mouseleave', function() {
            document.querySelectorAll('.tooltip').forEach(t => t.remove());
        });
    });
}

/**
 * Utility Functions
 */

// Format currency
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

// Show notification
function showNotification(message, type = 'info') {
    const container = document.querySelector('.notifications-container') || createNotificationContainer();
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    container.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function createNotificationContainer() {
    const container = document.createElement('div');
    container.className = 'notifications-container';
    document.body.appendChild(container);
    return container;
}

// Loading state
function setLoading(element, loading = true) {
    if (loading) {
        element.disabled = true;
        element.dataset.originalText = element.innerHTML;
        element.innerHTML = '<span class="loading"></span> Carregando...';
    } else {
        element.disabled = false;
        element.innerHTML = element.dataset.originalText;
    }
}

// AJAX Helper
async function fetchData(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });

        if (!response.ok) {
            return false;
        }

        return true; // ✅ sucesso
    } catch (error) {
        console.error('Fetch error:', error);
        return false;
    }
}


// Debounce function
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

// Search with debounce
const searchInput = document.querySelector('.search-box input');
if (searchInput) {
    searchInput.addEventListener('input', debounce(function() {
        const query = this.value;
        // Implement search logic
        console.log('Searching:', query);
    }, 300));
}

// Export functions for global use
window.openModal = openModal;
window.closeModal = closeModal;
window.showNotification = showNotification;
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;
window.setLoading = setLoading;
window.fetchData = fetchData;