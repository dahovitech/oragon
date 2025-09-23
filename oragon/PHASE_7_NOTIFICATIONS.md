# Phase 7 : Système de Notifications et Emails

## Vue d'ensemble

La Phase 7 implémente un système complet de notifications et d'emails pour la plateforme. Ce système permet :

- Envoi de notifications multi-canaux (email, base de données, push, SMS)
- Gestion de templates d'email responsives et personnalisables
- Préférences utilisateur pour les notifications
- Interface d'administration complète
- Traitement en arrière-plan des notifications
- Gestion des échecs et des tentatives

## Architecture

### Entités principales

1. **Notification** (`src/Entity/Notification.php`)
   - Stocke toutes les notifications du système
   - Support pour différents types, priorités et canaux
   - Gestion des statuts (pending, sent, failed)
   - Planification des envois

2. **EmailTemplate** (`src/Entity/EmailTemplate.php`)
   - Templates d'email réutilisables
   - Support multilingue
   - Gestion des variables et métadonnées
   - Versioning des templates

3. **NotificationPreference** (`src/Entity/NotificationPreference.php`)
   - Préférences utilisateur par type de notification
   - Configuration des canaux de réception
   - Heures de silence et fréquence

### Services

1. **NotificationService** (`src/Service/NotificationService.php`)
   - Service principal pour l'envoi de notifications
   - Gestion des canaux multiples
   - Traitement des notifications en lot
   - Logique de retry et de planification

2. **EmailService** (`src/Service/EmailService.php`)
   - Service dédié à l'envoi d'emails
   - Traitement des templates
   - Support pour newsletters et emails personnalisés
   - Validation des templates

### Contrôleurs

1. **Admin/NotificationController** (`src/Controller/Admin/NotificationController.php`)
   - Interface d'administration des notifications
   - Gestion des templates
   - Statistiques et monitoring
   - Actions de maintenance

2. **NotificationController** (`src/Controller/NotificationController.php`)
   - Interface utilisateur pour les notifications
   - Gestion des préférences
   - API pour notifications en temps réel
   - Widget de notifications

## Fonctionnalités

### 1. Envoi de Notifications

```php
// Envoi simple à un utilisateur
$notificationService->sendToUser(
    $user,
    'welcome',
    'Bienvenue !',
    'Votre compte a été créé avec succès.',
    ['action_url' => '/dashboard']
);

// Envoi en masse
$notificationService->sendBulk(
    $users,
    'newsletter',
    'Newsletter mensuelle',
    $content,
    ['newsletter_id' => 123]
);
```

### 2. Templates d'Email

Les templates supportent :
- Variables dynamiques avec `{{ variable_name }}`
- Contenu HTML et texte
- Prévisualisation et validation
- Versioning automatique
- Support multilingue

### 3. Canaux de Notification

- **Email** : Envoi via Symfony Mailer
- **Database** : Notifications in-app
- **Push** : Support pour notifications push (extensible)
- **SMS** : Support pour SMS (extensible)

### 4. Préférences Utilisateur

Les utilisateurs peuvent configurer :
- Types de notifications à recevoir
- Canaux préférés par type
- Fréquence (immédiat, quotidien, hebdomadaire)
- Heures de silence

## Installation et Configuration

### 1. Migration de la base de données

```bash
# Appliquer les migrations
php bin/console doctrine:migrations:migrate

# Ou créer une nouvelle migration
php bin/console make:migration
```

### 2. Configuration des emails

Configurer les variables d'environnement dans `.env` :

```env
# Configuration SMTP
MAILER_DSN=smtp://username:password@smtp.gmail.com:587

# Configuration des notifications
NOTIFICATION_DEFAULT_FROM_EMAIL=noreply@example.com
NOTIFICATION_DEFAULT_FROM_NAME="Notre Plateforme"
SITE_NAME="Notre Plateforme"
SITE_URL="https://example.com"
```

### 3. Initialisation des templates par défaut

```bash
# Créer les templates par défaut
php bin/console app:notifications:init-templates

# Avec remplacement des existants
php bin/console app:notifications:init-templates --overwrite
```

## Commandes CLI

### 1. Traitement des notifications

```bash
# Traiter les notifications en attente
php bin/console app:notifications:process

# Avec retry des échecs
php bin/console app:notifications:process --retry-failed

# Limiter le nombre de notifications
php bin/console app:notifications:process --limit=50
```

### 2. Nettoyage

```bash
# Supprimer les notifications anciennes (90 jours par défaut)
php bin/console app:notifications:cleanup

# Spécifier le nombre de jours
php bin/console app:notifications:cleanup --days=30

# Mode test (affichage sans suppression)
php bin/console app:notifications:cleanup --dry-run
```

### 3. Gestion des templates

```bash
# Initialiser les templates par défaut
php bin/console app:notifications:init-templates

# Pour une locale spécifique
php bin/console app:notifications:init-templates --locale=en
```

## Interface d'Administration

Accessible via `/admin/notifications`, l'interface permet :

1. **Dashboard** : Vue d'ensemble et statistiques
2. **Liste des notifications** : Filtrage et actions en masse
3. **Gestion des templates** : Création, édition, prévisualisation
4. **Actions de maintenance** : Nettoyage, test d'emails, retry

### Fonctionnalités clés

- **Statistiques en temps réel** : Envoyées, en attente, échecs
- **Filtrage avancé** : Par type, statut, date
- **Actions rapides** : Retry, test email, nettoyage
- **Éditeur de templates** : Avec prévisualisation en temps réel

## API Utilisateur

### Endpoints disponibles

- `GET /notifications` : Liste des notifications
- `GET /notifications/unread` : Notifications non lues
- `POST /notifications/mark-read` : Marquer comme lu
- `GET /notifications/preferences` : Gérer les préférences
- `GET /notifications/count` : Nombre de notifications non lues

### Widget de notifications

Intégration facile dans le frontend :

```javascript
// Récupérer les notifications non lues
fetch('/notifications/api/latest')
    .then(response => response.json())
    .then(data => {
        updateNotificationWidget(data.notifications);
        updateNotificationCount(data.unread_count);
    });
```

## Types de Notifications par Défaut

1. **welcome** : Bienvenue nouvel utilisateur
2. **password_reset** : Réinitialisation mot de passe
3. **order_confirmation** : Confirmation de commande
4. **system_alert** : Alertes système
5. **newsletter** : Newsletter
6. **marketing** : Communications marketing
7. **security** : Alertes de sécurité

## Sécurité

- **Validation des templates** : Vérification des variables et contenu
- **Rate limiting** : Protection contre le spam
- **Échappement automatique** : Protection XSS dans les templates
- **Logs d'audit** : Traçabilité des envois

## Performance

- **Traitement asynchrone** : Envois en arrière-plan
- **Cache des templates** : Optimisation des rendus
- **Index de base de données** : Requêtes optimisées
- **Nettoyage automatique** : Suppression des anciennes notifications

## Extensibilité

Le système est conçu pour être facilement extensible :

1. **Nouveaux canaux** : Ajouter des providers (SMS, Push, Slack, etc.)
2. **Nouveaux types** : Créer des types de notifications personnalisés
3. **Templates personnalisés** : Ajouter des templates spécifiques métier
4. **Événements** : Hook sur les envois pour monitoring/analytics

## Monitoring et Logs

- **Statistiques détaillées** : Taux de livraison, ouverture, clics
- **Logs structurés** : Traçabilité complète des envois
- **Alertes automatiques** : Notification en cas de problème système
- **Dashboard de santé** : Monitoring en temps réel

## Maintenance

### Tâches recommandées

1. **Quotidienne** : Traitement des notifications en attente
2. **Hebdomadaire** : Vérification des statistiques et alertes
3. **Mensuelle** : Nettoyage des anciennes notifications
4. **Trimestrielle** : Audit des templates et préférences

### Commandes cron suggérées

```bash
# Traitement des notifications (toutes les 5 minutes)
*/5 * * * * php /path/to/project/bin/console app:notifications:process

# Nettoyage mensuel des anciennes notifications
0 2 1 * * php /path/to/project/bin/console app:notifications:cleanup --days=90
```

## Tests

Le système inclut des tests pour :
- Services de notification
- Génération de templates
- API endpoints
- Commandes CLI

```bash
# Lancer les tests de notification
php bin/phpunit tests/Service/NotificationServiceTest.php

# Tests d'intégration email
php bin/phpunit tests/Integration/EmailTest.php
```

---

**Note** : Cette phase utilise Symfony Mailer pour les envois d'email. Assurez-vous d'avoir une configuration SMTP valide pour l'environnement de production.