<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add 2FA fields to User entity for Phase 8 security features
 */
final class Version20241224001500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add two-factor authentication fields to user table';
    }

    public function up(Schema $schema): void
    {
        // Add 2FA related columns to user table
        $this->addSql('ALTER TABLE user ADD google_authenticator_secret VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD totp_secret VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD is_two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE user ADD backup_codes JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD trusted_token_version INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // Remove 2FA related columns from user table
        $this->addSql('ALTER TABLE user DROP google_authenticator_secret');
        $this->addSql('ALTER TABLE user DROP totp_secret');
        $this->addSql('ALTER TABLE user DROP is_two_factor_enabled');
        $this->addSql('ALTER TABLE user DROP backup_codes');
        $this->addSql('ALTER TABLE user DROP trusted_token_version');
    }
}