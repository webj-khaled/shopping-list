<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502112400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shopping_item (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, quantity DOUBLE PRECISION NOT NULL, unit VARCHAR(40) DEFAULT NULL, category VARCHAR(80) DEFAULT NULL, checked TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, shopping_list_id INT NOT NULL, INDEX IDX_6612795F23245BF9 (shopping_list_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shopping_list (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, cover_image VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX IDX_3DC1A4597E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE shopping_item ADD CONSTRAINT FK_6612795F23245BF9 FOREIGN KEY (shopping_list_id) REFERENCES shopping_list (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shopping_list ADD CONSTRAINT FK_3DC1A4597E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shopping_item DROP FOREIGN KEY FK_6612795F23245BF9');
        $this->addSql('ALTER TABLE shopping_list DROP FOREIGN KEY FK_3DC1A4597E3C61F9');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE shopping_item');
        $this->addSql('DROP TABLE shopping_list');
    }
}
