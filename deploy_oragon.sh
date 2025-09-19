#!/bin/bash

# =============================================================================
# Script de déploiement automatique pour Oragon
# Serveur: oragon.achatrembourse.online
# =============================================================================

echo "🚀 Début du déploiement d'Oragon..."
echo "====================================="

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variables de configuration
PROJECT_NAME="oragon"
WEB_ROOT="/var/www/html"
PROJECT_DIR="$WEB_ROOT/$PROJECT_NAME"
BACKUP_DIR="/home/mrjoker/backups"
REPO_URL="https://github.com/dahovitech/oragon.git"

# Fonction pour afficher les messages colorés
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Vérification des prérequis
log_info "Vérification des prérequis..."

# Vérifier si on est connecté en tant que root ou sudo
if [ "$EUID" -ne 0 ]; then
    log_error "Ce script doit être exécuté avec des privilèges root (sudo)"
    exit 1
fi

# Vérifier que Git est installé
if ! command -v git &> /dev/null; then
    log_warning "Git n'est pas installé. Installation en cours..."
    apt update && apt install -y git
fi

# Vérifier que PHP est installé
if ! command -v php &> /dev/null; then
    log_error "PHP n'est pas installé. Veuillez installer PHP 8.2 ou supérieur."
    exit 1
fi

# Vérifier que Composer est installé
if ! command -v composer &> /dev/null; then
    log_warning "Composer n'est pas installé. Installation en cours..."
    cd /tmp
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi

# Créer le répertoire de sauvegarde
mkdir -p "$BACKUP_DIR"

# Sauvegarder l'ancienne version si elle existe
if [ -d "$PROJECT_DIR" ]; then
    log_info "Sauvegarde de l'ancienne version..."
    BACKUP_NAME="oragon_backup_$(date +%Y%m%d_%H%M%S)"
    cp -r "$PROJECT_DIR" "$BACKUP_DIR/$BACKUP_NAME"
    log_success "Sauvegarde créée: $BACKUP_DIR/$BACKUP_NAME"
fi

# Cloner ou mettre à jour le projet
log_info "Déploiement du code source..."

if [ -d "$PROJECT_DIR" ]; then
    log_info "Mise à jour du projet existant..."
    cd "$PROJECT_DIR"
    git fetch origin
    git reset --hard origin/main
else
    log_info "Clonage du projet..."
    mkdir -p "$WEB_ROOT"
    cd "$WEB_ROOT"
    git clone "$REPO_URL" "$PROJECT_NAME"
    cd "$PROJECT_DIR"
fi

# Configuration des permissions
log_info "Configuration des permissions..."
chown -R www-data:www-data "$PROJECT_DIR"
chmod -R 755 "$PROJECT_DIR"
chmod -R 775 "$PROJECT_DIR/var"
chmod -R 775 "$PROJECT_DIR/public"

# Installation des dépendances
log_info "Installation des dépendances PHP..."
cd "$PROJECT_DIR"
composer install --no-dev --optimize-autoloader

# Configuration de l'environnement
log_info "Configuration de l'environnement de production..."
if [ -f ".env.prod" ]; then
    cp .env.prod .env.local
    log_success "Configuration de production appliquée"
else
    log_warning "Fichier .env.prod non trouvé, utilisation de la configuration par défaut"
fi

# Initialisation de la base de données
log_info "Initialisation de la base de données..."
php bin/console doctrine:database:create --if-not-exists --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Optimisation pour la production
log_info "Optimisation pour la production..."
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
php bin/console asset-map:compile

# Configuration d'Apache/Nginx
log_info "Configuration du serveur web..."

# Créer la configuration Apache pour le site
cat > /etc/apache2/sites-available/oragon.conf << 'EOF'
<VirtualHost *:80>
    ServerName oragon.achatrembourse.online
    DocumentRoot /var/www/html/oragon/public
    
    <Directory /var/www/html/oragon/public>
        AllowOverride All
        Require all granted
        
        FallbackResource /index.php
    </Directory>
    
    # Optimisations
    <Directory /var/www/html/oragon/public/bundles>
        FallbackResource disabled
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/oragon_error.log
    CustomLog ${APACHE_LOG_DIR}/oragon_access.log combined
</VirtualHost>
EOF

# Activer le site et les modules nécessaires
a2enmod rewrite
a2ensite oragon.conf
a2dissite 000-default.conf 2>/dev/null || true

# Redémarrer Apache
systemctl reload apache2

# Vérifications finales
log_info "Vérifications finales..."

# Vérifier que les permissions sont correctes
chown -R www-data:www-data "$PROJECT_DIR"

# Vérifier que le site répond
sleep 2
HTTP_STATUS=$(curl -o /dev/null -s -w "%{http_code}\n" http://oragon.achatrembourse.online/ || echo "000")

if [ "$HTTP_STATUS" = "200" ]; then
    log_success "✅ Déploiement réussi!"
    log_success "🌐 Site accessible sur: http://oragon.achatrembourse.online"
else
    log_warning "⚠️  Le site ne répond pas correctement (Code: $HTTP_STATUS)"
    log_info "Vérifiez les logs Apache: /var/log/apache2/oragon_error.log"
fi

echo ""
echo "====================================="
echo "📋 RÉSUMÉ DU DÉPLOIEMENT"
echo "====================================="
echo "• Projet: $PROJECT_DIR"
echo "• URL: http://oragon.achatrembourse.online"
echo "• Logs d'erreur: /var/log/apache2/oragon_error.log"
echo "• Logs d'accès: /var/log/apache2/oragon_access.log"
echo "• Sauvegarde: $BACKUP_DIR"
echo ""
echo "🔧 COMMANDES UTILES:"
echo "• Voir les logs: sudo tail -f /var/log/apache2/oragon_error.log"
echo "• Vider le cache: cd $PROJECT_DIR && php bin/console cache:clear --env=prod"
echo "• Redémarrer Apache: sudo systemctl restart apache2"
echo ""

log_success "✨ Déploiement terminé!"
