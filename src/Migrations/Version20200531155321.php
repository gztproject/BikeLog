<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200531155321 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE maintenance ADD workshop_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD bike_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD date DATE NOT NULL, ADD odometer INT NOT NULL, ADD spent_time NUMERIC(5, 2) NOT NULL, ADD unspecified_costs NUMERIC(5, 2) NOT NULL, ADD comment VARCHAR(2048) NOT NULL, DROP name');
        $this->addSql('ALTER TABLE maintenance ADD CONSTRAINT FK_2F84F8E91FDCE57C FOREIGN KEY (workshop_id) REFERENCES workshop (id)');
        $this->addSql('ALTER TABLE maintenance ADD CONSTRAINT FK_2F84F8E9D5A4816F FOREIGN KEY (bike_id) REFERENCES bike (id)');
        $this->addSql('CREATE INDEX IDX_2F84F8E91FDCE57C ON maintenance (workshop_id)');
        $this->addSql('CREATE INDEX IDX_2F84F8E9D5A4816F ON maintenance (bike_id)');
        $this->addSql('ALTER TABLE maintenance_task ADD comment VARCHAR(2048) NOT NULL, ADD cost NUMERIC(5, 2) NOT NULL, DROP name');
        $this->addSql('ALTER TABLE refueling ADD bike_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD datetime DATETIME NOT NULL, ADD odometer INT NOT NULL, ADD fuel_quantity NUMERIC(5, 2) NOT NULL, ADD price NUMERIC(5, 2) NOT NULL, DROP name');
        $this->addSql('ALTER TABLE refueling ADD CONSTRAINT FK_5C524674D5A4816F FOREIGN KEY (bike_id) REFERENCES bike (id)');
        $this->addSql('CREATE INDEX IDX_5C524674D5A4816F ON refueling (bike_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE maintenance DROP FOREIGN KEY FK_2F84F8E91FDCE57C');
        $this->addSql('ALTER TABLE maintenance DROP FOREIGN KEY FK_2F84F8E9D5A4816F');
        $this->addSql('DROP INDEX IDX_2F84F8E91FDCE57C ON maintenance');
        $this->addSql('DROP INDEX IDX_2F84F8E9D5A4816F ON maintenance');
        $this->addSql('ALTER TABLE maintenance ADD name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, DROP workshop_id, DROP bike_id, DROP date, DROP odometer, DROP spent_time, DROP unspecified_costs, DROP comment');
        $this->addSql('ALTER TABLE maintenance_task ADD name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, DROP comment, DROP cost');
        $this->addSql('ALTER TABLE refueling DROP FOREIGN KEY FK_5C524674D5A4816F');
        $this->addSql('DROP INDEX IDX_5C524674D5A4816F ON refueling');
        $this->addSql('ALTER TABLE refueling ADD name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, DROP bike_id, DROP datetime, DROP odometer, DROP fuel_quantity, DROP price');
    }
}
