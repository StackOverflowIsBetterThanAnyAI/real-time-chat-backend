<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021082951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_1483a5e924a232cf');
        $this->addSql('ALTER TABLE users ALTER profile_picture TYPE TEXT');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_1483A5E9F85E0677 ON users (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_1483A5E9F85E0677');
        $this->addSql('ALTER TABLE users ALTER profile_picture TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE users RENAME COLUMN username TO user_name');
        $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e924a232cf ON users (user_name)');
    }
}
