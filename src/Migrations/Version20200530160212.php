<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200530160212 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE workshop (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', owner_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_9B6F02C47E3C61F9 (owner_id), INDEX IDX_9B6F02C4B03A8386 (created_by_id), INDEX IDX_9B6F02C4896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE workshop_user (workshop_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_B714FF0E1FDCE57C (workshop_id), INDEX IDX_B714FF0EA76ED395 (user_id), PRIMARY KEY(workshop_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE maintenance (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_2F84F8E9B03A8386 (created_by_id), INDEX IDX_2F84F8E9896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service_interval (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', task_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', model_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', bike_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', `interval` INT NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_E0B0B4F68DB60186 (task_id), INDEX IDX_E0B0B4F67975B7E7 (model_id), INDEX IDX_E0B0B4F6D5A4816F (bike_id), INDEX IDX_E0B0B4F6B03A8386 (created_by_id), INDEX IDX_E0B0B4F6896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE part (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_490F70C6B03A8386 (created_by_id), INDEX IDX_490F70C6896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE part_manufacturer (part_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', manufacturer_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_F4EB64C4CE34BEC (part_id), INDEX IDX_F4EB64CA23B42D (manufacturer_id), PRIMARY KEY(part_id, manufacturer_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE maintenance_task (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_9D6DBBE9B03A8386 (created_by_id), INDEX IDX_9D6DBBE9896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE maintenance_task_part (maintenance_task_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', part_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_7F7DC0CCF4404414 (maintenance_task_id), INDEX IDX_7F7DC0CC4CE34BEC (part_id), PRIMARY KEY(maintenance_task_id, part_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE task (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', part_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, description VARCHAR(1024) NOT NULL, comment VARCHAR(2048) NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_527EDB254CE34BEC (part_id), INDEX IDX_527EDB25B03A8386 (created_by_id), INDEX IDX_527EDB25896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE model (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', manufacturer_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, alter_name VARCHAR(255) NOT NULL, displacement INT NOT NULL, year_from INT NOT NULL, year_to INT NOT NULL, vin_ranges LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_D79572D9A23B42D (manufacturer_id), INDEX IDX_D79572D9B03A8386 (created_by_id), INDEX IDX_D79572D9896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE refueling (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_5C524674B03A8386 (created_by_id), INDEX IDX_5C524674896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_users (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', username VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', email VARCHAR(255) NOT NULL, mobile VARCHAR(20) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_C2502824B03A8386 (created_by_id), INDEX IDX_C2502824896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE bike (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', owner_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', model_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', nickname VARCHAR(255) NOT NULL, purchase_price NUMERIC(5, 2) NOT NULL, year INT NOT NULL, vin VARCHAR(50) NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_4CBC37807E3C61F9 (owner_id), INDEX IDX_4CBC37807975B7E7 (model_id), INDEX IDX_4CBC3780B03A8386 (created_by_id), INDEX IDX_4CBC3780896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE manufacturer (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_by_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, created_on DATETIME NOT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_3D0AE6DCB03A8386 (created_by_id), INDEX IDX_3D0AE6DC896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE workshop ADD CONSTRAINT FK_9B6F02C47E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE workshop ADD CONSTRAINT FK_9B6F02C4B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE workshop ADD CONSTRAINT FK_9B6F02C4896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE workshop_user ADD CONSTRAINT FK_B714FF0E1FDCE57C FOREIGN KEY (workshop_id) REFERENCES workshop (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workshop_user ADD CONSTRAINT FK_B714FF0EA76ED395 FOREIGN KEY (user_id) REFERENCES app_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenance ADD CONSTRAINT FK_2F84F8E9B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE maintenance ADD CONSTRAINT FK_2F84F8E9896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE service_interval ADD CONSTRAINT FK_E0B0B4F68DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
        $this->addSql('ALTER TABLE service_interval ADD CONSTRAINT FK_E0B0B4F67975B7E7 FOREIGN KEY (model_id) REFERENCES model (id)');
        $this->addSql('ALTER TABLE service_interval ADD CONSTRAINT FK_E0B0B4F6D5A4816F FOREIGN KEY (bike_id) REFERENCES bike (id)');
        $this->addSql('ALTER TABLE service_interval ADD CONSTRAINT FK_E0B0B4F6B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE service_interval ADD CONSTRAINT FK_E0B0B4F6896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE part ADD CONSTRAINT FK_490F70C6B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE part ADD CONSTRAINT FK_490F70C6896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE part_manufacturer ADD CONSTRAINT FK_F4EB64C4CE34BEC FOREIGN KEY (part_id) REFERENCES part (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE part_manufacturer ADD CONSTRAINT FK_F4EB64CA23B42D FOREIGN KEY (manufacturer_id) REFERENCES manufacturer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenance_task ADD CONSTRAINT FK_9D6DBBE9B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE maintenance_task ADD CONSTRAINT FK_9D6DBBE9896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE maintenance_task_part ADD CONSTRAINT FK_7F7DC0CCF4404414 FOREIGN KEY (maintenance_task_id) REFERENCES maintenance_task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenance_task_part ADD CONSTRAINT FK_7F7DC0CC4CE34BEC FOREIGN KEY (part_id) REFERENCES part (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB254CE34BEC FOREIGN KEY (part_id) REFERENCES part (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE model ADD CONSTRAINT FK_D79572D9A23B42D FOREIGN KEY (manufacturer_id) REFERENCES manufacturer (id)');
        $this->addSql('ALTER TABLE model ADD CONSTRAINT FK_D79572D9B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE model ADD CONSTRAINT FK_D79572D9896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE refueling ADD CONSTRAINT FK_5C524674B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE refueling ADD CONSTRAINT FK_5C524674896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE app_users ADD CONSTRAINT FK_C2502824B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE app_users ADD CONSTRAINT FK_C2502824896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE bike ADD CONSTRAINT FK_4CBC37807E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE bike ADD CONSTRAINT FK_4CBC37807975B7E7 FOREIGN KEY (model_id) REFERENCES model (id)');
        $this->addSql('ALTER TABLE bike ADD CONSTRAINT FK_4CBC3780B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE bike ADD CONSTRAINT FK_4CBC3780896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE manufacturer ADD CONSTRAINT FK_3D0AE6DCB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_users (id)');
        $this->addSql('ALTER TABLE manufacturer ADD CONSTRAINT FK_3D0AE6DC896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_users (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE workshop_user DROP FOREIGN KEY FK_B714FF0E1FDCE57C');
        $this->addSql('ALTER TABLE part_manufacturer DROP FOREIGN KEY FK_F4EB64C4CE34BEC');
        $this->addSql('ALTER TABLE maintenance_task_part DROP FOREIGN KEY FK_7F7DC0CC4CE34BEC');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB254CE34BEC');
        $this->addSql('ALTER TABLE maintenance_task_part DROP FOREIGN KEY FK_7F7DC0CCF4404414');
        $this->addSql('ALTER TABLE service_interval DROP FOREIGN KEY FK_E0B0B4F68DB60186');
        $this->addSql('ALTER TABLE service_interval DROP FOREIGN KEY FK_E0B0B4F67975B7E7');
        $this->addSql('ALTER TABLE bike DROP FOREIGN KEY FK_4CBC37807975B7E7');
        $this->addSql('ALTER TABLE workshop DROP FOREIGN KEY FK_9B6F02C47E3C61F9');
        $this->addSql('ALTER TABLE workshop DROP FOREIGN KEY FK_9B6F02C4B03A8386');
        $this->addSql('ALTER TABLE workshop DROP FOREIGN KEY FK_9B6F02C4896DBBDE');
        $this->addSql('ALTER TABLE workshop_user DROP FOREIGN KEY FK_B714FF0EA76ED395');
        $this->addSql('ALTER TABLE maintenance DROP FOREIGN KEY FK_2F84F8E9B03A8386');
        $this->addSql('ALTER TABLE maintenance DROP FOREIGN KEY FK_2F84F8E9896DBBDE');
        $this->addSql('ALTER TABLE service_interval DROP FOREIGN KEY FK_E0B0B4F6B03A8386');
        $this->addSql('ALTER TABLE service_interval DROP FOREIGN KEY FK_E0B0B4F6896DBBDE');
        $this->addSql('ALTER TABLE part DROP FOREIGN KEY FK_490F70C6B03A8386');
        $this->addSql('ALTER TABLE part DROP FOREIGN KEY FK_490F70C6896DBBDE');
        $this->addSql('ALTER TABLE maintenance_task DROP FOREIGN KEY FK_9D6DBBE9B03A8386');
        $this->addSql('ALTER TABLE maintenance_task DROP FOREIGN KEY FK_9D6DBBE9896DBBDE');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B03A8386');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25896DBBDE');
        $this->addSql('ALTER TABLE model DROP FOREIGN KEY FK_D79572D9B03A8386');
        $this->addSql('ALTER TABLE model DROP FOREIGN KEY FK_D79572D9896DBBDE');
        $this->addSql('ALTER TABLE refueling DROP FOREIGN KEY FK_5C524674B03A8386');
        $this->addSql('ALTER TABLE refueling DROP FOREIGN KEY FK_5C524674896DBBDE');
        $this->addSql('ALTER TABLE app_users DROP FOREIGN KEY FK_C2502824B03A8386');
        $this->addSql('ALTER TABLE app_users DROP FOREIGN KEY FK_C2502824896DBBDE');
        $this->addSql('ALTER TABLE bike DROP FOREIGN KEY FK_4CBC37807E3C61F9');
        $this->addSql('ALTER TABLE bike DROP FOREIGN KEY FK_4CBC3780B03A8386');
        $this->addSql('ALTER TABLE bike DROP FOREIGN KEY FK_4CBC3780896DBBDE');
        $this->addSql('ALTER TABLE manufacturer DROP FOREIGN KEY FK_3D0AE6DCB03A8386');
        $this->addSql('ALTER TABLE manufacturer DROP FOREIGN KEY FK_3D0AE6DC896DBBDE');
        $this->addSql('ALTER TABLE service_interval DROP FOREIGN KEY FK_E0B0B4F6D5A4816F');
        $this->addSql('ALTER TABLE part_manufacturer DROP FOREIGN KEY FK_F4EB64CA23B42D');
        $this->addSql('ALTER TABLE model DROP FOREIGN KEY FK_D79572D9A23B42D');
        $this->addSql('DROP TABLE workshop');
        $this->addSql('DROP TABLE workshop_user');
        $this->addSql('DROP TABLE maintenance');
        $this->addSql('DROP TABLE service_interval');
        $this->addSql('DROP TABLE part');
        $this->addSql('DROP TABLE part_manufacturer');
        $this->addSql('DROP TABLE maintenance_task');
        $this->addSql('DROP TABLE maintenance_task_part');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE model');
        $this->addSql('DROP TABLE refueling');
        $this->addSql('DROP TABLE app_users');
        $this->addSql('DROP TABLE bike');
        $this->addSql('DROP TABLE manufacturer');
    }
}
