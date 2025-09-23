<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250923230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create analytics tables for page views and events tracking';
    }

    public function up(Schema $schema): void
    {
        // Create analytics_page_views table
        $this->addSql('CREATE TABLE analytics_page_views (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            url VARCHAR(500) NOT NULL, 
            title VARCHAR(255) DEFAULT NULL, 
            referrer VARCHAR(500) DEFAULT NULL, 
            ip_address VARCHAR(45) NOT NULL, 
            user_agent TEXT DEFAULT NULL, 
            user_id INTEGER DEFAULT NULL, 
            session_id VARCHAR(100) DEFAULT NULL, 
            created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , 
            view_duration INTEGER DEFAULT NULL, 
            metadata TEXT DEFAULT NULL --(DC2Type:json)
        )');
        
        $this->addSql('CREATE INDEX idx_page_views_date ON analytics_page_views (created_at)');
        $this->addSql('CREATE INDEX idx_page_views_url ON analytics_page_views (url)');
        $this->addSql('CREATE INDEX idx_page_views_user ON analytics_page_views (user_id)');

        // Create analytics_events table
        $this->addSql('CREATE TABLE analytics_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            event_type VARCHAR(100) NOT NULL, 
            category VARCHAR(100) NOT NULL, 
            action VARCHAR(255) DEFAULT NULL, 
            label VARCHAR(255) DEFAULT NULL, 
            value INTEGER DEFAULT NULL, 
            url VARCHAR(500) DEFAULT NULL, 
            user_id INTEGER DEFAULT NULL, 
            session_id VARCHAR(100) DEFAULT NULL, 
            ip_address VARCHAR(45) NOT NULL, 
            created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , 
            properties TEXT DEFAULT NULL --(DC2Type:json)
        )');
        
        $this->addSql('CREATE INDEX idx_events_date ON analytics_events (created_at)');
        $this->addSql('CREATE INDEX idx_events_type ON analytics_events (event_type)');
        $this->addSql('CREATE INDEX idx_events_category ON analytics_events (category)');
        $this->addSql('CREATE INDEX idx_events_user ON analytics_events (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE analytics_events');
        $this->addSql('DROP TABLE analytics_page_views');
    }
}