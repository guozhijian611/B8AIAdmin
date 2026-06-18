<?php

namespace plugin\saiboard\app\service;

use InvalidArgumentException;
use PDO;

class SqlBuilder
{
    private array $columnsCache = [];

    public function build(PDO $pdo, string $datasetType, array $config): array
    {
        return match ($datasetType) {
            'table_raw' => $this->buildRaw($pdo, $config),
            'table_count' => $this->buildCount($pdo, $config),
            default => throw new InvalidArgumentException('不支持的 MySQL 取数类型'),
        };
    }

    private function buildRaw(PDO $pdo, array $config): array
    {
        $table = $this->assertTable($pdo, (string) ($config['table'] ?? ''));
        $columns = $this->columns($pdo, $table);
        $fields = $this->normalizeFields($config['fields'] ?? [], $columns);
        [$whereSql, $bindings] = $this->buildWhere($columns, $config['conditions'] ?? []);
        $orderSql = $this->buildOrder($columns, $config);
        $limit = $this->normalizeLimit($config['limit'] ?? 100);

        $sql = sprintf(
            'SELECT %s FROM `%s`%s%s LIMIT %d',
            implode(', ', array_map(static fn ($field) => "`{$field}`", $fields)),
            $table,
            $whereSql,
            $orderSql,
            $limit
        );

        return [$sql, $bindings, 'raw'];
    }

    private function buildCount(PDO $pdo, array $config): array
    {
        $table = $this->assertTable($pdo, (string) ($config['table'] ?? ''));
        $columns = $this->columns($pdo, $table);
        [$whereSql, $bindings] = $this->buildWhere($columns, $config['conditions'] ?? []);

        return [sprintf('SELECT COUNT(*) AS total FROM `%s`%s', $table, $whereSql), $bindings, 'count'];
    }

    private function assertTable(PDO $pdo, string $table): string
    {
        $table = trim($table);
        if (!$this->isIdentifier($table)) {
            throw new InvalidArgumentException('数据表名称不正确');
        }

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (!in_array($table, $tables, true)) {
            throw new InvalidArgumentException('数据表不存在或不允许访问');
        }

        return $table;
    }

    private function columns(PDO $pdo, string $table): array
    {
        if (isset($this->columnsCache[$table])) {
            return $this->columnsCache[$table];
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $columns = array_values(array_map(static fn ($row) => (string) $row['Field'], $rows));
        if ($columns === []) {
            throw new InvalidArgumentException('数据表没有可用字段');
        }

        return $this->columnsCache[$table] = $columns;
    }

    private function normalizeFields(mixed $fields, array $columns): array
    {
        if (is_string($fields)) {
            $fields = array_filter(array_map('trim', explode(',', $fields)));
        }
        if (!is_array($fields) || $fields === []) {
            return $columns;
        }

        $result = [];
        foreach ($fields as $field) {
            $field = trim((string) $field);
            if (!$this->isIdentifier($field) || !in_array($field, $columns, true)) {
                throw new InvalidArgumentException("字段 {$field} 不存在或不允许访问");
            }
            $result[] = $field;
        }

        return array_values(array_unique($result));
    }

    private function buildWhere(array $columns, mixed $conditions): array
    {
        if (!is_array($conditions) || $conditions === []) {
            return ['', []];
        }

        $parts = [];
        $bindings = [];
        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            $field = trim((string) ($condition['field'] ?? ''));
            if (!$this->isIdentifier($field) || !in_array($field, $columns, true)) {
                throw new InvalidArgumentException("条件字段 {$field} 不存在或不允许访问");
            }

            $operator = strtolower(trim((string) ($condition['op'] ?? '=')));
            $value = $condition['value'] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            match ($operator) {
                '=', '!=', '>', '>=', '<', '<=' => $this->appendSimpleWhere($parts, $bindings, $field, $operator, $value),
                'like' => $this->appendSimpleWhere($parts, $bindings, $field, 'LIKE', '%' . (string) $value . '%'),
                'in' => $this->appendInWhere($parts, $bindings, $field, $value),
                'between' => $this->appendBetweenWhere($parts, $bindings, $field, $value),
                default => throw new InvalidArgumentException("条件操作符 {$operator} 不支持"),
            };
        }

        return $parts === [] ? ['', []] : [' WHERE ' . implode(' AND ', $parts), $bindings];
    }

    private function appendSimpleWhere(array &$parts, array &$bindings, string $field, string $operator, mixed $value): void
    {
        $parts[] = "`{$field}` {$operator} ?";
        $bindings[] = $value;
    }

    private function appendInWhere(array &$parts, array &$bindings, string $field, mixed $value): void
    {
        $values = is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)));
        if ($values === []) {
            return;
        }

        $parts[] = "`{$field}` IN (" . implode(',', array_fill(0, count($values), '?')) . ')';
        array_push($bindings, ...array_values($values));
    }

    private function appendBetweenWhere(array &$parts, array &$bindings, string $field, mixed $value): void
    {
        $values = is_array($value) ? array_values($value) : array_map('trim', explode(',', (string) $value));
        if (count($values) < 2 || $values[0] === '' || $values[1] === '') {
            return;
        }

        $parts[] = "`{$field}` BETWEEN ? AND ?";
        $bindings[] = $values[0];
        $bindings[] = $values[1];
    }

    private function buildOrder(array $columns, array $config): string
    {
        $order = $config['order'] ?? [];
        if ($order === [] && !empty($config['order_field'])) {
            $order = [[
                'field' => $config['order_field'],
                'direction' => $config['order_type'] ?? 'asc',
            ]];
        }
        if (!is_array($order) || $order === []) {
            return '';
        }

        $parts = [];
        foreach ($order as $item) {
            if (is_string($item)) {
                $item = ['field' => $item, 'direction' => 'asc'];
            }
            if (!is_array($item)) {
                continue;
            }
            $field = trim((string) ($item['field'] ?? ''));
            if (!$this->isIdentifier($field) || !in_array($field, $columns, true)) {
                throw new InvalidArgumentException("排序字段 {$field} 不存在或不允许访问");
            }
            $direction = strtolower((string) ($item['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $parts[] = "`{$field}` {$direction}";
        }

        return $parts === [] ? '' : ' ORDER BY ' . implode(', ', $parts);
    }

    private function normalizeLimit(mixed $limit): int
    {
        $limit = (int) $limit;
        if ($limit <= 0) {
            return 100;
        }

        return min($limit, 1000);
    }

    private function isIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1;
    }
}
