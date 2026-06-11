<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class EmptyDescriptionMigration extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
    }

    #[Override]
    public function down(Schema $schema): void
    {
    }
}
