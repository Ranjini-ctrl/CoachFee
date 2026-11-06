/**
 * CoachFee – Main Application JavaScript
 */

/* ============================================================
   SIDEBAR (mobile toggle)
   ============================================================ */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.style.display = 'none';
}

/* ============================================================
   TOAST NOTIFICATIONS
   ============================================================ */
function showToast(message, type = 'success', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = {
        success: '<i data-lucide="check-circle"></i>',
        error:   '<i data-lucide="alert-circle"></i>',
        warning: '<i data-lucide="alert-triangle"></i>',
        info:    '<i data-lucide="info"></i>',
    };

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `${icons[type] || icons.info} <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--text-muted);"><i data-lucide="x"></i></button>`;
    container.appendChild(toast);
    if (typeof lucide !== 'undefined') lucide.createIcons();

    setTimeout(() => {
        toast.style.animation = 'slideOutRight .3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/* ============================================================
   MODAL HELPERS
   ============================================================ */
function openModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// ESC to close modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
            document.body.style.overflow = '';
        });
    }
});

/* ============================================================
   CONFIRM DIALOG
   ============================================================ */
function confirmAction(message, onConfirm) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay open';
    overlay.innerHTML = `
        <div class="modal" style="max-width:400px;">
            <div class="modal-header">
                <span class="modal-title" style="display:flex;align-items:center;gap:8px;">
                    <i data-lucide="alert-triangle" style="color:var(--warning);"></i> Confirm Action
                </span>
            </div>
            <div class="modal-body"><p style="color:var(--text-main);">${message}</p></div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="this.closest('.modal-overlay').remove();document.body.style.overflow='';">Cancel</button>
                <button class="btn btn-danger" id="confirmBtn">Yes, proceed</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    if (typeof lucide !== 'undefined') lucide.createIcons();
    overlay.querySelector('#confirmBtn').onclick = function() {
        overlay.remove();
        document.body.style.overflow = '';
        onConfirm();
    };
}

/* ============================================================
   FORM VALIDATION
   ============================================================ */
function validateForm(formEl) {
    let valid = true;
    formEl.querySelectorAll('[required]').forEach(input => {
        input.classList.remove('error');
        const errorEl = input.parentElement.querySelector('.form-error');
        if (errorEl) errorEl.remove();

        if (!input.value.trim()) {
            valid = false;
            input.classList.add('error');
            const err = document.createElement('div');
            err.className = 'form-error';
            err.textContent = `${input.labels[0]?.textContent || 'This field'} is required.`;
            input.after(err);
        }
    });
    return valid;
}

/* ============================================================
   TABLE SEARCH (client-side filter)
   ============================================================ */
function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

/* ============================================================
   LOADING BUTTON STATE
   ============================================================ */
function setButtonLoading(btn, loading) {
    if (loading) {
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span> Please wait…';
        btn.disabled = true;
    } else {
        btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
        btn.disabled = false;
    }
}

/* ============================================================
   NUMBER FORMATTING
   ============================================================ */
function formatINR(amount) {
    return '₹' + Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ============================================================
   CHART DEFAULT CONFIG
   ============================================================ */
if (typeof Chart !== 'undefined') {
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#7C8798';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.tooltip.backgroundColor = '#24324A';
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 10;
}

/* ============================================================
   SLIDE-OUT ANIMATION (for toasts)
   ============================================================ */
const style = document.createElement('style');
style.textContent = `
@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to   { transform: translateX(120%); opacity: 0; }
}`;
document.head.appendChild(style);

/* ============================================================
   AUTO-DISMISS ALERTS
   ============================================================ */
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity .4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    }, 5000);
});

/* ============================================================
   PRINT RECEIPT
   ============================================================ */
function printReceipt() {
    window.print();
}

/* ============================================================
   INIT
   ============================================================ */
document.addEventListener('DOMContentLoaded', function() {
    // Re-init Lucide icons for any dynamically added content
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
