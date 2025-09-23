# Debug du Sélecteur de Médias - Guide de résolution

## Problème identifié
L'image sélectionnée ne s'affiche pas après la fermeture du modal.

## Modifications apportées

### 1. Suppression des conflits d'événements
- **Problème**: Les attributs `onload` et `onerror` dans le HTML créaient des conflits avec la logique JavaScript
- **Solution**: Suppression de ces attributs pour laisser le JavaScript gérer l'affichage

### 2. Ajout de logs de débogage détaillés
- **openMediaLibrary()**: Trace de l'initialisation de `currentInputName`
- **selectMedia()**: Trace complète de tous les éléments DOM et de leur état

## Instructions de débogage

### Étape 1: Ouvrir la console du navigateur
1. F12 pour ouvrir les outils développeur
2. Aller dans l'onglet "Console"

### Étape 2: Tester la sélection d'image
1. Cliquer sur "Sélectionner une image"
2. Choisir une image dans le modal
3. Observer les logs dans la console

### Étape 3: Analyser les logs
Rechercher ces messages dans la console :
- `=== DEBUT openMediaLibrary ===`
- `currentInputName défini à: [nom]`
- `=== DEBUT selectMedia ===` 
- `previewContainer:` et `emptyContainer:` (doivent être des éléments HTML, pas null)
- `previewImage trouvée:` (doit être un élément IMG, pas null)
- `Image chargée avec succès` ou erreur de chargement

### Étape 4: Vérifications possibles
Si le problème persiste, vérifier :

1. **Éléments DOM**: Les IDs `mediaPreview_[inputName]` et `mediaEmpty_[inputName]` existent-ils ?
2. **CSS**: La classe `d-none` fonctionne-t-elle correctement ?
3. **URL d'image**: L'URL générée est-elle valide ?
4. **JavaScript**: Des erreurs JavaScript bloquent-elles l'exécution ?

## Corrections apportées dans ce commit

```javascript
// Avant (conflictuel)
onload="this.parentElement.parentElement.classList.remove('d-none');"

// Après (géré par JavaScript)
previewImage.onload = function() {
    console.log('Image chargée avec succès');
    previewContainer.classList.remove('d-none');
    emptyContainer.classList.add('d-none');
};
```

## État attendu après correction
1. Les logs doivent apparaître dans la console
2. L'image doit s'afficher après sélection
3. Les conteneurs doivent basculer entre visible/caché correctement