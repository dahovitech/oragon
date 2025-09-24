# Gestion des Utilisateurs - Oragon

## Vue d'ensemble

Ce module fournit une fonctionnalité complète de gestion d'utilisateurs pour l'application Oragon. Il permet aux administrateurs de créer, modifier, supprimer et gérer les utilisateurs du système.

## Fonctionnalités MVP

### 1. Entité User Améliorée
- **Champs de base** : email, mot de passe, rôles
- **Champs personnels** : prénom, nom
- **Gestion des statuts** : actif/inactif
- **Horodatage** : date de création, modification, dernière connexion
- **Validation** : contraintes de validation pour tous les champs

### 2. Contrôleur de Gestion des Utilisateurs (`UserController`)
- **Liste des utilisateurs** : `/admin/users`
- **Création d'utilisateur** : `/admin/users/new`
- **Affichage des détails** : `/admin/users/{id}`
- **Modification d'utilisateur** : `/admin/users/{id}/edit`
- **Activation/désactivation** : `/admin/users/{id}/toggle-active`
- **Suppression d'utilisateur** : `/admin/users/{id}/delete`
- **Profil utilisateur** : `/admin/users/profile`

### 3. Formulaires
- **UserType** : Formulaire complet de création/modification d'utilisateur
- Gestion des mots de passe (optionnel en modification)
- Sélection multiple des rôles
- Validation côté client et serveur

### 4. Templates Twig
- **index.html.twig** : Liste paginée des utilisateurs avec filtres
- **new.html.twig** : Formulaire de création d'utilisateur
- **edit.html.twig** : Formulaire de modification d'utilisateur
- **show.html.twig** : Affichage des détails d'un utilisateur
- **profile.html.twig** : Gestion du profil personnel

### 5. Sécurité et Autorisations
- **Contrôle d'accès** : Seuls les administrateurs peuvent gérer les utilisateurs
- **Auto-protection** : Empêche l'auto-suppression
- **Tokens CSRF** : Protection contre les attaques CSRF
- **Hachage des mots de passe** : Utilisation de l'algorithme auto de Symfony

### 6. Repository Amélioré (`UserRepository`)
- Méthodes de recherche avancées
- Statistiques des utilisateurs
- Filtrage par rôle et statut
- Recherche textuelle

## Rôles et Permissions

### Rôles disponibles
- **ROLE_USER** : Utilisateur de base
- **ROLE_ADMIN** : Administrateur
- **ROLE_SUPER_ADMIN** : Super administrateur

### Permissions
- **ROLE_ADMIN** : Accès complet à la gestion des utilisateurs
- **Utilisateurs** : Peuvent modifier leur propre profil

## Installation et Configuration

### 1. Migration de la base de données
```bash
php bin/console doctrine:migrations:migrate
```

### 2. Création d'un administrateur initial
```bash
php bin/console app:create-admin
```

### 3. Chargement des données de test (optionnel)
```bash
php bin/console doctrine:fixtures:load
```

## Utilisation

### Accès à l'interface
1. Connectez-vous avec un compte administrateur
2. Accédez au menu "Utilisateurs" dans la sidebar
3. Gérez les utilisateurs via l'interface web

### Création d'un utilisateur
1. Cliquez sur "Nouvel utilisateur"
2. Remplissez le formulaire
3. Sélectionnez les rôles appropriés
4. Sauvegardez

### Gestion des statuts
- **Activer/Désactiver** : Bouton de basculement rapide
- **Suppression** : Confirmation requise, auto-protection active

## Sécurité

### Mesures de sécurité implémentées
- **Hachage sécurisé** des mots de passe
- **Validation stricte** des entrées
- **Protection CSRF** sur toutes les actions sensibles
- **Contrôle d'accès** basé sur les rôles
- **Limitation des auto-modifications** dangereuses

### Bonnes pratiques
- Les mots de passe doivent faire au moins 6 caractères
- Les emails doivent être uniques
- Validation côté client et serveur
- Logs des actions sensibles

## Développement

### Structure des fichiers
```
src/
├── Command/CreateAdminCommand.php
├── Controller/Admin/UserController.php
├── DataFixtures/UserFixtures.php
├── Entity/User.php
├── Form/UserType.php
├── Repository/UserRepository.php
└── Security/AppAuthenticator.php

templates/admin/user/
├── index.html.twig
├── new.html.twig
├── edit.html.twig
├── show.html.twig
└── profile.html.twig
```

### Extensions possibles
- Pagination avancée
- Filtres de recherche
- Export des données utilisateurs
- Historique des connexions
- Notifications par email
- Gestion des groupes d'utilisateurs
- API REST pour la gestion des utilisateurs

## Tests

### Données de test
Les fixtures fournissent :
- 1 Super administrateur : `superadmin@oragon.com` / `admin123`
- 1 Administrateur : `admin@oragon.com` / `admin123`
- 5 Utilisateurs réguliers : mot de passe `user123`

### Comptes de test
```
Super Admin: superadmin@oragon.com / admin123
Admin: admin@oragon.com / admin123
User: marie.dupont@example.com / user123
```

## Maintenance

### Commandes utiles
```bash
# Créer un administrateur
php bin/console app:create-admin

# Voir les utilisateurs en base
php bin/console doctrine:query:sql "SELECT * FROM user"

# Réinitialiser les fixtures
php bin/console doctrine:fixtures:load --purge-with-truncate
```

## Support

Pour toute question ou problème, consultez :
1. Les logs de l'application
2. La documentation Symfony Security
3. Les issues du projet Oragon

---

**Auteur** : MiniMax Agent  
**Version** : 1.0.0 (MVP)  
**Date** : Septembre 2025