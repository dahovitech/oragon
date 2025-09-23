<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for Order system entities - Phase 2 E-commerce Core
 */
final class Version20250923235000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create OrderStatus, ShippingMethod, PaymentMethod entities and their translations for Phase 2';
    }

    public function up(Schema $schema): void
    {
        // Create order_statuses table
        $this->addSql('CREATE TABLE order_statuses (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            code VARCHAR(50) NOT NULL UNIQUE,
            color VARCHAR(50) NOT NULL DEFAULT "#007bff",
            is_active BOOLEAN NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        // Create order_status_translations table
        $this->addSql('CREATE TABLE order_status_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            order_status_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            name VARCHAR(100) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT FK_ORDER_STATUS_TRANSLATIONS_STATUS FOREIGN KEY (order_status_id) REFERENCES order_statuses (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_ORDER_STATUS_TRANSLATIONS_LANGUAGE FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');

        // Create unique constraint and indexes for order_status_translations
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ORDER_STATUS_LANGUAGE ON order_status_translations (order_status_id, language_id)');
        $this->addSql('CREATE INDEX IDX_ORDER_STATUS_TRANSLATIONS_STATUS ON order_status_translations (order_status_id)');
        $this->addSql('CREATE INDEX IDX_ORDER_STATUS_TRANSLATIONS_LANGUAGE ON order_status_translations (language_id)');

        // Create shipping_methods table
        $this->addSql('CREATE TABLE shipping_methods (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            code VARCHAR(50) NOT NULL UNIQUE,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            estimated_days INTEGER DEFAULT NULL,
            free_shipping_threshold DECIMAL(10,2) DEFAULT NULL,
            max_weight DECIMAL(10,2) DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            configuration JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        // Create shipping_method_translations table
        $this->addSql('CREATE TABLE shipping_method_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            shipping_method_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            name VARCHAR(100) NOT NULL,
            description VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT FK_SHIPPING_METHOD_TRANSLATIONS_METHOD FOREIGN KEY (shipping_method_id) REFERENCES shipping_methods (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_SHIPPING_METHOD_TRANSLATIONS_LANGUAGE FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');

        // Create unique constraint and indexes for shipping_method_translations
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SHIPPING_METHOD_LANGUAGE ON shipping_method_translations (shipping_method_id, language_id)');
        $this->addSql('CREATE INDEX IDX_SHIPPING_METHOD_TRANSLATIONS_METHOD ON shipping_method_translations (shipping_method_id)');
        $this->addSql('CREATE INDEX IDX_SHIPPING_METHOD_TRANSLATIONS_LANGUAGE ON shipping_method_translations (language_id)');

        // Create payment_methods table
        $this->addSql('CREATE TABLE payment_methods (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            code VARCHAR(50) NOT NULL UNIQUE,
            provider VARCHAR(50) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            fee_percentage DECIMAL(5,2) DEFAULT NULL,
            fee_fixed DECIMAL(10,2) DEFAULT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            configuration JSON DEFAULT NULL,
            icon_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        // Create payment_method_translations table
        $this->addSql('CREATE TABLE payment_method_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            payment_method_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            name VARCHAR(100) NOT NULL,
            description VARCHAR(500) DEFAULT NULL,
            instructions VARCHAR(1000) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT FK_PAYMENT_METHOD_TRANSLATIONS_METHOD FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_PAYMENT_METHOD_TRANSLATIONS_LANGUAGE FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');

        // Create unique constraint and indexes for payment_method_translations
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PAYMENT_METHOD_LANGUAGE ON payment_method_translations (payment_method_id, language_id)');
        $this->addSql('CREATE INDEX IDX_PAYMENT_METHOD_TRANSLATIONS_METHOD ON payment_method_translations (payment_method_id)');
        $this->addSql('CREATE INDEX IDX_PAYMENT_METHOD_TRANSLATIONS_LANGUAGE ON payment_method_translations (language_id)');

        // Update orders table to use foreign keys instead of strings
        $this->addSql('ALTER TABLE orders ADD COLUMN payment_method_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD COLUMN shipping_method_id INTEGER DEFAULT NULL');
        
        // Add foreign key constraints to orders table
        $this->addSql('CREATE INDEX IDX_ORDERS_PAYMENT_METHOD ON orders (payment_method_id)');
        $this->addSql('CREATE INDEX IDX_ORDERS_SHIPPING_METHOD ON orders (shipping_method_id)');

        // Insert default order statuses
        $this->addSql("INSERT INTO order_statuses (code, color, is_active, sort_order, created_at, updated_at) VALUES 
            ('pending', '#ffc107', 1, 1, datetime('now'), datetime('now')),
            ('processing', '#17a2b8', 1, 2, datetime('now'), datetime('now')),
            ('shipped', '#fd7e14', 1, 3, datetime('now'), datetime('now')),
            ('delivered', '#28a745', 1, 4, datetime('now'), datetime('now')),
            ('cancelled', '#dc3545', 1, 5, datetime('now'), datetime('now'))");

        // Insert default shipping methods
        $this->addSql("INSERT INTO shipping_methods (code, price, estimated_days, is_active, sort_order, created_at, updated_at) VALUES 
            ('standard', 5.99, 5, 1, 1, datetime('now'), datetime('now')),
            ('express', 12.99, 2, 1, 2, datetime('now'), datetime('now')),
            ('free', 0.00, 7, 1, 3, datetime('now'), datetime('now'))");

        // Insert default payment methods
        $this->addSql("INSERT INTO payment_methods (code, provider, is_active, sort_order, created_at, updated_at) VALUES 
            ('stripe_card', 'stripe', 1, 1, datetime('now'), datetime('now')),
            ('paypal', 'paypal', 1, 2, datetime('now'), datetime('now')),
            ('bank_transfer', 'bank_transfer', 1, 3, datetime('now'), datetime('now')),
            ('cod', 'cod', 1, 4, datetime('now'), datetime('now'))");
    }

    public function down(Schema $schema): void
    {
        // Remove foreign key columns from orders table
        $this->addSql('DROP INDEX IDX_ORDERS_PAYMENT_METHOD');
        $this->addSql('DROP INDEX IDX_ORDERS_SHIPPING_METHOD');
        
        // Drop translation tables
        $this->addSql('DROP TABLE payment_method_translations');
        $this->addSql('DROP TABLE shipping_method_translations');
        $this->addSql('DROP TABLE order_status_translations');
        
        // Drop main tables
        $this->addSql('DROP TABLE payment_methods');
        $this->addSql('DROP TABLE shipping_methods');
        $this->addSql('DROP TABLE order_statuses');
    }
}