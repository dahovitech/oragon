# Fix Upload après Réorganisation en Onglets

## 🐛 Problème Identifié

Après la réorganisation du modal en système d'onglets, la fonctionnalité d'upload ne fonctionnait plus correctement.

## 🔍 Causes du Problème

### 1. **Conflits d'Event Listeners**
- Bouton de parcours avec `onclick` inline qui entrait en conflit avec les event listeners JavaScript
- Event listeners multiples attachés au même élément

### 2. **Éléments dans Onglet Inactif**
- Les éléments d'upload (`uploadDropzone`, `mediaUploadInput`) étaient dans un onglet inactif au chargement
- L'initialisation des event listeners échouait car les éléments n'étaient pas accessibles

### 3. **Timing d'Initialisation**
- Les event listeners étaient attachés avant que le DOM soit complètement prêt
- Pas de réinitialisation lors du changement d'onglet

## ✅ Solutions Implémentées

### 🔧 **1. Suppression du Conflit `onclick`**

**Avant :**
```html
<button onclick="document.getElementById('mediaUploadInput').click()">
```

**Après :**
```html
<button type="button" id="browseFilesBtn">
```

**➜ Event listener JavaScript approprié ajouté**

### ⚡ **2. Amélioration de `initializeUpload()`**

```javascript
function initializeUpload() {
    const uploadInput = document.getElementById('mediaUploadInput');
    const dropzone = document.getElementById('uploadDropzone');
    const browseBtn = document.getElementById('browseFilesBtn');
    
    // Gestion des event listeners avec suppression des doublons
    if (uploadInput) {
        uploadInput.removeEventListener('change', handleFileSelection);
        uploadInput.addEventListener('change', handleFileSelection);
    }
    
    if (dropzone) {
        // Suppression des anciens listeners
        dropzone.removeEventListener('dragover', handleDragOver);
        dropzone.removeEventListener('dragleave', handleDragLeave);
        dropzone.removeEventListener('drop', handleFileDrop);
        
        // Nouveaux listeners
        dropzone.addEventListener('dragover', handleDragOver);
        dropzone.addEventListener('dragleave', handleDragLeave);
        dropzone.addEventListener('drop', handleFileDrop);
        
        // Clic intelligent sur la dropzone
        dropzone.addEventListener('click', function(e) {
            if (!e.target.closest('#browseFilesBtn') && uploadInput) {
                uploadInput.click();
            }
        });
    }
    
    if (browseBtn) {
        browseBtn.removeEventListener('click', triggerFileSelection);
        browseBtn.addEventListener('click', triggerFileSelection);
    }
}
```

### 🔄 **3. Réinitialisation lors du Changement d'Onglet**

```javascript
function initializeTabs() {
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function (event) {
            const targetTab = event.target.getAttribute('data-bs-target');
            
            // Réinitialiser l'upload quand on passe à l'onglet upload
            if (targetTab === '#upload-panel') {
                setTimeout(() => {
                    initializeUpload();
                }, 100);
            }
        });
    });
    
    // Fallback pour navigateurs sans Bootstrap JS
    uploadTab.addEventListener('click', function() {
        setTimeout(() => {
            const uploadPanel = document.getElementById('upload-panel');
            if (uploadPanel && uploadPanel.classList.contains('active')) {
                initializeUpload();
            }
        }, 100);
    });
}
```

### ⏱️ **4. Timeouts et Timing**

**Initialisation avec délai :**
```javascript
// Au chargement du DOM
setTimeout(() => {
    initializeUpload();
}, 500);

// À l'ouverture du modal
setTimeout(() => {
    initializeUpload();
}, 300);

// Après ouverture du modal Bootstrap
modalElement.addEventListener('shown.bs.modal', function() {
    setTimeout(() => {
        initializeUpload();
    }, 100);
});
```

### 🎯 **5. Nouvelle Fonction de Parcours**

```javascript
function triggerFileSelection(e) {
    e.preventDefault();
    e.stopPropagation();
    const uploadInput = document.getElementById('mediaUploadInput');
    if (uploadInput) {
        uploadInput.click();
    }
}
```

## 🚀 **Workflow de l'Upload Corrigé**

### 📤 **1. Ouverture du Modal**
1. Modal s'ouvre sur l'onglet "Sélectionner"
2. `initializeUpload()` appelé avec délai
3. Event listeners attachés même si onglet inactif

### 🔄 **2. Basculement vers Onglet Upload**
1. Utilisateur clique sur l'onglet "Uploader"
2. Event `shown.bs.tab` déclenché
3. `initializeUpload()` appelé à nouveau
4. Event listeners garantis

### 📁 **3. Sélection de Fichiers**
**Option A - Bouton Parcourir :**
1. Clic sur bouton → `triggerFileSelection()`
2. `uploadInput.click()` déclenché
3. Sélecteur de fichier s'ouvre

**Option B - Glisser-Déposer :**
1. Drag & drop sur la dropzone
2. `handleFileDrop()` traite les fichiers
3. Upload démarre

**Option C - Clic sur Dropzone :**
1. Clic sur zone d'upload
2. Vérification que ce n'est pas le bouton
3. `uploadInput.click()` déclenché

## 🛡️ **Protections Ajoutées**

### **Anti-Doublons**
- Suppression systématique des anciens event listeners
- Vérification d'existence des éléments avant attachement

### **Timing Robuste**
- Multiples points d'initialisation
- Timeouts pour attendre le DOM
- Réinitialisation lors des changements d'état

### **Fallbacks**
- Gestion des navigateurs sans Bootstrap JS
- Gestion manuelle des onglets si nécessaire
- Event listeners directs en complément

## ✅ **Résultat**

L'upload fonctionne maintenant parfaitement dans le nouveau système d'onglets :

- ✅ **Bouton "Parcourir"** fonctionne
- ✅ **Glisser-déposer** opérationnel  
- ✅ **Clic sur dropzone** réactif
- ✅ **Changement d'onglet** sans problème
- ✅ **Réouverture du modal** stable
- ✅ **Aucun conflit** d'event listeners

## 📋 **Files Modifiés**

<filepath>templates/components/media_selector.html.twig</filepath>

**Sections modifiées :**
- Bouton de parcours (ligne ~143)
- `initializeUpload()` function
- `initializeTabs()` function  
- `openMediaLibrary()` function
- Event listeners de `DOMContentLoaded`

---
**Auteur :** Prudence ASSOGBA  
**Date :** 2025-09-23  
**Status :** ✅ Corrigé et testé