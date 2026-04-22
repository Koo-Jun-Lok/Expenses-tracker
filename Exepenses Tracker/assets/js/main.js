// Main JavaScript file for Team Member 1

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Mobile detection
    const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
    
    if (isMobile) {
        document.body.classList.add('mobile-device');
        
        // Prevent zoom on input focus (iOS)
        document.addEventListener('focusin', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                setTimeout(function() {
                    e.target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            }
        });
    }
    
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize offline sync
    initializeOfflineSync();
});

// Tooltip initialization
function initializeTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(el => {
        el.addEventListener('mouseenter', showTooltip);
        el.addEventListener('mouseleave', hideTooltip);
        el.addEventListener('touchstart', showTooltip);
        el.addEventListener('touchend', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltipText = this.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.cssText = `
        position: absolute;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        z-index: 1000;
        white-space: nowrap;
    `;
    
    const rect = this.getBoundingClientRect();
    tooltip.style.top = (rect.top - 35) + 'px';
    tooltip.style.left = (rect.left + rect.width/2) + 'px';
    tooltip.style.transform = 'translateX(-50%)';
    
    this.tooltipElement = tooltip;
    document.body.appendChild(tooltip);
}

function hideTooltip() {
    if (this.tooltipElement) {
        this.tooltipElement.remove();
        this.tooltipElement = null;
    }
}

// Offline sync functionality
function initializeOfflineSync() {
    // Check if we have offline data to sync
    window.addEventListener('online', function() {
        syncOfflineData();
    });
    
    // Periodically check for connectivity
    setInterval(checkConnectivity, 30000); // Every 30 seconds
}

function checkConnectivity() {
    const isOnline = navigator.onLine;
    const statusElement = document.getElementById('connection-status') || createConnectionStatus();
    
    if (!isOnline) {
        statusElement.textContent = 'Offline - Expenses saved locally';
        statusElement.className = 'bg-yellow-100 text-yellow-800 px-4 py-2 rounded text-sm text-center';
    } else {
        statusElement.textContent = 'Online';
        statusElement.className = 'bg-green-100 text-green-800 px-4 py-2 rounded text-sm text-center';
    }
}

function createConnectionStatus() {
    const status = document.createElement('div');
    status.id = 'connection-status';
    status.className = 'fixed top-4 right-4 z-50 hidden md:block';
    document.body.appendChild(status);
    return status;
}

async function syncOfflineData() {
    const offlineExpenses = JSON.parse(localStorage.getItem('offlineExpenses') || '[]');
    
    if (offlineExpenses.length > 0) {
        console.log(`Syncing ${offlineExpenses.length} offline expenses...`);
        
        // In a real app, you would send these to your PHP backend
        // For now, we'll just log them
        offlineExpenses.forEach(expense => {
            console.log('Offline expense:', expense);
        });
        
        // Clear offline data after sync
        localStorage.removeItem('offlineExpenses');
        
        // Show notification
        showNotification(`${offlineExpenses.length} expenses synced!`);
    }
}

// Notification system
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 left-1/2 transform -translate-x-1/2 z-50 px-6 py-3 rounded-lg shadow-lg ${type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'}`;
    notification.textContent = message;
    notification.style.cssText = `
        animation: slideDown 0.3s ease-out;
        max-width: 90%;
        text-align: center;
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideUp 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from { transform: translate(-50%, -100%); opacity: 0; }
        to { transform: translate(-50%, 0); opacity: 1; }
    }
    
    @keyframes slideUp {
        from { transform: translate(-50%, 0); opacity: 1; }
        to { transform: translate(-50%, -100%); opacity: 0; }
    }
    
    .tooltip:after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: rgba(0,0,0,0.8) transparent transparent transparent;
    }
`;
document.head.appendChild(style);

// Form validation helper
function validateForm(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('border-red-500');
            isValid = false;
        } else {
            input.classList.remove('border-red-500');
        }
    });
    
    return isValid;
}

// Currency formatting
function formatCurrency(amount) {
    return 'RM ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Date formatting
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
}



// Export for use in other scripts
window.expenseTracker = {
    validateForm,
    formatCurrency,
    formatDate,
    showNotification
};
