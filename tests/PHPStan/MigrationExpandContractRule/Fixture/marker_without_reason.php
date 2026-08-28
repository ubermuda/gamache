<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class UnexplainedContractionMigration extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Drop the legacy slug';
    }

    public function up(Schema $schema): void
    {
        // @contract-phase
        $this->addSql('ALTER TABLE profiles DROP legacy_slug');

        $this->addSql('ALTER TABLE profiles DROP legacy_avatar'); // @contract-phase: markers do not trail
        $this->addSql('ALTER TABLE profiles DROP legacy_bio');
    }

    #[Override]
    public function down(Schema $schema): void
    {
    }
}
