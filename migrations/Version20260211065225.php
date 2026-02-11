<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211065225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE diocese ADD CONSTRAINT FK_8849E742B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE intention_messe ADD CONSTRAINT FK_4E18CB4030572FAC FOREIGN KEY (occurrence_id) REFERENCES occurrence_messe (id)');
        $this->addSql('ALTER TABLE intention_messe ADD CONSTRAINT FK_4E18CB40E82E2050 FOREIGN KEY (fidele_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE messe ADD CONSTRAINT FK_C4052A3BC40C2240 FOREIGN KEY (paroisse_id) REFERENCES paroisse (id)');
        $this->addSql('ALTER TABLE occurrence_messe ADD CONSTRAINT FK_EA712952517EF722 FOREIGN KEY (messe_id) REFERENCES messe (id)');
        $this->addSql('ALTER TABLE paroisse ADD CONSTRAINT FK_9068949CB600009 FOREIGN KEY (diocese_id) REFERENCES diocese (id)');
        $this->addSql('ALTER TABLE user ADD reset_token VARCHAR(100) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649B600009 FOREIGN KEY (diocese_id) REFERENCES diocese (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649C40C2240 FOREIGN KEY (paroisse_id) REFERENCES paroisse (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647A76ED395');
        $this->addSql('ALTER TABLE diocese DROP FOREIGN KEY FK_8849E742B03A8386');
        $this->addSql('ALTER TABLE intention_messe DROP FOREIGN KEY FK_4E18CB4030572FAC');
        $this->addSql('ALTER TABLE intention_messe DROP FOREIGN KEY FK_4E18CB40E82E2050');
        $this->addSql('ALTER TABLE messe DROP FOREIGN KEY FK_C4052A3BC40C2240');
        $this->addSql('ALTER TABLE occurrence_messe DROP FOREIGN KEY FK_EA712952517EF722');
        $this->addSql('ALTER TABLE paroisse DROP FOREIGN KEY FK_9068949CB600009');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649B600009');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649C40C2240');
        $this->addSql('ALTER TABLE `user` DROP reset_token, DROP reset_token_expires_at');
    }
}
