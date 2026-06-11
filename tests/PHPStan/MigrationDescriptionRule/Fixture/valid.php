<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class ValidDescriptionMigration extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create users table';
    }

    public function up(Schema $schema): void
    {
    }

    #[Override]
    public function down(Schema $schema): void
    {
    }
}
