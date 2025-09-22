# 🚀 Guide de Test Rapide - Système Multilingue Oragon

## Installation et Configuration

### 1. Prérequis
```bash
# Vérifier PHP 8.2+
php -v

# Installer les dépendances
composer install

# Configurer la base de données (SQLite par défaut)
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
```

### 2. Charger les Données de Test
```bash
# Charger les langues (FR, EN, ES, DE)
php bin/console doctrine:fixtures:load --group=language --no-interaction

# Charger les services avec traductions
php bin/console doctrine:fixtures:load --group=service-only --append --no-interaction
```

## 🎯 Tests Fonctionnels

### Administration
1. **Accéder à l'admin** : `/admin`
2. **Gérer les langues** : `/admin/languages`
3. **Gérer les services** : `/admin/services`
4. **Éditer traductions** : `/admin/services/{id}/edit-translations`

### Frontend Multilingue
1. **Page d'accueil** : 
   - `/` → Redirection automatique vers `/fr/`
   - `/fr/services` → Services en français
   - `/en/services` → Services en anglais

2. **Services individuels** :
   - `/fr/services/web-development` → Service en français
   - `/en/services/web-development` → Service en anglais

3. **Recherche** :
   - `/fr/services/search?q=web` → Recherche en français
   - `/en/services/search?q=development` → Recherche en anglais

### API REST
```bash
# Obtenir un service en JSON
curl http://localhost/api/fr/services/web-development
curl http://localhost/api/en/services/mobile-app-development
```

## 🧪 Scénarios de Test

### Test 1 : Détection Automatique de Langue
1. **Ouvrir** `http://localhost/` dans un navigateur en français
2. **Vérifier** la redirection vers `/fr/`
3. **Changer** la langue du navigateur vers anglais
4. **Rafraîchir** et vérifier la redirection vers `/en/`

### Test 2 : Sélecteur de Langue
1. **Aller** sur `/fr/services`
2. **Cliquer** sur le sélecteur de langue (en haut à droite)
3. **Choisir** "English"
4. **Vérifier** le changement vers `/en/services` avec contenu traduit

### Test 3 : Fallback de Contenu
1. **Créer** un nouveau service en admin sans traduction anglaise
2. **Visiter** `/en/services/{nouveau-service}`
3. **Vérifier** la redirection vers `/fr/services/{nouveau-service}`

### Test 4 : Interface d'Administration
1. **Aller** sur `/admin/services`
2. **Cliquer** "Nouvelle langue" pour créer un service
3. **Après création**, cliquer "Gérer traductions"
4. **Tester** l'interface à onglets avec différentes langues
5. **Utiliser** "Copier vers..." pour dupliquer une traduction

## 📊 Vérifications de Performance

### Base de Données
```bash
# Vérifier les tables créées
php bin/console doctrine:schema:validate

# Statistiques des traductions
SELECT 
    l.name as langue,
    COUNT(st.id) as traductions,
    AVG(CASE WHEN st.title != '' AND st.description IS NOT NULL THEN 100 ELSE 0 END) as completion
FROM languages l
LEFT JOIN service_translations st ON l.id = st.language_id
GROUP BY l.id;
```

### URLs SEO
- **Vérifier** que toutes les URLs contiennent le préfixe de langue
- **Tester** le sitemap avec différentes langues
- **Valider** les meta tags par langue

## 🐛 Dépannage Courant

### Problème : URLs sans préfixe de langue
**Solution** : Vérifier que `LocaleListener` est enregistré et actif

### Problème : Traductions non affichées
**Solution** : 
1. Vérifier les fixtures : `php bin/console doctrine:fixtures:load --group=service-only`
2. Nettoyer le cache : `php bin/console cache:clear`

### Problème : Erreur de base de données
**Solution** :
1. Recréer le schéma : `php bin/console doctrine:schema:drop --force && php bin/console doctrine:schema:create`
2. Recharger les données : `php bin/console doctrine:fixtures:load --no-interaction`

## 📈 Métriques à Surveiller

1. **Temps de réponse** par langue
2. **Taux de completion** des traductions
3. **Utilisation** du sélecteur de langue
4. **Redirections** de fallback

## 🎉 Fonctionnalités Bonus Testées

- **Partage social** avec URLs localisées
- **Recherche multilingue** avec highlighting
- **Mode mobile** responsive
- **Accessibilité** avec navigation clavier
- **API REST** pour intégrations

---

**Prêt pour la production** ✅  
**Tests complets** ✅  
**Documentation** ✅  
**Performance optimisée** ✅
