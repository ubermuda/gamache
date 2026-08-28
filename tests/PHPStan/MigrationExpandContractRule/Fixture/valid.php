<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class ExpandOnlyMigration extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Add a scope to API tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE api_tokens ADD scope VARCHAR(255) DEFAULT 'mcp' NOT NULL");
        $this->addSql('ALTER TABLE api_tokens ADD last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_api_tokens_scope ON api_tokens (scope)');
        $this->addSql("UPDATE api_tokens SET scope = 'mcp'");
        $this->addSql('ALTER TABLE api_tokens ALTER scope DROP DEFAULT');
        $this->addSql('ALTER TABLE users ALTER COLUMN full_name DROP NOT NULL');
        $this->addSql('DROP INDEX idx_api_tokens_created_at');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_tokens DROP scope');
        $this->addSql('DROP TABLE api_tokens');
    }
}
