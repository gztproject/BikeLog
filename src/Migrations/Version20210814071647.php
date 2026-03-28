<?php


declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210814071647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added location data to refuelings.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('refueling')) {
            $this->skipIf(true, 'The refueling table does not exist.');
        }

        $table = $schema->getTable('refueling');

        if (!$table->hasColumn('latitude')) {
            $this->addSql('ALTER TABLE refueling ADD latitude NUMERIC(9, 6) NOT NULL');
        }

        if (!$table->hasColumn('longitude')) {
            $this->addSql('ALTER TABLE refueling ADD longitude NUMERIC(9, 6) NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('refueling')) {
            $this->skipIf(true, 'The refueling table does not exist.');
        }

        $table = $schema->getTable('refueling');

        if ($table->hasColumn('latitude')) {
            $this->addSql('ALTER TABLE refueling DROP latitude');
        }

        if ($table->hasColumn('longitude')) {
            $this->addSql('ALTER TABLE refueling DROP longitude');
        }
    }
}
