<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class BackfilledNotNullMigration extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Add a per-version sequence to reviews';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reviews ADD sequence INT DEFAULT NULL');
        $this->addSql('UPDATE reviews SET sequence = 1');
        $this->addSql('ALTER TABLE reviews ALTER COLUMN sequence SET NOT NULL');
    }

    #[Override]
    public function down(Schema $schema): void
    {
    }
}
