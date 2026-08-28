<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class RecreatedTableMigration extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Rebuild the projects table around a UUID key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE projects');
        $this->addSql('CREATE TABLE projects (id UUID NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE projects ADD owner_id UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE projects');
    }
}
