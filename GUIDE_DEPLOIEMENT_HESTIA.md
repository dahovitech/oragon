# 🚀 Guide de Déploiement - Oragon sur HestiaCP

## 📋 Informations de Déploiement

- **Serveur:** 46.202.129.197  
- **Panel:** HestiaCP  
- **Utilisateur:** mrjoker  
- **Domaine:** oragon.achatrembourse.online  
- **Projet GitHub:** https://github.com/dahovitech/oragon  
- **Environnement:** DÉVELOPPEMENT (Debug activé)

---

## 🎯 Prérequis HestiaCP

### 1. Créer le domaine dans HestiaCP

**Connectez-vous au panel HestiaCP :**
- URL : `https://46.202.129.197:8083`
- Utilisateur : `mrjoker`
- Mot de passe : `j20U5HrazAo|0F9dwmAUY`

**Étapes dans le panel :**
1. Aller dans **Web** → **Add Web Domain**
2. Domaine : `oragon.achatrembourse.online`
3. Cocher **SSL Support** (Let's Encrypt)
4. Cliquer sur **Add**

### 2. Configuration DNS
Vérifiez que votre DNS pointe vers `46.202.129.197` :
```
Type: A
Nom: oragon
Valeur: 46.202.129.197
TTL: 3600
```

---

## 🚀 Méthode 1: Déploiement Automatique HestiaCP

### Connexion SSH et déploiement

```bash
# Connexion SSH
ssh mrjoker@46.202.129.197
```

### Commande de déploiement unique
```bash
wget https://raw.githubusercontent.com/dahovitech/oragon/main/deploy_oragon_hestia.sh && chmod +x deploy_oragon_hestia.sh && ./deploy_oragon_hestia.sh
```

**C'est tout !** Le script va automatiquement :
- ✅ Vérifier l'environnement HestiaCP
- ✅ Installer Composer si nécessaire  
- ✅ Cloner le projet dans le bon répertoire
- ✅ Configurer les permissions HestiaCP
- ✅ Installer les dépendances PHP
- ✅ Configurer l'environnement de développement
- ✅ Initialiser la base de données SQLite
- ✅ Créer la configuration .htaccess pour Symfony
- ✅ Adapter la structure des fichiers pour HestiaCP

---

## 🔧 Méthode 2: Déploiement Manuel HestiaCP

### Structure des répertoires HestiaCP
```
/home/mrjoker/web/oragon.achatrembourse.online/
├── public_html/          ← Répertoire web principal
├── logs/                 ← Logs du domaine
└── stats/                ← Statistiques
```

### Étapes manuelles

```bash
# 1. Aller dans le répertoire du domaine
cd /home/mrjoker/web/oragon.achatrembourse.online/public_html

# 2. Cloner le projet
git clone https://github.com/dahovitech/oragon.git .

# 3. Installer Composer si nécessaire
curl -sS https://getcomposer.org/installer | php
mkdir -p /home/mrjoker/bin
mv composer.phar /home/mrjoker/bin/composer
chmod +x /home/mrjoker/bin/composer
export PATH="/home/mrjoker/bin:$PATH"

# 4. Installer les dépendances
composer install

# 5. Configuration environnement de développement
cp .env.dev.server .env.local

# 6. Adapter pour HestiaCP - Copier les fichiers publics
cp -r public/* ./
cp public/.htaccess ./ 2>/dev/null || true

# 7. Permissions
chmod -R 755 .
chmod -R 775 var/

# 8. Base de données
php bin/console doctrine:database:create --if-not-exists --env=dev
php bin/console doctrine:migrations:migrate --no-interaction --env=dev

# 9. Cache
php bin/console cache:clear --env=dev
```

---

## 🌐 Structure Spéciale HestiaCP

HestiaCP attend les fichiers web dans `public_html/`, mais Symfony utilise un répertoire `public/`. Le script adapte automatiquement :

```
public_html/
├── index.php              ← Copié depuis public/
├── .htaccess             ← Configuré pour Symfony
├── src/                  ← Code source Symfony
├── var/                  ← Cache et logs
├── vendor/               ← Dépendances
├── templates/            ← Templates Twig
└── public/               ← Répertoire Symfony original
```

---

## 🔍 Vérifications Post-Déploiement

### 1. Vérifier le site
```bash
curl -I http://oragon.achatrembourse.online/
```

### 2. Vérifier les logs HestiaCP
```bash
# Logs du domaine
tail -f /home/mrjoker/web/oragon.achatrembourse.online/logs/access.log
tail -f /home/mrjoker/web/oragon.achatrembourse.online/logs/error.log

# Logs Symfony
tail -f /home/mrjoker/web/oragon.achatrembourse.online/public_html/var/log/dev.log
```

### 3. Tester le profiler Symfony (environnement dev)
- URL : `http://oragon.achatrembourse.online/_profiler`

---

## 🔧 Commandes de Maintenance HestiaCP

### Mise à jour du site
```bash
cd /home/mrjoker/web/oragon.achatrembourse.online/public_html
git pull origin main
composer install
php bin/console cache:clear --env=dev
# Recopier les fichiers publics si nécessaire
cp -r public/* ./
```

### Vider le cache
```bash
cd /home/mrjoker/web/oragon.achatrembourse.online/public_html
php bin/console cache:clear --env=dev
```

### Reconfigurer les permissions
```bash
cd /home/mrjoker/web/oragon.achatrembourse.online/public_html
chmod -R 755 .
chmod -R 775 var/
```

---

## 🆘 Dépannage HestiaCP

### Problème: Site inaccessible
1. Vérifiez le domaine dans HestiaCP
2. Vérifiez les logs : `tail -f /home/mrjoker/web/oragon.achatrembourse.online/logs/error.log`
3. Vérifiez les permissions : `ls -la /home/mrjoker/web/oragon.achatrembourse.online/public_html`

### Problème: Erreur 500
1. Logs Symfony : `tail -f /home/mrjoker/web/oragon.achatrembourse.online/public_html/var/log/dev.log`
2. Vérifiez .htaccess : `cat /home/mrjoker/web/oragon.achatrembourse.online/public_html/.htaccess`
3. Videz le cache : `php bin/console cache:clear --env=dev`

### Problème: CSS/JS ne se chargent pas
1. Vérifiez que les fichiers sont dans `public_html/` : `ls -la public_html/build/`
2. Recopiez depuis public/ : `cp -r public/* ./`

---

## 📱 Accès aux Outils

- **Site web :** http://oragon.achatrembourse.online
- **Profiler Symfony :** http://oragon.achatrembourse.online/_profiler
- **Panel HestiaCP :** https://46.202.129.197:8083
- **Webmail :** https://46.202.129.197:8083/webmail

---

## 🔐 Sécurité et SSL

HestiaCP peut automatiquement configurer Let's Encrypt :
1. Dans le panel → **Web** → Domaine → **Edit**
2. Cocher **SSL Support** → **Let's Encrypt**
3. Le site sera accessible en HTTPS automatiquement

---

**🎉 Temps estimé de déploiement HestiaCP : 1-2 minutes** ⏱️
