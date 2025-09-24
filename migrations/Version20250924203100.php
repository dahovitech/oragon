<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 5: Add Document entity and update User entity for KYC system
 */
final class Version20250924203100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Document entity and update User entity for KYC system';
    }

    public function up(Schema $schema): void
    {
        // Create the document table
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, verified_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, filename VARCHAR(255) DEFAULT NULL, file_size INT DEFAULT NULL, status VARCHAR(20) NOT NULL, rejection_reason LONGTEXT DEFAULT NULL, uploaded_at DATETIME NOT NULL, verified_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_D8698A76A76ED395 (user_id), INDEX IDX_D8698A76B8120EC8 (verified_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Add foreign key constraints
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76B8120EC8 FOREIGN KEY (verified_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop the document table
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76A76ED395');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76B8120EC8');
        $this->addSql('DROP TABLE document');
    }
}