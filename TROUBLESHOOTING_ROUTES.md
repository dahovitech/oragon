# Guide de Résolution des Problèmes de Routes - Oragon

## Problème Résolu : "ServiceController.php does not exist"

### ✅ Actions Correctives Appliquées

1. **Réécriture complète de `config/routes/frontend.yaml`**
   - Suppression de toutes les références à `Admin/ServiceController`
   - Configuration uniquement des routes frontend

2. **Nettoyage du cache Symfony**
   - Suppression complète du dossier `var/cache/`
   - Régénération automatique au prochain accès

3. **Vérification de la configuration des routes**
   - Routes admin gérées dans `config/routes.yaml`
   - Routes frontend gérées dans `config/routes/frontend.yaml`

### 🔧 Actions à Effectuer Côté Utilisateur

#### 1. Récupérer les Dernières Modifications
```bash
git fetch origin
git pull origin dev-chrome
```

#### 2. Nettoyer le Cache Local
```bash
# Option 1: Utiliser le script fourni
bash clear_cache.sh

# Option 2: Manuellement
rm -rf var/cache/*
rm -rf var/log/*

# Option 3: Avec Symfony CLI (si disponible)
php bin/console cache:clear
php bin/console cache:clear --env=prod
```

#### 3. Vérifier les Permissions
```bash
# Linux/Mac
chmod -R 777 var/cache
chmod -R 777 var/log

# Windows (dans PowerShell)
# Les permissions sont généralement automatiques
```

#### 4. Redémarrer le Serveur Web
```bash
# Si vous utilisez le serveur Symfony
symfony server:stop
symfony server:start

# Si vous utilisez Apache/Nginx, redémarrez le service
```

### 🏗️ Structure des Routes

#### Routes Admin (`config/routes.yaml`)
```yaml
admin:
    resource: ../src/Controller/Admin/
    type: attribute
    prefix: /admin
```

#### Routes Frontend (`config/routes/frontend.yaml`)
```yaml
frontend_service_routes:
    resource: '../src/Controller/FrontendServiceController.php'
    type: attribute
```

### 🔍 Vérification

#### Tester les Routes Admin
- `/admin/service` - Liste des services
- `/admin/service/new` - Créer un service
- `/admin/service/{id}/edit` - Éditer un service

#### Tester les Routes Frontend
- `/fr/services` - Services en français
- `/en/services` - Services en anglais
- `/` - Redirection vers la page d'accueil localisée

### 🚨 En Cas de Problème Persistant

Si l'erreur persiste après ces étapes :

1. **Vérifier les fichiers locaux**
   ```bash
   find config/ -name "*.yaml" | xargs grep -i "servicecont"
   ```

2. **Vérifier l'autoload Composer**
   ```bash
   composer dump-autoload
   ```

3. **Vérifier les permissions des fichiers**
   ```bash
   ls -la config/routes/
   ```

4. **Créer un environnement de test propre**
   ```bash
   git clone https://github.com/dahovitech/oragon.git test-oragon
   cd test-oragon
   git checkout dev-chrome
   composer install
   ```

### 📞 Support

Si le problème persiste malgré toutes ces étapes, fournir :
- Le message d'erreur complet
- Le résultat de `git status`
- Le contenu de `config/routes/frontend.yaml`
- La version PHP et Symfony utilisée

### 📝 Notes Importantes

- ✅ Tous les fichiers ServiceController existent dans le repository
- ✅ La configuration des routes est correcte et testée
- ✅ Le cache a été nettoyé
- ✅ Les commits sont synchronisés avec GitHub

Le système devrait maintenant fonctionner correctement ! 🎉
