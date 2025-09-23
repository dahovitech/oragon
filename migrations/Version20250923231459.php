<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for e-commerce multilingual entities
 */
final class Version20250923231459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create e-commerce multilingual entities: Products, Categories, Brands, Attributes, Orders, etc.';
    }

    public function up(Schema $schema): void
    {
        // Extend languages table
        $this->addSql('ALTER TABLE languages ADD currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE languages ADD date_format VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE languages ADD is_rtl BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE languages ADD region VARCHAR(10) DEFAULT NULL');

        // Create brands table
        $this->addSql('CREATE TABLE brands (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            logo_media_id INTEGER DEFAULT NULL,
            logo VARCHAR(255) DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (logo_media_id) REFERENCES media (id)
        )');

        // Create brand_translations table
        $this->addSql('CREATE TABLE brand_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            brand_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            meta_title VARCHAR(500) DEFAULT NULL,
            meta_description TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES languages (id),
            UNIQUE(brand_id, language_id)
        )');

        // Create categories table
        $this->addSql('CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            parent_id INTEGER DEFAULT NULL,
            image_id INTEGER DEFAULT NULL,
            image_url VARCHAR(255) DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (parent_id) REFERENCES categories (id),
            FOREIGN KEY (image_id) REFERENCES media (id)
        )');

        // Create category_translations table
        $this->addSql('CREATE TABLE category_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            category_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            meta_title VARCHAR(500) DEFAULT NULL,
            meta_description TEXT DEFAULT NULL,
            slug VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES languages (id),
            UNIQUE(category_id, language_id)
        )');

        // Create attributes table
        $this->addSql('CREATE TABLE attributes (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT "select",
            is_required BOOLEAN NOT NULL DEFAULT 0,
            is_filterable BOOLEAN NOT NULL DEFAULT 1,
            is_searchable BOOLEAN NOT NULL DEFAULT 0,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        // Create attribute_translations table
        $this->addSql('CREATE TABLE attribute_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            attribute_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (attribute_id) REFERENCES attributes (id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES languages (id),
            UNIQUE(attribute_id, language_id)
        )');

        // Create attribute_values table
        $this->addSql('CREATE TABLE attribute_values (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            attribute_id INTEGER NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            color_code VARCHAR(7) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (attribute_id) REFERENCES attributes (id) ON DELETE CASCADE
        )');

        // Create attribute_value_translations table
        $this->addSql('CREATE TABLE attribute_value_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            attribute_value_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            value VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (attribute_value_id) REFERENCES attribute_values (id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES languages (id),
            UNIQUE(attribute_value_id, language_id)
        )');

        // Create products table
        $this->addSql('CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            brand_id INTEGER DEFAULT NULL,
            category_id INTEGER DEFAULT NULL,
            sku VARCHAR(255) UNIQUE NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            compare_price DECIMAL(10,2) DEFAULT NULL,
            cost_price DECIMAL(10,2) DEFAULT NULL,
            weight DECIMAL(8,3) DEFAULT NULL,
            dimensions TEXT DEFAULT NULL,
            stock_quantity INTEGER NOT NULL DEFAULT 0,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            is_featured BOOLEAN NOT NULL DEFAULT 0,
            is_digital BOOLEAN NOT NULL DEFAULT 0,
            track_stock BOOLEAN NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (brand_id) REFERENCES brands (id),
            FOREIGN KEY (category_id) REFERENCES categories (id)
        )');

        // Create product_translations table
        $this->addSql('CREATE TABLE product_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            product_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            short_description TEXT DEFAULT NULL,
            meta_title VARCHAR(500) DEFAULT NULL,
            meta_description TEXT DEFAULT NULL,
            slug VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES languages (id),
            UNIQUE(product_id, language_id)
        )');

        // Create product_images table
        $this->addSql('CREATE TABLE product_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            product_id INTEGER NOT NULL,
            media_id INTEGER NOT NULL,
            is_main BOOLEAN NOT NULL DEFAULT 0,
            sort_order INTEGER NOT NULL DEFAULT 0,
            alt VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
            FOREIGN KEY (media_id) REFERENCES media (id)
        )');

        // Create product_attribute_values junction table
        $this->addSql('CREATE TABLE product_attribute_values (
            product_id INTEGER NOT NULL,
            attribute_value_id INTEGER NOT NULL,
            PRIMARY KEY(product_id, attribute_value_id),
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
            FOREIGN KEY (attribute_value_id) REFERENCES attribute_values (id) ON DELETE CASCADE
        )');

        // Extend users table
        $this->addSql('ALTER TABLE users ADD phone VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD birth_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD gender VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD preferences TEXT DEFAULT NULL');

        // Create addresses table
        $this->addSql('CREATE TABLE addresses (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            type VARCHAR(20) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            company VARCHAR(100) DEFAULT NULL,
            address1 VARCHAR(255) NOT NULL,
            address2 VARCHAR(255) DEFAULT NULL,
            city VARCHAR(100) NOT NULL,
            postal_code VARCHAR(20) NOT NULL,
            country VARCHAR(100) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            is_default BOOLEAN NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )');

        // Create wishlists table
        $this->addSql('CREATE TABLE wishlists (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
            UNIQUE(user_id, product_id)
        )');

        // Create reviews table
        $this->addSql('CREATE TABLE reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            rating INTEGER NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            comment TEXT NOT NULL,
            is_approved BOOLEAN NOT NULL DEFAULT 0,
            helpful_count INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users (id),
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
        )');

        // Create orders table
        $this->addSql('CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER DEFAULT NULL,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "pending",
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            shipping_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            shipping_address TEXT NOT NULL,
            billing_address TEXT NOT NULL,
            payment_method VARCHAR(50) DEFAULT NULL,
            payment_status VARCHAR(20) NOT NULL DEFAULT "pending",
            payment_transaction_id VARCHAR(255) DEFAULT NULL,
            shipping_method VARCHAR(50) DEFAULT NULL,
            tracking_number VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT "EUR",
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            shipped_at DATETIME DEFAULT NULL,
            delivered_at DATETIME DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users (id)
        )');

        // Create order_items table
        $this->addSql('CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            product_sku VARCHAR(255) DEFAULT NULL,
            product_attributes TEXT DEFAULT NULL,
            product_image_url VARCHAR(500) DEFAULT NULL,
            FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products (id)
        )');

        // Create pages table
        $this->addSql('CREATE TABLE pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            type VARCHAR(50) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        // Create page_translations table
        $this->addSql('CREATE TABLE page_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            page_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT DEFAULT NULL,
            meta_title VARCHAR(500) DEFAULT NULL,
            meta_description TEXT DEFAULT NULL,
            slug VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES languages (id),
            UNIQUE(page_id, language_id)
        )');

        // Create indexes for performance
        $this->addSql('CREATE INDEX IDX_PRODUCTS_ACTIVE ON products (is_active)');
        $this->addSql('CREATE INDEX IDX_PRODUCTS_FEATURED ON products (is_featured)');
        $this->addSql('CREATE INDEX IDX_PRODUCTS_CATEGORY ON products (category_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCTS_BRAND ON products (brand_id)');
        $this->addSql('CREATE INDEX IDX_CATEGORIES_ACTIVE ON categories (is_active)');
        $this->addSql('CREATE INDEX IDX_CATEGORIES_PARENT ON categories (parent_id)');
        $this->addSql('CREATE INDEX IDX_ORDERS_STATUS ON orders (status)');
        $this->addSql('CREATE INDEX IDX_ORDERS_USER ON orders (user_id)');
        $this->addSql('CREATE INDEX IDX_REVIEWS_PRODUCT_APPROVED ON reviews (product_id, is_approved)');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order
        $this->addSql('DROP TABLE page_translations');
        $this->addSql('DROP TABLE pages');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE reviews');
        $this->addSql('DROP TABLE wishlists');
        $this->addSql('DROP TABLE addresses');
        $this->addSql('DROP TABLE product_attribute_values');
        $this->addSql('DROP TABLE product_images');
        $this->addSql('DROP TABLE product_translations');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE attribute_value_translations');
        $this->addSql('DROP TABLE attribute_values');
        $this->addSql('DROP TABLE attribute_translations');
        $this->addSql('DROP TABLE attributes');
        $this->addSql('DROP TABLE category_translations');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE brand_translations');
        $this->addSql('DROP TABLE brands');

        // Remove added columns from existing tables
        $this->addSql('ALTER TABLE languages DROP COLUMN currency');
        $this->addSql('ALTER TABLE languages DROP COLUMN date_format');
        $this->addSql('ALTER TABLE languages DROP COLUMN is_rtl');
        $this->addSql('ALTER TABLE languages DROP COLUMN region');
        $this->addSql('ALTER TABLE users DROP COLUMN phone');
        $this->addSql('ALTER TABLE users DROP COLUMN birth_date');
        $this->addSql('ALTER TABLE users DROP COLUMN gender');
        $this->addSql('ALTER TABLE users DROP COLUMN preferences');
    }
}