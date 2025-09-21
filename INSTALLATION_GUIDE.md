# 🚀 Guide d'installation - Gestionnaire de médias et Éditeur de texte

## 📋 Prérequis

- **PHP 8.1+** avec les extensions nécessaires
- **Composer** pour la gestion des dépendances PHP
- **Node.js 16+** et **npm** pour la gestion des assets
- **Base de données** (MySQL, PostgreSQL, etc.)

## 🔧 Installation étape par étape

### 1. Cloner le projet et basculer sur la branche de développement

```bash
git clone https://github.com/dahovitech/oragon.git
cd oragon
git checkout dev-media-master
```

### 2. Installation des dépendances PHP

```bash
composer install
```

### 3. Configuration de l'environnement

```bash
# Copier le fichier d'environnement
cp .env .env.local

# Modifier .env.local avec vos paramètres de base de données
# DATABASE_URL="mysql://user:password@127.0.0.1:3306/oragon_db?serverVersion=8.0"
```

### 4. Installation des dépendances JavaScript

```bash
# Nettoyer le cache npm (si nécessaire)
npm cache clean --force

# Installer les dépendances
npm install

# En cas de problème, essayez :
# rm -rf node_modules package-lock.json
# npm install
```

### 5. Compilation des assets

```bash
# Mode développement (avec watch)
npm run dev

# Ou mode développement avec surveillance automatique
npm run watch

# Mode production
npm run build
```

### 6. Configuration de la base de données

```bash
# Créer la base de données
php bin/console doctrine:database:create

# Appliquer les migrations (inclut la nouvelle table media)
php bin/console doctrine:migrations:migrate

# (Optionnel) Charger les données de test
php bin/console doctrine:fixtures:load
```

### 7. Démarrer le serveur de développement

```bash
# Serveur Symfony
symfony server:start

# Ou avec PHP natif
php -S localhost:8000 -t public/
```

## 🎯 Vérification de l'installation

1. **Interface d'administration** : `http://localhost:8000/admin`
2. **Gestionnaire de médias** : `http://localhost:8000/admin/media`

### Tests de fonctionnalité

- ✅ Upload de fichiers par drag & drop
- ✅ Recherche et filtrage des médias
- ✅ Éditeur de texte avec intégration média
- ✅ Sélection de médias dans les formulaires

## 🐛 Résolution des problèmes courants

### Erreur "$ is not defined"

**Solution temporaire :** jQuery est inclus via CDN dans la template.

**Solution permanente :**
```bash
# Réinstaller les dépendances npm
rm -rf node_modules package-lock.json
npm install
npm run dev
```

### Erreur de migration

```bash
# Vérifier l'état des migrations
php bin/console doctrine:migrations:status

# Forcer l'exécution d'une migration spécifique
php bin/console doctrine:migrations:execute --up VERSION_NUMBER
```

### Assets non compilés

```bash
# Nettoyer et recompiler
rm -rf public/build
npm run dev
```

### Permissions de fichiers

```bash
# Donner les bonnes permissions
chmod -R 755 public/uploads
chmod -R 777 var/
```

## 📁 Structure des nouveaux fichiers

```
├── src/
│   ├── Controller/Admin/MediaController.php    # Contrôleur principal
│   ├── Entity/Media.php                        # Entité média
│   └── Form/Type/MediaTextareaType.php         # Type de formulaire personnalisé
├── assets/
│   └── js/components/
│       ├── custom-editor.js                    # Éditeur de texte
│       ├── media-picker.js                     # Sélecteur de médias
│       └── media-selector.js                   # Intégration formulaires
├── templates/admin/media/
│   └── index.html.twig                         # Interface du gestionnaire
├── migrations/
│   └── Version*.php                            # Migration de la table media
└── public/uploads/media/                       # Dossier de stockage des médias
```

## 🎨 Utilisation dans vos formulaires

### Éditeur de texte avec médias

```php
// Dans votre FormType
->add('description', MediaTextareaType::class, [
    'enable_media' => true,    // Activer l'intégration média
    'enable_editor' => true,   // Activer l'éditeur riche
    'editor_height' => 400,    // Hauteur de l'éditeur
])
```

### Sélection multiple de médias

```php
// Dans votre FormType
->add('medias', EntityType::class, [
    'class' => Media::class,
    'choice_label' => 'filename',
    'multiple' => true,
    'expanded' => false,
    'attr' => ['class' => 'media-selector'],
])
```

## 📞 Support

- **Documentation complète** : <filepath>MEDIA_FEATURES.md</filepath>
- **Issues GitHub** : https://github.com/dahovitech/oragon/issues

---

**🏆 Installation réussie !** Votre gestionnaire de médias et éditeur de texte sont maintenant opérationnels.
