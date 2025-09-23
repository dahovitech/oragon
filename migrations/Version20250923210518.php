<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250923210518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account_verifications (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, verified_by_id INTEGER DEFAULT NULL, verification_type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, submitted_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , verified_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , comments CLOB DEFAULT NULL, rejection_reason CLOB DEFAULT NULL, updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_13A0DBF5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_13A0DBF569F4B775 FOREIGN KEY (verified_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_13A0DBF5A76ED395 ON account_verifications (user_id)');
        $this->addSql('CREATE INDEX IDX_13A0DBF569F4B775 ON account_verifications (verified_by_id)');
        $this->addSql('CREATE TABLE languages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(10) NOT NULL, name VARCHAR(100) NOT NULL, native_name VARCHAR(100) NOT NULL, is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, sort_order INTEGER NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A0D1537977153098 ON languages (code)');
        $this->addSql('CREATE TABLE loan_applications (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, loan_type_id INTEGER NOT NULL, application_number VARCHAR(50) NOT NULL, requested_amount NUMERIC(10, 2) NOT NULL, duration INTEGER NOT NULL, purpose CLOB DEFAULT NULL, status VARCHAR(255) NOT NULL, monthly_payment NUMERIC(10, 2) DEFAULT NULL, interest_rate NUMERIC(5, 2) DEFAULT NULL, total_amount NUMERIC(10, 2) DEFAULT NULL, personal_info CLOB DEFAULT NULL --(DC2Type:json)
        , financial_info CLOB DEFAULT NULL --(DC2Type:json)
        , guarantees CLOB DEFAULT NULL, rejection_reason CLOB DEFAULT NULL, admin_notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , submitted_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , reviewed_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , approved_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , rejected_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_86DC2226A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_86DC2226EB0302E7 FOREIGN KEY (loan_type_id) REFERENCES loan_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_86DC222626A31391 ON loan_applications (application_number)');
        $this->addSql('CREATE INDEX IDX_86DC2226A76ED395 ON loan_applications (user_id)');
        $this->addSql('CREATE INDEX IDX_86DC2226EB0302E7 ON loan_applications (loan_type_id)');
        $this->addSql('CREATE TABLE loan_contracts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, loan_application_id INTEGER NOT NULL, contract_number VARCHAR(50) NOT NULL, signed_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , contract_pdf VARCHAR(500) DEFAULT NULL, digital_signature CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, payment_schedule CLOB NOT NULL --(DC2Type:json)
        , original_amount NUMERIC(10, 2) NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, monthly_payment NUMERIC(10, 2) NOT NULL, interest_rate NUMERIC(5, 2) NOT NULL, duration INTEGER NOT NULL, remaining_amount NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_480395E998D09CC8 FOREIGN KEY (loan_application_id) REFERENCES loan_applications (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_480395E9AAD0FA19 ON loan_contracts (contract_number)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_480395E998D09CC8 ON loan_contracts (loan_application_id)');
        $this->addSql('CREATE TABLE loan_documents (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, loan_application_id INTEGER NOT NULL, verified_by_id INTEGER DEFAULT NULL, document_type VARCHAR(255) NOT NULL, file_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, file_size INTEGER NOT NULL, mime_type VARCHAR(100) NOT NULL, uploaded_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , is_verified BOOLEAN NOT NULL, verified_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , verification_notes CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, CONSTRAINT FK_E3E34E1298D09CC8 FOREIGN KEY (loan_application_id) REFERENCES loan_applications (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_E3E34E1269F4B775 FOREIGN KEY (verified_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_E3E34E1298D09CC8 ON loan_documents (loan_application_id)');
        $this->addSql('CREATE INDEX IDX_E3E34E1269F4B775 ON loan_documents (verified_by_id)');
        $this->addSql('CREATE TABLE loan_type_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, language_id INTEGER NOT NULL, loan_type_id INTEGER NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, conditions CLOB DEFAULT NULL, benefits CLOB DEFAULT NULL, short_description CLOB DEFAULT NULL, CONSTRAINT FK_7FFE0C3D82F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7FFE0C3DEB0302E7 FOREIGN KEY (loan_type_id) REFERENCES loan_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_7FFE0C3D82F1BAF4 ON loan_type_translations (language_id)');
        $this->addSql('CREATE INDEX IDX_7FFE0C3DEB0302E7 ON loan_type_translations (loan_type_id)');
        $this->addSql('CREATE TABLE loan_types (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, image_id INTEGER DEFAULT NULL, slug VARCHAR(255) NOT NULL, is_active BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, min_amount NUMERIC(10, 2) NOT NULL, max_amount NUMERIC(10, 2) NOT NULL, min_duration INTEGER NOT NULL, max_duration INTEGER NOT NULL, base_interest_rate NUMERIC(5, 2) NOT NULL, allowed_account_types CLOB NOT NULL --(DC2Type:json)
        , required_documents CLOB NOT NULL --(DC2Type:json)
        , requires_guarantee BOOLEAN NOT NULL, processing_fees NUMERIC(10, 2) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_E89916F3DA5256D FOREIGN KEY (image_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E89916F989D9B62 ON loan_types (slug)');
        $this->addSql('CREATE INDEX IDX_E89916F3DA5256D ON loan_types (image_id)');
        $this->addSql('CREATE TABLE media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, file_name VARCHAR(255) DEFAULT NULL, alt VARCHAR(255) DEFAULT NULL, extension VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, loan_contract_id INTEGER NOT NULL, payment_number INTEGER NOT NULL, due_date DATE NOT NULL, amount NUMERIC(10, 2) NOT NULL, principal_amount NUMERIC(10, 2) NOT NULL, interest_amount NUMERIC(10, 2) NOT NULL, paid_amount NUMERIC(10, 2) NOT NULL, status VARCHAR(255) NOT NULL, paid_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , payment_method VARCHAR(100) DEFAULT NULL, transaction_id VARCHAR(255) DEFAULT NULL, notes CLOB DEFAULT NULL, late_fees NUMERIC(10, 2) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_65D29B3281B7BFB9 FOREIGN KEY (loan_contract_id) REFERENCES loan_contracts (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_65D29B3281B7BFB9 ON payments (loan_contract_id)');
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
        , account_type VARCHAR(255) NOT NULL, phone_number VARCHAR(20) DEFAULT NULL, address CLOB DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, date_of_birth DATE DEFAULT NULL, national_id VARCHAR(50) DEFAULT NULL, is_verified BOOLEAN NOT NULL, verification_status VARCHAR(255) NOT NULL, monthly_income NUMERIC(10, 2) DEFAULT NULL, employment_status VARCHAR(100) DEFAULT NULL, company_name VARCHAR(255) DEFAULT NULL, siret_number VARCHAR(14) DEFAULT NULL, business_sector VARCHAR(100) DEFAULT NULL, legal_form VARCHAR(100) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON user (email)');
        $this->addSql('CREATE TABLE verification_documents (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, account_verification_id INTEGER NOT NULL, verified_by_id INTEGER DEFAULT NULL, document_type VARCHAR(255) NOT NULL, file_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, file_size INTEGER NOT NULL, mime_type VARCHAR(100) NOT NULL, uploaded_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , is_verified BOOLEAN NOT NULL, verified_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , verification_notes CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, CONSTRAINT FK_51F8CC48F6A4D5D0 FOREIGN KEY (account_verification_id) REFERENCES account_verifications (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_51F8CC4869F4B775 FOREIGN KEY (verified_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_51F8CC48F6A4D5D0 ON verification_documents (account_verification_id)');
        $this->addSql('CREATE INDEX IDX_51F8CC4869F4B775 ON verification_documents (verified_by_id)');
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
        $this->addSql('DROP TABLE account_verifications');
        $this->addSql('DROP TABLE languages');
        $this->addSql('DROP TABLE loan_applications');
        $this->addSql('DROP TABLE loan_contracts');
        $this->addSql('DROP TABLE loan_documents');
        $this->addSql('DROP TABLE loan_type_translations');
        $this->addSql('DROP TABLE loan_types');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE service_translations');
        $this->addSql('DROP TABLE services');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE verification_documents');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
