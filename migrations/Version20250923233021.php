<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250923233021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attribute_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, attribute_id INTEGER NOT NULL, language_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_4059D4A0B6E62EFA FOREIGN KEY (attribute_id) REFERENCES attributes (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4059D4A082F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4059D4A0B6E62EFA ON attribute_translations (attribute_id)');
        $this->addSql('CREATE INDEX IDX_4059D4A082F1BAF4 ON attribute_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ATTRIBUTE_LANGUAGE ON attribute_translations (attribute_id, language_id)');
        $this->addSql('CREATE TABLE attribute_value_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, attribute_value_id INTEGER NOT NULL, language_id INTEGER NOT NULL, value VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_1293849B65A22152 FOREIGN KEY (attribute_value_id) REFERENCES attribute_values (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1293849B82F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1293849B65A22152 ON attribute_value_translations (attribute_value_id)');
        $this->addSql('CREATE INDEX IDX_1293849B82F1BAF4 ON attribute_value_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ATTRIBUTE_VALUE_LANGUAGE ON attribute_value_translations (attribute_value_id, language_id)');
        $this->addSql('CREATE TABLE attribute_values (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, attribute_id INTEGER NOT NULL, color_code VARCHAR(50) DEFAULT NULL, sort_order INTEGER NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_184662BCB6E62EFA FOREIGN KEY (attribute_id) REFERENCES attributes (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_184662BCB6E62EFA ON attribute_values (attribute_id)');
        $this->addSql('CREATE TABLE attributes (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(50) NOT NULL, is_required BOOLEAN NOT NULL, is_filterable BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE TABLE brand_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, brand_id INTEGER NOT NULL, language_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, meta_title VARCHAR(500) DEFAULT NULL, meta_description CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_B018D3444F5D008 FOREIGN KEY (brand_id) REFERENCES brands (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B018D3482F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B018D3444F5D008 ON brand_translations (brand_id)');
        $this->addSql('CREATE INDEX IDX_B018D3482F1BAF4 ON brand_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BRAND_LANGUAGE ON brand_translations (brand_id, language_id)');
        $this->addSql('CREATE TABLE brands (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, logo_id INTEGER DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_7EA24434F98F144A FOREIGN KEY (logo_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_7EA24434F98F144A ON brands (logo_id)');
        $this->addSql('CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, image_id INTEGER DEFAULT NULL, is_active BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3AF346683DA5256D FOREIGN KEY (image_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON categories (parent_id)');
        $this->addSql('CREATE INDEX IDX_3AF346683DA5256D ON categories (image_id)');
        $this->addSql('CREATE TABLE category_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, category_id INTEGER NOT NULL, language_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, meta_title VARCHAR(500) DEFAULT NULL, meta_description CLOB DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_1C60F91512469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1C60F91582F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1C60F91512469DE2 ON category_translations (category_id)');
        $this->addSql('CREATE INDEX IDX_1C60F91582F1BAF4 ON category_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CATEGORY_LANGUAGE ON category_translations (category_id, language_id)');
        $this->addSql('CREATE TABLE product_attributes (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, product_id INTEGER NOT NULL, attribute_value_id INTEGER NOT NULL, custom_value VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_A2FCC15B4584665A FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A2FCC15B65A22152 FOREIGN KEY (attribute_value_id) REFERENCES attribute_values (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A2FCC15B4584665A ON product_attributes (product_id)');
        $this->addSql('CREATE INDEX IDX_A2FCC15B65A22152 ON product_attributes (attribute_value_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRODUCT_ATTRIBUTE_VALUE ON product_attributes (product_id, attribute_value_id)');
        $this->addSql('CREATE TABLE product_images (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, product_id INTEGER NOT NULL, media_id INTEGER NOT NULL, alt VARCHAR(255) DEFAULT NULL, is_primary BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_8263FFCE4584665A FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8263FFCEEA9FDD75 FOREIGN KEY (media_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8263FFCE4584665A ON product_images (product_id)');
        $this->addSql('CREATE INDEX IDX_8263FFCEEA9FDD75 ON product_images (media_id)');
        $this->addSql('CREATE TABLE product_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, product_id INTEGER NOT NULL, language_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, short_description CLOB DEFAULT NULL, meta_title VARCHAR(500) DEFAULT NULL, meta_description CLOB DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_4B13F8EC4584665A FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4B13F8EC82F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4B13F8EC4584665A ON product_translations (product_id)');
        $this->addSql('CREATE INDEX IDX_4B13F8EC82F1BAF4 ON product_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRODUCT_LANGUAGE ON product_translations (product_id, language_id)');
        $this->addSql('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, brand_id INTEGER DEFAULT NULL, category_id INTEGER DEFAULT NULL, sku VARCHAR(100) NOT NULL, price NUMERIC(10, 2) NOT NULL, compare_price NUMERIC(10, 2) DEFAULT NULL, cost_price NUMERIC(10, 2) DEFAULT NULL, weight NUMERIC(8, 3) DEFAULT NULL, dimensions CLOB DEFAULT NULL --(DC2Type:json)
        , stock_quantity INTEGER NOT NULL, is_active BOOLEAN NOT NULL, is_featured BOOLEAN NOT NULL, is_digital BOOLEAN NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_B3BA5A5A44F5D008 FOREIGN KEY (brand_id) REFERENCES brands (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B3BA5A5A12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B3BA5A5AF9038C4 ON products (sku)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A44F5D008 ON products (brand_id)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A12469DE2 ON products (category_id)');
        $this->addSql('CREATE TABLE reviews (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, product_id INTEGER NOT NULL, rating INTEGER NOT NULL, title VARCHAR(255) DEFAULT NULL, comment CLOB DEFAULT NULL, is_approved BOOLEAN NOT NULL, helpful_count INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_6970EB0FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6970EB0F4584665A FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6970EB0FA76ED395 ON reviews (user_id)');
        $this->addSql('CREATE INDEX IDX_6970EB0F4584665A ON reviews (product_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE attribute_translations');
        $this->addSql('DROP TABLE attribute_value_translations');
        $this->addSql('DROP TABLE attribute_values');
        $this->addSql('DROP TABLE attributes');
        $this->addSql('DROP TABLE brand_translations');
        $this->addSql('DROP TABLE brands');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE category_translations');
        $this->addSql('DROP TABLE product_attributes');
        $this->addSql('DROP TABLE product_images');
        $this->addSql('DROP TABLE product_translations');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE reviews');
    }
}
