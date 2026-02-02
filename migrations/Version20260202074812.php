<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260202074812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE diocese (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, statut VARCHAR(255) NOT NULL, fedapay_api_key VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INT NOT NULL, INDEX IDX_8849E742B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ROW_FORMAT = DYNAMIC');
        $this->addSql('CREATE TABLE intention_messe (id INT AUTO_INCREMENT NOT NULL, beneficiaire VARCHAR(255) NOT NULL, type_intention VARCHAR(100) NOT NULL, montant_paye NUMERIC(10, 2) NOT NULL, statut_paiement VARCHAR(255) NOT NULL, transaction_fedapay VARCHAR(100) DEFAULT NULL, statut_payout VARCHAR(255) NOT NULL, payout_reference VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, occurrence_id INT NOT NULL, fidele_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_4E18CB40B57FBE6F (transaction_fedapay), INDEX IDX_4E18CB4030572FAC (occurrence_id), INDEX IDX_4E18CB40E82E2050 (fidele_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ROW_FORMAT = DYNAMIC');
        $this->addSql('CREATE TABLE messe (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, recurrence VARCHAR(255) DEFAULT NULL, heure TIME NOT NULL, jour_semaine SMALLINT DEFAULT NULL, date_debut DATE DEFAULT NULL, date_fin DATE DEFAULT NULL, montant_suggere NUMERIC(10, 2) NOT NULL, statut VARCHAR(255) NOT NULL, paroisse_id INT NOT NULL, INDEX IDX_C4052A3BC40C2240 (paroisse_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ROW_FORMAT = DYNAMIC');
        $this->addSql('CREATE TABLE occurrence_messe (id INT AUTO_INCREMENT NOT NULL, date_heure DATETIME NOT NULL, statut VARCHAR(255) NOT NULL, nombre_intentions INT DEFAULT 0 NOT NULL, messe_id INT NOT NULL, INDEX IDX_EA712952517EF722 (messe_id), INDEX idx_occurrence_date_heure (date_heure), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ROW_FORMAT = DYNAMIC');
        $this->addSql('CREATE TABLE paroisse (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, adresse LONGTEXT DEFAULT NULL, numero_mobile_money VARCHAR(20) DEFAULT NULL, delai_minimum_jours INT DEFAULT 2 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, diocese_id INT NOT NULL, INDEX IDX_9068949CB600009 (diocese_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ROW_FORMAT = DYNAMIC');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, diocese_id INT DEFAULT NULL, paroisse_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D649B600009 (diocese_id), INDEX IDX_8D93D649C40C2240 (paroisse_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ROW_FORMAT = DYNAMIC');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ROW_FORMAT = DYNAMIC');
        $this->addSql('ALTER TABLE diocese ADD CONSTRAINT FK_8849E742B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE intention_messe ADD CONSTRAINT FK_4E18CB4030572FAC FOREIGN KEY (occurrence_id) REFERENCES occurrence_messe (id)');
        $this->addSql('ALTER TABLE intention_messe ADD CONSTRAINT FK_4E18CB40E82E2050 FOREIGN KEY (fidele_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE messe ADD CONSTRAINT FK_C4052A3BC40C2240 FOREIGN KEY (paroisse_id) REFERENCES paroisse (id)');
        $this->addSql('ALTER TABLE occurrence_messe ADD CONSTRAINT FK_EA712952517EF722 FOREIGN KEY (messe_id) REFERENCES messe (id)');
        $this->addSql('ALTER TABLE paroisse ADD CONSTRAINT FK_9068949CB600009 FOREIGN KEY (diocese_id) REFERENCES diocese (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649B600009 FOREIGN KEY (diocese_id) REFERENCES diocese (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649C40C2240 FOREIGN KEY (paroisse_id) REFERENCES paroisse (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE diocese DROP FOREIGN KEY FK_8849E742B03A8386');
        $this->addSql('ALTER TABLE intention_messe DROP FOREIGN KEY FK_4E18CB4030572FAC');
        $this->addSql('ALTER TABLE intention_messe DROP FOREIGN KEY FK_4E18CB40E82E2050');
        $this->addSql('ALTER TABLE messe DROP FOREIGN KEY FK_C4052A3BC40C2240');
        $this->addSql('ALTER TABLE occurrence_messe DROP FOREIGN KEY FK_EA712952517EF722');
        $this->addSql('ALTER TABLE paroisse DROP FOREIGN KEY FK_9068949CB600009');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649B600009');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649C40C2240');
        $this->addSql('DROP TABLE diocese');
        $this->addSql('DROP TABLE intention_messe');
        $this->addSql('DROP TABLE messe');
        $this->addSql('DROP TABLE occurrence_messe');
        $this->addSql('DROP TABLE paroisse');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
