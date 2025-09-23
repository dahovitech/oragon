<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250924051037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__payments AS SELECT id, loan_contract_id, payment_number, due_date, amount, principal_amount, interest_amount, status, paid_at, payment_method, created_at, updated_at FROM payments');
        $this->addSql('DROP TABLE payments');
        $this->addSql('CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, loan_contract_id INTEGER NOT NULL, payment_number INTEGER NOT NULL, due_date DATE NOT NULL, amount NUMERIC(10, 2) NOT NULL, principal_amount NUMERIC(10, 2) NOT NULL, interest_amount NUMERIC(10, 2) NOT NULL, status VARCHAR(255) NOT NULL, paid_at DATETIME DEFAULT NULL, payment_method VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_65D29B3281B7BFB9 FOREIGN KEY (loan_contract_id) REFERENCES loan_contracts (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO payments (id, loan_contract_id, payment_number, due_date, amount, principal_amount, interest_amount, status, paid_at, payment_method, created_at, updated_at) SELECT id, loan_contract_id, payment_number, due_date, amount, principal_amount, interest_amount, status, paid_at, payment_method, created_at, updated_at FROM __temp__payments');
        $this->addSql('DROP TABLE __temp__payments');
        $this->addSql('CREATE INDEX IDX_65D29B3281B7BFB9 ON payments (loan_contract_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__payments AS SELECT id, loan_contract_id, payment_number, due_date, amount, principal_amount, interest_amount, status, paid_at, payment_method, created_at, updated_at FROM payments');
        $this->addSql('DROP TABLE payments');
        $this->addSql('CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, loan_contract_id INTEGER NOT NULL, payment_number INTEGER NOT NULL, due_date DATE NOT NULL, amount NUMERIC(10, 2) NOT NULL, principal_amount NUMERIC(10, 2) NOT NULL, interest_amount NUMERIC(10, 2) NOT NULL, status VARCHAR(255) NOT NULL, paid_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , payment_method VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , paid_amount NUMERIC(10, 2) NOT NULL, transaction_id VARCHAR(255) DEFAULT NULL, notes CLOB DEFAULT NULL, late_fees NUMERIC(10, 2) DEFAULT NULL, CONSTRAINT FK_65D29B3281B7BFB9 FOREIGN KEY (loan_contract_id) REFERENCES loan_contracts (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO payments (id, loan_contract_id, payment_number, due_date, amount, principal_amount, interest_amount, status, paid_at, payment_method, created_at, updated_at) SELECT id, loan_contract_id, payment_number, due_date, amount, principal_amount, interest_amount, status, paid_at, payment_method, created_at, updated_at FROM __temp__payments');
        $this->addSql('DROP TABLE __temp__payments');
        $this->addSql('CREATE INDEX IDX_65D29B3281B7BFB9 ON payments (loan_contract_id)');
    }
}
