<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101000000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Drop the legacy slug from profiles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profiles DROP legacy_slug');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profiles ADD legacy_slug VARCHAR(255) DEFAULT NULL');
    }
}
