<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NormalizeB8cmsSeoUrls extends AbstractMigration
{
    public function up(): void
    {
        $this->replaceNavigationUrls([
            '/?lang=zh-CN' => '/',
            '/page/products?lang=zh-CN' => '/page/products',
            '/page/news?lang=zh-CN' => '/page/news',
            '/page/about?lang=zh-CN' => '/page/about',
            '/page/contact?lang=zh-CN' => '/page/contact',
            '/?lang=en-US' => '/en-US',
            '/page/products?lang=en-US' => '/en-US/page/products',
            '/page/news?lang=en-US' => '/en-US/page/news',
            '/page/about?lang=en-US' => '/en-US/page/about',
            '/page/contact?lang=en-US' => '/en-US/page/contact',
        ]);
    }

    public function down(): void
    {
        $this->replaceNavigationUrls([
            '/' => '/?lang=zh-CN',
            '/page/products' => '/page/products?lang=zh-CN',
            '/page/news' => '/page/news?lang=zh-CN',
            '/page/about' => '/page/about?lang=zh-CN',
            '/page/contact' => '/page/contact?lang=zh-CN',
            '/en-US' => '/?lang=en-US',
            '/en-US/page/products' => '/page/products?lang=en-US',
            '/en-US/page/news' => '/page/news?lang=en-US',
            '/en-US/page/about' => '/page/about?lang=en-US',
            '/en-US/page/contact' => '/page/contact?lang=en-US',
        ]);
    }

    private function replaceNavigationUrls(array $replacements): void
    {
        foreach ($replacements as $from => $to) {
            $this->execute(
                'UPDATE `b8cms_navigation`
                SET `url` = ' . $this->q($to) . ', `update_time` = NOW()
                WHERE `url` = ' . $this->q($from) . ' AND `delete_time` IS NULL'
            );
        }
    }

    private function q(string $value): string
    {
        return $this->getAdapter()->getConnection()->quote($value);
    }
}
