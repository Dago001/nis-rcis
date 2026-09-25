/**
 * NIS RESIDENCE CARD ISSUANCE SYSTEM (NIS-RCIS)
 * Global Client Scripting, UI Interactivity & Input Handling
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Sidebar Toggle & Drawer Interactivity
    const menuToggleBtn = document.getElementById('menuToggleBtn');
    const appSidebar = document.getElementById('appSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (menuToggleBtn && appSidebar) {
        menuToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (window.innerWidth <= 992) {
                // Mobile behavior: open drawer
                appSidebar.classList.toggle('mobile-open');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.toggle('active');
                }
            } else {
                // Desktop behavior: collapse sidebar
                appSidebar.classList.toggle('collapsed');
            }
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            if (appSidebar) {
                appSidebar.classList.remove('mobile-open');
            }
            sidebarOverlay.classList.remove('active');
        });
    }

    // Auto-close mobile drawer when navigation links are clicked
    document.querySelectorAll('.app-sidebar .sidebar-link').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 992 && appSidebar) {
                appSidebar.classList.remove('mobile-open');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('active');
                }
            }
        });
    });

    // 2. User Profile Dropdown Toggle
    const profileTrigger = document.getElementById('profileTrigger');
    const userDropdownMenu = document.getElementById('userDropdownMenu');

    if (profileTrigger && userDropdownMenu) {
        profileTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdownMenu.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!profileTrigger.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                userDropdownMenu.classList.remove('show');
            }
        });
    }

    // 2b. Notification Bell Dropdown & Interactivity
    const notifTrigger = document.getElementById('notificationTrigger');
    const notifMenu = document.getElementById('notificationMenu');
    const notifBadge = document.getElementById('notificationBadge');
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    const notifHeaderCount = document.getElementById('notifHeaderCount');
    const notifList = document.getElementById('notificationList');

    if (notifTrigger && notifMenu) {
        notifTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (userDropdownMenu) userDropdownMenu.classList.remove('show');
            notifMenu.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!notifTrigger.contains(e.target) && !notifMenu.contains(e.target)) {
                notifMenu.classList.remove('show');
            }
        });

        // Mark All As Read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                fetch('notifications-api?action=mark_all_read', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data && data.success) {
                        if (notifBadge) notifBadge.style.display = 'none';
                        if (notifHeaderCount) notifHeaderCount.textContent = '';
                        markAllReadBtn.style.display = 'none';

                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });
                        document.querySelectorAll('.unread-dot').forEach(dot => dot.remove());
                    }
                })
                .catch(() => {});
            });
        }

        // Periodic Background Polling for Approvals (every 30 seconds)
        setInterval(function() {
            fetch('notifications-api?action=fetch', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    const count = data.unread_count || 0;
                    if (notifBadge) {
                        if (count > 0) {
                            notifBadge.textContent = count > 99 ? '99+' : count;
                            notifBadge.style.display = 'flex';
                        } else {
                            notifBadge.style.display = 'none';
                        }
                    }
                    if (notifHeaderCount) {
                        notifHeaderCount.textContent = count > 0 ? `(${count} new)` : '';
                    }
                }
            })
            .catch(() => {});
        }, 30000);
    }

    // 3. Real-Time Dynamic Uppercase Conversion for All Immigration Inputs
    document.addEventListener('input', function(e) {
        const target = e.target;
        if (target && target.tagName === 'INPUT' && (target.type === 'text' || target.type === 'search')) {
            // Keep cursor position while converting
            const start = target.selectionStart;
            const end = target.selectionEnd;
            target.value = target.value.toUpperCase();
            target.setSelectionRange(start, end);
        }
    });

    // 4. Expiry Date Auto-Calculator (Default 2 Years for Residence Card)
    const issuedOnInput = document.getElementById('issued_on');
    const expiresOnInput = document.getElementById('expires_on');

    if (issuedOnInput && expiresOnInput && !expiresOnInput.value) {
        issuedOnInput.addEventListener('change', function() {
            if (this.value) {
                const issueDate = new Date(this.value);
                if (!isNaN(issueDate.getTime())) {
                    // Standard Residence Card validity: 2 Years
                    const expiryDate = new Date(issueDate);
                    expiryDate.setFullYear(expiryDate.getFullYear() + 2);
                    expiryDate.setDate(expiryDate.getDate() - 1); // 1 day before anniversary
                    
                    const yyyy = expiryDate.getFullYear();
                    const mm = String(expiryDate.getMonth() + 1).padStart(2, '0');
                    const dd = String(expiryDate.getDate()).padStart(2, '0');
                    expiresOnInput.value = `${yyyy}-${mm}-${dd}`;
                }
            }
        });
    }

    // 5. Destructive Action Confirmations
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you wish to execute this action?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 6. Auto-dismiss Flash Alerts after 7 seconds
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 7000);
    });
});
