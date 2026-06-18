<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ElForm :model="search" inline class="mb-4">
        <ElFormItem label="名称"><ElInput v-model="search.name" clearable /></ElFormItem>
        <ElFormItem label="数据源">
          <ElSelect v-model="search.datasource_id" clearable style="width: 180px">
            <ElOption
              v-for="item in datasourceOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem><ElButton type="primary" @click="loadData">搜索</ElButton></ElFormItem>
      </ElForm>

      <ArtTableHeader :loading="loading" @refresh="loadData">
        <template #left>
          <ElButton v-permission="'saiboard:query_template:save'" @click="openDialog()">
            <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
            新增模板
          </ElButton>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="id" label="ID" width="90" />
        <ElTableColumn prop="name" label="名称" min-width="180" />
        <ElTableColumn label="数据源" min-width="160">
          <template #default="{ row }">{{ datasourceName(row.datasource_id) }}</template>
        </ElTableColumn>
        <ElTableColumn label="类型" width="150">
          <template #default="{ row }">{{ datasetTypeLabel(row.dataset_type) }}</template>
        </ElTableColumn>
        <ElTableColumn label="状态" width="110">
          <template #default="{ row }">
            <ElSwitch
              v-permission="'saiboard:query_template:changeStatus'"
              v-model="row.status"
              :active-value="1"
              :inactive-value="2"
              @change="(status) => changeStatus(row, Number(status))"
            />
          </template>
        </ElTableColumn>
        <ElTableColumn label="操作" width="170" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <ElButton
                v-permission="'saiboard:query_template:preview'"
                size="small"
                @click="preview(row)"
              >
                预览
              </ElButton>
              <SaButton
                v-permission="'saiboard:query_template:update'"
                type="secondary"
                @click="openDialog(row)"
              />
              <SaButton
                v-permission="'saiboard:query_template:destroy'"
                type="error"
                @click="deleteRow(row)"
              />
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog
      v-model="dialogVisible"
      :title="form.id ? '编辑查询模板' : '新增查询模板'"
      width="980px"
    >
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="120px">
        <ElFormItem label="名称" prop="name"><ElInput v-model="form.name" /></ElFormItem>
        <ElFormItem label="数据源" prop="datasource_id">
          <ElSelect v-model="form.datasource_id" style="width: 100%" @change="onDatasourceChange">
            <ElOption
              v-for="item in datasourceOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="取数类型" prop="dataset_type">
          <ElSelect v-model="form.dataset_type" style="width: 100%" @change="onDatasetTypeChange">
            <ElOption
              v-for="item in datasetTypeOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </ElSelect>
        </ElFormItem>

        <template v-if="isMysqlTemplate">
          <ElFormItem label="数据表">
            <ElSelect
              v-model="form.config.table"
              filterable
              :loading="schemaLoading"
              style="width: 100%"
              @change="onTableChange"
            >
              <ElOption
                v-for="item in tableOptions"
                :key="item.name"
                :label="item.name"
                :value="item.name"
              />
            </ElSelect>
          </ElFormItem>

          <ElFormItem v-if="form.dataset_type === 'table_raw'" label="返回字段">
            <ElSelect
              v-model="form.config.fields"
              multiple
              collapse-tags
              collapse-tags-tooltip
              style="width: 100%"
              @change="onRawFieldsChange"
            >
              <ElOption
                v-for="item in columnOptions"
                :key="item.name"
                :label="`${item.name} (${item.type})`"
                :value="item.name"
              />
            </ElSelect>
          </ElFormItem>

          <ElFormItem v-if="form.dataset_type === 'table_raw'" label="字段别名">
            <div class="config-list">
              <div
                v-for="(alias, index) in form.config.field_aliases"
                :key="index"
                class="alias-row"
              >
                <ElSelect v-model="alias.field" filterable placeholder="字段">
                  <ElOption
                    v-for="item in aliasColumnOptions"
                    :key="item.name"
                    :label="item.name"
                    :value="item.name"
                  />
                </ElSelect>
                <ElInput v-model="alias.alias" placeholder="显示名，例如 订单号" />
                <ElButton text type="danger" @click="removeFieldAlias(index)">删除</ElButton>
              </div>
              <ElButton @click="addFieldAlias">
                <template #icon><ArtSvgIcon icon="ri:add-line" /></template>
                添加别名
              </ElButton>
            </div>
          </ElFormItem>

          <ElFormItem v-if="form.dataset_type === 'table_raw'" label="计算字段">
            <div class="config-list">
              <div
                v-for="(item, index) in form.config.computed_fields"
                :key="index"
                class="computed-row"
              >
                <ElInput v-model="item.alias" placeholder="字段名，例如 实付金额" />
                <ElInput
                  v-model="item.expression"
                  placeholder="表达式，例如 round(order_price * 1.2, 2)"
                />
                <ElButton text type="danger" @click="removeComputedField(index)">删除</ElButton>
              </div>
              <ElButton @click="addComputedField">
                <template #icon><ArtSvgIcon icon="ri:add-line" /></template>
                添加计算字段
              </ElButton>
            </div>
          </ElFormItem>

          <template v-if="form.dataset_type === 'table_aggregate'">
            <ElFormItem label="维度字段">
              <ElSelect v-model="form.config.dimension" filterable style="width: 100%">
                <ElOption
                  v-for="item in columnOptions"
                  :key="item.name"
                  :label="`${item.name} (${item.type})`"
                  :value="item.name"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem label="维度粒度">
              <ElSelect v-model="form.config.dimension_type" style="width: 100%">
                <ElOption label="原始值" value="raw" />
                <ElOption label="按日" value="day" />
                <ElOption label="按月" value="month" />
                <ElOption label="按年" value="year" />
              </ElSelect>
            </ElFormItem>
            <ElFormItem label="聚合指标">
              <div class="config-list">
                <div v-for="(metric, index) in form.config.metrics" :key="index" class="metric-row">
                  <ElInput v-model="metric.alias" placeholder="输出名，例如 订单金额" />
                  <ElSelect
                    v-model="metric.aggregate"
                    placeholder="聚合方式"
                    @change="onMetricAggregateChange(metric)"
                  >
                    <ElOption
                      v-for="item in aggregateOptions"
                      :key="item.value"
                      :label="item.label"
                      :value="item.value"
                    />
                  </ElSelect>
                  <ElSelect
                    v-if="metric.aggregate !== 'count'"
                    v-model="metric.field"
                    filterable
                    placeholder="数值字段"
                  >
                    <ElOption
                      v-for="item in numericColumnOptions"
                      :key="item.name"
                      :label="`${item.name} (${item.type})`"
                      :value="item.name"
                    />
                  </ElSelect>
                  <ElInput v-else model-value="COUNT(*)" disabled />
                  <ElButton text type="danger" @click="removeAggregateMetric(index)">删除</ElButton>
                </div>
                <ElButton @click="addAggregateMetric">
                  <template #icon><ArtSvgIcon icon="ri:add-line" /></template>
                  添加指标
                </ElButton>
              </div>
            </ElFormItem>
            <ElFormItem label="排序">
              <ElSpace wrap>
                <ElSelect v-model="form.config.order_by" style="width: 160px">
                  <ElOption label="按维度" value="label" />
                  <ElOption
                    v-for="item in aggregateOrderOptions"
                    :key="item.value"
                    :label="`按 ${item.label}`"
                    :value="item.value"
                  />
                </ElSelect>
                <ElSelect v-model="form.config.order_type" style="width: 140px">
                  <ElOption label="升序" value="asc" />
                  <ElOption label="降序" value="desc" />
                </ElSelect>
              </ElSpace>
            </ElFormItem>
          </template>

          <ElFormItem label="条件">
            <div class="config-list">
              <div
                v-for="(condition, index) in form.config.conditions"
                :key="index"
                class="config-row"
              >
                <ElSelect v-model="condition.field" filterable placeholder="字段">
                  <ElOption
                    v-for="item in conditionColumnOptions(condition)"
                    :key="item.name"
                    :label="item.name"
                    :value="item.name"
                  />
                </ElSelect>
                <ElSelect
                  v-model="condition.op"
                  placeholder="操作符"
                  @change="onConditionOperatorChange(condition)"
                >
                  <ElOption
                    v-for="item in operatorOptions"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </ElSelect>
                <ElSelect
                  v-if="condition.op === 'time_range'"
                  v-model="condition.value"
                  placeholder="选择时间范围"
                >
                  <ElOption
                    v-for="item in timeRangeOptions"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </ElSelect>
                <ElInput
                  v-else
                  v-model="condition.value"
                  placeholder="值，in/between 可用逗号分隔"
                />
                <ElButton text type="danger" @click="removeCondition(index)">删除</ElButton>
              </div>
              <ElButton @click="addCondition">
                <template #icon><ArtSvgIcon icon="ri:add-line" /></template>
                添加条件
              </ElButton>
            </div>
          </ElFormItem>

          <ElFormItem v-if="form.dataset_type === 'table_raw'" label="排序">
            <div class="config-list">
              <div v-for="(order, index) in form.config.order" :key="index" class="config-row">
                <ElSelect v-model="order.field" filterable placeholder="字段">
                  <ElOption
                    v-for="item in columnOptions"
                    :key="item.name"
                    :label="item.name"
                    :value="item.name"
                  />
                </ElSelect>
                <ElSelect v-model="order.direction" placeholder="方向">
                  <ElOption label="升序" value="asc" />
                  <ElOption label="降序" value="desc" />
                </ElSelect>
                <ElButton text type="danger" @click="removeOrder(index)">删除</ElButton>
              </div>
              <ElButton @click="addOrder">
                <template #icon><ArtSvgIcon icon="ri:add-line" /></template>
                添加排序
              </ElButton>
            </div>
          </ElFormItem>

          <ElFormItem v-if="form.dataset_type !== 'table_count'" label="返回条数">
            <ElInputNumber v-model="form.config.limit" :min="1" :max="1000" />
          </ElFormItem>
        </template>

        <template v-else>
          <ElFormItem label="请求路径">
            <ElInput
              v-model="form.config.path"
              placeholder="例如 /metrics/orders，可留空直接请求数据源 URL"
            />
          </ElFormItem>
          <ElFormItem label="请求参数">
            <ElInput v-model="paramsText" type="textarea" :rows="6" />
          </ElFormItem>
        </template>

        <ElFormItem label="配置预览">
          <ElInput :model-value="configPreview" type="textarea" :rows="8" readonly />
        </ElFormItem>
        <ElFormItem label="状态">
          <ElRadioGroup v-model="form.status">
            <ElRadioButton :label="1">启用</ElRadioButton>
            <ElRadioButton :label="2">停用</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton v-permission="'saiboard:query_template:preview'" @click="preview(form)"
          >预览</ElButton
        >
        <ElButton
          v-permission="form.id ? 'saiboard:query_template:update' : 'saiboard:query_template:save'"
          type="primary"
          @click="submit"
        >
          提交
        </ElButton>
      </template>
    </ElDialog>

    <ElDialog v-model="previewVisible" title="预览结果" width="720px">
      <SaCode :code="previewText" language="json" />
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import datasourceApi from '../api/datasource'
  import api from '../api/query-template'

  interface DatasourceOption {
    id: number
    name: string
    type: 'mysql' | 'http'
  }

  interface SchemaColumn {
    name: string
    type: string
    kind: 'string' | 'number' | 'date'
  }

  interface AggregateMetric {
    alias: string
    aggregate: string
    field: string
  }

  const rows = ref<any[]>([])
  const datasourceOptions = ref<DatasourceOption[]>([])
  const tableOptions = ref<{ name: string }[]>([])
  const columnOptions = ref<SchemaColumn[]>([])
  const loading = ref(false)
  const schemaLoading = ref(false)
  const dialogVisible = ref(false)
  const previewVisible = ref(false)
  const previewText = ref('')
  const paramsText = ref('{}')
  const formRef = ref<FormInstance>()
  const search = reactive({ name: '', datasource_id: undefined as number | undefined })
  const form = reactive<any>({
    id: undefined,
    datasource_id: undefined,
    name: '',
    dataset_type: 'table_raw',
    config: {},
    status: 1
  })

  const rules: FormRules = {
    name: [{ required: true, message: '名称必填', trigger: 'blur' }],
    datasource_id: [{ required: true, message: '数据源必选', trigger: 'change' }],
    dataset_type: [{ required: true, message: '取数类型必选', trigger: 'change' }]
  }

  const operatorOptions = [
    { label: '等于 =', value: '=' },
    { label: '不等于 !=', value: '!=' },
    { label: '大于 >', value: '>' },
    { label: '大于等于 >=', value: '>=' },
    { label: '小于 <', value: '<' },
    { label: '小于等于 <=', value: '<=' },
    { label: '包含 like', value: 'like' },
    { label: '在列表 in', value: 'in' },
    { label: '区间 between', value: 'between' },
    { label: '时间范围', value: 'time_range' }
  ]
  const timeRangeOptions = [
    { label: '今天', value: 'today' },
    { label: '昨天', value: 'yesterday' },
    { label: '近 7 天', value: 'last_7_days' },
    { label: '近 30 天', value: 'last_30_days' },
    { label: '本周至今', value: 'this_week' },
    { label: '本月', value: 'this_month' },
    { label: '上月', value: 'last_month' },
    { label: '本年', value: 'this_year' }
  ]
  const aggregateOptions = [
    { label: '计数 count', value: 'count' },
    { label: '求和 sum', value: 'sum' },
    { label: '平均 avg', value: 'avg' },
    { label: '最小 min', value: 'min' },
    { label: '最大 max', value: 'max' }
  ]
  const mysqlDatasetTypes = [
    { label: '表原始行', value: 'table_raw' },
    { label: '表计数', value: 'table_count' },
    { label: '表聚合', value: 'table_aggregate' }
  ]
  const httpDatasetTypes = [{ label: 'HTTP 透传', value: 'http_passthrough' }]

  const currentDatasource = computed(() =>
    datasourceOptions.value.find((item) => Number(item.id) === Number(form.datasource_id))
  )
  const isMysqlTemplate = computed(() => currentDatasource.value?.type !== 'http')
  const datasetTypeOptions = computed(() =>
    currentDatasource.value?.type === 'http' ? httpDatasetTypes : mysqlDatasetTypes
  )
  const numericColumnOptions = computed(() =>
    columnOptions.value.filter((item) => item.kind === 'number')
  )
  const aliasColumnOptions = computed(() => {
    const fields = Array.isArray(form.config.fields) ? form.config.fields : []
    if (!fields.length) return columnOptions.value
    return columnOptions.value.filter((item) => fields.includes(item.name))
  })
  const dateColumnOptions = computed(() =>
    columnOptions.value.filter(
      (item) => item.kind === 'date' && !item.type.toLowerCase().startsWith('year')
    )
  )
  const configPreview = computed(() => JSON.stringify(buildConfig(false), null, 2))
  const aggregateOrderOptions = computed(() =>
    normalizeAggregateMetrics(form.config.metrics, false, form.config).map((item, index) => ({
      label: aggregateMetricLabel(item, index),
      value: aggregateMetricAlias(item, index)
    }))
  )

  const defaultConfig = (type: string): Record<string, any> => {
    if (type === 'table_count') return { table: '', conditions: [] }
    if (type === 'table_aggregate') {
      return {
        table: '',
        dimension: '',
        dimension_type: 'raw',
        metrics: [{ alias: '数量', aggregate: 'count', field: '' }],
        conditions: [],
        order_by: 'label',
        order_type: 'asc',
        limit: 100
      }
    }
    if (type === 'http_passthrough') return { path: '', params: {} }
    return {
      table: '',
      fields: [],
      field_aliases: [],
      computed_fields: [],
      conditions: [],
      order: [],
      limit: 100
    }
  }

  const normalizeConfig = (type: string, config: Record<string, any> = {}) => {
    const next = { ...defaultConfig(type), ...(config || {}) }
    if (!Array.isArray(next.conditions)) next.conditions = []
    if (type === 'table_raw') {
      if (!Array.isArray(next.fields)) next.fields = []
      next.field_aliases = normalizeAliasRows(next.field_aliases)
      next.computed_fields = normalizeComputedRows(next.computed_fields)
      if (!Array.isArray(next.order)) next.order = []
    }
    if (type === 'table_aggregate') {
      next.metrics = normalizeAggregateMetrics(next.metrics, false, next)
    }
    if (type === 'http_passthrough') {
      paramsText.value = JSON.stringify(next.params || {}, null, 2)
    }
    return next
  }

  const loadOptions = async () => {
    datasourceOptions.value = await datasourceApi.options()
  }

  const loadData = async () => {
    loading.value = true
    try {
      const data = await api.list({ ...search, saiType: 'all' })
      rows.value = Array.isArray(data) ? data : data?.data || []
    } finally {
      loading.value = false
    }
  }

  const loadSchema = async (table = form.config.table) => {
    if (!form.datasource_id || !isMysqlTemplate.value) {
      tableOptions.value = []
      columnOptions.value = []
      return
    }

    schemaLoading.value = true
    try {
      const result = await datasourceApi.schema({
        id: form.datasource_id,
        table: table || undefined
      })
      tableOptions.value = result.tables || []
      columnOptions.value = result.columns || []
    } finally {
      schemaLoading.value = false
    }
  }

  const openDialog = async (row?: any) => {
    const firstDatasource = datasourceOptions.value[0]
    Object.assign(form, {
      id: undefined,
      datasource_id: firstDatasource?.id,
      name: '',
      dataset_type: firstDatasource?.type === 'http' ? 'http_passthrough' : 'table_raw',
      config: defaultConfig(firstDatasource?.type === 'http' ? 'http_passthrough' : 'table_raw'),
      status: 1
    })
    paramsText.value = '{}'
    tableOptions.value = []
    columnOptions.value = []
    if (row) {
      Object.assign(form, {
        ...row,
        config: normalizeConfig(row.dataset_type, row.config || {})
      })
    }
    dialogVisible.value = true
    await nextTick()
    await loadSchema(form.config.table)
  }

  const onDatasourceChange = async () => {
    const nextType = currentDatasource.value?.type === 'http' ? 'http_passthrough' : 'table_raw'
    form.dataset_type = nextType
    form.config = normalizeConfig(nextType)
    tableOptions.value = []
    columnOptions.value = []
    await loadSchema()
  }

  const onDatasetTypeChange = async () => {
    form.config = normalizeConfig(form.dataset_type)
    await loadSchema()
  }

  const onTableChange = async () => {
    form.config.fields = []
    form.config.field_aliases = []
    form.config.computed_fields = []
    form.config.order = []
    form.config.dimension = ''
    form.config.metrics = [{ alias: '数量', aggregate: 'count', field: '' }]
    await loadSchema(form.config.table)
  }

  const onMetricAggregateChange = (metric: AggregateMetric) => {
    if (metric.aggregate === 'count') metric.field = ''
  }

  const buildPayload = () => ({ ...form, config: buildConfig(true) })

  const buildConfig = (strict = true) => {
    const config = JSON.parse(JSON.stringify(form.config || {}))
    if (form.dataset_type === 'http_passthrough') {
      config.params = parseJson(paramsText.value, strict)
      return config
    }

    config.conditions = normalizeConditions(config.conditions)
    if (form.dataset_type === 'table_raw') {
      config.fields = Array.isArray(config.fields) ? config.fields : []
      config.field_aliases = normalizeFieldAliasMap(config.field_aliases, config.fields)
      config.computed_fields = normalizeComputedRows(config.computed_fields, true)
      config.order = normalizeOrders(config.order)
      config.limit = Number(config.limit || 100)
    }
    if (form.dataset_type === 'table_aggregate') {
      config.limit = Number(config.limit || 100)
      config.metrics = normalizeAggregateMetrics(config.metrics, true, config)
      config.order_by = normalizeAggregateOrderBy(config.order_by, config.metrics)
      config.order_type = config.order_type === 'desc' ? 'desc' : 'asc'
      delete config.aggregate
      delete config.metric
    }
    return config
  }

  const submit = async () => {
    await formRef.value?.validate()
    const payload = buildPayload()
    if (form.id) {
      await api.update(payload)
    } else {
      await api.save(payload)
    }
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadData()
  }

  const preview = async (row: any) => {
    const payload = row.id ? { id: row.id } : buildPayload()
    const result = await api.preview(payload)
    previewText.value = JSON.stringify(result, null, 2)
    previewVisible.value = true
  }

  const changeStatus = async (row: any, status: number) => {
    await api.changeStatus({ id: row.id, status })
    ElMessage.success('状态已更新')
    loadData()
  }

  const deleteRow = async (row: any) => {
    await ElMessageBox.confirm(`确定删除「${row.name}」吗？`, '删除查询模板', { type: 'warning' })
    await api.delete({ ids: [row.id] })
    ElMessage.success('删除成功')
    loadData()
  }

  const addCondition = () => {
    form.config.conditions.push({ field: '', op: '=', value: '' })
  }

  const onConditionOperatorChange = (condition: Record<string, any>) => {
    condition.value = condition.op === 'time_range' ? 'last_7_days' : ''
    if (condition.op === 'time_range' && !isDateField(condition.field)) {
      condition.field = ''
    }
  }

  const conditionColumnOptions = (condition: Record<string, any>) =>
    condition?.op === 'time_range' ? dateColumnOptions.value : columnOptions.value

  const isDateField = (field: string) => dateColumnOptions.value.some((item) => item.name === field)

  const onRawFieldsChange = () => {
    const fields = Array.isArray(form.config.fields) ? form.config.fields : []
    if (!fields.length || !Array.isArray(form.config.field_aliases)) return
    form.config.field_aliases = form.config.field_aliases.filter((item: any) =>
      fields.includes(item?.field)
    )
  }

  const addFieldAlias = () => {
    if (!Array.isArray(form.config.field_aliases)) form.config.field_aliases = []
    const usedFields = new Set(form.config.field_aliases.map((item: any) => item?.field))
    const first = aliasColumnOptions.value.find((item) => !usedFields.has(item.name))
    form.config.field_aliases.push({ field: first?.name || '', alias: '' })
  }

  const removeFieldAlias = (index: number) => {
    form.config.field_aliases.splice(index, 1)
  }

  const addComputedField = () => {
    if (!Array.isArray(form.config.computed_fields)) form.config.computed_fields = []
    form.config.computed_fields.push({ alias: '', expression: '' })
  }

  const removeComputedField = (index: number) => {
    form.config.computed_fields.splice(index, 1)
  }

  const addAggregateMetric = () => {
    if (!Array.isArray(form.config.metrics)) form.config.metrics = []
    form.config.metrics.push({ alias: '', aggregate: 'sum', field: '' })
  }

  const removeAggregateMetric = (index: number) => {
    form.config.metrics.splice(index, 1)
    if (!form.config.metrics.length) {
      form.config.metrics.push({ alias: '数量', aggregate: 'count', field: '' })
    }
    form.config.order_by = normalizeAggregateOrderBy(form.config.order_by, form.config.metrics)
  }

  const removeCondition = (index: number) => {
    form.config.conditions.splice(index, 1)
  }

  const addOrder = () => {
    form.config.order.push({ field: '', direction: 'asc' })
  }

  const removeOrder = (index: number) => {
    form.config.order.splice(index, 1)
  }

  const datasourceName = (id: number) =>
    datasourceOptions.value.find((item) => item.id === id)?.name || id
  const datasetTypeLabel = (value: string) =>
    [...mysqlDatasetTypes, ...httpDatasetTypes].find((item) => item.value === value)?.label || value

  function normalizeConditions(conditions: any[]) {
    if (!Array.isArray(conditions)) return []
    return conditions.filter((item) => item?.field && item?.op && item?.value !== '')
  }

  function normalizeOrders(orders: any[]) {
    if (!Array.isArray(orders)) return []
    return orders
      .filter((item) => item?.field)
      .map((item) => ({
        field: item.field,
        direction: item.direction === 'desc' ? 'desc' : 'asc'
      }))
  }

  function normalizeAliasRows(value: any) {
    if (Array.isArray(value)) {
      return value.map((item) => ({
        field: String(item?.field || '').trim(),
        alias: String(item?.alias || '').trim()
      }))
    }
    if (value && typeof value === 'object') {
      return Object.entries(value).map(([field, alias]) => ({
        field,
        alias: String(alias || '').trim()
      }))
    }
    return []
  }

  function normalizeFieldAliasMap(rows: any[], fields: any[] = []) {
    const selectedFields = new Set(
      Array.isArray(fields) && fields.length ? fields.map((item) => String(item)) : []
    )
    const result: Record<string, string> = {}
    for (const item of normalizeAliasRows(rows)) {
      if (!item.field || !item.alias) continue
      if (selectedFields.size && !selectedFields.has(item.field)) continue
      result[item.field] = item.alias
    }
    return result
  }

  function normalizeComputedRows(rows: any = [], strict = false) {
    if (!Array.isArray(rows)) return []
    return rows
      .map((item) => ({
        alias: String(item?.alias || '').trim(),
        expression: String(item?.expression || '').trim()
      }))
      .filter((item) => (strict ? item.alias && item.expression : item.alias || item.expression))
  }

  function normalizeAggregateMetrics(
    rows: any = [],
    strict = false,
    source: Record<string, any> = {}
  ): AggregateMetric[] {
    let items = Array.isArray(rows) ? rows : []
    if (!items.length && source.aggregate) {
      items = [
        {
          alias: source.aggregate === 'count' ? '数量' : String(source.metric || ''),
          aggregate: source.aggregate,
          field: source.metric || ''
        }
      ]
    }
    if (!items.length) {
      items = [{ alias: '数量', aggregate: 'count', field: '' }]
    }

    const metrics = items
      .slice(0, 8)
      .map((item, index) => {
        const aggregate = normalizeAggregateType(item?.aggregate)
        const field = aggregate === 'count' ? '' : String(item?.field || '').trim()
        const alias = String(
          item?.alias || aggregateMetricAlias({ aggregate, field, alias: '' }, index)
        ).trim()
        return { alias, aggregate, field }
      })
      .filter((item) =>
        strict ? item.aggregate === 'count' || item.field : item.alias || item.field
      )
    return metrics.length ? metrics : [{ alias: '数量', aggregate: 'count', field: '' }]
  }

  function normalizeAggregateType(value: string) {
    return ['count', 'sum', 'avg', 'min', 'max'].includes(value) ? value : 'count'
  }

  function aggregateMetricAlias(metric: Partial<AggregateMetric>, index: number) {
    const alias = String(metric.alias || '').trim()
    if (alias) return alias
    if (metric.aggregate === 'count') return index === 0 ? '数量' : `数量${index + 1}`
    return metric.field ? `${metric.field}_${metric.aggregate}` : `指标${index + 1}`
  }

  function aggregateMetricLabel(metric: AggregateMetric, index: number) {
    return metric.alias || aggregateMetricAlias(metric, index)
  }

  function normalizeAggregateOrderBy(value: string, metrics: AggregateMetric[]) {
    const aliases = new Set(metrics.map((item, index) => aggregateMetricAlias(item, index)))
    return value && aliases.has(value) ? value : 'label'
  }

  function parseJson(text: string, strict = true) {
    try {
      return JSON.parse(text || '{}')
    } catch {
      if (strict) {
        ElMessage.error('请求参数 JSON 格式不正确')
        throw new Error('请求参数 JSON 格式不正确')
      }
      return {}
    }
  }

  onMounted(async () => {
    await loadOptions()
    loadData()
  })
</script>

<style scoped lang="scss">
  .config-list {
    width: 100%;
  }

  .config-row {
    display: grid;
    grid-template-columns: minmax(160px, 1fr) 130px minmax(220px, 2fr) 64px;
    gap: 8px;
    margin-bottom: 8px;
  }

  .alias-row,
  .computed-row,
  .metric-row {
    display: grid;
    gap: 8px;
    margin-bottom: 8px;
  }

  .alias-row {
    grid-template-columns: minmax(180px, 1fr) minmax(220px, 2fr) 64px;
  }

  .computed-row {
    grid-template-columns: minmax(180px, 1fr) minmax(280px, 2fr) 64px;
  }

  .metric-row {
    grid-template-columns: minmax(160px, 1.2fr) 140px minmax(180px, 1.4fr) 64px;
  }
</style>
