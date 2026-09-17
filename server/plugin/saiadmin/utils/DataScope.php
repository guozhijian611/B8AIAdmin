<?php

namespace plugin\saiadmin\utils;

/**
 * 数据范围常量与工具类
 */
class DataScope
{
    public const ALL = 1;
    public const CUSTOM = 2;
    public const SELF_DEPT = 3;
    public const DEPT_BELOW = 4;
    public const SELF = 5;

    /**
     * 解析最宽泛的数据范围
     * 规则 (widest-wins)：ALL(1) > DEPT_BELOW(4) > SELF_DEPT(3) > CUSTOM(2) > SELF(5)
     *
     * @param array $scopes
     * @return int
     */
    public static function resolveWidest(array $scopes): int
    {
        if (empty($scopes)) {
            return self::SELF;
        }

        $priority = [
            self::ALL => 50,
            self::DEPT_BELOW => 40,
            self::SELF_DEPT => 30,
            self::CUSTOM => 20,
            self::SELF => 10,
        ];

        $widest = self::SELF;
        $maxPrio = 0;

        foreach ($scopes as $s) {
            $s = (int) $s;
            $p = $priority[$s] ?? 0;
            if ($p > $maxPrio) {
                $maxPrio = $p;
                $widest = $s;
            }
        }

        return $widest;
    }
}
