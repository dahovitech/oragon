/**
 * Système de notifications en temps réel
 * Gère les notifications AJAX pour Oragon
 */

class NotificationSystem {
    constructor() {
        this.pollingInterval = null;
        this.pollingRate = 30000; // 30 secondes
        this.notificationDropdown = null;
        this.notificationBadge = null;
        this.notificationList = null;
        
        this.init();
    }

    init() {
        // Attendre que le DOM soit chargé
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        // Rechercher les éléments de notification dans le DOM
        this.notificationDropdown = document.getElementById('notification-dropdown');
        this.notificationBadge = document.getElementById('notification-count');
        this.notificationList = document.getElementById('notification-list');

        if (this.notificationDropdown) {
            this.startPolling();
            this.setupEventListeners();
        }
    }

    setupEventListeners() {
        // Écouter les clics sur les notifications pour les marquer comme lues
        if (this.notificationList) {
            this.notificationList.addEventListener('click', (e) => {
                const notificationItem = e.target.closest('[data-notification-id]');
                if (notificationItem && !notificationItem.classList.contains('read')) {
                    const notificationId = notificationItem.getAttribute('data-notification-id');
                    this.markAsRead(notificationId);
                }
            });
        }

        // Marquer toutes les notifications comme lues quand le dropdown s'ouvre
        this.notificationDropdown.addEventListener('show.bs.dropdown', () => {
            this.markAllAsRead();
        });
    }

    startPolling() {
        // Récupérer immédiatement les notifications
        this.fetchNotifications();
        
        // Puis démarrer le polling
        this.pollingInterval = setInterval(() => {
            this.fetchNotifications();
        }, this.pollingRate);
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    async fetchNotifications() {
        try {
            const response = await fetch('/notifications/unread', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.updateNotificationUI(data.notifications);
                this.updateBadge(data.unread_count);
            }
        } catch (error) {
            console.error('Erreur lors de la récupération des notifications:', error);
        }
    }

    updateNotificationUI(notifications) {
        if (!this.notificationList) return;

        if (notifications.length === 0) {
            this.notificationList.innerHTML = '<li class="dropdown-item text-muted">Aucune nouvelle notification</li>';
            return;
        }

        const notificationHTML = notifications.map(notification => `
            <li>
                <a class="dropdown-item ${notification.is_read ? 'read' : 'unread'}" 
                   href="#" 
                   data-notification-id="${notification.id}">
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-${this.getNotificationIcon(notification.type)}"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold">${notification.title}</div>
                            <div class="small text-muted">${notification.message}</div>
                            <div class="small text-muted">${this.formatDate(notification.created_at)}</div>
                        </div>
                    </div>
                </a>
            </li>
        `).join('');

        this.notificationList.innerHTML = notificationHTML;
    }

    updateBadge(count) {
        if (!this.notificationBadge) return;

        if (count > 0) {
            this.notificationBadge.textContent = count;
            this.notificationBadge.style.display = 'inline-block';
        } else {
            this.notificationBadge.style.display = 'none';
        }
    }

    async markAsRead(notificationId) {
        try {
            const response = await fetch(`/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                // Marquer visuellement comme lu
                const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.classList.remove('unread');
                    notificationItem.classList.add('read');
                }
                
                // Recharger les notifications
                this.fetchNotifications();
            }
        } catch (error) {
            console.error('Erreur lors du marquage de la notification:', error);
        }
    }

    async markAllAsRead() {
        const unreadNotifications = document.querySelectorAll('.dropdown-item.unread[data-notification-id]');
        
        for (const notification of unreadNotifications) {
            const notificationId = notification.getAttribute('data-notification-id');
            await this.markAsRead(notificationId);
        }
    }

    getNotificationIcon(type) {
        const icons = {
            'info': 'info-circle',
            'success': 'check-circle',
            'warning': 'exclamation-triangle',
            'error': 'times-circle',
            'loan_update': 'file-alt',
            'verification': 'shield-alt',
            'contract': 'file-contract'
        };

        return icons[type] || 'bell';
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInMinutes = Math.floor((now - date) / (1000 * 60));

        if (diffInMinutes < 1) {
            return 'À l\'instant';
        } else if (diffInMinutes < 60) {
            return `Il y a ${diffInMinutes} minute${diffInMinutes > 1 ? 's' : ''}`;
        } else if (diffInMinutes < 1440) {
            const hours = Math.floor(diffInMinutes / 60);
            return `Il y a ${hours} heure${hours > 1 ? 's' : ''}`;
        } else {
            const days = Math.floor(diffInMinutes / 1440);
            return `Il y a ${days} jour${days > 1 ? 's' : ''}`;
        }
    }

    // Méthode pour ajouter une notification (pour usage interne)
    addNotification(title, message, type = 'info') {
        // Cette méthode pourrait être utilisée pour ajouter des notifications locales
        // sans faire un appel serveur
        console.log('Nouvelle notification:', { title, message, type });
    }
}

// Initialiser le système de notifications
const notificationSystem = new NotificationSystem();

// Exporter pour usage global
window.NotificationSystem = notificationSystem;