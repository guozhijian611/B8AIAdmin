#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 根据表规格 JSON 生成 B8AIadmin Phinx 建表迁移。
 *
 * 只创建迁移文件，不执行数据库变更。生成后请在 server/ 下运行：
 *   php webman b8:migrate --dry-run
 */

$args = parseArgs($argv);

if (isset($args['help']) || isset($args['h'])) {
    printUsage();
    exit(0);
}

if (empty($args['spec'])) {
    printUsage();
    exit(1);
}

$repoRoot = realpath(__DIR__ . '/../../../..');
if (!$repoRoot) {
    fwrite(STDERR, "错误: 无法定位仓库根目录\n");
    exit(1);
}

$specPath = resolvePath((string) $args['spec'], getcwd());
if (!is_file($specPath)) {
    fwrite(STDERR, "错误: 表规格文件不存在: {$specPath}\n");
    exit(1);
}

$spec = json_decode((string) file_get_contents($specPath), true);
if (!is_array($spec)) {
    fwrite(STDERR, "错误: JSON 解析失败 - " . json_last_error_msg() . "\n");
    exit(1);
}

$migration = buildMigration($spec, $args);

if (isset($args['dry-run'])) {
    echo $migration['content'];
    exit(0);
}

$outputDir = isset($args['output-dir'])
    ? resolvePath((string) $args['output-dir'], getcwd())
    : $repoRoot . '/Database/migrations';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$outputPath = $outputDir . DIRECTORY_SEPARATOR . $migration['filename'];
if (file_exists($outputPath) && !isset($args['force'])) {
    fwrite(STDERR, "错误: 迁移文件已存在: {$outputPath}\n");
    fwrite(STDERR, "如需覆盖，请加 --force\n");
    exit(1);
}

file_put_contents($outputPath, $migration['content']);

echo "已生成迁移文件: {$outputPath}\n";
echo "下一步建议:\n";
echo "  cd {$repoRoot}/server\n";
echo "  php webman b8:migrate:status\n";
echo "  php webman b8:migrate --dry-run\n";

function buildMigration(array $spec, array $args): array
{
    $tableName = requireString($spec, 'table');
    assertSafeIdentifier($tableName, 'table');

    $comment = (string) ($spec['comment'] ?? '');
    $className = (string) ($spec['class'] ?? ('Create' . studly($tableName) . 'Table'));
    assertSafeClassName($className);

    $timestamp = (string) ($args['timestamp'] ?? date('YmdHis'));
    if (!preg_match('/^\d{14}$/', $timestamp)) {
        throwRuntime('timestamp 必须是 14 位数字，例如 20260603093000');
    }

    $fields = $spec['fields'] ?? [];
    if (!is_array($fields) || empty($fields)) {
        throwRuntime('fields 必须是非空数组');
    }

    $audit = array_key_exists('audit', $spec) ? (bool) $spec['audit'] : true;
    $softDelete = array_key_exists('soft_delete', $spec) ? (bool) $spec['soft_delete'] : true;
    $fields = normalizeFields($fields, $audit, $softDelete);
    $indexes = normalizeIndexes($spec['indexes'] ?? []);
    $dicts = normalizeDicts($spec['dicts'] ?? []);

    $metaKey = 'created_table:' . $tableName;
    $migrationName = $timestamp . '_' . snake($className);

    $lines = [];
    $lines[] = '<?php';
    $lines[] = '';
    $lines[] = 'declare(strict_types=1);';
    $lines[] = '';
    $lines[] = 'use Phinx\Db\Adapter\MysqlAdapter;';
    $lines[] = 'use Phinx\Migration\AbstractMigration;';
    $lines[] = '';
    $lines[] = 'final class ' . $className . ' extends AbstractMigration';
    $lines[] = '{';
    $lines[] = "    private const META_TABLE = 'phinx_migration_meta';";
    $lines[] = '    private const MIGRATION = ' . phpValue($migrationName) . ';';
    $lines[] = '    private const TABLE = ' . phpValue($tableName) . ';';
    $lines[] = '    private const META_KEY = ' . phpValue($metaKey) . ';';
    $lines[] = '    private const DICTS = ' . renderArray($dicts, 4) . ';';
    $lines[] = '';
    $lines[] = '    public function up(): void';
    $lines[] = '    {';
    $lines[] = '        $this->ensureMetaTable();';
    $lines[] = '        $this->upsertDictionaries();';
    $lines[] = '';
    $lines[] = '        if ($this->hasTable(self::TABLE)) {';
    $lines[] = '            return;';
    $lines[] = '        }';
    $lines[] = '';
    $lines[] = '        $table = $this->table(self::TABLE, [';
    $lines[] = "            'id' => false,";
    $lines[] = "            'primary_key' => ['id'],";
    if ($comment !== '') {
        $lines[] = '            ' . phpValue('comment') . ' => ' . phpValue($comment) . ',';
    }
    $lines[] = '        ]);';
    $lines[] = "        \$table->addColumn('id', 'integer', [";
    $lines[] = "            'identity' => true,";
    $lines[] = "            'signed' => false,";
    $lines[] = "            'comment' => '主键',";
    $lines[] = '        ]);';

    foreach ($fields as $field) {
        $lines[] = renderAddColumn($field);
    }

    foreach ($indexes as $index) {
        $lines[] = renderAddIndex($index);
    }

    $lines[] = '        $table->create();';
    $lines[] = '';
    $lines[] = '        $this->markMeta(self::META_KEY);';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    public function down(): void';
    $lines[] = '    {';
    $lines[] = '        if (!$this->hasTable(self::META_TABLE)) {';
    $lines[] = '            return;';
    $lines[] = '        }';
    $lines[] = '';
    $lines[] = '        if ($this->hasMeta(self::META_KEY) && $this->hasTable(self::TABLE)) {';
    $lines[] = '            $this->table(self::TABLE)->drop()->save();';
    $lines[] = '            $this->deleteMeta(self::META_KEY);';
    $lines[] = '        }';
    $lines[] = '';
    $lines[] = '        $this->rollbackDictionaries();';
    $lines[] = '';
    $lines[] = '        $remaining = $this->fetchRow("SELECT 1 FROM `" . self::META_TABLE . "` LIMIT 1");';
    $lines[] = '        if (!$remaining) {';
    $lines[] = '            $this->execute("DROP TABLE IF EXISTS `" . self::META_TABLE . "`");';
    $lines[] = '        }';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    private function ensureMetaTable(): void';
    $lines[] = '    {';
    $lines[] = '        if ($this->hasTable(self::META_TABLE)) {';
    $lines[] = '            return;';
    $lines[] = '        }';
    $lines[] = '';
    $lines[] = "        \$this->table(self::META_TABLE, ['id' => false, 'primary_key' => ['migration', 'meta_key']])";
    $lines[] = "            ->addColumn('migration', 'string', ['limit' => 191])";
    $lines[] = "            ->addColumn('meta_key', 'string', ['limit' => 191])";
    $lines[] = "            ->addColumn('meta_value', 'string', ['limit' => 191, 'null' => true])";
    $lines[] = "            ->addColumn('created_at', 'datetime')";
    $lines[] = '            ->create();';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    private function upsertDictionaries(): void';
    $lines[] = '    {';
    $lines[] = '        foreach (self::DICTS as $dict) {';
    $lines[] = "            \$code = (string) \$dict['code'];";
    $lines[] = "            \$type = \$this->findDictType(\$code);";
    $lines[] = '            if (!$type) {';
    $lines[] = '                $this->execute(';
    $lines[] = '                    "INSERT INTO `sa_system_dict_type` (`name`, `code`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`) VALUES ("';
    $lines[] = "                    . \$this->sqlString((string) \$dict['name']) . ', '";
    $lines[] = '                    . $this->sqlString($code) . ", 1, "';
    $lines[] = "                    . \$this->sqlString((string) (\$dict['remark'] ?? '')) . ', 1, 1, NOW(), NOW(), NULL)'";
    $lines[] = '                );';
    $lines[] = '                $this->markMeta("created_dict_type:{$code}");';
    $lines[] = '                $type = $this->findDictType($code);';
    $lines[] = '            }';
    $lines[] = '';
    $lines[] = '            if (!$type) {';
    $lines[] = '                continue;';
    $lines[] = '            }';
    $lines[] = '';
    $lines[] = "            foreach (\$dict['items'] as \$item) {";
    $lines[] = "                \$value = (string) \$item['value'];";
    $lines[] = '                if ($this->findDictData($code, $value)) {';
    $lines[] = '                    continue;';
    $lines[] = '                }';
    $lines[] = '';
    $lines[] = '                $this->execute(';
    $lines[] = '                    "INSERT INTO `sa_system_dict_data` (`type_id`, `label`, `value`, `color`, `code`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`) VALUES ("';
    $lines[] = "                    . (int) \$type['id'] . ', '";
    $lines[] = "                    . \$this->sqlString((string) \$item['label']) . ', '";
    $lines[] = '                    . $this->sqlString($value) . ", "';
    $lines[] = "                    . \$this->sqlString((string) (\$item['color'] ?? '')) . ', '";
    $lines[] = '                    . $this->sqlString($code) . ", "';
    $lines[] = "                    . (int) (\$item['sort'] ?? 100) . ', '";
    $lines[] = "                    . (int) (\$item['status'] ?? 1) . ', '";
    $lines[] = "                    . \$this->sqlString((string) (\$item['remark'] ?? '')) . ', 1, 1, NOW(), NOW(), NULL)'";
    $lines[] = '                );';
    $lines[] = '                $this->markMeta("created_dict_data:{$code}:{$value}");';
    $lines[] = '            }';
    $lines[] = '        }';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    private function rollbackDictionaries(): void';
    $lines[] = '    {';
    $lines[] = '        foreach (array_reverse(self::DICTS) as $dict) {';
    $lines[] = "            \$code = (string) \$dict['code'];";
    $lines[] = "            foreach (array_reverse(\$dict['items']) as \$item) {";
    $lines[] = "                \$value = (string) \$item['value'];";
    $lines[] = '                $metaKey = "created_dict_data:{$code}:{$value}";';
    $lines[] = '                if (!$this->hasMeta($metaKey)) {';
    $lines[] = '                    continue;';
    $lines[] = '                }';
    $lines[] = '                $this->execute(';
    $lines[] = '                    "DELETE FROM `sa_system_dict_data` WHERE `code` = " . $this->sqlString($code)';
    $lines[] = '                    . " AND `value` = " . $this->sqlString($value)';
    $lines[] = '                );';
    $lines[] = '                $this->deleteMeta($metaKey);';
    $lines[] = '            }';
    $lines[] = '';
    $lines[] = '            $typeMetaKey = "created_dict_type:{$code}";';
    $lines[] = '            if ($this->hasMeta($typeMetaKey)) {';
    $lines[] = '                $this->execute("DELETE FROM `sa_system_dict_data` WHERE `code` = " . $this->sqlString($code));';
    $lines[] = '                $this->execute("DELETE FROM `sa_system_dict_type` WHERE `code` = " . $this->sqlString($code));';
    $lines[] = '                $this->deleteMeta($typeMetaKey);';
    $lines[] = '            }';
    $lines[] = '        }';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    private function findDictType(string $code): array|false';
    $lines[] = '    {';
    $lines[] = '        return $this->fetchRow(';
    $lines[] = '            "SELECT `id` FROM `sa_system_dict_type` WHERE `code` = " . $this->sqlString($code)';
    $lines[] = '            . " AND `delete_time` IS NULL LIMIT 1"';
    $lines[] = '        );';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    private function findDictData(string $code, string $value): array|false';
    $lines[] = '    {';
    $lines[] = '        return $this->fetchRow(';
    $lines[] = '            "SELECT `id` FROM `sa_system_dict_data` WHERE `code` = " . $this->sqlString($code)';
    $lines[] = '            . " AND `value` = " . $this->sqlString($value)';
    $lines[] = '            . " AND `delete_time` IS NULL LIMIT 1"';
    $lines[] = '        );';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    private function markMeta(string $key): void';
    $lines[] = '    {';
    $lines[] = '        $this->execute(';
    $lines[] = '            "INSERT INTO `" . self::META_TABLE . "` (`migration`, `meta_key`, `meta_value`, `created_at`) VALUES ("';
    $lines[] = '            . $this->sqlString(self::MIGRATION) . ", "';
    $lines[] = '            . $this->sqlString($key) . ", " . $this->sqlString("1") . ", NOW())"';
    $lines[] = '            . " ON DUPLICATE KEY UPDATE `meta_value` = VALUES(`meta_value`)"';
    $lines[] = '        );';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    private function hasMeta(string $key): bool';
    $lines[] = '    {';
    $lines[] = '        return (bool) $this->fetchRow(';
    $lines[] = '            "SELECT 1 FROM `" . self::META_TABLE . "` WHERE `migration` = " . $this->sqlString(self::MIGRATION)';
    $lines[] = '            . " AND `meta_key` = " . $this->sqlString($key)';
    $lines[] = '            . " LIMIT 1"';
    $lines[] = '        );';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    private function deleteMeta(string $key): void';
    $lines[] = '    {';
    $lines[] = '        $this->execute(';
    $lines[] = '            "DELETE FROM `" . self::META_TABLE . "` WHERE `migration` = " . $this->sqlString(self::MIGRATION)';
    $lines[] = '            . " AND `meta_key` = " . $this->sqlString($key)';
    $lines[] = '        );';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    private function sqlString(mixed $value): string';
    $lines[] = '    {';
    $lines[] = '        return "\'" . str_replace("\'", "\'\'", (string) $value) . "\'";';
    $lines[] = '    }';
    $lines[] = '}';
    $lines[] = '';

    return [
        'filename' => $migrationName . '.php',
        'content' => implode("\n", $lines),
    ];
}

function normalizeFields(array $fields, bool $audit, bool $softDelete): array
{
    $normalized = [];
    $seen = ['id' => true];

    foreach ($fields as $field) {
        if (!is_array($field)) {
            throwRuntime('fields 中每一项都必须是对象');
        }

        $name = requireString($field, 'name');
        assertSafeIdentifier($name, 'field');
        if (isset($seen[$name])) {
            throwRuntime("字段重复或保留字段重复: {$name}");
        }
        $seen[$name] = true;

        $normalized[] = normalizeField($field);
    }

    if ($audit) {
        foreach ([
            ['name' => 'created_by', 'type' => 'integer', 'null' => true, 'default' => null, 'comment' => '创建者'],
            ['name' => 'updated_by', 'type' => 'integer', 'null' => true, 'default' => null, 'comment' => '更新者'],
            ['name' => 'create_time', 'type' => 'datetime', 'null' => true, 'default' => null, 'comment' => '创建时间'],
            ['name' => 'update_time', 'type' => 'datetime', 'null' => true, 'default' => null, 'comment' => '修改时间'],
        ] as $field) {
            if (!isset($seen[$field['name']])) {
                $normalized[] = normalizeField($field);
                $seen[$field['name']] = true;
            }
        }
    }

    if ($softDelete && !isset($seen['delete_time'])) {
        $normalized[] = normalizeField([
            'name' => 'delete_time',
            'type' => 'datetime',
            'null' => true,
            'default' => null,
            'comment' => '删除时间',
        ]);
    }

    return $normalized;
}

function normalizeField(array $field): array
{
    $name = requireString($field, 'name');
    $type = strtolower((string) ($field['type'] ?? inferType($name)));

    if (!empty($field['dict'])) {
        assertSafeIdentifier((string) $field['dict'], 'field dict');
    }

    if (str_starts_with($name, 'is_') && !array_key_exists('default', $field)) {
        $field['default'] = 2;
        $field['comment'] = ($field['comment'] ?? $name) . '：1是 2否';
    }

    if ($name === 'status' && !array_key_exists('default', $field)) {
        $field['default'] = 1;
        $field['comment'] = $field['comment'] ?? '状态：1正常 2停用';
    }

    $field['type'] = normalizeType($type);
    $field['options'] = buildColumnOptions($field, $type);

    return $field;
}

function buildColumnOptions(array $field, string $rawType): array
{
    $options = [];

    if (in_array($rawType, ['tinyint', 'tinyinteger'], true)) {
        $options['limit'] = new RawPhp('MysqlAdapter::INT_TINY');
        $options['signed'] = false;
    } elseif (isset($field['limit'])) {
        $options['limit'] = (int) $field['limit'];
    } elseif ($field['type'] === 'string') {
        $options['limit'] = 255;
    }

    foreach (['precision', 'scale'] as $key) {
        if (isset($field[$key])) {
            $options[$key] = (int) $field[$key];
        }
    }

    $options['null'] = array_key_exists('null', $field) ? (bool) $field['null'] : true;

    if (array_key_exists('default', $field)) {
        $options['default'] = $field['default'];
    }

    if (!empty($field['comment'])) {
        $options['comment'] = (string) $field['comment'];
    }

    if (isset($field['signed'])) {
        $options['signed'] = (bool) $field['signed'];
    }

    return $options;
}

function normalizeIndexes(mixed $indexes): array
{
    if ($indexes === null) {
        return [];
    }

    if (!is_array($indexes)) {
        throwRuntime('indexes 必须是数组');
    }

    $result = [];
    foreach ($indexes as $index) {
        if (!is_array($index)) {
            throwRuntime('indexes 中每一项都必须是对象');
        }

        $columns = $index['columns'] ?? [];
        if (!is_array($columns) || empty($columns)) {
            throwRuntime('index.columns 必须是非空数组');
        }
        foreach ($columns as $column) {
            assertSafeIdentifier((string) $column, 'index column');
        }

        $normalized = [
            'columns' => array_values($columns),
            'unique' => !empty($index['unique']),
        ];

        if (!empty($index['name'])) {
            assertSafeIdentifier((string) $index['name'], 'index name');
            $normalized['name'] = (string) $index['name'];
        }

        $result[] = $normalized;
    }

    return $result;
}

function normalizeDicts(mixed $dicts): array
{
    if ($dicts === null) {
        return [];
    }

    if (!is_array($dicts)) {
        throwRuntime('dicts 必须是数组');
    }

    $result = [];
    $seen = [];
    foreach ($dicts as $dict) {
        if (!is_array($dict)) {
            throwRuntime('dicts 中每一项都必须是对象');
        }

        $code = requireString($dict, 'code');
        assertSafeIdentifier($code, 'dict code');
        if (isset($seen[$code])) {
            throwRuntime("字典编码重复: {$code}");
        }
        $seen[$code] = true;

        $items = $dict['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            throwRuntime("字典 {$code} 的 items 必须是非空数组");
        }

        $normalizedItems = [];
        $seenValues = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throwRuntime("字典 {$code} 的 items 每一项都必须是对象");
            }
            if (!isset($item['label']) || !is_string($item['label']) || trim($item['label']) === '') {
                throwRuntime("字典 {$code} 的 item.label 不能为空");
            }
            if (!array_key_exists('value', $item) || trim((string) $item['value']) === '') {
                throwRuntime("字典 {$code} 的 item.value 不能为空");
            }

            $value = (string) $item['value'];
            if (isset($seenValues[$value])) {
                throwRuntime("字典 {$code} 的 value 重复: {$value}");
            }
            $seenValues[$value] = true;

            $normalizedItems[] = [
                'label' => trim($item['label']),
                'value' => $value,
                'color' => (string) ($item['color'] ?? ''),
                'sort' => isset($item['sort']) ? (int) $item['sort'] : 100,
                'status' => isset($item['status']) ? (int) $item['status'] : 1,
                'remark' => (string) ($item['remark'] ?? ''),
            ];
        }

        $result[] = [
            'code' => $code,
            'name' => trim((string) ($dict['name'] ?? $code)),
            'remark' => (string) ($dict['remark'] ?? ''),
            'items' => $normalizedItems,
        ];
    }

    return $result;
}

function renderAddColumn(array $field): string
{
    return '        $table->addColumn(' . phpValue($field['name']) . ', ' . phpValue($field['type']) . ', ' . renderArray($field['options'], 12) . ');';
}

function renderAddIndex(array $index): string
{
    $options = [];
    if (!empty($index['name'])) {
        $options['name'] = $index['name'];
    }
    if (!empty($index['unique'])) {
        $options['unique'] = true;
    }

    if (empty($options)) {
        return '        $table->addIndex(' . renderArray($index['columns'], 12) . ');';
    }

    return '        $table->addIndex(' . renderArray($index['columns'], 12) . ', ' . renderArray($options, 12) . ');';
}

function normalizeType(string $type): string
{
    return match ($type) {
        'varchar', 'char' => 'string',
        'int', 'uint', 'unsignedint' => 'integer',
        'tinyint', 'tinyinteger' => 'integer',
        'bigint', 'biginteger' => 'biginteger',
        'bool', 'boolean' => 'boolean',
        'longtext', 'mediumtext' => 'text',
        default => $type,
    };
}

function inferType(string $name): string
{
    if ($name === 'status' || $name === 'sort' || str_ends_with($name, '_id') || str_starts_with($name, 'is_')) {
        return 'integer';
    }

    if (str_contains($name, 'time')) {
        return 'datetime';
    }

    return 'string';
}

function parseArgs(array $argv): array
{
    $result = [];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $parts = explode('=', substr($arg, 2), 2);
        $result[$parts[0]] = $parts[1] ?? true;
    }
    return $result;
}

function resolvePath(string $path, string $base): string
{
    if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
        return $path;
    }

    return $base . DIRECTORY_SEPARATOR . $path;
}

function requireString(array $data, string $key): string
{
    if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
        throwRuntime("缺少必填字符串字段: {$key}");
    }

    return trim($data[$key]);
}

function assertSafeIdentifier(string $value, string $label): void
{
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $value)) {
        throwRuntime("{$label} 只能使用小写蛇形命名: {$value}");
    }
}

function assertSafeClassName(string $value): void
{
    if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $value)) {
        throwRuntime("class 必须是 PascalCase 类名: {$value}");
    }
}

function studly(string $value): string
{
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $value)));
}

function snake(string $value): string
{
    $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?: $value;
    return strtolower($value);
}

function renderArray(array $array, int $indent): string
{
    if ($array === []) {
        return '[]';
    }

    $space = str_repeat(' ', $indent);
    $inner = str_repeat(' ', $indent + 4);
    $lines = ['['];
    foreach ($array as $key => $value) {
        $prefix = is_int($key) ? '' : phpValue((string) $key) . ' => ';
        $lines[] = $inner . $prefix . phpValue($value, $indent + 4) . ',';
    }
    $lines[] = $space . ']';

    return implode("\n", $lines);
}

function phpValue(mixed $value, int $indent = 0): string
{
    if ($value instanceof RawPhp) {
        return $value->code;
    }
    if (is_array($value)) {
        return renderArray($value, $indent);
    }
    if ($value === null) {
        return 'null';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return var_export((string) $value, true);
}

function throwRuntime(string $message): never
{
    fwrite(STDERR, "错误: {$message}\n");
    exit(1);
}

function printUsage(): void
{
    echo <<<USAGE
根据表规格 JSON 生成 B8AIadmin Phinx 建表迁移

用法:
  php create_table_migration.php --spec=table.json [--output-dir=Database/migrations]

选项:
  --spec=PATH          表规格 JSON 文件
  --output-dir=PATH    输出目录，默认 Database/migrations
  --timestamp=YYYYMMDDHHMMSS
  --dry-run            只输出迁移内容，不写文件
  --force              覆盖已存在文件

示例:
  php .codex/skills/saicode/scripts/create_table_migration.php \\
    --spec=.codex/skills/saicode/templates/table_spec.example.json \\
    --dry-run

USAGE;
}

final class RawPhp
{
    public function __construct(public string $code)
    {
    }
}
