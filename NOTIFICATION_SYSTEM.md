# Système de Notifications - Oragon

## Vue d'ensemble

Le système de notifications d'Oragon permet d'informer les utilisateurs en temps réel des événements importants liés à leurs demandes de prêt, contrats, et paiements.

## Architecture

### Entité Notification

L'entité `Notification` stocke les informations suivantes :
- `user` : Utilisateur destinataire
- `type` : Type de notification (loan_application, loan_contract, payment, etc.)
- `title` : Titre de la notification
- `message` : Message descriptif
- `data` : Données supplémentaires au format JSON
- `isRead` : Statut de lecture
- `readAt` : Date de lecture
- `createdAt` : Date de création
- `actionUrl` : URL d'action (optionnel)
- `actionLabel` : Libellé du bouton d'action (optionnel)
- `priority` : Priorité (normal, high)

### Service NotificationService

Le service `NotificationService` centralise la logique de gestion des notifications :

```php
// Créer une notification générique
$notificationService->createNotification(
    $user,
    'Titre',
    'Message',
    'type',
    '/lien-action',
    ['data' => 'value'],
    'normal'
);

// Créer une notification de demande de prêt
$notificationService->createLoanApplicationNotification(
    $user,
    'approved', // pending, approved, rejected, requires_documents
    $loanApplicationId
);

// Créer une notification de contrat
$notificationService->createLoanContractNotification(
    $user,
    'created', // created, signed, activated
    $contractId
);

// Créer une notification de paiement
$notificationService->createPaymentNotification(
    $user,
    'received', // received, overdue, reminder
    $amount,
    $paymentId
);
```

## Interface Utilisateur

### Menu Dropdown

Un dropdown de notifications est intégré dans la navigation principale :
- Affichage du nombre de notifications non lues
- Liste des dernières notifications
- Actions rapides (marquer comme lu, voir tout)

### Page de Gestion

Une page dédiée (`/notifications`) permet :
- Visualisation de toutes les notifications
- Filtrage par statut de lecture
- Actions individuelles (marquer comme lu, supprimer)
- Actions groupées (tout marquer comme lu)

## API Endpoints

- `GET /notifications` : Page de gestion des notifications
- `GET /notifications/unread` : API pour récupérer les notifications non lues (JSON)
- `POST /notifications/{id}/read` : Marquer une notification comme lue
- `POST /notifications/mark-all-read` : Marquer toutes les notifications comme lues
- `DELETE /notifications/{id}/delete` : Supprimer une notification
- `GET /notifications/count` : Compter les notifications non lues

## Intégration Automatique

Les notifications sont automatiquement créées lors de :

### Demandes de Prêt
- Soumission d'une nouvelle demande
- Passage en cours d'étude
- Approbation de la demande
- Rejet de la demande

### Contrats
- Génération d'un contrat
- Signature du contrat
- Activation du prêt

### Paiements (à implémenter)
- Réception d'un paiement
- Paiement en retard
- Rappels de paiement

## Commandes Console

### Nettoyage des Notifications
```bash
php bin/console app:notifications:clean --days=30
```
Supprime les notifications lues de plus de 30 jours.

### Test de Notifications
```bash
php bin/console app:notifications:test user@example.com --title="Test" --message="Message de test"
```
Envoie une notification de test à un utilisateur.

## Optimisations Performances

- Index sur `user_id` et `is_read` pour les requêtes fréquentes
- Pagination automatique des notifications
- Nettoyage automatique des anciennes notifications
- Cache côté client avec rafraîchissement périodique

## Tests

Des tests unitaires complets couvrent :
- Création de notifications
- Gestion des statuts de lecture
- Nettoyage automatique
- Méthodes de récupération

Exécuter les tests :
```bash
php bin/phpunit tests/Service/NotificationServiceTest.php
```

## Personnalisation

### Types de Notifications

Pour ajouter un nouveau type de notification :

1. Créer une méthode dans `NotificationService`
2. Ajouter l'icône correspondante dans le template
3. Mettre à jour les tests

### Templates

Les templates peuvent être personnalisés :
- `templates/notification/index.html.twig` : Page de gestion
- `templates/base.html.twig` : Dropdown de navigation

### Styles

Les notifications utilisent les classes Bootstrap suivantes :
- `.notification-item` : Élément de notification
- `.notification-count` : Badge de comptage
- `.notification-dropdown` : Menu dropdown

## Sécurité

- Vérification de propriété des notifications
- Protection CSRF sur toutes les actions
- Validation des données d'entrée
- Restriction d'accès par rôle utilisateur

## Maintenance

### Surveillance
- Surveiller la taille de la table notifications
- Vérifier les performances des requêtes
- Contrôler la fréquence des notifications

### Sauvegarde
Les notifications font partie des données utilisateur critiques et doivent être incluses dans les sauvegardes régulières.