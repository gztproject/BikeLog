<?php


declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121000000 extends AbstractMigration
{
    private const INDEX_NAME = 'UNIQ_APP_USERS_USERNAME';

    public function getDescription(): string
    {
        return 'Made user names unique.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('app_users')) {
            $this->skipIf(true, 'The app_users table does not exist.');
        }

        $table = $schema->getTable('app_users');
        if ($table->hasIndex(self::INDEX_NAME)) {
            $this->skipIf(true, 'The username index already exists.');
        }

        $duplicates = $this->connection->fetchFirstColumn(
            'SELECT username FROM app_users GROUP BY username HAVING COUNT(*) > 1'
        );
        $this->abortIf(
            [] !== $duplicates,
            'These usernames belong to more than one account. Rename the extra accounts, then run the migration again: '.implode(', ', $duplicates)
        );

        $this->addSql('CREATE UNIQUE INDEX '.self::INDEX_NAME.' ON app_users (username)');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('app_users')) {
            $this->skipIf(true, 'The app_users table does not exist.');
        }

        if ($schema->getTable('app_users')->hasIndex(self::INDEX_NAME)) {
            $this->addSql($this->platform->getDropIndexSQL(self::INDEX_NAME, 'app_users'));
        }
    }
}
