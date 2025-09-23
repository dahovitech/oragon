# API REST Documentation

Cette API REST fournit un accès complet aux fonctionnalités du système de gestion (Blog, E-commerce, Utilisateurs) avec authentification JWT et documentation Swagger.

## Table des matières

- [Installation](#installation)
- [Authentification](#authentification)
- [Endpoints disponibles](#endpoints-disponibles)
- [Tests](#tests)
- [Documentation Swagger](#documentation-swagger)

## Installation

### 1. Configuration JWT

Les clés JWT sont déjà générées et configurées. Si vous devez les régénérer :

```bash
mkdir -p config/jwt

# Générer la clé privée
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096

# Générer la clé publique
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

### 2. Variables d'environnement

Assurez-vous que les variables JWT sont configurées dans `.env` :

```env
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=mysecretpassphrase
```

### 3. Base de données

Appliquez les migrations existantes :

```bash
php bin/console doctrine:migrations:migrate
```

## Authentification

L'API utilise l'authentification JWT (JSON Web Token). Voici le processus :

### 1. Inscription

```http
POST /api/register
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password123",
    "firstName": "John",
    "lastName": "Doe"
}
```

### 2. Connexion

```http
POST /api/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password123"
}
```

Réponse :
```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "user": {
        "id": 1,
        "email": "user@example.com",
        "firstName": "John",
        "lastName": "Doe",
        "roles": ["ROLE_USER"]
    }
}
```

### 3. Utilisation du token

Incluez le token JWT dans l'en-tête Authorization pour les endpoints protégés :

```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

## Endpoints disponibles

### Authentification

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| POST | `/api/register` | Inscription d'un nouvel utilisateur | Public |
| POST | `/api/login` | Connexion et obtention du token JWT | Public |
| GET | `/api/profile` | Profil de l'utilisateur connecté | JWT |

### Blog

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/blog/posts` | Liste des articles de blog | Public |
| GET | `/api/blog/posts/{id}` | Détails d'un article | Public |
| POST | `/api/blog/posts` | Créer un article | Admin |
| PUT | `/api/blog/posts/{id}` | Modifier un article | Admin |
| DELETE | `/api/blog/posts/{id}` | Supprimer un article | Admin |
| GET | `/api/blog/categories` | Liste des catégories | Public |
| POST | `/api/blog/categories` | Créer une catégorie | Admin |

### E-commerce

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/ecommerce/products` | Liste des produits | Public |
| GET | `/api/ecommerce/products/{id}` | Détails d'un produit | Public |
| POST | `/api/ecommerce/products` | Créer un produit | Admin |
| GET | `/api/ecommerce/categories` | Liste des catégories produits | Public |
| GET | `/api/ecommerce/orders` | Commandes de l'utilisateur | User |
| GET | `/api/ecommerce/orders/{id}` | Détails d'une commande | User/Admin |
| POST | `/api/ecommerce/orders` | Créer une commande | User |

### Utilisateurs

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/users` | Liste des utilisateurs | Admin |
| GET | `/api/users/{id}` | Détails d'un utilisateur | Admin |
| POST | `/api/users` | Créer un utilisateur | Admin |
| PUT | `/api/users/{id}` | Modifier un utilisateur | Admin |
| DELETE | `/api/users/{id}` | Supprimer un utilisateur | Admin |
| GET | `/api/users/me` | Profil utilisateur actuel | User |
| PUT | `/api/users/me` | Modifier son profil | User |
| PUT | `/api/users/me/password` | Changer son mot de passe | User |

## Paramètres de requête

### Pagination

La plupart des endpoints de liste supportent la pagination :

- `page` : Numéro de page (défaut: 1)
- `limit` : Nombre d'éléments par page (défaut: 10, max: 50)

Exemple :
```http
GET /api/blog/posts?page=2&limit=20
```

### Filtres

#### Articles de blog
- `category` : ID de catégorie
- `published` : true/false pour filtrer par statut de publication

#### Produits
- `category` : ID de catégorie
- `active` : true/false pour filtrer par statut actif
- `search` : Recherche dans nom et description

#### Utilisateurs
- `search` : Recherche dans nom, prénom, email
- `role` : Filtrer par rôle

## Format de réponse

### Succès

```json
{
    "data": [...],
    "pagination": {
        "page": 1,
        "limit": 10,
        "total": 100,
        "pages": 10
    }
}
```

### Erreur

```json
{
    "error": "Message d'erreur",
    "errors": ["Détail 1", "Détail 2"] // Pour les erreurs de validation
}
```

## Codes de statut HTTP

- `200` : Succès
- `201` : Créé avec succès
- `204` : Supprimé avec succès (pas de contenu)
- `400` : Erreur de validation ou données invalides
- `401` : Non authentifié
- `403` : Accès refusé (permissions insuffisantes)
- `404` : Ressource non trouvée
- `500` : Erreur serveur

## Tests

### Exécuter les tests

```bash
# Tous les tests
php bin/phpunit

# Tests spécifiques à l'API
php bin/phpunit tests/Bundle/ApiBundle/

# Tests avec couverture
php bin/phpunit --coverage-html var/coverage
```

### Structure des tests

- `tests/Bundle/ApiBundle/Controller/AuthControllerTest.php` : Tests d'authentification
- `tests/Bundle/ApiBundle/Controller/BlogApiControllerTest.php` : Tests API blog
- Tests additionnels pour e-commerce et utilisateurs

## Documentation Swagger

### Accéder à la documentation

La documentation interactive Swagger est disponible à l'adresse :

```
http://localhost:8000/api/doc
```

### Utilisation

1. Ouvrez l'URL dans votre navigateur
2. Testez les endpoints directement depuis l'interface
3. Authentifiez-vous en cliquant sur "Authorize" et en entrant votre token JWT

### Configuration

La configuration Swagger se trouve dans :
- `config/packages/nelmio_api_doc.yaml`
- Annotations dans les contrôleurs API

## Exemples d'utilisation

### Créer un article de blog

```bash
# 1. Se connecter
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin123"}'

# 2. Utiliser le token pour créer un article
curl -X POST http://localhost:8000/api/blog/posts \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "title": "Mon nouvel article",
    "content": "Contenu de l'article...",
    "excerpt": "Résumé de l'article",
    "isPublished": true
  }'
```

### Récupérer les produits avec filtres

```bash
curl "http://localhost:8000/api/ecommerce/products?page=1&limit=20&active=true&search=laptop"
```

### Passer une commande

```bash
curl -X POST http://localhost:8000/api/ecommerce/orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "items": [
      {"productId": 1, "quantity": 2},
      {"productId": 3, "quantity": 1}
    ],
    "shippingAddress": {
      "street": "123 Rue Example",
      "city": "Paris",
      "postalCode": "75001",
      "country": "France"
    },
    "billingAddress": {
      "street": "123 Rue Example",
      "city": "Paris", 
      "postalCode": "75001",
      "country": "France"
    },
    "paymentMethod": "credit_card"
  }'
```

## Sécurité

### Bonnes pratiques

1. **Tokens JWT** : Durée de vie limitée, stockage sécurisé côté client
2. **HTTPS** : Utilisez HTTPS en production
3. **Validation** : Toutes les entrées sont validées
4. **Autorisation** : Contrôle d'accès basé sur les rôles
5. **Rate limiting** : Recommandé en production

### Rôles et permissions

- `ROLE_USER` : Utilisateur standard (profil, commandes)
- `ROLE_ADMIN` : Administrateur (gestion complète)
- `ROLE_SUPER_ADMIN` : Super administrateur

## Support et maintenance

Pour toute question ou problème :

1. Consultez les logs dans `var/log/`
2. Vérifiez la documentation Swagger
3. Exécutez les tests pour valider l'installation
4. Vérifiez la configuration JWT et les permissions