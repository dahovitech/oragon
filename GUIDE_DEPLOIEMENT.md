# 🚀 Guide de Déploiement - Oragon (Environnement de Développement)

## 📋 Informations de Déploiement

- **Serveur:** 46.202.129.197
- **Utilisateur:** mrjoker
- **Domaine:** https://oragon.achatrembourse.online/
- **Projet GitHub:** https://github.com/dahovitech/oragon
- **Environnement:** DÉVELOPPEMENT (Debug activé)

---

## 🔧 Méthode 1: Déploiement Automatique (Recommandé)

### Étape 1: Connexion au serveur
```bash
ssh mrjoker@46.202.129.197
```
*Mot de passe: j20U5HrazAo|0F9dwmAUY*

### Étape 2: Télécharger le script de déploiement
```bash
# Télécharger le script depuis GitHub
wget https://raw.githubusercontent.com/dahovitech/oragon/main/deploy_oragon.sh

# Ou copier le script manuellement (voir deploy_oragon.sh)

# Rendre le script exécutable
chmod +x deploy_oragon.sh
```

### Étape 3: Exécuter le déploiement
```bash
sudo ./deploy_oragon.sh
```

Le script va automatiquement:
- ✅ Installer les prérequis (Git, Composer)
- ✅ Cloner le projet depuis GitHub
- ✅ Configurer l'environnement de production
- ✅ Installer les dépendances PHP
- ✅ Configurer Apache
- ✅ Initialiser la base de données
- ✅ Optimiser pour la production

---

## 🔧 Méthode 2: Déploiement Manuel

### Prérequis à installer

```bash
# Mise à jour du système
sudo apt update && sudo apt upgrade -y

# Installation de PHP 8.2 et modules nécessaires
sudo apt install -y php8.2 php8.2-cli php8.2-common php8.2-mysql php8.2-zip php8.2-gd php8.2-mbstring php8.2-curl php8.2-xml php8.2-bcmath php8.2-sqlite3

# Installation d'Apache
sudo apt install -y apache2

# Installation de Git
sudo apt install -y git

# Installation de Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Activation des modules Apache nécessaires
sudo a2enmod rewrite
sudo a2enmod ssl
```

### Déploiement du projet

```bash
# 1. Aller dans le répertoire web
cd /var/www/html

# 2. Cloner le projet
sudo git clone https://github.com/dahovitech/oragon.git
cd oragon

# 3. Installer les dépendances PHP (avec les outils de développement)
sudo composer install

# 4. Configurer les permissions
sudo chown -R www-data:www-data /var/www/html/oragon
sudo chmod -R 755 /var/www/html/oragon
sudo chmod -R 775 /var/www/html/oragon/var
sudo chmod -R 775 /var/www/html/oragon/public

# 5. Configurer l'environnement de développement
sudo cp .env.dev.server .env.local

# 6. Initialiser la base de données
sudo -u www-data php bin/console doctrine:database:create --if-not-exists --env=dev
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction --env=dev

# 7. Préparer l'environnement de développement
sudo -u www-data php bin/console cache:clear --env=dev
sudo -u www-data php bin/console asset-map:compile
```

### Configuration Apache

```bash
# Créer la configuration du site
sudo nano /etc/apache2/sites-available/oragon.conf
```

Contenu du fichier:
```apache
<VirtualHost *:80>
    ServerName oragon.achatrembourse.online
    DocumentRoot /var/www/html/oragon/public
    
    <Directory /var/www/html/oragon/public>
        AllowOverride All
        Require all granted
        
        FallbackResource /index.php
    </Directory>
    
    <Directory /var/www/html/oragon/public/bundles>
        FallbackResource disabled
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/oragon_error.log
    CustomLog ${APACHE_LOG_DIR}/oragon_access.log combined
</VirtualHost>
```

```bash
# Activer le site et redémarrer Apache
sudo a2ensite oragon.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

---

## 🌐 Configuration DNS

Assurez-vous que votre domaine `oragon.achatrembourse.online` pointe vers l'IP `46.202.129.197`.

Dans votre panneau de gestion DNS:
```
Type: A
Nom: oragon
Valeur: 46.202.129.197
TTL: 3600
```

---

## 🔍 Vérifications Post-Déploiement

### 1. Vérifier que le site fonctionne
```bash
curl -I http://oragon.achatrembourse.online/
```

### 2. Vérifier les logs Apache
```bash
# Logs d'erreur
sudo tail -f /var/log/apache2/oragon_error.log

# Logs d'accès
sudo tail -f /var/log/apache2/oragon_access.log
```

### 3. Vérifier les permissions
```bash
ls -la /var/www/html/oragon/
```

---

## 🔧 Commandes de Maintenance

### Mise à jour du site
```bash
cd /var/www/html/oragon
sudo git pull origin main
sudo composer install
sudo -u www-data php bin/console cache:clear --env=dev
sudo chown -R www-data:www-data /var/www/html/oragon
```

### Vider le cache
```bash
cd /var/www/html/oragon
sudo -u www-data php bin/console cache:clear --env=dev
```

### Redémarrer Apache
```bash
sudo systemctl restart apache2
```

---

## 🆘 Dépannage

### Problème: Site inaccessible
1. Vérifiez les logs: `sudo tail -f /var/log/apache2/oragon_error.log`
2. Vérifiez les permissions: `sudo chown -R www-data:www-data /var/www/html/oragon`
3. Redémarrez Apache: `sudo systemctl restart apache2`

### Problème: Erreur 500
1. Vérifiez les logs Symfony: `tail -f /var/www/html/oragon/var/log/dev.log`
2. Videz le cache: `sudo -u www-data php bin/console cache:clear --env=dev`
3. Consultez le profiler web: `http://oragon.achatrembourse.online/_profiler`

### Problème: Base de données
1. Vérifiez la configuration: `cat /var/www/html/oragon/.env.local`
2. Recréez la base: `sudo -u www-data php bin/console doctrine:database:drop --force --env=dev && sudo -u www-data php bin/console doctrine:database:create --env=dev`

---

## 📞 Support

En cas de problème, contactez l'équipe technique avec:
- Les logs d'erreur complets
- La commande qui a échoué
- Le message d'erreur exact

---

**🎉 Bon déploiement !**
