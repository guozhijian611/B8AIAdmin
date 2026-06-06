<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UpdateB8cmsContactMenuIcon extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "UPDATE `sa_system_menu`
            SET `icon` = 'ri:message-3-line', `update_time` = NOW()
            WHERE `code` = 'B8CMSContact'
              AND `delete_time` IS NULL
              AND (`icon` = '' OR `icon` = 'ri:mail-3-line')"
        );
    }

    public function down(): void
    {
        $this->execute(
            "UPDATE `sa_system_menu`
            SET `icon` = 'ri:mail-3-line', `update_time` = NOW()
            WHERE `code` = 'B8CMSContact'
              AND `delete_time` IS NULL
              AND `icon` = 'ri:message-3-line'"
        );
    }
}
