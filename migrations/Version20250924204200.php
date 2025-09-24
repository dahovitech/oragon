<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for loan_contract table (Phase 7: Contract Generation System)
 */
final class Version20250924204200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create loan_contract table for contract generation system';
    }

    public function up(Schema $schema): void
    {
        // Create loan_contract table
        $this->addSql('CREATE TABLE loan_contract (
            id INT AUTO_INCREMENT NOT NULL, 
            loan_application_id INT NOT NULL, 
            contract_number VARCHAR(255) NOT NULL UNIQUE, 
            file_path VARCHAR(255) NOT NULL, 
            file_name VARCHAR(255) NOT NULL, 
            contract_content LONGTEXT DEFAULT NULL, 
            generated_at DATETIME NOT NULL, 
            signed_at DATETIME DEFAULT NULL, 
            status VARCHAR(50) DEFAULT "generated" NOT NULL, 
            contract_amount DECIMAL(10,2) NOT NULL, 
            interest_rate DECIMAL(5,2) NOT NULL, 
            duration_months INT NOT NULL, 
            start_date DATE NOT NULL, 
            end_date DATE NOT NULL, 
            monthly_payment DECIMAL(10,2) NOT NULL, 
            total_amount DECIMAL(10,2) NOT NULL, 
            terms LONGTEXT DEFAULT NULL, 
            conditions LONGTEXT DEFAULT NULL, 
            signature VARCHAR(255) DEFAULT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            INDEX IDX_9ADE5530D6996D50 (loan_application_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Add foreign key constraint
        $this->addSql('ALTER TABLE loan_contract ADD CONSTRAINT FK_9ADE5530D6996D50 FOREIGN KEY (loan_application_id) REFERENCES loan_application (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key constraint first
        $this->addSql('ALTER TABLE loan_contract DROP FOREIGN KEY FK_9ADE5530D6996D50');
        
        // Drop the table
        $this->addSql('DROP TABLE loan_contract');
    }
}