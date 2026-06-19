<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ElForm :model="search" inline class="mb-4">
        <ElFormItem label="名称">
          <ElInput v-model="search.name" clearable />
        </ElFormItem>
        <ElFormItem label="编码">
          <ElInput v-model="search.code" clearable />
        </ElFormItem>
        <ElFormItem label="状态">
          <ElSelect v-model="search.status" clearable style="width: 120px">
            <ElOption label="已发布" :value="1" />
            <ElOption label="草稿" :value="2" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem>
          <ElButton type="primary" @click="loadData">搜索</ElButton>
          <ElButton @click="resetSearch">重置</ElButton>
        </ElFormItem>
      </ElForm>

      <ArtTableHeader :loading="loading" @refresh="loadData">
        <template #left>
          <ElSpace wrap>
            <ElButton v-permission="'saiboard:screen:save'" @click="openDialog()">
              <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
              新增大屏
            </ElButton>
            <ElButton
              v-permission="'saiboard:screen:generateFromTable'"
              type="primary"
              @click="openAutoDialog"
            >
              <template #icon><ArtSvgIcon icon="ri:magic-line" /></template>
              从数据表生成
            </ElButton>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="id" label="ID" width="90" />
        <ElTableColumn prop="name" label="名称" min-width="160" />
        <ElTableColumn prop="code" label="访问编码" min-width="180" show-overflow-tooltip />
        <ElTableColumn label="尺寸" width="130">
          <template #default="{ row }">{{ row.width }} x {{ row.height }}</template>
        </ElTableColumn>
        <ElTableColumn label="适配" width="110">
          <template #default="{ row }">{{ fitModeLabel(row.bg_config?.fit_mode) }}</template>
        </ElTableColumn>
        <ElTableColumn label="访问" width="110">
          <template #default="{ row }">
            <ElTag :type="row.is_public === 1 ? 'success' : 'warning'">
              {{ row.is_public === 1 ? '公开' : '鉴权' }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="状态" width="110">
          <template #default="{ row }">
            <ElTag :type="row.status === 1 ? 'success' : 'info'">
              {{ row.status === 1 ? '已发布' : '草稿' }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="update_time" label="更新时间" width="180" />
        <ElTableColumn label="操作" width="300" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <ElButton
                v-permission="'saiboard:screen:saveLayout'"
                size="small"
                @click="goEditor(row)"
              >
                编辑器
              </ElButton>
              <ElButton
                v-permission="'saiboard:screen:read'"
                size="small"
                @click="openRuntime(row)"
              >
                发布预览
              </ElButton>
              <SaButton
                v-permission="'saiboard:screen:update'"
                type="secondary"
                @click="openDialog(row)"
              />
              <ElDropdown>
                <SaButton type="info" />
                <template #dropdown>
                  <ElDropdownMenu>
                    <ElDropdownItem v-permission="'saiboard:screen:publish'" @click="publish(row)">
                      发布
                    </ElDropdownItem>
                    <ElDropdownItem v-permission="'saiboard:screen:copy'" @click="copy(row)">
                      复制
                    </ElDropdownItem>
                    <ElDropdownItem
                      v-permission="'saiboard:screen:versions'"
                      @click="openVersions(row)"
                    >
                      版本
                    </ElDropdownItem>
                    <ElDropdownItem
                      v-permission="'saiboard:screen:tokens'"
                      @click="openTokens(row)"
                    >
                      访问令牌
                    </ElDropdownItem>
                    <ElDropdownItem
                      v-permission="'saiboard:screen:index'"
                      @click="openMetrics(row)"
                    >
                      运行统计
                    </ElDropdownItem>
                    <ElDropdownItem
                      v-permission="'saiboard:screen:destroy'"
                      @click="deleteRow(row)"
                    >
                      删除
                    </ElDropdownItem>
                  </ElDropdownMenu>
                </template>
              </ElDropdown>
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog v-model="dialogVisible" :title="form.id ? '编辑大屏' : '新增大屏'" width="680px">
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
        <ElFormItem label="名称" prop="name">
          <ElInput v-model="form.name" maxlength="60" />
        </ElFormItem>
        <ElFormItem label="访问编码" prop="code">
          <ElInput v-model="form.code" maxlength="32" placeholder="留空自动生成" />
        </ElFormItem>
        <ElFormItem label="设计尺寸" required>
          <ElSpace>
            <ElInputNumber v-model="form.width" :min="320" :max="7680" />
            <span>x</span>
            <ElInputNumber v-model="form.height" :min="240" :max="4320" />
          </ElSpace>
        </ElFormItem>
        <ElFormItem label="背景色">
          <ElColorPicker v-model="form.bgColor" />
        </ElFormItem>
        <ElFormItem label="主题">
          <ElSegmented v-model="form.theme" :options="boardThemeOptions" />
        </ElFormItem>
        <ElFormItem label="背景图">
          <ElInput v-model="form.bgImage" clearable placeholder="图片 URL" />
        </ElFormItem>
        <ElFormItem label="图片适配">
          <ElSelect v-model="form.bgImageFit">
            <ElOption
              v-for="item in backgroundFitOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="适配模式">
          <ElSegmented v-model="form.fitMode" :options="fitModeOptions" />
        </ElFormItem>
        <ElFormItem label="访问方式" prop="is_public">
          <ElRadioGroup v-model="form.is_public">
            <ElRadioButton :label="1">公开</ElRadioButton>
            <ElRadioButton :label="2">鉴权</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton
          v-permission="form.id ? 'saiboard:screen:update' : 'saiboard:screen:save'"
          type="primary"
          @click="submit"
        >
          提交
        </ElButton>
      </template>
    </ElDialog>

    <ElDialog v-model="autoDialogVisible" title="从数据表生成大屏" width="620px">
      <ElForm ref="autoFormRef" :model="autoForm" :rules="autoRules" label-width="110px">
        <ElFormItem label="MySQL 数据源" prop="datasource_id">
          <ElSelect
            v-model="autoForm.datasource_id"
            filterable
            placeholder="请选择已启用的 MySQL 数据源"
            style="width: 100%"
            @change="onAutoDatasourceChange"
          >
            <ElOption
              v-for="item in mysqlDatasourceOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="数据表" prop="table">
          <ElSelect
            v-model="autoForm.table"
            v-loading="autoSchemaLoading"
            filterable
            placeholder="请选择数据表"
            style="width: 100%"
            @change="onAutoTableChange"
          >
            <ElOption
              v-for="item in autoTableOptions"
              :key="item.name"
              :label="item.name"
              :value="item.name"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="大屏名称" prop="name">
          <ElInput v-model="autoForm.name" maxlength="60" placeholder="留空按表名生成" />
        </ElFormItem>
        <ElFormItem label="设计尺寸" required>
          <ElSpace>
            <ElInputNumber v-model="autoForm.width" :min="320" :max="7680" />
            <span>x</span>
            <ElInputNumber v-model="autoForm.height" :min="240" :max="4320" />
          </ElSpace>
        </ElFormItem>
        <ElDivider content-position="left">生成配置</ElDivider>
        <ElFormItem label="生成模块" prop="chart_types">
          <ElCheckboxGroup v-model="autoForm.chart_types">
            <ElCheckbox
              v-for="item in autoChartOptions"
              :key="item.value"
              :label="item.value"
              :disabled="item.disabled"
            >
              {{ item.label }}
            </ElCheckbox>
          </ElCheckboxGroup>
        </ElFormItem>
        <ElFormItem label="时间字段">
          <ElSelect
            v-model="autoForm.date_field"
            clearable
            filterable
            placeholder="用于趋势图"
            style="width: 100%"
            @change="applyAutoChartAvailability"
          >
            <ElOption
              v-for="item in autoDateOptions"
              :key="item.name"
              :label="fieldOptionLabel(item)"
              :value="item.name"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="指标字段">
          <ElSelect
            v-model="autoForm.metric_field"
            clearable
            filterable
            placeholder="留空则按记录数统计"
            style="width: 100%"
          >
            <ElOption
              v-for="item in autoMetricOptions"
              :key="item.name"
              :label="fieldOptionLabel(item)"
              :value="item.name"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="排行维度">
          <ElSelect
            v-model="autoForm.label_field"
            clearable
            filterable
            placeholder="用于排行图"
            style="width: 100%"
            @change="applyAutoChartAvailability"
          >
            <ElOption
              v-for="item in autoStringOptions"
              :key="item.name"
              :label="fieldOptionLabel(item)"
              :value="item.name"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="分布维度">
          <ElSelect
            v-model="autoForm.category_field"
            clearable
            filterable
            placeholder="用于环形分布"
            style="width: 100%"
            @change="applyAutoChartAvailability"
          >
            <ElOption
              v-for="item in autoDimensionOptions"
              :key="item.name"
              :label="fieldOptionLabel(item)"
              :value="item.name"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="状态字段">
          <ElSelect
            v-model="autoForm.status_field"
            clearable
            filterable
            placeholder="用于状态矩阵"
            style="width: 100%"
            @change="applyAutoChartAvailability"
          >
            <ElOption
              v-for="item in autoStatusOptions"
              :key="item.name"
              :label="fieldOptionLabel(item)"
              :value="item.name"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="明细字段" prop="raw_fields">
          <ElSelect
            v-model="autoForm.raw_fields"
            multiple
            :multiple-limit="8"
            collapse-tags
            collapse-tags-tooltip
            filterable
            placeholder="最多选择 8 个字段"
            style="width: 100%"
            @change="applyAutoChartAvailability"
          >
            <ElOption
              v-for="item in autoFieldOptions"
              :key="item.name"
              :label="fieldOptionLabel(item)"
              :value="item.name"
            />
          </ElSelect>
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="autoDialogVisible = false">取消</ElButton>
        <ElButton
          v-permission="'saiboard:screen:generateFromTable'"
          type="primary"
          :loading="autoSubmitting"
          @click="generateFromTable"
        >
          生成并编辑
        </ElButton>
      </template>
    </ElDialog>

    <ElDialog
      v-model="metricsVisible"
      :title="`${metricsTarget?.name || '大屏'}运行统计`"
      width="760px"
    >
      <div v-loading="metricsLoading" class="metrics-panel">
        <ElDescriptions v-if="metrics" :column="3" border>
          <ElDescriptionsItem label="统计窗口"> {{ metrics.window }} 秒 </ElDescriptionsItem>
          <ElDescriptionsItem label="请求数">
            {{ currentMetrics.request || 0 }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="限流数">
            {{ currentMetrics.rate_limited || 0 }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="缓存命中">
            {{ currentMetrics.cache_hit || 0 }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="缓存未命中">
            {{ currentMetrics.cache_miss || 0 }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="命中率">
            {{ formatPercent(currentHitRate) }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="回源成功">
            {{ currentMetrics.source_success || 0 }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="回源失败">
            {{ currentMetrics.source_fail || 0 }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="Stale 命中">
            {{ currentMetrics.stale_hit || 0 }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="Redis 锁">
            {{ currentMetrics.redis_lock_acquired || 0 }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="文件锁">
            {{ currentMetrics.file_lock_acquired || 0 }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="锁等待 / 超时">
            {{ currentMetrics.lock_wait || 0 }} / {{ currentMetrics.lock_timeout || 0 }}
          </ElDescriptionsItem>
        </ElDescriptions>
      </div>
      <template #footer>
        <ElButton @click="metricsVisible = false">关闭</ElButton>
        <ElButton type="primary" @click="refreshMetrics">刷新</ElButton>
      </template>
    </ElDialog>

    <ElDialog
      v-model="versionVisible"
      :title="`${versionTarget?.name || '大屏'}版本`"
      width="860px"
    >
      <ElTable v-loading="versionLoading" :data="versionRows" row-key="id" max-height="460">
        <ElTableColumn prop="version_no" label="版本" width="90">
          <template #default="{ row }">#{{ row.version_no }}</template>
        </ElTableColumn>
        <ElTableColumn prop="title" label="标题" min-width="150" show-overflow-tooltip />
        <ElTableColumn label="来源" width="120">
          <template #default="{ row }">
            <ElTag>{{ versionSourceLabel(row.source) }}</ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="尺寸" width="130">
          <template #default="{ row }">{{ row.width }} x {{ row.height }}</template>
        </ElTableColumn>
        <ElTableColumn prop="create_time" label="创建时间" width="180" />
        <ElTableColumn label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <ElButton
                v-permission="'saiboard:screen:restoreVersion'"
                size="small"
                type="primary"
                @click="restoreVersion(row)"
              >
                恢复
              </ElButton>
              <ElButton
                v-permission="'saiboard:screen:deleteVersion'"
                size="small"
                type="danger"
                @click="deleteVersion(row)"
              >
                删除
              </ElButton>
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
      <template #footer>
        <ElButton @click="versionVisible = false">关闭</ElButton>
        <ElButton type="primary" @click="refreshVersions">刷新</ElButton>
      </template>
    </ElDialog>

    <ElDialog
      v-model="tokenVisible"
      :title="`${tokenTarget?.name || '大屏'}访问令牌`"
      width="920px"
    >
      <div class="token-toolbar">
        <ElInput
          v-model="tokenForm.name"
          maxlength="80"
          clearable
          placeholder="客户或用途"
          class="token-name-input"
        />
        <ElDatePicker
          v-model="tokenForm.expire_time"
          type="datetime"
          value-format="YYYY-MM-DD HH:mm:ss"
          placeholder="过期时间"
          clearable
        />
        <ElButton
          v-permission="'saiboard:screen:createToken'"
          type="primary"
          :loading="tokenCreating"
          @click="createToken"
        >
          新增令牌
        </ElButton>
      </div>
      <ElTable v-loading="tokenLoading" :data="tokenRows" row-key="id" max-height="430">
        <ElTableColumn prop="name" label="名称" min-width="160" show-overflow-tooltip />
        <ElTableColumn prop="token_prefix" label="前缀" width="110">
          <template #default="{ row }">
            <span class="token-prefix">{{ row.token_prefix }}...</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="状态" width="100">
          <template #default="{ row }">
            <ElTag :type="tokenStatusType(row)">{{ tokenStatusLabel(row) }}</ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="expire_time" label="过期时间" width="180">
          <template #default="{ row }">{{ row.expire_time || '长期有效' }}</template>
        </ElTableColumn>
        <ElTableColumn prop="last_used_time" label="最近使用" width="180">
          <template #default="{ row }">{{ row.last_used_time || '-' }}</template>
        </ElTableColumn>
        <ElTableColumn prop="create_time" label="创建时间" width="180" />
        <ElTableColumn label="操作" width="210" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <ElButton
                v-permission="'saiboard:screen:resetToken'"
                size="small"
                @click="resetToken(row)"
              >
                重置
              </ElButton>
              <ElButton
                v-permission="'saiboard:screen:changeTokenStatus'"
                size="small"
                @click="changeTokenStatus(row, row.status === 1 ? 2 : 1)"
              >
                {{ row.status === 1 ? '停用' : '启用' }}
              </ElButton>
              <ElButton
                v-permission="'saiboard:screen:deleteToken'"
                size="small"
                type="danger"
                @click="deleteToken(row)"
              >
                删除
              </ElButton>
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
      <template #footer>
        <ElButton @click="tokenVisible = false">关闭</ElButton>
        <ElButton type="primary" @click="refreshTokens">刷新</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import api from '../api/screen'
  import datasourceApi from '../api/datasource'
  import {
    backgroundFitOptions,
    boardThemeOptions,
    normalizeBgConfig,
    normalizeFitMode
  } from '../widgets/theme'

  interface AutoColumn {
    name: string
    type: string
    kind: 'string' | 'number' | 'date' | string
  }

  const rows = ref<any[]>([])
  const loading = ref(false)
  const dialogVisible = ref(false)
  const metricsVisible = ref(false)
  const metricsLoading = ref(false)
  const metrics = ref<any>(null)
  const metricsTarget = ref<any>(null)
  const versionVisible = ref(false)
  const versionLoading = ref(false)
  const versionRows = ref<any[]>([])
  const versionTarget = ref<any>(null)
  const tokenVisible = ref(false)
  const tokenLoading = ref(false)
  const tokenCreating = ref(false)
  const tokenRows = ref<any[]>([])
  const tokenTarget = ref<any>(null)
  const autoDialogVisible = ref(false)
  const autoSchemaLoading = ref(false)
  const autoSubmitting = ref(false)
  const datasourceOptions = ref<any[]>([])
  const autoTableOptions = ref<any[]>([])
  const autoColumns = ref<AutoColumn[]>([])
  const formRef = ref<FormInstance>()
  const autoFormRef = ref<FormInstance>()
  const search = reactive({ name: '', code: '', status: undefined as number | undefined })
  const tokenForm = reactive({ name: '', expire_time: '' })
  const autoForm = reactive({
    datasource_id: undefined as number | undefined,
    table: '',
    name: '',
    width: 1920,
    height: 1080,
    chart_types: [] as string[],
    date_field: '',
    metric_field: '',
    label_field: '',
    category_field: '',
    status_field: '',
    order_field: '',
    raw_fields: [] as string[]
  })
  const form = reactive({
    id: undefined as number | undefined,
    name: '',
    code: '',
    width: 1920,
    height: 1080,
    bgColor: '#07111f',
    theme: 'midnight',
    bgImage: '',
    bgImageFit: 'cover',
    fitMode: 'contain',
    bg_config: {} as Record<string, any>,
    is_public: 1,
    status: 2
  })

  const fitModeOptions = [
    { label: '完整显示', value: 'contain' },
    { label: '裁切铺满', value: 'cover' },
    { label: '非等比拉伸', value: 'stretch' }
  ]
  const baseAutoChartOptions = [
    { label: '总数指标', value: 'count' },
    { label: '趋势图', value: 'trend' },
    { label: '排行图', value: 'rank' },
    { label: '分布图', value: 'distribution' },
    { label: '状态矩阵', value: 'status' },
    { label: '明细表', value: 'raw' }
  ]
  const versionSourceMap: Record<string, string> = {
    publish: '发布',
    restore_before: '恢复前',
    save_layout: '保存'
  }

  const validateScreenCode = (
    _rule: unknown,
    value: unknown,
    callback: (error?: Error) => void
  ) => {
    const code = String(value || '').trim()
    if (code !== '' && !/^[A-Za-z0-9_-]{1,32}$/.test(code)) {
      callback(new Error('访问编码只能包含字母、数字、下划线和短横线，最多32位'))
      return
    }
    callback()
  }

  const rules: FormRules = {
    name: [{ required: true, message: '名称必填', trigger: 'blur' }],
    code: [{ validator: validateScreenCode, trigger: 'blur' }],
    width: [{ required: true, message: '设计宽度必填', trigger: 'blur' }],
    height: [{ required: true, message: '设计高度必填', trigger: 'blur' }]
  }
  const autoRules: FormRules = {
    datasource_id: [{ required: true, message: '请选择 MySQL 数据源', trigger: 'change' }],
    table: [{ required: true, message: '请选择数据表', trigger: 'change' }],
    chart_types: [{ required: true, message: '请选择生成模块', trigger: 'change' }]
  }

  const currentMetrics = computed(() => metrics.value?.screen || metrics.value?.totals || {})
  const mysqlDatasourceOptions = computed(() =>
    datasourceOptions.value.filter((item) => item.type === 'mysql')
  )
  const autoFieldOptions = computed(() => autoColumns.value)
  const autoDateOptions = computed(() => autoColumns.value.filter((item) => item.kind === 'date'))
  const autoMetricOptions = computed(() =>
    autoColumns.value.filter((item) => item.kind === 'number' && !isMetricExcludedField(item.name))
  )
  const autoStringOptions = computed(() =>
    autoColumns.value.filter((item) => item.kind === 'string')
  )
  const autoDimensionOptions = computed(() =>
    autoColumns.value.filter((item) => item.kind === 'string' || isDimensionNumberField(item.name))
  )
  const autoStatusOptions = computed(() =>
    autoDimensionOptions.value.filter((item) => isStatusLikeField(item.name))
  )
  const autoChartOptions = computed(() =>
    baseAutoChartOptions.map((item) => ({
      ...item,
      disabled:
        (item.value === 'trend' && !autoForm.date_field) ||
        (item.value === 'rank' && !autoForm.label_field) ||
        (item.value === 'distribution' &&
          (!autoForm.category_field || autoForm.category_field === autoForm.label_field)) ||
        (item.value === 'status' &&
          (!autoForm.status_field ||
            !autoForm.label_field ||
            autoForm.status_field === autoForm.label_field)) ||
        (item.value === 'raw' && autoForm.raw_fields.length === 0)
    }))
  )
  const currentHitRate = computed(() => {
    const hit = Number(currentMetrics.value.cache_hit || 0)
    const miss = Number(currentMetrics.value.cache_miss || 0)
    return hit + miss > 0 ? hit / (hit + miss) : 0
  })

  const loadData = async () => {
    loading.value = true
    try {
      const data = await api.list({ ...search, saiType: 'all' })
      rows.value = Array.isArray(data) ? data : data?.data || []
    } finally {
      loading.value = false
    }
  }

  const resetSearch = () => {
    Object.assign(search, { name: '', code: '', status: undefined })
    loadData()
  }

  const loadDatasourceOptions = async () => {
    datasourceOptions.value = await datasourceApi.options()
  }

  const resetAutoForm = () => {
    Object.assign(autoForm, {
      datasource_id: undefined,
      table: '',
      name: '',
      width: 1920,
      height: 1080,
      chart_types: [],
      date_field: '',
      metric_field: '',
      label_field: '',
      category_field: '',
      status_field: '',
      order_field: '',
      raw_fields: []
    })
    autoColumns.value = []
    autoTableOptions.value = []
  }

  const fieldOptionLabel = (field: AutoColumn) => `${field.name} (${field.type || field.kind})`

  const isDimensionNumberField = (name: string) =>
    name === 'status' ||
    name.startsWith('is_') ||
    name.endsWith('_status') ||
    name.endsWith('_type') ||
    name.endsWith('_level')

  const isStatusLikeField = (name: string) =>
    /status|state|health|level|result|online|risk|alarm|warn/i.test(name)

  const isMetricExcludedField = (name: string) =>
    name === 'id' ||
    name === 'created_by' ||
    name === 'updated_by' ||
    name === 'sort' ||
    name.endsWith('_id') ||
    isDimensionNumberField(name)

  const preferredAutoField = (
    columns: AutoColumn[],
    patterns: RegExp[],
    excluded: string[] = []
  ) => {
    for (const pattern of patterns) {
      const matched = columns.find(
        (column) => !excluded.includes(column.name) && pattern.test(column.name)
      )
      if (matched) return matched.name
    }

    return columns.find((column) => !excluded.includes(column.name))?.name || ''
  }

  const matchedAutoField = (columns: AutoColumn[], patterns: RegExp[], excluded: string[] = []) => {
    for (const pattern of patterns) {
      const matched = columns.find(
        (column) => !excluded.includes(column.name) && pattern.test(column.name)
      )
      if (matched) return matched.name
    }

    return ''
  }

  const preferredRawFields = () => {
    const fields: string[] = []
    const pushField = (field: string) => {
      if (
        field &&
        autoColumns.value.some((column) => column.name === field) &&
        !fields.includes(field)
      ) {
        fields.push(field)
      }
    }
    ;[
      'id',
      'order_no',
      'code',
      'title',
      'name',
      'category',
      'type',
      'status',
      'price',
      'amount',
      'total',
      'create_time',
      'created_at',
      'update_time'
    ].forEach(pushField)
    autoColumns.value.forEach((column) => pushField(column.name))

    return fields.slice(0, 8)
  }

  const applyAutoRecommendations = () => {
    const dateColumns = autoColumns.value.filter((column) => column.kind === 'date')
    const metricColumns = autoColumns.value.filter(
      (column) => column.kind === 'number' && !isMetricExcludedField(column.name)
    )
    const stringColumns = autoColumns.value.filter((column) => column.kind === 'string')
    const dimensionColumns = autoColumns.value.filter(
      (column) => column.kind === 'string' || isDimensionNumberField(column.name)
    )
    const dateField = preferredAutoField(dateColumns, [
      /create|created|order|pay|paid|time|date|day|month|update/i
    ])
    const metricField = preferredAutoField(metricColumns, [
      /amount|price|money|total|fee|cost|revenue|income|sales|stock|qty|quantity|num|count|view|click|score|value/i
    ])
    const labelField = preferredAutoField(stringColumns, [
      /name|title|subject|label|category|type|method|source|channel|city|province|platform|lang|code/i
    ])
    const categoryField =
      preferredAutoField(
        dimensionColumns,
        [/status|type|category|method|source|channel|platform|lang|level|city|province/i],
        labelField ? [labelField] : []
      ) || labelField
    const statusField = matchedAutoField(dimensionColumns, [
      /status|state|health|level|result|online|risk|alarm|warn/i
    ])
    const rawFields = preferredRawFields()
    const chartTypes = ['count']
    if (dateField) chartTypes.push('trend')
    if (labelField) chartTypes.push('rank')
    if (categoryField && categoryField !== labelField) chartTypes.push('distribution')
    if (statusField && labelField && statusField !== labelField) chartTypes.push('status')
    if (rawFields.length) chartTypes.push('raw')

    Object.assign(autoForm, {
      date_field: dateField,
      metric_field: metricField,
      label_field: labelField,
      category_field: categoryField,
      status_field: statusField,
      order_field: dateField || preferredAutoField(autoColumns.value, [/^id$/i, /_id$/i]),
      raw_fields: rawFields,
      chart_types: chartTypes
    })
  }

  const applyAutoChartAvailability = () => {
    const allowed = new Set(['count'])
    if (autoForm.date_field) allowed.add('trend')
    if (autoForm.label_field) allowed.add('rank')
    if (autoForm.category_field && autoForm.category_field !== autoForm.label_field) {
      allowed.add('distribution')
    }
    if (
      autoForm.status_field &&
      autoForm.label_field &&
      autoForm.status_field !== autoForm.label_field
    ) {
      allowed.add('status')
    }
    if (autoForm.raw_fields.length) allowed.add('raw')
    autoForm.chart_types = autoForm.chart_types.filter((item) => allowed.has(item))
  }

  const openDialog = (row?: any) => {
    const defaultBg = normalizeBgConfig()
    Object.assign(form, {
      id: undefined,
      name: '',
      code: '',
      width: 1920,
      height: 1080,
      bgColor: defaultBg.color,
      theme: defaultBg.theme,
      bgImage: defaultBg.image,
      bgImageFit: defaultBg.image_fit,
      fitMode: 'contain',
      bg_config: defaultBg,
      is_public: 1,
      status: 2
    })
    if (row) {
      const bg = normalizeBgConfig(row.bg_config || {})
      Object.assign(form, row, {
        bgColor: bg.color,
        theme: bg.theme,
        bgImage: bg.image,
        bgImageFit: bg.image_fit,
        fitMode: normalizeFitMode(bg.fit_mode),
        bg_config: bg
      })
    }
    dialogVisible.value = true
  }

  const openAutoDialog = async () => {
    resetAutoForm()
    autoDialogVisible.value = true
    if (!datasourceOptions.value.length) {
      await loadDatasourceOptions()
    }
    const firstMysql = mysqlDatasourceOptions.value[0]
    if (!firstMysql) {
      ElMessage.warning('请先新增并启用 MySQL 数据源')
      return
    }
    autoForm.datasource_id = firstMysql.id
    await loadAutoTables()
  }

  const loadAutoTables = async () => {
    if (!autoForm.datasource_id) {
      autoTableOptions.value = []
      autoColumns.value = []
      return
    }
    autoSchemaLoading.value = true
    try {
      const result = await datasourceApi.schema({ id: autoForm.datasource_id })
      autoTableOptions.value = result.tables || []
    } finally {
      autoSchemaLoading.value = false
    }
  }

  const onAutoDatasourceChange = async () => {
    autoForm.table = ''
    autoForm.name = ''
    autoColumns.value = []
    await loadAutoTables()
  }

  const onAutoTableChange = async () => {
    if (!autoForm.name && autoForm.table) {
      autoForm.name = `${autoForm.table.replace(/_/g, ' ')} 数据大屏`
    }
    await loadAutoColumns()
  }

  const loadAutoColumns = async () => {
    if (!autoForm.datasource_id || !autoForm.table) {
      autoColumns.value = []
      return
    }
    autoSchemaLoading.value = true
    try {
      const result = await datasourceApi.schema({
        id: autoForm.datasource_id,
        table: autoForm.table
      })
      autoColumns.value = Array.isArray(result.columns) ? result.columns : []
      applyAutoRecommendations()
      autoFormRef.value?.clearValidate()
    } finally {
      autoSchemaLoading.value = false
    }
  }

  const generateFromTable = async () => {
    applyAutoChartAvailability()
    if (!autoForm.chart_types.length) {
      ElMessage.warning('请至少选择一个可用生成模块')
      return
    }
    if (autoForm.chart_types.includes('raw') && !autoForm.raw_fields.length) {
      ElMessage.warning('生成明细表时请至少选择一个明细字段')
      return
    }
    await autoFormRef.value?.validate()
    autoSubmitting.value = true
    try {
      const result = await api.generateFromTable({ ...autoForm })
      ElMessage.success('大屏草稿已生成')
      autoDialogVisible.value = false
      await loadData()
      if (result?.id) {
        goEditor({ id: result.id })
      }
    } finally {
      autoSubmitting.value = false
    }
  }

  const submit = async () => {
    await formRef.value?.validate()
    const payload: Record<string, any> = {
      ...form,
      bg_config: normalizeBgConfig({
        ...(form.bg_config || {}),
        color: form.bgColor,
        theme: form.theme,
        image: form.bgImage,
        image_fit: form.bgImageFit,
        fit_mode: normalizeFitMode(form.fitMode)
      })
    }
    delete payload.status
    delete payload.access_token
    if (!form.id) {
      payload.draft_layout = {
        canvas: { width: form.width, height: form.height },
        components: []
      }
      payload.layout = {
        canvas: { width: form.width, height: form.height },
        components: []
      }
    }
    if (form.id) {
      await api.update(payload)
    } else {
      await api.save(payload)
    }
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadData()
  }

  const goEditor = (row: any) => {
    window.location.hash = `#/saiboard/editor/${row.id}`
  }

  const openRuntime = (row: any) => {
    if (row.status !== 1) {
      ElMessage.warning('草稿尚未发布，请进入编辑器预览草稿')
      return
    }
    const params = new URLSearchParams()
    if (row.is_public === 2) {
      params.set('admin_preview', '1')
    }
    window.open(`#/screen/${row.code}${params.toString() ? `?${params.toString()}` : ''}`, '_blank')
  }

  const publish = async (row: any) => {
    await api.publish({ id: row.id })
    ElMessage.success('发布成功')
    loadData()
  }

  const copy = async (row: any) => {
    await api.copy({ id: row.id })
    ElMessage.success('复制成功')
    loadData()
  }

  const openVersions = async (row: any) => {
    versionTarget.value = row
    versionVisible.value = true
    await refreshVersions()
  }

  const refreshVersions = async () => {
    if (!versionTarget.value?.id) return
    versionLoading.value = true
    try {
      const data = await api.versions({ id: versionTarget.value.id })
      versionRows.value = Array.isArray(data) ? data : []
    } finally {
      versionLoading.value = false
    }
  }

  const restoreVersion = async (row: any) => {
    if (!versionTarget.value?.id) return
    await ElMessageBox.confirm(
      `确定恢复到版本 #${row.version_no} 吗？当前草稿会先保存为恢复前快照。`,
      '恢复版本',
      { type: 'warning' }
    )
    await api.restoreVersion({ id: versionTarget.value.id, version_id: row.id })
    ElMessage.success('已恢复为草稿')
    await Promise.all([loadData(), refreshVersions()])
  }

  const deleteVersion = async (row: any) => {
    if (!versionTarget.value?.id) return
    await ElMessageBox.confirm(`确定删除版本 #${row.version_no} 吗？`, '删除版本', {
      type: 'warning'
    })
    await api.deleteVersion({ id: versionTarget.value.id, version_id: row.id })
    ElMessage.success('删除成功')
    refreshVersions()
  }

  const openTokens = async (row: any) => {
    tokenTarget.value = row
    Object.assign(tokenForm, { name: '', expire_time: '' })
    tokenVisible.value = true
    await refreshTokens()
  }

  const refreshTokens = async () => {
    if (!tokenTarget.value?.id) return
    tokenLoading.value = true
    try {
      const data = await api.tokens({ id: tokenTarget.value.id })
      tokenRows.value = Array.isArray(data) ? data : []
    } finally {
      tokenLoading.value = false
    }
  }

  const createToken = async () => {
    if (!tokenTarget.value?.id) return
    if (!tokenForm.name.trim()) {
      ElMessage.warning('请填写令牌名称')
      return
    }
    tokenCreating.value = true
    try {
      const data = await api.createToken({
        id: tokenTarget.value.id,
        name: tokenForm.name,
        expire_time: tokenForm.expire_time
      })
      Object.assign(tokenForm, { name: '', expire_time: '' })
      await refreshTokens()
      await showPlainToken(data?.token)
    } finally {
      tokenCreating.value = false
    }
  }

  const resetToken = async (row: any) => {
    if (!tokenTarget.value?.id) return
    await ElMessageBox.confirm(
      `确定重置「${row.name}」的访问令牌吗？旧令牌会立即失效。`,
      '重置令牌',
      {
        type: 'warning'
      }
    )
    const data = await api.resetToken({ id: tokenTarget.value.id, token_id: row.id })
    await refreshTokens()
    await showPlainToken(data?.token)
  }

  const changeTokenStatus = async (row: any, status: number) => {
    if (!tokenTarget.value?.id) return
    await api.changeTokenStatus({ id: tokenTarget.value.id, token_id: row.id, status })
    ElMessage.success(status === 1 ? '已启用' : '已停用')
    refreshTokens()
  }

  const deleteToken = async (row: any) => {
    if (!tokenTarget.value?.id) return
    await ElMessageBox.confirm(`确定删除「${row.name}」吗？`, '删除令牌', { type: 'warning' })
    await api.deleteToken({ id: tokenTarget.value.id, token_id: row.id })
    ElMessage.success('删除成功')
    refreshTokens()
  }

  const openMetrics = async (row: any) => {
    metricsTarget.value = row
    metricsVisible.value = true
    await refreshMetrics()
  }

  const refreshMetrics = async () => {
    metricsLoading.value = true
    try {
      metrics.value = await api.runtimeMetrics(
        metricsTarget.value?.id ? { id: metricsTarget.value.id } : {}
      )
    } finally {
      metricsLoading.value = false
    }
  }

  const deleteRow = async (row: any) => {
    await ElMessageBox.confirm(`确定删除「${row.name}」吗？`, '删除大屏', { type: 'warning' })
    await api.delete({ ids: [row.id] })
    ElMessage.success('删除成功')
    loadData()
  }

  const fitModeLabel = (mode: unknown) =>
    fitModeOptions.find((item) => item.value === normalizeFitMode(mode))?.label || '完整显示'

  const formatPercent = (value: number) => `${Math.round(value * 10000) / 100}%`

  const versionSourceLabel = (source: string) => versionSourceMap[source] || source || '保存'

  const tokenStatusLabel = (row: any) => {
    if (row.status === 2) return '停用'
    return row.is_expired ? '已过期' : '启用'
  }

  const tokenStatusType = (row: any) => {
    if (row.status === 2) return 'info'
    return row.is_expired ? 'warning' : 'success'
  }

  const showPlainToken = async (token?: string) => {
    if (!token) return
    await ElMessageBox.alert(
      `令牌：${token}\n\n请立即保存，关闭后无法再次查看。`,
      '访问令牌只显示一次',
      {
        confirmButtonText: '我已保存'
      }
    )
  }

  onMounted(async () => {
    await Promise.all([loadData(), loadDatasourceOptions()])
  })
</script>

<style scoped lang="scss">
  .metrics-panel {
    min-height: 160px;
  }

  .token-toolbar {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 16px;
  }

  .token-name-input {
    width: 220px;
  }

  .token-prefix {
    font-family: monospace;
  }
</style>
