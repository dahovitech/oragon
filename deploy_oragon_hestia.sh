#!/bin/bash

# =============================================================================
# Script de déploiement automatique pour Oragon sur HestiaCP
# Serveur: oragon.achatrembourse.online
# Panel: HestiaCP
# =============================================================================

echo "🚀 Début du déploiement d'Oragon sur HestiaCP (DEV)..."
echo "====================================="

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variables de configuration pour HestiaCP
PROJECT_NAME="oragon"
HESTIA_USER="mrjoker"
DOMAIN="oragon.achatrembourse.online"
WEB_ROOT="/home/$HESTIA_USER/web/$DOMAIN/public_html"
BACKUP_DIR="/home/$HESTIA_USER/backups"
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

# Vérification de l'utilisateur
log_info "Vérification de l'environnement HestiaCP..."

# Vérifier qu'on est connecté en tant que mrjoker
CURRENT_USER=$(whoami)
if [ "$CURRENT_USER" != "$HESTIA_USER" ]; then
    log_error "Ce script doit être exécuté en tant que $HESTIA_USER, utilisateur actuel: $CURRENT_USER"
    log_info "Changez d'utilisateur avec: su - $HESTIA_USER"
    exit 1
fi

# Vérifier que le répertoire web existe
if [ ! -d "/home/$HESTIA_USER/web/$DOMAIN" ]; then
    log_error "Le domaine $DOMAIN n'est pas configuré dans HestiaCP"
    log_info "Veuillez d'abord créer le domaine dans le panel HestiaCP"
    exit 1
fi

# Vérifier les prérequis
log_info "Vérification des prérequis..."

# Vérifier que Git est installé
if ! command -v git &> /dev/null; then
    log_error "Git n'est pas installé. Veuillez contacter l'administrateur."
    exit 1
fi

# Vérifier que PHP est installé
if ! command -v php &> /dev/null; then
    log_error "PHP n'est pas installé. Veuillez contacter l'administrateur."
    exit 1
fi

# Vérifier que Composer est installé
if ! command -v composer &> /dev/null; then
    log_warning "Composer n'est pas installé. Installation en cours..."
    cd /tmp
    curl -sS https://getcomposer.org/installer | php
    mkdir -p /home/$HESTIA_USER/bin
    mv composer.phar /home/$HESTIA_USER/bin/composer
    chmod +x /home/$HESTIA_USER/bin/composer
    export PATH="/home/$HESTIA_USER/bin:$PATH"
    echo 'export PATH="/home/$HESTIA_USER/bin:$PATH"' >> /home/$HESTIA_USER/.bashrc
fi

# Créer le répertoire de sauvegarde
mkdir -p "$BACKUP_DIR"

# Sauvegarder l'ancienne version si elle existe
if [ -d "$WEB_ROOT" ] && [ "$(ls -A $WEB_ROOT)" ]; then
    log_info "Sauvegarde du contenu existant..."
    BACKUP_NAME="web_backup_$(date +%Y%m%d_%H%M%S)"
    cp -r "$WEB_ROOT" "$BACKUP_DIR/$BACKUP_NAME"
    log_success "Sauvegarde créée: $BACKUP_DIR/$BACKUP_NAME"
fi

# Nettoyer le répertoire web (sauf les fichiers cachés système)
log_info "Nettoyage du répertoire web..."
if [ -d "$WEB_ROOT" ]; then
    find "$WEB_ROOT" -mindepth 1 -not -name '.htaccess' -not -name '.well-known' -delete 2>/dev/null || true
fi

# Cloner le projet
log_info "Clonage du projet Symfony..."
cd "$(dirname "$WEB_ROOT")"
git clone "$REPO_URL" temp_oragon
mv temp_oragon/* "$WEB_ROOT/" 2>/dev/null || true
mv temp_oragon/.* "$WEB_ROOT/" 2>/dev/null || true
rm -rf temp_oragon

cd "$WEB_ROOT"

# Configuration des permissions pour HestiaCP
log_info "Configuration des permissions..."
chmod -R 755 "$WEB_ROOT"
chmod -R 775 "$WEB_ROOT/var" 2>/dev/null || true
chmod -R 775 "$WEB_ROOT/public" 2>/dev/null || true

# Installation des dépendances
log_info "Installation des dépendances PHP (environnement de développement)..."
cd "$WEB_ROOT"
export PATH="/home/$HESTIA_USER/bin:$PATH"
composer install --no-interaction

# Configuration de l'environnement
log_info "Configuration de l'environnement de développement..."
if [ -f ".env.dev.server" ]; then
    cp .env.dev.server .env.local
    log_success "Configuration de développement appliquée"
else
    # Créer un fichier .env.local de base pour le développement
    cat > .env.local << 'EOF'
APP_ENV=dev
APP_DEBUG=true
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_dev.db"
EOF
    log_warning "Configuration par défaut créée"
fi

# Initialisation de la base de données
log_info "Initialisation de la base de données..."
php bin/console doctrine:database:create --if-not-exists --env=dev --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction --env=dev

# Préparation pour l'environnement de développement
log_info "Préparation de l'environnement de développement..."
php bin/console cache:clear --env=dev
php bin/console asset-map:compile 2>/dev/null || log_warning "Compilation des assets ignorée"

# Configuration spéciale pour HestiaCP
log_info "Configuration pour HestiaCP..."

# Créer ou modifier le .htaccess pour Symfony
cat > "$WEB_ROOT/public/.htaccess" << 'EOF'
# Symfony .htaccess for HestiaCP
DirectoryIndex index.php

<IfModule mod_negotiation.c>
    Options -MultiViews
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect to URI without front controller to prevent duplicate content
    RewriteCond %{ENV:REDIRECT_STATUS} ^$
    RewriteRule ^index\.php(?:/(.*)|$) %{ENV:BASE}/$1 [R=301,L]

    # If the requested filename exists, simply serve it.
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ - [L]

    # Rewrite all other queries to the front controller.
    RewriteRule ^ %{ENV:BASE}/index.php [L]
</IfModule>

<IfModule !mod_rewrite.c>
    <IfModule mod_alias.c>
        RedirectMatch 307 ^/$ /index.php/
    </IfModule>
</IfModule>
EOF

# Créer un symlink vers le répertoire public si nécessaire
if [ ! -L "$WEB_ROOT/index.php" ] && [ -f "$WEB_ROOT/public/index.php" ]; then
    log_info "Création des liens symboliques..."
    # Copier le contenu de public vers la racine web
    cp -r "$WEB_ROOT/public/"* "$WEB_ROOT/"
    cp "$WEB_ROOT/public/.htaccess" "$WEB_ROOT/" 2>/dev/null || true
fi

# Permissions finales
chmod -R 755 "$WEB_ROOT"
chmod -R 775 "$WEB_ROOT/var" 2>/dev/null || true

# Vérifications finales
log_info "Vérifications finales..."

# Test basique de l'application
sleep 2
HTTP_STATUS=$(curl -o /dev/null -s -w "%{http_code}\n" http://$DOMAIN/ 2>/dev/null || echo "000")

if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ]; then
    log_success "✅ Déploiement réussi!"
    log_success "🌐 Site accessible sur: http://$DOMAIN"
else
    log_warning "⚠️  Le site ne répond pas encore (Code: $HTTP_STATUS)"
    log_info "Il peut falloir quelques minutes pour que le DNS se propage"
fi

echo ""
echo "====================================="
echo "📋 RÉSUMÉ DU DÉPLOIEMENT HESTIACP (DEV)"
echo "====================================="
echo "• Utilisateur: $HESTIA_USER"
echo "• Domaine: $DOMAIN"
echo "• Répertoire web: $WEB_ROOT"
echo "• Environnement: DÉVELOPPEMENT (avec debug activé)"
echo "• Base de données: SQLite (var/data_dev.db)"
echo "• Sauvegarde: $BACKUP_DIR"
echo ""
echo "🔧 COMMANDES UTILES:"
echo "• Accéder au projet: cd $WEB_ROOT"
echo "• Vider le cache: php bin/console cache:clear --env=dev"
echo "• Voir les logs: tail -f var/log/dev.log"
echo "• Profiler web: http://$DOMAIN/_profiler"
echo "• Panel HestiaCP: https://$(hostname -I | awk '{print $1}'):8083"
echo ""
echo "📁 STRUCTURE DES FICHIERS:"
echo "• Code source: $WEB_ROOT"
echo "• Fichiers publics: $WEB_ROOT (symlink depuis public/)"
echo "• Base de données: $WEB_ROOT/var/data_dev.db"
echo "• Logs: $WEB_ROOT/var/log/"
echo ""

log_success "✨ Déploiement HestiaCP de développement terminé!"
