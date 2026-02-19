<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219141000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add foreign key and index for person.owner_id to user.id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE person ADD INDEX IDX_34DCD1767E3C61F9 (owner_id)');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT FK_34DCD1767E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE person DROP FOREIGN KEY FK_34DCD1767E3C61F9');
        $this->addSql('DROP INDEX IDX_34DCD1767E3C61F9 ON person');
    }
}
