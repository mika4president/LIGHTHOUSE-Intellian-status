<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221111103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE scheepsdata (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, received_at DATETIME NOT NULL, ship_position VARCHAR(255) DEFAULT NULL, source_ip VARCHAR(45) DEFAULT NULL, devices CLOB NOT NULL, created_at DATETIME NOT NULL, ship_id INTEGER NOT NULL, CONSTRAINT FK_A94E6FEDC256317D FOREIGN KEY (ship_id) REFERENCES schip (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A94E6FEDC256317D ON scheepsdata (ship_id)');
        $this->addSql('CREATE INDEX idx_ship_received ON scheepsdata (ship_id, received_at)');
        $this->addSql('CREATE INDEX idx_received_at ON scheepsdata (received_at)');
        $this->addSql('CREATE TABLE schip (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, naam VARCHAR(200) NOT NULL, slug VARCHAR(220) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F33D0EA5FC4DB938 ON schip (naam)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F33D0EA5989D9B62 ON schip (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE scheepsdata');
        $this->addSql('DROP TABLE schip');
    }
}
