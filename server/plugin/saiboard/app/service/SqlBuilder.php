<?php

namespace plugin\saiboard\app\service;

use InvalidArgumentException;
use PDO;

class SqlBuilder
{
    private array $columnsCache = [];
    private array $columnTypesCache = [];

    public function build(PDO $pdo, string $datasetType, array $config, array $runtimeParams = []): array
    {
        $templateParams = $this->resolveTemplateParams($config['params'] ?? [], $runtimeParams);

        return match ($datasetType) {
            'table_raw' => $this->buildRaw($pdo, $config, $templateParams),
            'table_count' => $this->buildCount($pdo, $config, $templateParams),
            'table_aggregate' => $this->buildAggregate($pdo, $config, $templateParams),
            default => throw new InvalidArgumentException('不支持的 MySQL 取数类型'),
        };
    }

    private function buildRaw(PDO $pdo, array $config, array $templateParams = []): array
    {
        $table = $this->assertTable($pdo, (string) ($config['table'] ?? ''));
        $columns = $this->columns($pdo, $table);
        $columnTypes = $this->columnTypes($pdo, $table);
        $fields = $this->normalizeFields($config['fields'] ?? [], $columns);
        $selectExpressions = $this->buildRawSelectExpressions(
            $fields,
            $config,
            $columns,
            $columnTypes
        );
        [$whereSql, $bindings] = $this->buildWhere(
            $columns,
            $config['conditions'] ?? [],
            $columnTypes,
            $templateParams
        );
        $orderSql = $this->buildOrder($columns, $config);
        $limit = $this->normalizeLimit($config['limit'] ?? 100);

        $sql = sprintf(
            'SELECT %s FROM `%s`%s%s LIMIT %d',
            implode(', ', $selectExpressions),
            $table,
            $whereSql,
            $orderSql,
            $limit
        );

        return [$sql, $bindings, 'raw'];
    }

    private function buildCount(PDO $pdo, array $config, array $templateParams = []): array
    {
        $table = $this->assertTable($pdo, (string) ($config['table'] ?? ''));
        $columns = $this->columns($pdo, $table);
        $columnTypes = $this->columnTypes($pdo, $table);
        [$whereSql, $bindings] = $this->buildWhere(
            $columns,
            $config['conditions'] ?? [],
            $columnTypes,
            $templateParams
        );

        return [sprintf('SELECT COUNT(*) AS total FROM `%s`%s', $table, $whereSql), $bindings, 'count'];
    }

    private function buildAggregate(PDO $pdo, array $config, array $templateParams = []): array
    {
        $table = $this->assertTable($pdo, (string) ($config['table'] ?? ''));
        $columns = $this->columns($pdo, $table);
        $columnTypes = $this->columnTypes($pdo, $table);
        $dimension = $this->assertColumn($columns, (string) ($config['dimension'] ?? ''), '维度字段');
        $metrics = $this->normalizeAggregateMetrics($config, $columns, $columnTypes);

        [$whereSql, $bindings] = $this->buildWhere(
            $columns,
            $config['conditions'] ?? [],
            $columnTypes,
            $templateParams
        );
        $labelExpression = $this->dimensionExpression($dimension, (string) ($config['dimension_type'] ?? 'raw'));
        $metricExpressions = array_map(
            fn (array $metric) => $metric['expression'] . ' AS ' . $this->quoteAlias($metric['alias']),
            $metrics
        );
        $orderBy = $this->aggregateOrderBy($config['order_by'] ?? 'label', $metrics);
        $direction = strtolower((string) ($config['order_type'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $limit = $this->normalizeLimit($config['limit'] ?? 100);

        $sql = sprintf(
            'SELECT %s AS `label`, %s FROM `%s`%s GROUP BY `label` ORDER BY %s %s LIMIT %d',
            $labelExpression,
            implode(', ', $metricExpressions),
            $table,
            $whereSql,
            $orderBy,
            $direction,
            $limit
        );

        return [$sql, $bindings, 'raw'];
    }

    private function normalizeAggregateMetrics(array $config, array $columns, array $columnTypes): array
    {
        $items = $config['metrics'] ?? [];
        if (!is_array($items) || $items === []) {
            $items = [[
                'aggregate' => $config['aggregate'] ?? 'count',
                'field' => $config['metric'] ?? '',
                'alias' => 'value',
            ]];
        }
        if (count($items) > 8) {
            throw new InvalidArgumentException('聚合指标最多支持 8 项');
        }

        $result = [];
        $usedNames = ['label' => true];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('聚合指标配置不正确');
            }

            $aggregate = $this->assertAggregate((string) ($item['aggregate'] ?? 'count'));
            $field = '';
            $expression = 'COUNT(*)';
            if ($aggregate !== 'count') {
                $field = $this->assertColumn($columns, (string) ($item['field'] ?? ''), '指标字段');
                if (!$this->isNumericType($columnTypes[$field] ?? '')) {
                    throw new InvalidArgumentException('指标字段必须是数值类型');
                }
                $expression = strtoupper($aggregate) . "(`{$field}`)";
            }

            $alias = trim((string) ($item['alias'] ?? ''));
            if ($alias === '') {
                $alias = $this->defaultMetricAlias($aggregate, $field, $index);
            }
            $alias = $this->assertOutputAlias($alias, '聚合指标别名');
            $this->assertUniqueOutputName($usedNames, $alias);

            $result[] = [
                'aggregate' => $aggregate,
                'field' => $field,
                'alias' => $alias,
                'expression' => $expression,
            ];
        }

        if ($result === []) {
            throw new InvalidArgumentException('聚合指标必须至少配置一项');
        }

        return $result;
    }

    private function assertAggregate(string $aggregate): string
    {
        $aggregate = strtolower(trim($aggregate));
        if (!in_array($aggregate, ['count', 'sum', 'avg', 'min', 'max'], true)) {
            throw new InvalidArgumentException('聚合方式不支持');
        }

        return $aggregate;
    }

    private function defaultMetricAlias(string $aggregate, string $field, int $index): string
    {
        if ($aggregate === 'count') {
            return $index === 0 ? 'value' : 'count_' . ($index + 1);
        }

        return $field === '' ? 'value_' . ($index + 1) : $field . '_' . $aggregate;
    }

    private function aggregateOrderBy(mixed $orderBy, array $metrics): string
    {
        $orderBy = trim((string) $orderBy);
        if ($orderBy === '' || strtolower($orderBy) === 'label') {
            return '`label`';
        }

        foreach ($metrics as $metric) {
            if ($orderBy === $metric['alias']) {
                return $this->quoteAlias($orderBy);
            }
        }

        return '`label`';
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

    private function columnTypes(PDO $pdo, string $table): array
    {
        if (isset($this->columnTypesCache[$table])) {
            return $this->columnTypesCache[$table];
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $types = [];
        foreach ($rows as $row) {
            $types[(string) $row['Field']] = strtolower((string) $row['Type']);
        }

        return $this->columnTypesCache[$table] = $types;
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

    private function buildRawSelectExpressions(
        array $fields,
        array $config,
        array $columns,
        array $columnTypes
    ): array {
        $aliases = $this->normalizeFieldAliases($config['field_aliases'] ?? [], $fields);
        $expressions = [];
        $usedNames = [];

        foreach ($fields as $field) {
            $alias = $aliases[$field] ?? $field;
            $this->assertUniqueOutputName($usedNames, $alias);
            $expressions[] = $alias === $field
                ? "`{$field}`"
                : "`{$field}` AS " . $this->quoteAlias($alias);
        }

        foreach ($this->normalizeComputedFields($config['computed_fields'] ?? []) as $item) {
            $alias = $this->assertOutputAlias($item['alias'], '计算字段别名');
            $this->assertUniqueOutputName($usedNames, $alias);
            $expression = $this->buildComputedExpression((string) $item['expression'], $columns, $columnTypes);
            $expressions[] = "({$expression}) AS " . $this->quoteAlias($alias);
        }

        return $expressions;
    }

    private function normalizeFieldAliases(mixed $aliases, array $fields): array
    {
        if (!is_array($aliases) || $aliases === []) {
            return [];
        }

        $allowedFields = array_flip($fields);
        $result = [];
        foreach ($aliases as $field => $alias) {
            $field = trim((string) $field);
            if (!$this->isIdentifier($field) || !isset($allowedFields[$field])) {
                throw new InvalidArgumentException("别名字段 {$field} 必须在返回字段中");
            }

            $alias = $this->assertOutputAlias((string) $alias, '字段别名');
            if ($alias !== $field) {
                $result[$field] = $alias;
            }
        }

        return $result;
    }

    private function normalizeComputedFields(mixed $items): array
    {
        if (!is_array($items) || $items === []) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('计算字段配置不正确');
            }

            $alias = trim((string) ($item['alias'] ?? ''));
            $expression = trim((string) ($item['expression'] ?? ''));
            if ($alias === '' || $expression === '') {
                throw new InvalidArgumentException('计算字段别名和表达式必须填写');
            }

            $result[] = [
                'alias' => $alias,
                'expression' => $expression,
            ];
        }

        return $result;
    }

    private function buildComputedExpression(string $expression, array $columns, array $columnTypes): string
    {
        $expression = trim($expression);
        if ($expression === '' || mb_strlen($expression) > 200) {
            throw new InvalidArgumentException('计算字段表达式长度不正确');
        }

        preg_match_all('/[A-Za-z_][A-Za-z0-9_]*|\d+(?:\.\d+)?|[(),+\-*\/]/', $expression, $matches);
        $tokens = $matches[0] ?? [];
        $compactExpression = preg_replace('/\s+/', '', $expression);
        if ($tokens === [] || implode('', $tokens) !== $compactExpression) {
            throw new InvalidArgumentException('计算字段表达式只支持数值字段、数字、括号、四则运算和安全函数');
        }

        $hasField = false;
        $offset = 0;
        $sql = $this->parseComputedExpression($tokens, $offset, $columns, $columnTypes, $hasField);
        if ($offset !== count($tokens) || !$hasField) {
            throw new InvalidArgumentException('计算字段表达式格式不正确');
        }

        return $sql;
    }

    private function parseComputedExpression(
        array $tokens,
        int &$offset,
        array $columns,
        array $columnTypes,
        bool &$hasField
    ): string {
        $sql = $this->parseComputedTerm($tokens, $offset, $columns, $columnTypes, $hasField);
        while (($tokens[$offset] ?? null) === '+' || ($tokens[$offset] ?? null) === '-') {
            $operator = $tokens[$offset++];
            $right = $this->parseComputedTerm($tokens, $offset, $columns, $columnTypes, $hasField);
            $sql = "{$sql} {$operator} {$right}";
        }

        return $sql;
    }

    private function parseComputedTerm(
        array $tokens,
        int &$offset,
        array $columns,
        array $columnTypes,
        bool &$hasField
    ): string {
        $sql = $this->parseComputedFactor($tokens, $offset, $columns, $columnTypes, $hasField);
        while (($tokens[$offset] ?? null) === '*' || ($tokens[$offset] ?? null) === '/') {
            $operator = $tokens[$offset++];
            $right = $this->parseComputedFactor($tokens, $offset, $columns, $columnTypes, $hasField);
            $sql = "{$sql} {$operator} {$right}";
        }

        return $sql;
    }

    private function parseComputedFactor(
        array $tokens,
        int &$offset,
        array $columns,
        array $columnTypes,
        bool &$hasField
    ): string {
        $token = $tokens[$offset] ?? null;
        if ($token === null) {
            throw new InvalidArgumentException('计算字段表达式格式不正确');
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $token) === 1) {
            $offset++;
            return $token;
        }

        if ($token === '(') {
            $offset++;
            $sql = $this->parseComputedExpression($tokens, $offset, $columns, $columnTypes, $hasField);
            if (($tokens[$offset] ?? null) !== ')') {
                throw new InvalidArgumentException('计算字段表达式格式不正确');
            }
            $offset++;
            return "({$sql})";
        }

        if ($this->isIdentifier((string) $token)) {
            if (($tokens[$offset + 1] ?? null) === '(') {
                return $this->parseComputedFunction($tokens, $offset, $columns, $columnTypes, $hasField);
            }

            if (!in_array($token, $columns, true) || !$this->isNumericType($columnTypes[$token] ?? '')) {
                throw new InvalidArgumentException("计算字段 {$token} 必须是数值字段");
            }
            $hasField = true;
            $offset++;
            return "`{$token}`";
        }

        throw new InvalidArgumentException('计算字段表达式格式不正确');
    }

    private function parseComputedFunction(
        array $tokens,
        int &$offset,
        array $columns,
        array $columnTypes,
        bool &$hasField
    ): string {
        $name = strtolower((string) $tokens[$offset]);
        $sqlName = match ($name) {
            'round' => 'ROUND',
            'abs' => 'ABS',
            'ceil' => 'CEIL',
            'floor' => 'FLOOR',
            default => throw new InvalidArgumentException("计算字段函数 {$name} 不支持"),
        };

        $offset += 2;
        $args = [];
        if (($tokens[$offset] ?? null) !== ')') {
            while (true) {
                if ($name === 'round' && count($args) === 1) {
                    $args[] = $this->parseComputedRoundScale($tokens, $offset);
                } else {
                    $args[] = $this->parseComputedExpression($tokens, $offset, $columns, $columnTypes, $hasField);
                }

                if (($tokens[$offset] ?? null) !== ',') {
                    break;
                }
                $offset++;
            }
        }

        if (($tokens[$offset] ?? null) !== ')') {
            throw new InvalidArgumentException('计算字段表达式格式不正确');
        }
        $offset++;

        $count = count($args);
        if ($name === 'round') {
            if ($count < 1 || $count > 2) {
                throw new InvalidArgumentException('round 函数参数数量不正确');
            }
        } elseif ($count !== 1) {
            throw new InvalidArgumentException("{$name} 函数参数数量不正确");
        }

        return $sqlName . '(' . implode(', ', $args) . ')';
    }

    private function parseComputedRoundScale(array $tokens, int &$offset): string
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null || preg_match('/^\d+$/', (string) $token) !== 1) {
            throw new InvalidArgumentException('round 函数小数位必须是 0-6 的整数');
        }

        $scale = (int) $token;
        if ($scale < 0 || $scale > 6) {
            throw new InvalidArgumentException('round 函数小数位必须是 0-6 的整数');
        }

        $offset++;
        return (string) $scale;
    }

    private function assertOutputAlias(string $alias, string $label): string
    {
        $alias = trim($alias);
        if ($alias === '' || mb_strlen($alias) > 64 || preg_match('/[`\\x00-\\x1F\\x7F]/u', $alias) === 1) {
            throw new InvalidArgumentException("{$label}不正确");
        }

        return $alias;
    }

    private function assertUniqueOutputName(array &$usedNames, string $name): void
    {
        if (isset($usedNames[$name])) {
            throw new InvalidArgumentException("返回字段 {$name} 重复");
        }

        $usedNames[$name] = true;
    }

    private function quoteAlias(string $alias): string
    {
        return '`' . $alias . '`';
    }

    private function assertColumn(array $columns, string $field, string $label): string
    {
        $field = trim($field);
        if (!$this->isIdentifier($field) || !in_array($field, $columns, true)) {
            throw new InvalidArgumentException("{$label}不存在或不允许访问");
        }

        return $field;
    }

    private function dimensionExpression(string $field, string $type): string
    {
        return match (strtolower(trim($type))) {
            'date', 'day' => "DATE(`{$field}`)",
            'month' => "DATE_FORMAT(`{$field}`, '%Y-%m')",
            'year' => "YEAR(`{$field}`)",
            default => "`{$field}`",
        };
    }

    private function resolveTemplateParams(mixed $definitions, array $runtimeParams): array
    {
        if (!is_array($definitions) || $definitions === []) {
            return [];
        }
        if (!array_is_list($definitions)) {
            throw new InvalidArgumentException('查询参数配置不正确');
        }
        if (count($definitions) > 20) {
            throw new InvalidArgumentException('查询参数最多支持 20 个');
        }

        $result = [];
        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                throw new InvalidArgumentException('查询参数配置不正确');
            }

            $name = trim((string) ($definition['name'] ?? ''));
            if (!$this->isParameterName($name)) {
                throw new InvalidArgumentException('查询参数名称不正确');
            }
            if (array_key_exists($name, $result)) {
                throw new InvalidArgumentException("查询参数 {$name} 重复");
            }

            $type = strtolower(trim((string) ($definition['type'] ?? 'string')));
            if (!in_array($type, ['string', 'number', 'date', 'datetime', 'time_range'], true)) {
                throw new InvalidArgumentException("查询参数 {$name} 类型不支持");
            }

            $rawValue = array_key_exists($name, $runtimeParams)
                ? $runtimeParams[$name]
                : ($definition['default'] ?? $definition['default_value'] ?? null);
            $required = filter_var($definition['required'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($rawValue === null || $rawValue === '' || $rawValue === []) {
                if ($required) {
                    throw new InvalidArgumentException("查询参数 {$name} 必须填写");
                }
                $result[$name] = null;
                continue;
            }

            $result[$name] = $this->normalizeTemplateParamValue($name, $type, $rawValue);
        }

        return $result;
    }

    private function normalizeTemplateParamValue(string $name, string $type, mixed $value): mixed
    {
        if (is_array($value)) {
            if (count($value) > 100) {
                throw new InvalidArgumentException("查询参数 {$name} 最多支持 100 个值");
            }

            return array_values(array_map(
                fn (mixed $item) => $this->normalizeTemplateParamValue($name, $type, $item),
                $value
            ));
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException("查询参数 {$name} 值不正确");
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return match ($type) {
            'number' => $this->normalizeNumberParam($name, $value),
            'date' => $this->normalizeDateParam($name, $value),
            'datetime' => $this->normalizeDateTimeParam($name, $value),
            'time_range' => $this->normalizeTimeRangeParam($name, $value),
            default => mb_substr($value, 0, 200),
        };
    }

    private function normalizeNumberParam(string $name, string $value): int|float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("查询参数 {$name} 必须是数字");
        }

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    private function normalizeDateParam(string $name, string $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("查询参数 {$name} 必须是日期");
        }

        return $value;
    }

    private function normalizeDateTimeParam(string $name, string $value): string
    {
        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
            $value .= ':00';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException("查询参数 {$name} 必须是日期时间");
        }

        return $value;
    }

    private function normalizeTimeRangeParam(string $name, string $value): string
    {
        $value = strtolower($value);
        if (!in_array($value, $this->timeRangePresets(), true)) {
            throw new InvalidArgumentException("查询参数 {$name} 时间范围不支持");
        }

        return $value;
    }

    private function resolveConditionValue(mixed $value, array $templateParams): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->resolveConditionValue($item, $templateParams), $value);
        }
        if (!is_string($value) || !str_starts_with(trim($value), ':')) {
            return $value;
        }

        $name = substr(trim($value), 1);
        if (!$this->isParameterName($name) || !array_key_exists($name, $templateParams)) {
            throw new InvalidArgumentException("查询参数 {$name} 未定义");
        }

        return $templateParams[$name];
    }

    private function buildWhere(
        array $columns,
        mixed $conditions,
        array $columnTypes = [],
        array $templateParams = []
    ): array
    {
        if (!is_array($conditions) || $conditions === []) {
            return ['', []];
        }
        if (!array_is_list($conditions)) {
            if (!$this->isConditionGroup($conditions)) {
                throw new InvalidArgumentException('查询条件配置不正确');
            }
            $conditions = [$conditions];
        }

        [$parts, $bindings] = $this->buildConditionParts(
            $columns,
            $conditions,
            $columnTypes,
            $templateParams
        );

        return $parts === [] ? ['', []] : [' WHERE ' . implode(' AND ', $parts), $bindings];
    }

    private function buildConditionParts(
        array $columns,
        array $conditions,
        array $columnTypes,
        array $templateParams,
        int $depth = 0
    ): array {
        if ($depth > 3) {
            throw new InvalidArgumentException('查询条件分组最多支持 3 层');
        }
        if (count($conditions) > 50) {
            throw new InvalidArgumentException('单层查询条件最多支持 50 项');
        }

        $parts = [];
        $bindings = [];
        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            if ($this->isConditionGroup($condition)) {
                [$groupParts, $groupBindings] = $this->buildConditionParts(
                    $columns,
                    $this->conditionChildren($condition),
                    $columnTypes,
                    $templateParams,
                    $depth + 1
                );
                if ($groupParts === []) {
                    continue;
                }

                $logic = $this->normalizeConditionLogic($condition['logic'] ?? 'and');
                $parts[] = '(' . implode(" {$logic} ", $groupParts) . ')';
                array_push($bindings, ...$groupBindings);
                continue;
            }

            [$part, $partBindings] = $this->buildSingleCondition(
                $columns,
                $condition,
                $columnTypes,
                $templateParams
            );
            if ($part === '') {
                continue;
            }

            $parts[] = $part;
            array_push($bindings, ...$partBindings);
        }

        return [$parts, $bindings];
    }

    private function isConditionGroup(array $condition): bool
    {
        return ($condition['type'] ?? '') === 'group'
            || isset($condition['conditions'])
            || isset($condition['children']);
    }

    private function conditionChildren(array $condition): array
    {
        $children = $condition['conditions'] ?? $condition['children'] ?? [];
        if (!is_array($children) || !array_is_list($children)) {
            throw new InvalidArgumentException('查询条件分组配置不正确');
        }

        return $children;
    }

    private function normalizeConditionLogic(mixed $logic): string
    {
        return strtolower((string) $logic) === 'or' ? 'OR' : 'AND';
    }

    private function buildSingleCondition(
        array $columns,
        array $condition,
        array $columnTypes,
        array $templateParams
    ): array {
        $field = trim((string) ($condition['field'] ?? ''));
        if (!$this->isIdentifier($field) || !in_array($field, $columns, true)) {
            throw new InvalidArgumentException("条件字段 {$field} 不存在或不允许访问");
        }

        $operator = strtolower(trim((string) ($condition['op'] ?? '=')));
        $value = $this->resolveConditionValue($condition['value'] ?? null, $templateParams);
        if ($value === null || $value === '') {
            return ['', []];
        }

        $parts = [];
        $bindings = [];
        match ($operator) {
            '=', '!=', '>', '>=', '<', '<=' => $this->appendSimpleWhere($parts, $bindings, $field, $operator, $value),
            'like' => $this->appendLikeWhere($parts, $bindings, $field, $value),
            'in' => $this->appendInWhere($parts, $bindings, $field, $value),
            'between' => $this->appendBetweenWhere($parts, $bindings, $field, $value),
            'time_range' => $this->appendTimeRangeWhere($parts, $bindings, $field, $value, $columnTypes[$field] ?? ''),
            default => throw new InvalidArgumentException("条件操作符 {$operator} 不支持"),
        };

        return [$parts[0] ?? '', $bindings];
    }

    private function appendSimpleWhere(array &$parts, array &$bindings, string $field, string $operator, mixed $value): void
    {
        if (is_array($value)) {
            throw new InvalidArgumentException("条件字段 {$field} 的值不正确");
        }

        $parts[] = "`{$field}` {$operator} ?";
        $bindings[] = $value;
    }

    private function appendLikeWhere(array &$parts, array &$bindings, string $field, mixed $value): void
    {
        if (is_array($value)) {
            throw new InvalidArgumentException("条件字段 {$field} 的值不正确");
        }

        $this->appendSimpleWhere($parts, $bindings, $field, 'LIKE', '%' . (string) $value . '%');
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

    private function appendTimeRangeWhere(
        array &$parts,
        array &$bindings,
        string $field,
        mixed $value,
        string $columnType
    ): void {
        if (is_array($value)) {
            throw new InvalidArgumentException("时间范围字段 {$field} 的值不正确");
        }
        if (!$this->isTemporalType($columnType)) {
            throw new InvalidArgumentException("时间范围字段 {$field} 必须是日期或时间类型");
        }

        [$start, $end] = $this->timeRangeBounds((string) $value);
        $parts[] = "(`{$field}` >= ? AND `{$field}` < ?)";
        $bindings[] = $start->format('Y-m-d H:i:s');
        $bindings[] = $end->format('Y-m-d H:i:s');
    }

    private function timeRangeBounds(string $preset): array
    {
        if (!in_array(strtolower(trim($preset)), $this->timeRangePresets(), true)) {
            throw new InvalidArgumentException("时间范围 {$preset} 不支持");
        }

        $today = new \DateTimeImmutable('today');
        $thisMonth = $today->modify('first day of this month');
        $thisYear = $today->setDate((int) $today->format('Y'), 1, 1);

        return match (strtolower(trim($preset))) {
            'today' => [$today, $today->modify('+1 day')],
            'yesterday' => [$today->modify('-1 day'), $today],
            'last_7_days' => [$today->modify('-6 days'), $today->modify('+1 day')],
            'last_30_days' => [$today->modify('-29 days'), $today->modify('+1 day')],
            'this_week' => [$today->modify('monday this week'), $today->modify('+1 day')],
            'this_month' => [$thisMonth, $thisMonth->modify('+1 month')],
            'last_month' => [$thisMonth->modify('-1 month'), $thisMonth],
            'this_year' => [$thisYear, $thisYear->modify('+1 year')],
            default => throw new InvalidArgumentException("时间范围 {$preset} 不支持"),
        };
    }

    private function timeRangePresets(): array
    {
        return [
            'today',
            'yesterday',
            'last_7_days',
            'last_30_days',
            'this_week',
            'this_month',
            'last_month',
            'this_year',
        ];
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

    private function isParameterName(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $value) === 1;
    }

    private function isNumericType(string $type): bool
    {
        return preg_match('/int|decimal|double|float|real|numeric|bit|bool/', strtolower($type)) === 1;
    }

    private function isTemporalType(string $type): bool
    {
        return preg_match('/date|time|timestamp/', strtolower($type)) === 1;
    }
}
