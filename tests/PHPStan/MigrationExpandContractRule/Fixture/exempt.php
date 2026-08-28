<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class SameMigrationShapeMigration extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create the projects table and rebuild the comment foreign key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE projects (id UUID NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE projects DROP name');
        $this->addSql('ALTER TABLE projects ALTER COLUMN id TYPE VARCHAR(64)');

        $this->addSql('ALTER TABLE comments DROP CONSTRAINT FK_5F9E962A4BBC2705');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962A4BBC2705 FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("ALTER TABLE comments ALTER COLUMN status SET DEFAULT 'pending'");
        $this->addSql('ALTER TABLE comments ALTER COLUMN status SET NOT NULL');

        $table = 'documents';
        $this->addSql('ALTER TABLE '.$table.' DROP legacy_body');
    }

    #[Override]
    public function down(Schema $schema): void
    {
    }
}
