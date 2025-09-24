# Gestionnaire de Médias et Éditeur de Texte - Oragon

## 🚀 Fonctionnalités Développées

Ce projet contient l'implémentation complète d'un gestionnaire de médias et d'un éditeur de texte personnalisé pour l'application Symfony Oragon.

### 📁 Gestionnaire de Médias

#### Fonctionnalités principales :
- **Upload de médias** : Support de multiples formats (images, vidéos, audio, PDF)
- **Interface intuitive** : Glisser-déposer, sélection multiple, prévisualisation
- **Gestion complète** : CRUD complet avec suppression, édition des métadonnées
- **Recherche et filtres** : Recherche textuelle et filtres par type de média
- **Pagination** : Navigation efficace dans la bibliothèque de médias
- **Validation** : Taille maximum (10MB), types de fichiers autorisés

#### Interface d'administration :
- Route : `/admin/media`
- Navigation intégrée dans le menu d'administration
- Design responsive avec Bootstrap 5
- Modals pour upload et édition

### ✏️ Éditeur de Texte Personnalisé

#### Caractéristiques :
- **Intégration transparente** : Remplace automatiquement les champs `TextareaType`
- **Barre d'outils complète** : Formatage texte, titres, listes, liens
- **Insertion de médias** : Accès direct au gestionnaire de médias
- **Interface moderne** : Design cohérent avec l'interface d'administration
- **Responsive** : Fonctionne sur tous les appareils

#### Utilisation :
```php
// Dans un formulaire Symfony
->add('detail', MediaTextareaType::class, [
    'enable_media' => true,
    'enable_editor' => true,
    'editor_height' => 400,
])
```

```html
<!-- Auto-initialisation avec une classe CSS -->
<textarea class="custom-editor" data-enable-media="true"></textarea>
```

## 🛠️ Architecture Technique

### Composants JavaScript (jQuery)

#### 1. MediaPicker (`assets/js/components/media-picker.js`)
Composant réutilisable pour la sélection de médias :
```javascript
const picker = new MediaPicker(element, {
    multiple: true,
    showUpload: true,
    onSelect: (medias) => { /* callback */ }
});
```

#### 2. CustomEditor (`assets/js/components/custom-editor.js`)
Éditeur de texte enrichi :
```javascript
$('textarea.custom-editor').customEditor({
    height: 300,
    enableMedia: true,
    placeholder: 'Tapez votre contenu...'
});
```

#### 3. MediaSelector (`assets/js/components/media-selector.js`)
Sélecteur pour les formulaires :
```javascript
$('select.media-selector').mediaSelector({
    multiple: true,
    showPreview: true
});
```

### Types de Formulaires Symfony

#### MediaTextareaType
Type personnalisé pour les champs texte avec médias :
```php
use App\Form\Type\MediaTextareaType;

$builder->add('content', MediaTextareaType::class, [
    'enable_media' => true,
    'enable_editor' => true,
    'editor_height' => 400,
]);
```

#### MediaSelectorType
Type pour la sélection de médias :
```php
use App\Form\Type\MediaSelectorType;

$builder->add('medias', MediaSelectorType::class, [
    'multiple' => true,
    'show_preview' => true,
    'allow_upload' => true,
]);
```

### Contrôleur MediaController

#### Endpoints API :
- `POST /admin/media/upload` : Upload de fichiers
- `GET /admin/media/list` : Liste paginée des médias
- `PUT /admin/media/{id}/update` : Mise à jour des métadonnées
- `DELETE /admin/media/{id}/delete` : Suppression

### Base de Données


### CSS Intégré
Tous les styles sont intégrés dans `assets/styles/admin.scss` :
- Composants médias responsive
- Éditeur de texte moderne
- Upload par glisser-déposer
- Prévisualisations médias

### Design System
- **Bootstrap 5** : Framework CSS principal
- **Bootstrap Icons** : Icônes cohérentes
- **Variables CSS** : Thème personnalisable (mode sombre/clair)

## 🌐 Internationalisation

### Traductions Françaises
Toutes les interfaces sont traduites dans `translations/admin.fr.yaml` :
- Navigation et menus
- Messages d'erreur/succès
- Labels de formulaires
- Textes d'aide

## 📋 Installation et Configuration

### Prérequis
- PHP 8.2+
- Symfony 7.3
- Doctrine ORM
- Node.js et npm (pour la compilation des assets)

### Migration
```bash

# Compilation pour la production
npm run build
```

## 🚀 Utilisation

### 1. Accès au Gestionnaire de Médias
- Connectez-vous à l'administration
- Naviguez vers "Gestionnaire de médias" dans le menu
- Uploadez vos premiers médias

### 2. Utilisation de l'Éditeur
- Le champ "Détails" utilise automatiquement l'éditeur enrichi
- Cliquez sur l'icône médias pour insérer des images/fichiers

### 3. Association de Médias
- Cliquez sur "Sélectionner des médias"
- Choisissez vos médias dans la modal

## 🔧 Personnalisation

### Configuration de l'Éditeur
```javascript
// Options disponibles
$('#myTextarea').customEditor({
    height: 400,              // Hauteur de l'éditeur
    enableMedia: true,        // Activer l'insertion de médias
    enableFormatting: true,   // Activer le formatage
    enableLinks: true,        // Activer les liens
    placeholder: 'Tapez...',  // Texte placeholder
    toolbar: [                // Personnaliser la barre d'outils
        'bold', 'italic', '|',
        'h1', 'h2', 'h3', '|',
        'image', 'media'
    ]
});
```

### Types de Fichiers Supportés
```php
// Dans MediaController::upload()
$allowedMimeTypes = [
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
    'image/svg+xml', 'application/pdf', 'video/mp4', 'video/webm',
    'audio/mpeg', 'audio/wav', 'audio/ogg'
];
```

## 📝 Notes de Développement

### Auto-initialisation
Les composants s'initialisent automatiquement :
```javascript
// Éditeur personnalisé
$(document).ready(() => {
    $('textarea.custom-editor').customEditor();
});

// Sélecteur de médias
$(document).ready(() => {
    $('select.media-selector').mediaSelector();
});
```

### Intégration Transparente
- Aucune modification des contrôleurs existants nécessaire
- Les formulaires existants fonctionnent sans modification
- L'ajout des nouvelles fonctionnalités est optionnel

### Performance
- Chargement paresseux des médias
- Pagination côté serveur
- Compression des images automatique
- Cache des prévisualisations

## 🔐 Sécurité

### Validation des Uploads
- Vérification des types MIME
- Limitation de taille (10MB par défaut)
- Nettoyage des noms de fichiers
- Protection contre les scripts malveillants

### Authentification
- Toutes les routes d'administration sont protégées
- Validation CSRF sur les formulaires
- Contrôle d'accès basé sur les rôles Symfony

---

**Auteur** : Prudence ASSOGBA  
**Date** : 2025-09-21  
**Version** : 1.0.0  
**Branche** : dev-media-master
