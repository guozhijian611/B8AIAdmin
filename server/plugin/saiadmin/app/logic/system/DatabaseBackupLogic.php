<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\logic\system;

use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;
use support\think\Db;
use zjkal\MysqlHelper;

/**
 * 数据库导入导出逻辑层
 */
class DatabaseBackupLogic extends BaseLogic
{
    /**
     * 获取数据库状态
     */
    public function overview(string $source = 'mysql'): array
    {
        $config = $this->getConnectionConfig($source);
        $tables = $this->getTables($source);

        return [
            'source' => $source,
            'database' => $config['database'],
            'host' => ($config['host'] ?? '127.0.0.1') . ':' . ($config['port'] ?? 3306),
            'charset' => $config['charset'] ?? 'utf8mb4',
            'prefix' => $config['prefix'] ?? '',
            'table_count' => count($tables),
            'sources' => array_keys(config('database.connections', [])),
            'tables' => $tables,
            'backups' => $this->getBackupFiles(),
        ];
    }

    /**
     * 导出 SQL 文件
     */
    public function exportSql(array $data): array
    {
        $source = $data['source'] ?? 'mysql';
        $tables = $this->filterTables($source, $data['tables'] ?? []);
        $withData = $this->parseBool($data['with_data'] ?? true);
        $disableForeignKeyChecks = $this->parseBool($data['disable_foreign_key_checks'] ?? true);

        $filename = $this->makeExportFilename($source, $withData);
        $path = $this->getBackupDir() . DIRECTORY_SEPARATOR . $filename;

        $mysql = $this->makeMysqlHelper($source);
        $mysql->exportSqlFile($path, $withData, $tables, $disableForeignKeyChecks);

        if (!is_file($path)) {
            throw new ApiException('导出文件创建失败');
        }

        return [
            'path' => $path,
            'filename' => $filename,
        ];
    }

    /**
     * 导入 SQL 文件
     */
    public function importSql($file, array $data): void
    {
        $ext = strtolower((string)$file->getUploadExtension());
        if ($ext !== 'sql') {
            throw new ApiException('仅支持导入 .sql 文件');
        }

        $source = $data['source'] ?? 'mysql';
        $dropTableIfExists = $this->parseBool($data['drop_table_if_exists'] ?? false);
        $path = $this->moveImportFile($file);
        $config = $this->getConnectionConfig($source);

        $mysql = $this->makeMysqlHelper($source);
        $mysql->importSqlFile($path, (string)($config['prefix'] ?? ''), $dropTableIfExists);
    }

    /**
     * 删除本地备份文件
     */
    public function deleteBackup(string $filename): bool
    {
        $path = $this->getSafeBackupPath($filename);
        if (!is_file($path)) {
            throw new ApiException('备份文件不存在');
        }
        return unlink($path);
    }

    /**
     * 获取本地备份文件路径
     */
    public function getBackupPath(string $filename): string
    {
        $path = $this->getSafeBackupPath($filename);
        if (!is_file($path)) {
            throw new ApiException('备份文件不存在');
        }
        return $path;
    }

    /**
     * 创建 MysqlHelper 实例
     */
    protected function makeMysqlHelper(string $source): MysqlHelper
    {
        $mysql = new MysqlHelper();
        $mysql->setConfig($this->getConnectionConfig($source));
        return $mysql;
    }

    /**
     * 获取数据库连接配置
     */
    protected function getConnectionConfig(string $source): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $source)) {
            throw new ApiException('数据源名称不合法');
        }

        $config = config("database.connections.$source");
        if (empty($config)) {
            throw new ApiException('数据库配置读取失败');
        }

        return $config;
    }

    /**
     * 获取表名列表
     */
    protected function getTables(string $source): array
    {
        $list = Db::connect($source)->query('SHOW TABLES');
        $tables = [];
        foreach ($list as $row) {
            $tables[] = current($row);
        }
        sort($tables);
        return $tables;
    }

    /**
     * 过滤要导出的表名
     */
    protected function filterTables(string $source, array $tables): array
    {
        if (empty($tables)) {
            return [];
        }

        $allowTables = $this->getTables($source);
        $result = [];
        foreach ($tables as $table) {
            if (!is_string($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                throw new ApiException('导出表名不合法');
            }
            if (!in_array($table, $allowTables, true)) {
                throw new ApiException("数据表 {$table} 不存在");
            }
            $result[] = $table;
        }

        return $result;
    }

    /**
     * 生成导出文件名
     */
    protected function makeExportFilename(string $source, bool $withData): string
    {
        $type = $withData ? 'full' : 'structure';
        return sprintf('%s_%s_%s.sql', $source, $type, date('Ymd_His'));
    }

    /**
     * 解析前端传入的布尔值
     */
    protected function parseBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * 移动导入文件
     */
    protected function moveImportFile($file): string
    {
        $dir = runtime_path() . '/mysql-helper/imports';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.sql';
        $file->move($path);
        return $path;
    }

    /**
     * 获取备份目录
     */
    protected function getBackupDir(): string
    {
        $dir = runtime_path() . '/mysql-helper/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * 获取备份文件列表
     */
    protected function getBackupFiles(): array
    {
        $files = glob($this->getBackupDir() . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        $list = [];
        foreach ($files as $file) {
            $list[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'create_time' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        usort($list, fn($a, $b) => strcmp($b['create_time'], $a['create_time']));
        return $list;
    }

    /**
     * 获取安全的备份文件路径
     */
    protected function getSafeBackupPath(string $filename): string
    {
        if (!preg_match('/^[a-zA-Z0-9_.-]+\.sql$/', $filename)) {
            throw new ApiException('备份文件名不合法');
        }
        return $this->getBackupDir() . DIRECTORY_SEPARATOR . $filename;
    }
}
