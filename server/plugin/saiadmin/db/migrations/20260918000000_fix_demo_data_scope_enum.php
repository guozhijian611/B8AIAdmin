<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * 幂等修复：仅按已知 demo 角色 code，把「旧注释语义」种子值对齐到运行时枚举。
 *
 * 运行时：1全部 / 2自定义 / 3本部门 / 4本部门及以下 / 5本人
 * 旧注释曾写：1全部 / 2本部门及下属 / 3本部门 / 4仅本人 / 5自定义
 *
 * 不可对全表做盲映射（前端已按新枚举写入的库会损坏）。
 * 仅修复：
 * - bg_president、gm：若仍为 2 → 4
 * - staff：若仍为 4 → 5
 */
final class FixDemoDataScopeEnum extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("UPDATE sa_system_role SET data_scope = 4 WHERE code IN ('bg_president', 'gm') AND data_scope = 2");
        $this->execute("UPDATE sa_system_role SET data_scope = 5 WHERE code = 'staff' AND data_scope = 4");
    }

    public function down(): void
    {
        // 不自动回滚：无法区分「修复后的 4/5」与用户有意配置的 4/5
    }
}
