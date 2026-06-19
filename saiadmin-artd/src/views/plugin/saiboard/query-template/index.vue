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
        <ElAlert
          class="dataset-type-help"
          type="info"
          show-icon
          :closable="false"
          :title="datasetTypeHelp.title"
          :description="datasetTypeHelp.description"
        />

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
              <ElSelect
                v-model="form.config.dimension"
                filterable
                style="width: 100%"
                @change="onDimensionChange"
              >
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
            <ElFormItem label="第二维度">
              <ElSelect
                v-model="form.config.secondary_dimension"
                clearable
                filterable
                placeholder="可选，用于热力图 Y 轴"
                style="width: 100%"
                @change="onSecondaryDimensionChange"
              >
                <ElOption
                  v-for="item in secondaryDimensionOptions"
                  :key="item.name"
                  :label="`${item.name} (${item.type})`"
                  :value="item.name"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem v-if="form.config.secondary_dimension" label="第二粒度">
              <ElSelect v-model="form.config.secondary_dimension_type" style="width: 100%">
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
                    v-if="form.config.secondary_dimension"
                    label="按第二维度"
                    value="series"
                  />
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

          <ElFormItem label="参数">
            <ElAlert
              class="param-help"
              type="info"
              show-icon
              :closable="false"
              title="参数类型说明"
              :description="paramTypeDescription"
            />
            <div class="config-list">
              <div v-for="(param, index) in form.config.params" :key="index" class="param-row">
                <ElInput v-model="param.name" placeholder="参数名，例如 pay_method" />
                <ElInput v-model="param.label" placeholder="显示名，例如 支付方式" />
                <ElSelect
                  v-model="param.type"
                  placeholder="类型"
                  @change="onParamTypeChange(param)"
                >
                  <ElOption
                    v-for="item in paramTypeOptions"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </ElSelect>
                <ElInput v-model="param.default" :placeholder="paramDefaultPlaceholder(param)" />
                <ElSwitch
                  v-model="param.required"
                  inline-prompt
                  active-text="必填"
                  inactive-text="可空"
                />
                <ElButton text type="danger" @click="removeParam(index)">删除</ElButton>
              </div>
              <ElButton @click="addParam">
                <template #icon><ArtSvgIcon icon="ri:add-line" /></template>
                添加参数
              </ElButton>
            </div>
          </ElFormItem>

          <ElFormItem label="条件">
            <div class="config-list">
              <template v-for="(condition, index) in form.config.conditions" :key="index">
                <div v-if="isConditionGroup(condition)" class="condition-group">
                  <div class="condition-group__header">
                    <ElSpace>
                      <span class="condition-group__title">条件组</span>
                      <ElSelect v-model="condition.logic" style="width: 130px">
                        <ElOption
                          v-for="item in conditionLogicOptions"
                          :key="item.value"
                          :label="item.label"
                          :value="item.value"
                        />
                      </ElSelect>
                    </ElSpace>
                    <ElButton text type="danger" @click="removeCondition(index)">删除组</ElButton>
                  </div>
                  <div
                    v-for="(child, childIndex) in condition.conditions"
                    :key="childIndex"
                    class="config-row"
                  >
                    <ElSelect v-model="child.field" filterable placeholder="字段">
                      <ElOption
                        v-for="item in conditionColumnOptions(child)"
                        :key="item.name"
                        :label="item.name"
                        :value="item.name"
                      />
                    </ElSelect>
                    <ElSelect
                      v-model="child.op"
                      placeholder="操作符"
                      @change="onConditionOperatorChange(child)"
                    >
                      <ElOption
                        v-for="item in operatorOptions"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value"
                      />
                    </ElSelect>
                    <ElSelect
                      v-if="child.op === 'time_range'"
                      v-model="child.value"
                      placeholder="选择时间范围"
                    >
                      <ElOption
                        v-for="item in timeRangeOptions"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value"
                      />
                      <ElOption
                        v-for="item in timeRangeParamOptions"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value"
                      />
                    </ElSelect>
                    <ElInput
                      v-else
                      v-model="child.value"
                      placeholder="值或 :参数名，in/between 可用逗号分隔"
                    />
                    <ElButton
                      text
                      type="danger"
                      @click="removeGroupCondition(condition, childIndex)"
                    >
                      删除
                    </ElButton>
                  </div>
                  <ElButton @click="addGroupCondition(condition)">
                    <template #icon><ArtSvgIcon icon="ri:add-line" /></template>
                    组内条件
                  </ElButton>
                </div>
                <div v-else class="config-row">
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
                    <ElOption
                      v-for="item in timeRangeParamOptions"
                      :key="item.value"
                      :label="item.label"
                      :value="item.value"
                    />
                  </ElSelect>
                  <ElInput
                    v-else
                    v-model="condition.value"
                    placeholder="值或 :参数名，in/between 可用逗号分隔"
                  />
                  <ElButton text type="danger" @click="removeCondition(index)">删除</ElButton>
                </div>
              </template>
              <ElSpace wrap>
                <ElButton @click="addCondition">
                  <template #icon><ArtSvgIcon icon="ri:add-line" /></template>
                  添加条件
                </ElButton>
                <ElButton @click="addConditionGroup">
                  <template #icon><ArtSvgIcon icon="ri:git-branch-line" /></template>
                  添加条件组
                </ElButton>
              </ElSpace>
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
            <ElInput
              v-model="paramsText"
              type="textarea"
              :rows="6"
              placeholder='例如 {"range":"7d"}'
            />
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

  interface TemplateParam {
    name: string
    label: string
    type: string
    default: string
    required: boolean
  }

  interface QueryCondition {
    field: string
    op: string
    value: any
  }

  interface QueryConditionGroup {
    type: 'group'
    logic: 'and' | 'or'
    conditions: QueryCondition[]
  }

  type QueryConditionItem = QueryCondition | QueryConditionGroup

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
  const conditionLogicOptions = [
    { label: '全部满足 AND', value: 'and' },
    { label: '任一满足 OR', value: 'or' }
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
  const paramTypeOptions = [
    { label: '文本', value: 'string' },
    { label: '数字', value: 'number' },
    { label: '日期', value: 'date' },
    { label: '日期时间', value: 'datetime' },
    { label: '时间范围', value: 'time_range' }
  ]
  const mysqlDatasetTypes = [
    { label: '表原始行', value: 'table_raw' },
    { label: '表计数', value: 'table_count' },
    { label: '表聚合', value: 'table_aggregate' }
  ]
  const httpDatasetTypes = [{ label: 'HTTP 透传', value: 'http_passthrough' }]
  const datasetTypeHelpMap: Record<string, { title: string; description: string }> = {
    table_raw: {
      title: '表原始行',
      description:
        '按字段、条件和排序读取明细行，返回 rows 列表；适合表格、排行榜、折线/柱状图的原始明细数据。'
    },
    table_count: {
      title: '表计数',
      description:
        '只返回一行 total 计数，返回结构为 rows[0].total；适合指标卡、总数统计和告警数量。'
    },
    table_aggregate: {
      title: '表聚合',
      description:
        '按维度字段分组，输出 label 加一个或多个聚合指标；配置第二维度后输出 label/series/指标列，适合热力图二维矩阵。'
    },
    http_passthrough: {
      title: 'HTTP 透传',
      description:
        '请求数据源 URL 加模板路径和参数，接口返回 rows/total 时直接使用，否则会把 data 或根对象转换为 rows。'
    }
  }
  const paramTypeDescription =
    '文本会按字符串绑定；数字必须是数值；日期格式为 YYYY-MM-DD；日期时间格式为 YYYY-MM-DD HH:mm:ss；时间范围可用 today、last_7_days 等预设。条件值写成 :参数名 时，会优先取运行时传入值，没有传入则使用默认值。'

  const currentDatasource = computed(() =>
    datasourceOptions.value.find((item) => Number(item.id) === Number(form.datasource_id))
  )
  const isMysqlTemplate = computed(() => currentDatasource.value?.type !== 'http')
  const datasetTypeOptions = computed(() =>
    currentDatasource.value?.type === 'http' ? httpDatasetTypes : mysqlDatasetTypes
  )
  const datasetTypeHelp = computed(
    () => datasetTypeHelpMap[form.dataset_type] || datasetTypeHelpMap.table_raw
  )
  const numericColumnOptions = computed(() =>
    columnOptions.value.filter((item) => item.kind === 'number')
  )
  const aliasColumnOptions = computed(() => {
    const fields = Array.isArray(form.config.fields) ? form.config.fields : []
    if (!fields.length) return columnOptions.value
    return columnOptions.value.filter((item) => fields.includes(item.name))
  })
  const secondaryDimensionOptions = computed(() =>
    columnOptions.value.filter((item) => item.name !== form.config.dimension)
  )
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
  const paramReferenceOptions = computed(() =>
    normalizeParamRows(form.config.params, false)
      .filter((item) => item.name)
      .map((item) => ({
        label: `${item.label || item.name} (:${item.name})`,
        value: `:${item.name}`,
        type: item.type
      }))
  )
  const timeRangeParamOptions = computed(() =>
    paramReferenceOptions.value.filter((item) => item.type === 'time_range')
  )

  const defaultConfig = (type: string): Record<string, any> => {
    if (type === 'table_count') return { table: '', params: [], conditions: [] }
    if (type === 'table_aggregate') {
      return {
        table: '',
        params: [],
        dimension: '',
        dimension_type: 'raw',
        secondary_dimension: '',
        secondary_dimension_type: 'raw',
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
      params: [],
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
    next.conditions = normalizeConditions(next.conditions, false)
    if (type !== 'http_passthrough') {
      next.params = normalizeParamRows(next.params)
    }
    if (type === 'table_raw') {
      if (!Array.isArray(next.fields)) next.fields = []
      next.field_aliases = normalizeAliasRows(next.field_aliases)
      next.computed_fields = normalizeComputedRows(next.computed_fields)
      if (!Array.isArray(next.order)) next.order = []
    }
    if (type === 'table_aggregate') {
      next.secondary_dimension = String(next.secondary_dimension || '').trim()
      next.secondary_dimension_type = normalizeDimensionType(next.secondary_dimension_type)
      next.metrics = normalizeAggregateMetrics(next.metrics, false, next)
    }
    if (type === 'http_passthrough') {
      paramsText.value = stringifyJsonObject(next.params)
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
    form.config.conditions = []
    form.config.dimension = ''
    form.config.secondary_dimension = ''
    form.config.secondary_dimension_type = 'raw'
    form.config.metrics = [{ alias: '数量', aggregate: 'count', field: '' }]
    await loadSchema(form.config.table)
  }

  const onMetricAggregateChange = (metric: AggregateMetric) => {
    if (metric.aggregate === 'count') metric.field = ''
  }

  const onDimensionChange = () => {
    if (form.config.secondary_dimension === form.config.dimension) {
      form.config.secondary_dimension = ''
    }
    onSecondaryDimensionChange()
  }

  const onSecondaryDimensionChange = () => {
    form.config.secondary_dimension_type = form.config.secondary_dimension
      ? normalizeDimensionType(form.config.secondary_dimension_type)
      : 'raw'
    form.config.order_by = normalizeAggregateOrderBy(
      form.config.order_by,
      form.config.metrics,
      Boolean(form.config.secondary_dimension)
    )
  }

  const buildPayload = () => ({ ...form, config: buildConfig(true) })

  const buildConfig = (strict = true) => {
    const config = JSON.parse(JSON.stringify(form.config || {}))
    if (form.dataset_type === 'http_passthrough') {
      config.params = parseJsonObject(paramsText.value, '请求参数', strict)
      return config
    }

    config.params = normalizeParamRows(config.params, true)
    config.conditions = normalizeConditions(config.conditions)
    if (form.dataset_type === 'table_raw') {
      config.fields = Array.isArray(config.fields) ? config.fields : []
      config.field_aliases = normalizeFieldAliasMap(config.field_aliases, config.fields)
      config.computed_fields = normalizeComputedRows(config.computed_fields, true)
      config.order = normalizeOrders(config.order)
      config.limit = Number(config.limit || 100)
    }
    if (form.dataset_type === 'table_aggregate') {
      config.dimension = String(config.dimension || '').trim()
      config.dimension_type = normalizeDimensionType(config.dimension_type)
      config.secondary_dimension = String(config.secondary_dimension || '').trim()
      if (config.secondary_dimension) {
        if (config.secondary_dimension === config.dimension) {
          if (strict) {
            throw new Error('第二维度字段不能与维度字段相同')
          }
          config.secondary_dimension = ''
        }
        config.secondary_dimension_type = normalizeDimensionType(config.secondary_dimension_type)
      }
      if (!config.secondary_dimension) {
        delete config.secondary_dimension
        delete config.secondary_dimension_type
      }
      config.limit = Number(config.limit || 100)
      config.metrics = normalizeAggregateMetrics(config.metrics, true, config)
      config.order_by = normalizeAggregateOrderBy(
        config.order_by,
        config.metrics,
        Boolean(config.secondary_dimension)
      )
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
    const payload = row.id
      ? { id: row.id, params: previewParams(row.config) }
      : { ...buildPayload(), params: previewParams(form.config) }
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
    form.config.conditions.push(createCondition())
  }

  const addConditionGroup = () => {
    form.config.conditions.push(createConditionGroup())
  }

  const addGroupCondition = (group: QueryConditionGroup) => {
    group.conditions.push(createCondition())
  }

  const addParam = () => {
    if (!Array.isArray(form.config.params)) form.config.params = []
    form.config.params.push({ name: '', label: '', type: 'string', default: '', required: false })
  }

  const removeParam = (index: number) => {
    form.config.params.splice(index, 1)
  }

  const onParamTypeChange = (param: TemplateParam) => {
    param.default = param.type === 'time_range' ? 'last_7_days' : ''
  }

  const paramDefaultPlaceholder = (param: TemplateParam) => {
    if (param.type === 'number') return '默认值，例如 1'
    if (param.type === 'date') return '默认值，例如 2026-06-19'
    if (param.type === 'datetime') return '默认值，例如 2026-06-19 00:00:00'
    if (param.type === 'time_range') return '默认值，例如 last_7_days'
    return '默认值，可留空'
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
    form.config.order_by = normalizeAggregateOrderBy(
      form.config.order_by,
      form.config.metrics,
      Boolean(form.config.secondary_dimension)
    )
  }

  const removeCondition = (index: number) => {
    form.config.conditions.splice(index, 1)
  }

  const removeGroupCondition = (group: QueryConditionGroup, index: number) => {
    group.conditions.splice(index, 1)
    if (!group.conditions.length) {
      group.conditions.push(createCondition())
    }
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

  function createCondition(): QueryCondition {
    return { field: '', op: '=', value: '' }
  }

  function createConditionGroup(): QueryConditionGroup {
    return { type: 'group', logic: 'or', conditions: [createCondition()] }
  }

  function isConditionGroup(condition: any): condition is QueryConditionGroup {
    return (
      condition &&
      typeof condition === 'object' &&
      (condition.type === 'group' ||
        Array.isArray(condition.conditions) ||
        Array.isArray(condition.children))
    )
  }

  function normalizeConditions(conditions: any, strict = true): QueryConditionItem[] {
    if (isConditionGroup(conditions)) {
      const group = normalizeConditionGroup(conditions, strict)
      return group ? [group] : []
    }
    if (!Array.isArray(conditions)) return []

    const result: QueryConditionItem[] = []
    for (const item of conditions) {
      const next = isConditionGroup(item)
        ? normalizeConditionGroup(item, strict)
        : normalizeConditionRow(item, strict)
      if (next) result.push(next)
    }

    return result
  }

  function normalizeConditionGroup(group: any, strict: boolean): QueryConditionGroup | null {
    const children = normalizeConditions(group.conditions || group.children || [], strict).flatMap(
      (item) => (isConditionGroup(item) ? item.conditions : [item])
    )
    if (strict && !children.length) return null

    return {
      type: 'group',
      logic: normalizeConditionLogic(group.logic),
      conditions: children.length ? children : [createCondition()]
    }
  }

  function normalizeConditionRow(item: any, strict: boolean): QueryCondition | null {
    const value = normalizeConditionValue(item?.value)
    const condition = {
      field: String(item?.field || '').trim(),
      op: normalizeConditionOperator(item?.op),
      value
    }
    const hasValue = Array.isArray(value) ? value.length > 0 : value !== ''
    const filled = condition.field && condition.op && hasValue
    if (strict ? !filled : !condition.field && !hasValue) return null

    return condition
  }

  function normalizeConditionLogic(value: string) {
    return value === 'or' ? 'or' : 'and'
  }

  function normalizeConditionOperator(value: string) {
    return operatorOptions.some((item) => item.value === value) ? value : '='
  }

  function normalizeConditionValue(value: any) {
    if (Array.isArray(value)) return value
    return String(value ?? '').trim()
  }

  function normalizeParamRows(rows: any = [], strict = false): TemplateParam[] {
    if (!Array.isArray(rows)) return []
    const items = rows
      .map((item) => ({
        name: String(item?.name || '').trim(),
        label: String(item?.label || '').trim(),
        type: normalizeParamType(item?.type),
        default: String(item?.default ?? item?.default_value ?? '').trim(),
        required: Boolean(item?.required)
      }))
      .filter((item) => (strict ? item.name : item.name || item.label || item.default))
    if (strict && items.length > 20) {
      ElMessage.error('查询参数最多支持 20 个')
      throw new Error('查询参数最多支持 20 个')
    }

    return items.slice(0, 20)
  }

  function normalizeParamType(value: string) {
    return ['string', 'number', 'date', 'datetime', 'time_range'].includes(value) ? value : 'string'
  }

  function previewParams(config: Record<string, any>) {
    const result: Record<string, string> = {}
    for (const item of normalizeParamRows(config?.params, true)) {
      if (item.default !== '') {
        result[item.name] = item.default
      }
    }
    return result
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

  function normalizeDimensionType(value: string) {
    return ['raw', 'day', 'date', 'month', 'year'].includes(value) ? value : 'raw'
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

  function normalizeAggregateOrderBy(
    value: string,
    metrics: AggregateMetric[],
    allowSeries = false
  ) {
    const aliases = new Set(metrics.map((item, index) => aggregateMetricAlias(item, index)))
    if (value === 'label') return value
    if (allowSeries && value === 'series') return value
    return value && aliases.has(value) ? value : 'label'
  }

  function parseJsonObject(text: string, label: string, strict = true) {
    try {
      const value = JSON.parse(text || '{}')
      if (!value || typeof value !== 'object' || Array.isArray(value)) {
        throw new Error(`${label}必须是 JSON 对象`)
      }
      return value
    } catch {
      if (strict) {
        ElMessage.error(`${label}必须是 JSON 对象`)
        throw new Error(`${label}必须是 JSON 对象`)
      }
      return {}
    }
  }

  function stringifyJsonObject(value: any) {
    const objectValue =
      value && typeof value === 'object' && (!Array.isArray(value) || value.length === 0)
        ? value
        : {}
    return JSON.stringify(Array.isArray(objectValue) ? {} : objectValue, null, 2)
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

  .dataset-type-help {
    margin-bottom: 18px;
  }

  .param-help {
    width: 100%;
    margin-bottom: 10px;
  }

  .config-row {
    display: grid;
    grid-template-columns: minmax(160px, 1fr) 130px minmax(220px, 2fr) 64px;
    gap: 8px;
    margin-bottom: 8px;
  }

  .condition-group {
    padding: 10px;
    margin-bottom: 8px;
    border: 1px solid var(--default-border);
    border-radius: 6px;
  }

  .condition-group__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
  }

  .condition-group__title {
    font-size: 13px;
    font-weight: 600;
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
