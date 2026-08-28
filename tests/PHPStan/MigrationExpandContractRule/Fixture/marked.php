<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class MarkedContractionMigration extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Drop the legacy slug now that nothing reads it';
    }

    public function up(Schema $schema): void
    {
        // @contract-phase: the release before this one stopped reading profiles.legacy_slug
        $this->addSql('ALTER TABLE profiles DROP legacy_slug');
    }

    #[Override]
    public function down(Schema $schema): void
    {
    }
}

/**
 * The marker on up() itself covers every statement in it.
 */
final class WhollyContractingMigration extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Drop the waitlist tables';
    }

    /** @contract-phase: the waitlist was removed from the application two releases ago */
    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE waitlist_entries');
        $this->addSql('DROP TABLE waitlist_invitations');
    }

    #[Override]
    public function down(Schema $schema): void
    {
    }
}
