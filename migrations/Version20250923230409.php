<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250923230409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account_verification (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, verified_by_id INTEGER DEFAULT NULL, verification_type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, submitted_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , verified_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , comments CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_C329D34DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C329D34D69F4B775 FOREIGN KEY (verified_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C329D34DA76ED395 ON account_verification (user_id)');
        $this->addSql('CREATE INDEX IDX_C329D34D69F4B775 ON account_verification (verified_by_id)');
        $this->addSql('CREATE TABLE languages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(10) NOT NULL, name VARCHAR(100) NOT NULL, native_name VARCHAR(100) NOT NULL, is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, sort_order INTEGER NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A0D1537977153098 ON languages (code)');
        $this->addSql('CREATE TABLE loan_application (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, loan_type_id INTEGER NOT NULL, requested_amount NUMERIC(10, 2) NOT NULL, duration INTEGER NOT NULL, purpose CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, monthly_payment NUMERIC(10, 2) DEFAULT NULL, interest_rate NUMERIC(5, 2) DEFAULT NULL, total_amount NUMERIC(10, 2) DEFAULT NULL, submitted_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , reviewed_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , approved_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , rejected_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , rejection_reason CLOB DEFAULT NULL, guarantees CLOB DEFAULT NULL, personal_info CLOB DEFAULT NULL --(DC2Type:json)
        , financial_info CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_9A8285A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9A8285EB0302E7 FOREIGN KEY (loan_type_id) REFERENCES loan_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9A8285A76ED395 ON loan_application (user_id)');
        $this->addSql('CREATE INDEX IDX_9A8285EB0302E7 ON loan_application (loan_type_id)');
        $this->addSql('CREATE TABLE loan_contract (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, loan_application_id INTEGER NOT NULL, contract_number VARCHAR(50) NOT NULL, signed_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , contract_pdf VARCHAR(500) DEFAULT NULL, digital_signature CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, start_date DATE NOT NULL --(DC2Type:date_immutable)
        , end_date DATE NOT NULL --(DC2Type:date_immutable)
        , payment_schedule CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_BEC9D2F298D09CC8 FOREIGN KEY (loan_application_id) REFERENCES loan_application (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BEC9D2F2AAD0FA19 ON loan_contract (contract_number)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BEC9D2F298D09CC8 ON loan_contract (loan_application_id)');
        $this->addSql('CREATE TABLE loan_document (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, loan_application_id INTEGER NOT NULL, verified_by_id INTEGER DEFAULT NULL, document_type VARCHAR(50) NOT NULL, file_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, uploaded_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , is_verified BOOLEAN NOT NULL, verified_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , verification_comments CLOB DEFAULT NULL, CONSTRAINT FK_8F2F70DD98D09CC8 FOREIGN KEY (loan_application_id) REFERENCES loan_application (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8F2F70DD69F4B775 FOREIGN KEY (verified_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8F2F70DD98D09CC8 ON loan_document (loan_application_id)');
        $this->addSql('CREATE INDEX IDX_8F2F70DD69F4B775 ON loan_document (verified_by_id)');
        $this->addSql('CREATE TABLE loan_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, image_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, min_amount NUMERIC(10, 2) NOT NULL, max_amount NUMERIC(10, 2) NOT NULL, min_duration INTEGER NOT NULL, max_duration INTEGER NOT NULL, base_interest_rate NUMERIC(5, 2) NOT NULL, is_active BOOLEAN NOT NULL, allowed_account_types CLOB NOT NULL --(DC2Type:json)
        , required_documents CLOB NOT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_5D732D5D3DA5256D FOREIGN KEY (image_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5D732D5D989D9B62 ON loan_type (slug)');
        $this->addSql('CREATE INDEX IDX_5D732D5D3DA5256D ON loan_type (image_id)');
        $this->addSql('CREATE TABLE media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, file_name VARCHAR(255) DEFAULT NULL, alt VARCHAR(255) DEFAULT NULL, extension VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE TABLE payment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, loan_contract_id INTEGER NOT NULL, payment_number INTEGER NOT NULL, due_date DATE NOT NULL --(DC2Type:date_immutable)
        , amount NUMERIC(10, 2) NOT NULL, principal_amount NUMERIC(10, 2) NOT NULL, interest_amount NUMERIC(10, 2) NOT NULL, status VARCHAR(20) NOT NULL, paid_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , payment_method VARCHAR(100) DEFAULT NULL, transaction_reference VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_6D28840D81B7BFB9 FOREIGN KEY (loan_contract_id) REFERENCES loan_contract (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6D28840D81B7BFB9 ON payment (loan_contract_id)');
        $this->addSql('CREATE TABLE service_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, service_id INTEGER NOT NULL, language_id INTEGER NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, meta_title VARCHAR(500) DEFAULT NULL, meta_description CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_191BAF62ED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_191BAF6282F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_191BAF62ED5CA9E6 ON service_translations (service_id)');
        $this->addSql('CREATE INDEX IDX_191BAF6282F1BAF4 ON service_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SERVICE_LANGUAGE ON service_translations (service_id, language_id)');
        $this->addSql('CREATE TABLE services (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, image_id INTEGER DEFAULT NULL, slug VARCHAR(255) NOT NULL, is_active BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_7332E1693DA5256D FOREIGN KEY (image_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7332E169989D9B62 ON services (slug)');
        $this->addSql('CREATE INDEX IDX_7332E1693DA5256D ON services (image_id)');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , last_login_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , account_type VARCHAR(20) DEFAULT NULL, phone_number VARCHAR(20) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, date_of_birth DATE DEFAULT NULL, national_id VARCHAR(50) DEFAULT NULL, is_verified BOOLEAN DEFAULT NULL, verification_status VARCHAR(20) DEFAULT NULL, monthly_income NUMERIC(10, 2) DEFAULT NULL, employment_status VARCHAR(100) DEFAULT NULL, company_name VARCHAR(255) DEFAULT NULL, siret_number VARCHAR(20) DEFAULT NULL, business_sector VARCHAR(100) DEFAULT NULL, legal_form VARCHAR(100) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON user (email)');
        $this->addSql('CREATE TABLE verification_document (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, account_verification_id INTEGER NOT NULL, verified_by_id INTEGER DEFAULT NULL, document_type VARCHAR(50) NOT NULL, file_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, uploaded_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , is_verified BOOLEAN NOT NULL, verified_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , verification_comments CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_29A60264F6A4D5D0 FOREIGN KEY (account_verification_id) REFERENCES account_verification (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_29A6026469F4B775 FOREIGN KEY (verified_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_29A60264F6A4D5D0 ON verification_document (account_verification_id)');
        $this->addSql('CREATE INDEX IDX_29A6026469F4B775 ON verification_document (verified_by_id)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , available_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE account_verification');
        $this->addSql('DROP TABLE languages');
        $this->addSql('DROP TABLE loan_application');
        $this->addSql('DROP TABLE loan_contract');
        $this->addSql('DROP TABLE loan_document');
        $this->addSql('DROP TABLE loan_type');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE service_translations');
        $this->addSql('DROP TABLE services');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE verification_document');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
