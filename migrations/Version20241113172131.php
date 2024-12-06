<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241113172131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE disponibilite (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', spv_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', date DATE NOT NULL, type VARCHAR(255) NOT NULL, INDEX IDX_2CBACE2F3C96A04D (spv_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE spv (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, display_name VARCHAR(255) DEFAULT NULL, status TINYINT(1) NOT NULL, cs VARCHAR(255) DEFAULT NULL, PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_2CBACE2F3C96A04D FOREIGN KEY (spv_uuid) REFERENCES spv (uuid)');
        $this->addSql('ALTER TABLE user MODIFY id INT NOT NULL');
        $this->addSql('DROP INDEX `primary` ON user');
        $this->addSql('ALTER TABLE user ADD uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', DROP id');
        $this->addSql('ALTER TABLE user ADD PRIMARY KEY (uuid)');
        $this->addSql('DROP INDEX series ON rememberme_token');
        $this->addSql('ALTER TABLE rememberme_token CHANGE lastUsed lastUsed DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE disponibilite DROP FOREIGN KEY FK_2CBACE2F3C96A04D');
        $this->addSql('DROP TABLE disponibilite');
        $this->addSql('DROP TABLE spv');
        $this->addSql('ALTER TABLE rememberme_token CHANGE lastUsed lastUsed DATETIME NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX series ON rememberme_token (series)');
        $this->addSql('ALTER TABLE `user` ADD id INT AUTO_INCREMENT NOT NULL, DROP uuid, DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
    }
}
