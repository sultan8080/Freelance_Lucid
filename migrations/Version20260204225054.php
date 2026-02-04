<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260204225054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX IDX_906517442DA68207 ON invoice (invoice_number)');
        $this->addSql('CREATE INDEX IDX_90651744386371F5 ON invoice (project_title)');
        $this->addSql('CREATE INDEX IDX_906517447B00651C ON invoice (status)');
        $this->addSql('CREATE INDEX IDX_90651744E673A031 ON invoice (due_date)');
        $this->addSql('DROP INDEX idx_invoice_created_at ON invoice');
        $this->addSql('CREATE INDEX IDX_906517448B8E8428 ON invoice (created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_906517442DA68207 ON invoice');
        $this->addSql('DROP INDEX IDX_90651744386371F5 ON invoice');
        $this->addSql('DROP INDEX IDX_906517447B00651C ON invoice');
        $this->addSql('DROP INDEX IDX_90651744E673A031 ON invoice');
        $this->addSql('DROP INDEX idx_906517448b8e8428 ON invoice');
        $this->addSql('CREATE INDEX idx_invoice_created_at ON invoice (created_at)');
    }
}
