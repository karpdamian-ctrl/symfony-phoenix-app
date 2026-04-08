<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraint for photos by user and image URL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_photos_user_image_url ON photos (user_id, image_url)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_photos_user_image_url');
    }
}
