<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedSaiboardDefaultTemplates extends AbstractMigration
{
    private const REMARK = 'phinx:20260619080105_seed_saiboard_default_templates';

    public function up(): void
    {
        if (!$this->hasTable('saiboard_market_item')) {
            return;
        }

        foreach ($this->templates() as $template) {
            $this->insertTemplate($template);
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('saiboard_market_item')) {
            return;
        }

        $this->execute(
            'DELETE FROM `saiboard_market_item`
             WHERE `remark` = ' . $this->q(self::REMARK) . '
               AND `created_by` = 1
               AND `updated_by` = 1'
        );
    }

    private function insertTemplate(array $template): void
    {
        $content = json_encode($template['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $type = (string) $template['type'];
        $name = (string) $template['name'];

        $this->execute(
            'INSERT INTO `saiboard_market_item` (`type`, `name`, `category`, `description`, `cover_image`, `content`, `component_count`, `is_public`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
             SELECT ' . $this->q($type) . ', ' . $this->q($name) . ', ' . $this->q($template['category']) . ', ' . $this->q($template['description']) . ", '', " . $this->q($content) . ', ' . (int) $template['component_count'] . ', 1, 1, ' . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
             WHERE NOT EXISTS (
                 SELECT 1 FROM `saiboard_market_item`
                 WHERE `type` = ' . $this->q($type) . '
                   AND `name` = ' . $this->q($name) . '
                   AND `delete_time` IS NULL
             )'
        );
    }

    private function templates(): array
    {
        return [
            [
                'type' => 'screen',
                'name' => '运营数据驾驶舱',
                'category' => '通用运营',
                'description' => '适合订单、内容、支付、告警等运营数据总览的通用大屏模板。',
                'component_count' => 9,
                'content' => [
                    'layout' => [
                        'canvas' => ['width' => 1920, 'height' => 1080],
                        'bg_config' => [
                            'color' => '#07111f',
                            'theme' => 'default',
                            'fit_mode' => 'contain',
                            'image' => '',
                            'image_fit' => 'cover',
                        ],
                        'components' => [
                            $this->component('tpl_title', 'decor-title', '', 40, 24, 1840, 86, 1, [], [
                                'text' => '运营数据驾驶舱',
                                'subtitle' => 'Operation Command Center',
                                'variant' => 'bar',
                                'align' => 'center',
                                'accent' => '#69b7ff',
                            ]),
                            $this->component('tpl_total_1', 'stat-number', '核心指标', 40, 132, 360, 150, 2, ['refresh' => 30], [
                                'prefix' => '',
                                'unit' => '单',
                                'decimals' => 0,
                            ]),
                            $this->component('tpl_total_2', 'stat-number', '交易金额', 430, 132, 360, 150, 2, ['refresh' => 30], [
                                'prefix' => '¥',
                                'unit' => '',
                                'decimals' => 2,
                            ]),
                            $this->component('tpl_total_3', 'stat-number', '内容数量', 820, 132, 360, 150, 2, ['refresh' => 30], [
                                'prefix' => '',
                                'unit' => '条',
                                'decimals' => 0,
                            ]),
                            $this->component('tpl_line', 'art-line-chart', '趋势分析', 40, 320, 720, 330, 3, ['refresh' => 30], [
                                'showAreaColor' => true,
                                'showLegend' => false,
                            ]),
                            $this->component('tpl_bar', 'art-bar-chart', '排行分析', 800, 320, 520, 330, 3, ['refresh' => 30], [
                                'showLegend' => false,
                            ]),
                            $this->component('tpl_ring', 'art-ring-chart', '结构分布', 1360, 320, 520, 330, 3, ['refresh' => 30], [
                                'showLegend' => true,
                                'legendPosition' => 'right',
                            ]),
                            $this->component('tpl_matrix', 'status-matrix', '服务状态', 40, 690, 620, 330, 3, ['refresh' => 30], [
                                'labelField' => '',
                                'statusField' => '',
                                'valueField' => '',
                                'groupField' => '',
                                'maxRows' => 12,
                                'columns' => 3,
                                'unit' => '',
                                'decimals' => 0,
                                'showValue' => true,
                                'showStatus' => true,
                                'accent' => '#23d8ff',
                            ]),
                            $this->component('tpl_table', 'data-table', '明细列表', 700, 690, 1180, 330, 3, [
                                'refresh' => 30,
                                'mapping' => ['tableFields' => []],
                            ], [
                                'showIndex' => true,
                                'rowStripe' => true,
                                'maxRows' => 8,
                            ]),
                        ],
                    ],
                ],
            ],
            [
                'type' => 'component',
                'name' => '核心指标三联卡',
                'category' => '指标组件',
                'description' => '三张指标卡组合，适合订单数、金额、用户数等顶部核心指标。',
                'component_count' => 3,
                'content' => [
                    'components' => [
                        $this->component('tpl_stat_group_1', 'stat-number', '今日订单', 40, 40, 300, 140, 1, ['refresh' => 30], [
                            'prefix' => '',
                            'unit' => '单',
                            'decimals' => 0,
                        ]),
                        $this->component('tpl_stat_group_2', 'stat-number', '今日金额', 370, 40, 300, 140, 1, ['refresh' => 30], [
                            'prefix' => '¥',
                            'unit' => '',
                            'decimals' => 2,
                        ]),
                        $this->component('tpl_stat_group_3', 'stat-number', '活跃用户', 700, 40, 300, 140, 1, ['refresh' => 30], [
                            'prefix' => '',
                            'unit' => '人',
                            'decimals' => 0,
                        ]),
                    ],
                ],
            ],
            [
                'type' => 'component',
                'name' => '服务健康状态矩阵',
                'category' => '监控组件',
                'description' => '状态矩阵加流光边框，适合服务健康、设备在线、区域运营状态扫描。',
                'component_count' => 2,
                'content' => [
                    'components' => [
                        $this->component('tpl_health_border', 'decor-flow-border', '', 40, 40, 560, 360, 1, [], [
                            'variant' => 'orbit',
                            'accent' => '#23d8ff',
                            'secondary' => '#ffcf5a',
                            'opacity' => 0.86,
                            'speed' => 5,
                            'thickness' => 2,
                            'glow' => true,
                        ]),
                        $this->component('tpl_health_matrix', 'status-matrix', '服务健康度', 64, 68, 512, 304, 2, ['refresh' => 30], [
                            'labelField' => '',
                            'statusField' => '',
                            'valueField' => '',
                            'groupField' => '',
                            'maxRows' => 12,
                            'columns' => 3,
                            'unit' => '%',
                            'decimals' => 0,
                            'showValue' => true,
                            'showStatus' => true,
                            'accent' => '#69b7ff',
                        ]),
                    ],
                ],
            ],
        ];
    }

    private function component(
        string $id,
        string $type,
        string $title,
        int $x,
        int $y,
        int $w,
        int $h,
        int $z,
        array $dataset,
        array $option
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'rect' => ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'z' => $z],
            'dataset' => $dataset,
            'option' => $option,
        ];
    }

    private function q(mixed $value): string
    {
        return $this->getAdapter()->getConnection()->quote((string) $value);
    }
}
