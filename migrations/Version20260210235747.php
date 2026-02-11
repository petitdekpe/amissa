<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210235747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, category VARCHAR(50) NOT NULL, action VARCHAR(100) NOT NULL, message LONGTEXT NOT NULL, level VARCHAR(20) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, entity_type VARCHAR(100) DEFAULT NULL, entity_id INT DEFAULT NULL, context JSON DEFAULT NULL, old_values JSON DEFAULT NULL, new_values JSON DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, INDEX idx_activity_category (category), INDEX idx_activity_created_at (created_at), INDEX idx_activity_user (user_id), INDEX idx_activity_entity (entity_type, entity_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ROW_FORMAT = DYNAMIC');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE diocese ADD CONSTRAINT FK_8849E742B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE intention_messe ADD CONSTRAINT FK_4E18CB4030572FAC FOREIGN KEY (occurrence_id) REFERENCES occurrence_messe (id)');
        $this->addSql('ALTER TABLE intention_messe ADD CONSTRAINT FK_4E18CB40E82E2050 FOREIGN KEY (fidele_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE messe ADD CONSTRAINT FK_C4052A3BC40C2240 FOREIGN KEY (paroisse_id) REFERENCES paroisse (id)');
        $this->addSql('ALTER TABLE occurrence_messe ADD CONSTRAINT FK_EA712952517EF722 FOREIGN KEY (messe_id) REFERENCES messe (id)');
        $this->addSql('ALTER TABLE paroisse ADD CONSTRAINT FK_9068949CB600009 FOREIGN KEY (diocese_id) REFERENCES diocese (id)');
        $this->addSql('ALTER TABLE user ADD is_validated TINYINT DEFAULT 0 NOT NULL, ADD validated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649B600009 FOREIGN KEY (diocese_id) REFERENCES diocese (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649C40C2240 FOREIGN KEY (paroisse_id) REFERENCES paroisse (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647A76ED395');
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('ALTER TABLE diocese DROP FOREIGN KEY FK_8849E742B03A8386');
        $this->addSql('ALTER TABLE intention_messe DROP FOREIGN KEY FK_4E18CB4030572FAC');
        $this->addSql('ALTER TABLE intention_messe DROP FOREIGN KEY FK_4E18CB40E82E2050');
        $this->addSql('ALTER TABLE messe DROP FOREIGN KEY FK_C4052A3BC40C2240');
        $this->addSql('ALTER TABLE occurrence_messe DROP FOREIGN KEY FK_EA712952517EF722');
        $this->addSql('ALTER TABLE paroisse DROP FOREIGN KEY FK_9068949CB600009');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649B600009');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649C40C2240');
        $this->addSql('ALTER TABLE `user` DROP is_validated, DROP validated_at');
    }
}
