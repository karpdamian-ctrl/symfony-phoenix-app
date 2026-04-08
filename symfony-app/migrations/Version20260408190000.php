<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove duplicate likes and add unique index for user and photo';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            DELETE FROM likes
            WHERE id IN (
                SELECT l1.id
                FROM likes l1
                JOIN likes l2
                  ON l1.user_id = l2.user_id
                 AND l1.photo_id = l2.photo_id
                 AND l1.id > l2.id
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX uniq_likes_user_photo ON likes (user_id, photo_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_likes_user_photo');
    }
}
