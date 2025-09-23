# Viva Shop - E-commerce Platform

A complete multilingual e-commerce platform built with Symfony, using the Oragon boilerplate.

## Project Overview

Viva Shop is a full-featured e-commerce platform with:
- Multilingual support (French, English, Spanish)
- Complete product catalog management
- Shopping cart functionality
- Order management system
- Admin dashboard
- Blog and static pages

## Features Implemented

### Phase 1: Multilingual Infrastructure ✅
- LocaleService for language management
- TranslationExtension for Twig templates
- Locale switcher functionality
- Support for fr, en, es locales

### Phase 2: Core E-commerce Entities ✅

#### Entities Created:
1. **Product** - Product catalog with SKU, pricing, stock, categories
2. **Category** - Hierarchical category system
3. **Order** - Order management with status tracking
4. **OrderItem** - Individual items in orders
5. **Review** - Product reviews and ratings
6. **Coupon** - Discount codes and promotions
7. **ShippingMethod** - Shipping options
8. **Blog** - Blog posts for content marketing
9. **Page** - Static pages (About, Contact, etc.)
10. **User** - Enhanced user entity with orders and reviews

#### Admin CRUD Interfaces:
- **Product Management** (`/admin/product`)
  - Create, read, update, delete products
  - Manage inventory, pricing, categories
  - Product images (ready for integration)
  
- **Category Management** (`/admin/category`)
  - Hierarchical category structure
  - Position-based ordering
  - Parent-child relationships
  
- **Order Management** (`/admin/order`)
  - View all orders
  - Order details with items
  - Status updates (pending, processing, shipped, delivered, cancelled)
  - Payment status tracking
  
- **Blog Management** (`/admin/blog`)
  - Create and manage blog posts
  - Draft/Published status
  - Rich content support
  
- **Page Management** (`/admin/page`)
  - Static page creation
  - SEO meta descriptions
  - Content management

- **Admin Dashboard** (`/admin`)
  - Central hub for all admin functions
  - Quick access to all CRUD interfaces

### Phase 3: Frontend Shop & Cart ✅

#### Public Pages:
1. **Home Page** (`/`)
   - Hero section
   - Features showcase
   - Call-to-action buttons
   
2. **Shop Page** (`/shop`)
   - Product grid layout
   - Category sidebar
   - Product filtering
   
3. **Category Page** (`/shop/category/{slug}`)
   - Category-specific products
   - Breadcrumb navigation
   - Product cards
   
4. **Product Detail** (`/shop/product/{slug}`)
   - Full product information
   - Add to cart functionality
   - Related products
   - Stock availability
   
5. **Shopping Cart** (`/cart`)
   - View cart items
   - Update quantities
   - Remove items
   - Order summary
   - Coupon code input (UI ready)

#### Cart Features:
- Session-based cart storage
- Add products with custom quantities
- Update item quantities
- Remove items from cart
- Clear entire cart
- Real-time total calculation
- Stock validation

### Design & UI
- **Bootstrap 5** for responsive design
- **Font Awesome 6** for icons
- **Mobile-first** approach
- **Navigation bar** with cart badge
- **Footer** with quick links and social media
- **Flash messages** for user feedback

## Technical Stack

- **Framework**: Symfony 7.2
- **Database**: SQLite (development), supports PostgreSQL/MySQL
- **ORM**: Doctrine
- **Template Engine**: Twig
- **Frontend**: Bootstrap 5, Font Awesome 6
- **JavaScript**: Webpack Encore

## Database Schema

### Key Relationships:
- Product → Category (ManyToOne)
- Order → User (ManyToOne)
- Order → OrderItems (OneToMany)
- OrderItem → Product (ManyToOne)
- Review → User, Product (ManyToOne)
- Category → Parent Category (self-referencing)

## Routes

### Frontend
- `GET /` - Home page
- `GET /shop` - Product catalog
- `GET /shop/category/{slug}` - Category products
- `GET /shop/product/{slug}` - Product details
- `GET /cart` - Shopping cart
- `POST /cart/add/{id}` - Add to cart
- `POST /cart/update/{id}` - Update cart item
- `GET /cart/remove/{id}` - Remove from cart
- `GET /cart/clear` - Clear cart

### Admin
- `GET /admin` - Admin dashboard
- `GET /admin/product` - Product list
- `GET /admin/product/new` - Create product
- `GET /admin/product/{id}` - View product
- `GET /admin/product/{id}/edit` - Edit product
- `POST /admin/product/{id}` - Delete product
- (Similar routes for category, order, blog, page)

## Installation

```bash
# Clone the repository
git clone https://github.com/dahovitech/oragon.git
cd oragon

# Install dependencies
composer install
npm install

# Setup database
php bin/console doctrine:database:create
php bin/console doctrine:schema:update --force

# Build assets
npm run build

# Start development server
symfony server:start
```

## Usage

### Admin Access
1. Navigate to `/admin`
2. Access all CRUD interfaces from the dashboard
3. Create products, categories, blog posts

### Customer Flow
1. Browse products at `/shop`
2. Filter by category
3. View product details
4. Add items to cart
5. Review cart at `/cart`
6. Proceed to checkout (coming soon)

## Next Steps (Phase 4)

- [ ] Checkout process
- [ ] Payment gateway integration (Stripe/PayPal)
- [ ] User authentication and registration
- [ ] Order confirmation emails
- [ ] Product search functionality
- [ ] Product images upload
- [ ] Wishlist functionality
- [ ] Customer reviews system
- [ ] Advanced filtering and sorting
- [ ] Multi-currency support

## Development

```bash
# Watch assets for changes
npm run watch

# Run tests
php bin/phpunit

# Code quality
php vendor/bin/phpstan analyse
php vendor/bin/php-cs-fixer fix
```

## Git Workflow

- **Main branch**: `main`
- **Development branch**: `dev-viva-shop`
- All features committed and pushed to `dev-viva-shop`

## Author

MiniMax Agent

## License

MIT License
