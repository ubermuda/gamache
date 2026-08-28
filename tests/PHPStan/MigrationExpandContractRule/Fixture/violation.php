<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class ContractingMigration extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Reshape the documents schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE waitlist_entries');
        $this->addSql('ALTER TABLE documents DROP project_id');
        $this->addSql('ALTER TABLE documents DROP CONSTRAINT FK_A2B07288166D1F9C');
        $this->addSql('ALTER TABLE site_review_reviews RENAME COLUMN site_id TO project_id');
        $this->addSql('ALTER TABLE site_review_sites RENAME TO projects');
        $this->addSql('ALTER TABLE users ALTER COLUMN full_name TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE users ALTER COLUMN username SET NOT NULL');
        $this->addSql(<<<'SQL'
            DROP TABLE
            document_highlights
            SQL);
        $this->addSql('DROP TABLE IF EXISTS document_tags');
        $this->addSql('ALTER TABLE users DROP COLUMN nickname');
    }

    #[Override]
    public function down(Schema $schema): void
    {
    }
}
