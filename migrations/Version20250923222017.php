<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250923222017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ecommerce_cart_items (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, cart_id INTEGER NOT NULL, product_id INTEGER NOT NULL, variant_id INTEGER DEFAULT NULL, quantity INTEGER NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, line_total NUMERIC(10, 2) NOT NULL, custom_options CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_4C2D14391AD5CDBF FOREIGN KEY (cart_id) REFERENCES ecommerce_carts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4C2D14394584665A FOREIGN KEY (product_id) REFERENCES ecommerce_products (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4C2D14393B69A9AF FOREIGN KEY (variant_id) REFERENCES ecommerce_product_variants (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4C2D14391AD5CDBF ON ecommerce_cart_items (cart_id)');
        $this->addSql('CREATE INDEX IDX_4C2D14394584665A ON ecommerce_cart_items (product_id)');
        $this->addSql('CREATE INDEX IDX_4C2D14393B69A9AF ON ecommerce_cart_items (variant_id)');
        $this->addSql('CREATE UNIQUE INDEX cart_product_variant_unique ON ecommerce_cart_items (cart_id, product_id, variant_id)');
        $this->addSql('CREATE TABLE ecommerce_carts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, session_id VARCHAR(255) DEFAULT NULL, subtotal NUMERIC(10, 2) NOT NULL, tax_amount NUMERIC(10, 2) NOT NULL, shipping_cost NUMERIC(10, 2) NOT NULL, discount_amount NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, coupon_code VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , last_activity DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_49630C21A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_49630C21613FECDF ON ecommerce_carts (session_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_49630C21A76ED395 ON ecommerce_carts (user_id)');
        $this->addSql('CREATE TABLE ecommerce_order_items (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_id INTEGER NOT NULL, product_name VARCHAR(255) NOT NULL, product_sku VARCHAR(50) NOT NULL, variant_name VARCHAR(255) DEFAULT NULL, variant_sku VARCHAR(50) DEFAULT NULL, quantity INTEGER NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, line_total NUMERIC(10, 2) NOT NULL, product_snapshot CLOB DEFAULT NULL --(DC2Type:json)
        , custom_options CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_3BC179378D9F6D38 FOREIGN KEY (order_id) REFERENCES ecommerce_orders (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3BC179378D9F6D38 ON ecommerce_order_items (order_id)');
        $this->addSql('CREATE TABLE ecommerce_orders (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, order_number VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, payment_status VARCHAR(20) NOT NULL, subtotal NUMERIC(10, 2) NOT NULL, tax_amount NUMERIC(10, 2) NOT NULL, shipping_cost NUMERIC(10, 2) NOT NULL, discount_amount NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, coupon_code VARCHAR(255) DEFAULT NULL, billing_first_name VARCHAR(255) NOT NULL, billing_last_name VARCHAR(255) NOT NULL, billing_email VARCHAR(255) NOT NULL, billing_phone VARCHAR(20) DEFAULT NULL, billing_address VARCHAR(255) NOT NULL, billing_address2 VARCHAR(255) DEFAULT NULL, billing_city VARCHAR(100) NOT NULL, billing_state VARCHAR(100) DEFAULT NULL, billing_postal_code VARCHAR(20) NOT NULL, billing_country VARCHAR(2) NOT NULL, shipping_first_name VARCHAR(255) DEFAULT NULL, shipping_last_name VARCHAR(255) DEFAULT NULL, shipping_phone VARCHAR(20) DEFAULT NULL, shipping_address VARCHAR(255) DEFAULT NULL, shipping_address2 VARCHAR(255) DEFAULT NULL, shipping_city VARCHAR(100) DEFAULT NULL, shipping_state VARCHAR(100) DEFAULT NULL, shipping_postal_code VARCHAR(20) DEFAULT NULL, shipping_country VARCHAR(2) DEFAULT NULL, shipping_method VARCHAR(100) DEFAULT NULL, tracking_number VARCHAR(255) DEFAULT NULL, payment_method VARCHAR(100) DEFAULT NULL, payment_transaction_id VARCHAR(255) DEFAULT NULL, notes CLOB DEFAULT NULL, metadata CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , shipped_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_76216135A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_76216135551F0F81 ON ecommerce_orders (order_number)');
        $this->addSql('CREATE INDEX IDX_76216135A76ED395 ON ecommerce_orders (user_id)');
        $this->addSql('CREATE TABLE ecommerce_product_images (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, product_id INTEGER NOT NULL, media_id INTEGER NOT NULL, position INTEGER NOT NULL, alt VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_5DD0B57C4584665A FOREIGN KEY (product_id) REFERENCES ecommerce_products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5DD0B57CEA9FDD75 FOREIGN KEY (media_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_5DD0B57C4584665A ON ecommerce_product_images (product_id)');
        $this->addSql('CREATE INDEX IDX_5DD0B57CEA9FDD75 ON ecommerce_product_images (media_id)');
        $this->addSql('CREATE TABLE ecommerce_product_variants (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, product_id INTEGER NOT NULL, sku VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, price_adjustment NUMERIC(10, 2) DEFAULT NULL, stock INTEGER NOT NULL, is_active BOOLEAN NOT NULL, weight NUMERIC(8, 3) DEFAULT NULL, dimensions VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_38D282714584665A FOREIGN KEY (product_id) REFERENCES ecommerce_products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_38D28271F9038C4 ON ecommerce_product_variants (sku)');
        $this->addSql('CREATE INDEX IDX_38D282714584665A ON ecommerce_product_variants (product_id)');
        $this->addSql('CREATE TABLE ecommerce_products (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, category_id INTEGER DEFAULT NULL, featured_image_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, short_description CLOB DEFAULT NULL, sku VARCHAR(50) NOT NULL, price NUMERIC(10, 2) NOT NULL, compare_price NUMERIC(10, 2) DEFAULT NULL, discount_percentage NUMERIC(5, 2) DEFAULT NULL, stock INTEGER NOT NULL, track_stock BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, is_featured BOOLEAN NOT NULL, is_digital BOOLEAN NOT NULL, weight NUMERIC(8, 3) DEFAULT NULL, dimensions VARCHAR(255) DEFAULT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description CLOB DEFAULT NULL, view_count INTEGER NOT NULL, sales_count INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_28CF0AEF12469DE2 FOREIGN KEY (category_id) REFERENCES core_categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_28CF0AEF3569D950 FOREIGN KEY (featured_image_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_28CF0AEF989D9B62 ON ecommerce_products (slug)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_28CF0AEFF9038C4 ON ecommerce_products (sku)');
        $this->addSql('CREATE INDEX IDX_28CF0AEF12469DE2 ON ecommerce_products (category_id)');
        $this->addSql('CREATE INDEX IDX_28CF0AEF3569D950 ON ecommerce_products (featured_image_id)');
        $this->addSql('CREATE TABLE ecommerce_reviews (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, product_id INTEGER NOT NULL, user_id INTEGER NOT NULL, rating INTEGER NOT NULL, title VARCHAR(255) NOT NULL, content CLOB NOT NULL, is_approved BOOLEAN NOT NULL, is_recommended BOOLEAN NOT NULL, helpful_votes INTEGER NOT NULL, total_votes INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_78E2EECF4584665A FOREIGN KEY (product_id) REFERENCES ecommerce_products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_78E2EECFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_78E2EECF4584665A ON ecommerce_reviews (product_id)');
        $this->addSql('CREATE INDEX IDX_78E2EECFA76ED395 ON ecommerce_reviews (user_id)');
        $this->addSql('CREATE TABLE ecommerce_variant_attributes (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, variant_id INTEGER NOT NULL, name VARCHAR(100) NOT NULL, value VARCHAR(255) NOT NULL, position INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_D0E8DEDC3B69A9AF FOREIGN KEY (variant_id) REFERENCES ecommerce_product_variants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D0E8DEDC3B69A9AF ON ecommerce_variant_attributes (variant_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE ecommerce_cart_items');
        $this->addSql('DROP TABLE ecommerce_carts');
        $this->addSql('DROP TABLE ecommerce_order_items');
        $this->addSql('DROP TABLE ecommerce_orders');
        $this->addSql('DROP TABLE ecommerce_product_images');
        $this->addSql('DROP TABLE ecommerce_product_variants');
        $this->addSql('DROP TABLE ecommerce_products');
        $this->addSql('DROP TABLE ecommerce_reviews');
        $this->addSql('DROP TABLE ecommerce_variant_attributes');
    }
}
