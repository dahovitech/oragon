<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250924000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification system tables';
    }

    public function up(Schema $schema): void
    {
        // Create notifications table
        $this->addSql('CREATE TABLE notifications (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            type VARCHAR(100) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message LONGTEXT NOT NULL,
            user_email VARCHAR(255) DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT "pending",
            priority VARCHAR(50) NOT NULL DEFAULT "normal",
            category VARCHAR(100) DEFAULT NULL,
            action_url VARCHAR(500) DEFAULT NULL,
            action_text VARCHAR(100) DEFAULT NULL,
            data JSON DEFAULT NULL,
            channels JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            sent_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            read_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            scheduled_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            attempts INT NOT NULL DEFAULT 0,
            failure_reason LONGTEXT DEFAULT NULL,
            INDEX idx_notification_user (user_id),
            INDEX idx_notification_type (type),
            INDEX idx_notification_status (status),
            INDEX idx_notification_created (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create email_templates table
        $this->addSql('CREATE TABLE email_templates (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(100) NOT NULL,
            locale VARCHAR(5) NOT NULL DEFAULT "fr",
            subject VARCHAR(255) NOT NULL,
            html_content LONGTEXT NOT NULL,
            text_content LONGTEXT DEFAULT NULL,
            variables JSON DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            version INT NOT NULL DEFAULT 1,
            description VARCHAR(255) DEFAULT NULL,
            preheader VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX idx_template_type (type),
            INDEX idx_template_active (active),
            UNIQUE INDEX uniq_template_name_locale (name, locale),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create notification_preferences table
        $this->addSql('CREATE TABLE notification_preferences (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            type VARCHAR(100) NOT NULL,
            channels JSON NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            frequency VARCHAR(50) NOT NULL DEFAULT "immediate",
            quiet_hours_start TIME DEFAULT NULL,
            quiet_hours_end TIME DEFAULT NULL,
            settings JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX idx_preferences_user (user_id),
            UNIQUE INDEX UNIQ_F8F4D1E2A76ED395A058193A (user_id, type),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification_preferences ADD CONSTRAINT FK_F8F4D1E2A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key constraints
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE notification_preferences DROP FOREIGN KEY FK_F8F4D1E2A76ED395');

        // Drop tables
        $this->addSql('DROP TABLE notification_preferences');
        $this->addSql('DROP TABLE email_templates');
        $this->addSql('DROP TABLE notifications');
    }
}