<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200530161016 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE maintenance_task_part');
        $this->addSql('ALTER TABLE maintenance_task ADD maintenance_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD task_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE maintenance_task ADD CONSTRAINT FK_9D6DBBE9F6C202BC FOREIGN KEY (maintenance_id) REFERENCES maintenance (id)');
        $this->addSql('ALTER TABLE maintenance_task ADD CONSTRAINT FK_9D6DBBE98DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
        $this->addSql('CREATE INDEX IDX_9D6DBBE9F6C202BC ON maintenance_task (maintenance_id)');
        $this->addSql('CREATE INDEX IDX_9D6DBBE98DB60186 ON maintenance_task (task_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE maintenance_task_part (maintenance_task_id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:uuid)\', part_id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:uuid)\', INDEX IDX_7F7DC0CC4CE34BEC (part_id), INDEX IDX_7F7DC0CCF4404414 (maintenance_task_id), PRIMARY KEY(maintenance_task_id, part_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE maintenance_task_part ADD CONSTRAINT FK_7F7DC0CC4CE34BEC FOREIGN KEY (part_id) REFERENCES part (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenance_task_part ADD CONSTRAINT FK_7F7DC0CCF4404414 FOREIGN KEY (maintenance_task_id) REFERENCES maintenance_task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenance_task DROP FOREIGN KEY FK_9D6DBBE9F6C202BC');
        $this->addSql('ALTER TABLE maintenance_task DROP FOREIGN KEY FK_9D6DBBE98DB60186');
        $this->addSql('DROP INDEX IDX_9D6DBBE9F6C202BC ON maintenance_task');
        $this->addSql('DROP INDEX IDX_9D6DBBE98DB60186 ON maintenance_task');
        $this->addSql('ALTER TABLE maintenance_task DROP maintenance_id, DROP task_id');
    }
}
