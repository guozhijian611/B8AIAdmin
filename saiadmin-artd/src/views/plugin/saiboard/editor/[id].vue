<template>
  <div class="saiboard-editor">
    <div class="editor-toolbar">
      <ElSpace>
        <ElButton @click="back">返回</ElButton>
        <ElDivider direction="vertical" />
        <strong>{{ screen.name || '大屏编辑器' }}</strong>
        <ElTag v-if="screen.status === 1" type="success">已发布</ElTag>
        <ElTag v-else>草稿</ElTag>
      </ElSpace>
      <ElSpace>
        <ElSelect v-model="zoom" style="width: 130px">
          <ElOption label="适应窗口" value="auto" />
          <ElOption label="50%" :value="0.5" />
          <ElOption label="75%" :value="0.75" />
          <ElOption label="100%" :value="1" />
        </ElSelect>
        <ElDivider direction="vertical" />
        <ElButton :disabled="!canUndo" @click="undoLayout">撤销</ElButton>
        <ElButton :disabled="!canRedo" @click="redoLayout">重做</ElButton>
        <ElButton :disabled="!canCopy" @click="copySelected">复制</ElButton>
        <ElButton :disabled="!canPaste" @click="pasteCopied">粘贴</ElButton>
        <ElButton v-permission="'saiboard:screen:saveLayout'" type="primary" @click="saveLayout">
          保存
        </ElButton>
        <ElButton v-permission="'saiboard:screen:publish'" type="success" @click="publish">
          发布
        </ElButton>
      </ElSpace>
    </div>

    <div class="editor-main">
      <aside class="widget-panel">
        <div class="panel-title">组件</div>
        <button
          v-for="widget in widgetRegistry"
          :key="widget.type"
          class="widget-button"
          type="button"
          @click="addWidget(widget.type)"
        >
          <ArtSvgIcon :icon="widget.icon" />
          <span>{{ widget.name }}</span>
        </button>
      </aside>

      <main ref="canvasShellRef" class="canvas-shell">
        <div
          class="canvas"
          :style="{
            ...canvasStyle,
            width: layout.canvas.width + 'px',
            height: layout.canvas.height + 'px',
            transform: `scale(${effectiveZoom})`
          }"
          @mousedown.self="selectedId = ''"
        >
          <DraggableItem
            v-for="component in layout.components"
            :key="component.id"
            :component="component"
            :rows="componentRows(component)"
            :error="componentError(component)"
            :selected="selectedId === component.id"
            @select="selectedId = $event"
            @update="updateComponent"
          />
        </div>
      </main>

      <aside class="property-panel">
        <template v-if="selectedComponent">
          <div class="panel-title">属性</div>
          <ElForm label-width="84px">
            <ElFormItem label="标题">
              <ElInput v-model="selectedComponent.title" />
            </ElFormItem>
            <ElFormItem label="类型">
              <ElSelect v-model="selectedComponent.type">
                <ElOption
                  v-for="widget in widgetRegistry"
                  :key="widget.type"
                  :label="widget.name"
                  :value="widget.type"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem v-if="requiresDataset" label="查询模板">
              <div class="template-bind-row">
                <ElSelect
                  v-model="selectedComponent.dataset.queryTemplateId"
                  clearable
                  filterable
                  @change="onTemplateChange"
                >
                  <ElOption
                    v-for="item in templateOptions"
                    :key="item.id"
                    :label="item.name"
                    :value="item.id"
                  />
                </ElSelect>
                <ElButton
                  v-permission="'saiboard:query_template:preview'"
                  :disabled="!selectedComponent.dataset.queryTemplateId"
                  :loading="selectedPreviewLoading"
                  @click="previewSelectedData"
                >
                  预览
                </ElButton>
              </div>
            </ElFormItem>
            <ElFormItem v-if="requiresDataset" label="刷新秒">
              <ElInputNumber v-model="selectedComponent.dataset.refresh" :min="10" :max="3600" />
            </ElFormItem>
            <ElFormItem v-if="supportsFieldMapping" label="类目字段">
              <ElSelect
                v-model="selectedComponent.dataset.mapping!.labelField"
                clearable
                filterable
                placeholder="自动识别"
                :disabled="!fieldOptions.length"
              >
                <ElOption
                  v-for="field in fieldOptions"
                  :key="field"
                  :label="field"
                  :value="field"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem v-if="supportsFieldMapping" label="数值字段">
              <ElSelect
                v-model="selectedComponent.dataset.mapping!.valueField"
                clearable
                filterable
                placeholder="自动识别"
                :disabled="!fieldOptions.length"
              >
                <ElOption
                  v-for="field in numericFieldOptions"
                  :key="field"
                  :label="field"
                  :value="field"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem v-if="selectedComponent.type === 'data-table'" label="表格字段">
              <ElSelect
                v-model="selectedComponent.dataset.mapping!.tableFields"
                multiple
                collapse-tags
                collapse-tags-tooltip
                filterable
                placeholder="默认前 8 列"
                :disabled="!fieldOptions.length"
                @change="syncSelectedTableColumns"
              >
                <ElOption
                  v-for="field in fieldOptions"
                  :key="field"
                  :label="field"
                  :value="field"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem v-if="selectedComponent.type === 'data-table'" label="列配置">
              <div class="table-column-config">
                <ElText v-if="!selectedTableColumns.length" type="info" size="small">
                  选择表格字段后可配置列别名、宽度和对齐
                </ElText>
                <div
                  v-for="column in selectedTableColumns"
                  :key="column.field"
                  class="table-column-config__item"
                >
                  <ElText class="table-column-config__field" truncated>
                    {{ column.field }}
                  </ElText>
                  <ElInput v-model="column.label" clearable placeholder="显示名，默认字段名" />
                  <div class="table-column-config__controls">
                    <ElInputNumber
                      v-model="column.width"
                      class="table-column-config__width"
                      :min="60"
                      :max="600"
                      :step="10"
                      :controls="false"
                      placeholder="宽度"
                    />
                    <ElSelect
                      v-model="column.align"
                      class="table-column-config__align"
                      placeholder="对齐"
                    >
                      <ElOption
                        v-for="item in tableAlignOptions"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value"
                      />
                    </ElSelect>
                  </div>
                </div>
              </div>
            </ElFormItem>
            <ElFormItem v-if="requiresDataset" label="数据预览">
              <ElText v-if="selectedPreviewError" type="danger" truncated>
                {{ selectedPreviewError }}
              </ElText>
              <ElText v-else-if="selectedComponent.dataset.queryTemplateId" type="success">
                已加载 {{ selectedPreviewRows.length }} 行
              </ElText>
              <ElText v-else type="info">未绑定查询模板，使用示例数据</ElText>
            </ElFormItem>
            <ElFormItem label="位置">
              <ElSpace wrap>
                <ElInputNumber
                  v-model="selectedComponent.rect.x"
                  :min="0"
                  :max="layout.canvas.width"
                />
                <ElInputNumber
                  v-model="selectedComponent.rect.y"
                  :min="0"
                  :max="layout.canvas.height"
                />
              </ElSpace>
            </ElFormItem>
            <ElFormItem label="尺寸">
              <ElSpace wrap>
                <ElInputNumber
                  v-model="selectedComponent.rect.w"
                  :min="120"
                  :max="layout.canvas.width"
                />
                <ElInputNumber
                  v-model="selectedComponent.rect.h"
                  :min="80"
                  :max="layout.canvas.height"
                />
              </ElSpace>
            </ElFormItem>
            <ElFormItem label="层级">
              <ElInputNumber v-model="selectedComponent.rect.z" :min="1" :max="999" />
            </ElFormItem>
            <template v-if="selectedComponent.type === 'stat-number'">
              <ElFormItem label="前缀">
                <ElInput v-model="selectedComponent.option!.prefix" placeholder="例如 ¥" />
              </ElFormItem>
              <ElFormItem label="小数位">
                <ElInputNumber
                  v-model="selectedComponent.option!.decimals"
                  :min="0"
                  :max="6"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="单位">
                <ElInput v-model="selectedComponent.option!.unit" placeholder="例如 单、元、%" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'data-table'">
              <ElFormItem label="最大行数">
                <ElInputNumber
                  v-model="selectedComponent.option!.maxRows"
                  :min="0"
                  :max="1000"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="序号列">
                <ElSwitch v-model="selectedComponent.option!.showIndex" />
              </ElFormItem>
              <ElFormItem label="斑马纹">
                <ElSwitch v-model="selectedComponent.option!.rowStripe" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'decor-border'">
              <ElFormItem label="样式">
                <ElSelect v-model="selectedComponent.option!.borderStyle">
                  <ElOption label="转角" value="corner" />
                  <ElOption label="线条" value="line" />
                  <ElOption label="辉光" value="glow" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="强调色">
                <ElColorPicker v-model="selectedComponent.option!.accent" />
              </ElFormItem>
              <ElFormItem label="透明度">
                <ElSlider
                  v-model="selectedComponent.option!.opacity"
                  :min="0.1"
                  :max="1"
                  :step="0.05"
                />
              </ElFormItem>
            </template>
            <ElFormItem>
              <ElSpace>
                <ElButton @click="bringToFront">置顶</ElButton>
                <ElButton type="danger" @click="removeSelected">删除</ElButton>
              </ElSpace>
            </ElFormItem>
          </ElForm>
        </template>
        <template v-else>
          <div class="panel-title">画布</div>
          <ElForm label-width="84px">
            <ElFormItem label="宽度">
              <ElInputNumber v-model="layout.canvas.width" :min="320" :max="7680" />
            </ElFormItem>
            <ElFormItem label="高度">
              <ElInputNumber v-model="layout.canvas.height" :min="240" :max="4320" />
            </ElFormItem>
            <ElFormItem label="背景色">
              <ElColorPicker v-model="screen.bg_config.color" />
            </ElFormItem>
            <ElFormItem label="主题">
              <ElSegmented v-model="screen.bg_config.theme" :options="boardThemeOptions" />
            </ElFormItem>
            <ElFormItem label="背景图">
              <ElInput v-model="screen.bg_config.image" clearable placeholder="图片 URL" />
            </ElFormItem>
            <ElFormItem label="图片适配">
              <ElSelect v-model="screen.bg_config.image_fit">
                <ElOption
                  v-for="item in backgroundFitOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem label="适配模式">
              <ElSegmented v-model="screen.bg_config.fit_mode" :options="fitModeOptions" />
            </ElFormItem>
          </ElForm>
        </template>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import api from '../api/screen'
  import templateApi from '../api/query-template'
  import DraggableItem from '../widgets/DraggableItem.vue'
  import { createDefaultComponent, getWidgetMeta, widgetRegistry } from '../widgets/registry'
  import {
    backgroundFitOptions,
    boardCanvasStyle,
    boardThemeOptions,
    normalizeBgConfig
  } from '../widgets/theme'
  import type { BoardComponent, BoardLayout, BoardTableColumn } from '../widgets/types'

  interface PreviewState {
    rows: Record<string, any>[]
    error: string
    loading: boolean
  }

  interface LayoutHistoryState {
    past: string[]
    future: string[]
    current: string
  }

  const route = useRoute()
  const zoom = ref<'auto' | number>('auto')
  const autoZoom = ref(0.75)
  const canvasShellRef = ref<HTMLElement>()
  const selectedId = ref('')
  const copiedComponent = ref<BoardComponent>()
  const templateOptions = ref<any[]>([])
  const previewMap = reactive<Record<string, PreviewState>>({})
  const layoutHistory = reactive<LayoutHistoryState>({
    past: [],
    future: [],
    current: ''
  })
  let canvasResizeObserver: ResizeObserver | undefined
  let suppressHistory = false
  let historyTimer = 0
  const historyLimit = 50
  const screen = reactive<any>({
    id: 0,
    name: '',
    status: 2,
    bg_config: normalizeBgConfig()
  })
  const layout = reactive<BoardLayout>({
    canvas: { width: 1920, height: 1080 },
    components: []
  })
  const sampleRows = [
    { label: '周一', value: 120 },
    { label: '周二', value: 180 },
    { label: '周三', value: 150 },
    { label: '周四', value: 220 },
    { label: '周五', value: 260 }
  ]
  const fitModeOptions = [
    { label: '完整显示', value: 'contain' },
    { label: '裁切铺满', value: 'cover' },
    { label: '非等比拉伸', value: 'stretch' }
  ]
  const tableAlignOptions: Array<{ label: string; value: NonNullable<BoardTableColumn['align']> }> =
    [
      { label: '左对齐', value: 'left' },
      { label: '居中', value: 'center' },
      { label: '右对齐', value: 'right' }
    ]
  const minRefreshSeconds = 10
  const maxRefreshSeconds = 3600
  const fieldMappingTypes = new Set([
    'art-bar-chart',
    'art-line-chart',
    'art-h-bar-chart',
    'art-ring-chart',
    'art-radar-chart',
    'art-scatter-chart',
    'stat-number',
    'data-table'
  ])
  const componentNeedsData = (component: BoardComponent) => component.type !== 'decor-border'

  const selectedComponent = computed(() =>
    layout.components.find((item) => item.id === selectedId.value)
  )
  const requiresDataset = computed(() =>
    Boolean(selectedComponent.value && componentNeedsData(selectedComponent.value))
  )
  const selectedPreviewState = computed(() =>
    selectedComponent.value ? previewMap[selectedComponent.value.id] : undefined
  )
  const selectedPreviewRows = computed(() => selectedPreviewState.value?.rows || [])
  const selectedPreviewError = computed(() => selectedPreviewState.value?.error || '')
  const selectedPreviewLoading = computed(() => Boolean(selectedPreviewState.value?.loading))
  const fieldOptions = computed(() => Object.keys(selectedPreviewRows.value[0] || {}))
  const numericFieldOptions = computed(() =>
    fieldOptions.value.filter((field) =>
      selectedPreviewRows.value.some((row) => Number.isFinite(Number(row[field])))
    )
  )
  const supportsFieldMapping = computed(() =>
    Boolean(selectedComponent.value && fieldMappingTypes.has(selectedComponent.value.type))
  )
  const selectedTableColumns = computed(
    () => selectedComponent.value?.dataset.mapping?.tableColumns || []
  )
  const canUndo = computed(() => layoutHistory.past.length > 0)
  const canRedo = computed(() => layoutHistory.future.length > 0)
  const canCopy = computed(() => Boolean(selectedComponent.value))
  const canPaste = computed(() => Boolean(copiedComponent.value))
  const effectiveZoom = computed(() =>
    zoom.value === 'auto' ? autoZoom.value : Number(zoom.value)
  )
  const canvasStyle = computed(() => boardCanvasStyle(screen.bg_config))

  const loadData = async () => {
    suppressHistory = true
    try {
      const id = Number(route.params.id)
      const data = await api.read(id)
      Object.assign(screen, data, { bg_config: normalizeBgConfig(data.bg_config) })
      const draft = normalizeLayout(data.draft_layout || data.layout || {})
      Object.assign(layout.canvas, draft.canvas)
      layout.components.splice(0, layout.components.length, ...draft.components)
      await nextTick()
      updateAutoZoom()
      await refreshAllComponentData()
      resetLayoutHistory()
    } finally {
      suppressHistory = false
    }
  }

  const loadTemplates = async () => {
    templateOptions.value = await templateApi.options()
  }

  const normalizeLayout = (value: any): BoardLayout => ({
    canvas: {
      width: Number(value?.canvas?.width || screen.width || 1920),
      height: Number(value?.canvas?.height || screen.height || 1080)
    },
    components: Array.isArray(value?.components)
      ? value.components.map((component: any, index: number) => {
          const type = component.type || 'art-bar-chart'
          const meta = getWidgetMeta(type)

          return {
            id: component.id || `w_${Date.now()}_${index}`,
            type,
            title: typeof component.title === 'string' ? component.title : meta?.name || '组件',
            rect: {
              x: Number(component.rect?.x || 0),
              y: Number(component.rect?.y || 0),
              w: Number(component.rect?.w || 320),
              h: Number(component.rect?.h || 180),
              z: Number(component.rect?.z || 1)
            },
            dataset: normalizeDataset(component.dataset),
            option: normalizeOption(type, component.option)
          }
        })
      : []
  })

  const normalizeDataset = (dataset: any = {}) => {
    const rawColumns = normalizeTableColumns(dataset?.mapping?.tableColumns)
    const rawTableFields = normalizeTableFields(dataset?.mapping?.tableFields)
    const tableFields = rawTableFields.length
      ? rawTableFields
      : rawColumns.map((column) => column.field)

    return {
      queryTemplateId:
        Number(dataset?.queryTemplateId || dataset?.query_template_id || 0) || undefined,
      refresh: normalizeRefresh(dataset?.refresh),
      mapping: {
        labelField: dataset?.mapping?.labelField || dataset?.fieldMap?.label || '',
        valueField: dataset?.mapping?.valueField || dataset?.fieldMap?.value || '',
        tableFields,
        tableColumns: normalizeTableColumns(dataset?.mapping?.tableColumns, tableFields)
      }
    }
  }

  const updateAutoZoom = () => {
    const shell = canvasShellRef.value
    if (!shell) return
    const availableWidth = Math.max(1, shell.clientWidth - 64)
    const availableHeight = Math.max(1, shell.clientHeight - 64)
    const canvasWidth = Math.max(1, Number(layout.canvas.width || 1920))
    const canvasHeight = Math.max(1, Number(layout.canvas.height || 1080))
    const nextZoom = Math.min(1, availableWidth / canvasWidth, availableHeight / canvasHeight)
    autoZoom.value = Number.isFinite(nextZoom) && nextZoom > 0 ? nextZoom : 0.75
  }

  const observeCanvasShell = () => {
    if (!canvasShellRef.value || !('ResizeObserver' in window)) return
    const observer = new ResizeObserver(updateAutoZoom)
    observer.observe(canvasShellRef.value)
    return observer
  }

  const addWidget = (type: string) => {
    const component = createDefaultComponent(type, layout.components.length) as BoardComponent
    ensureDataset(component)
    layout.components.push(component)
    selectedId.value = component.id
  }

  const updateComponent = (component: BoardComponent) => {
    ensureDataset(component)
    const index = layout.components.findIndex((item) => item.id === component.id)
    if (index >= 0) layout.components.splice(index, 1, component)
  }

  const ensureDataset = (component: BoardComponent) => {
    component.dataset = normalizeDataset(component.dataset)
    component.option = normalizeOption(component.type, component.option)
  }

  const normalizeOption = (type: string, option: Record<string, any> = {}) => ({
    ...(getWidgetMeta(type)?.defaultOption || {}),
    ...(option || {})
  })

  const normalizeRefresh = (refresh: unknown) => {
    const seconds = Number(refresh || 30)
    if (!Number.isFinite(seconds) || seconds <= 0) return 30
    return Math.min(maxRefreshSeconds, Math.max(minRefreshSeconds, Math.round(seconds)))
  }

  const normalizeTableFields = (value: unknown): string[] => {
    if (!Array.isArray(value)) return []

    return Array.from(
      new Set(value.map((field) => String(field || '').trim()).filter(Boolean))
    ).slice(0, 8)
  }

  const normalizeTableColumns = (value: unknown, fields: string[] = []): BoardTableColumn[] => {
    const configured = new Map<string, BoardTableColumn>()
    if (Array.isArray(value)) {
      for (const item of value) {
        const field =
          typeof item === 'string'
            ? item.trim()
            : String((item as Record<string, any>)?.field || '').trim()
        if (!field || configured.has(field)) continue
        configured.set(field, {
          field,
          label:
            typeof (item as Record<string, any>)?.label === 'string'
              ? String((item as Record<string, any>).label).trim()
              : '',
          width: normalizeTableColumnWidth((item as Record<string, any>)?.width),
          align: normalizeTableColumnAlign((item as Record<string, any>)?.align)
        })
      }
    }

    const orderedFields = fields.length ? fields : Array.from(configured.keys())
    return orderedFields
      .map((field) => {
        const config = configured.get(field)

        return {
          field,
          label: config?.label || '',
          width: config?.width,
          align: config?.align || 'left'
        }
      })
      .slice(0, 8)
  }

  const normalizeTableColumnWidth = (value: unknown) => {
    if (value === '' || value === null || value === undefined) return undefined
    const width = Number(value)
    if (!Number.isFinite(width) || width <= 0) return undefined
    return Math.min(600, Math.max(60, Math.round(width)))
  }

  const normalizeTableColumnAlign = (value: unknown): NonNullable<BoardTableColumn['align']> =>
    value === 'center' || value === 'right' ? value : 'left'

  const clonePlain = <T,>(value: T): T => JSON.parse(JSON.stringify(value))

  const serializeLayout = () =>
    JSON.stringify({
      canvas: clonePlain(layout.canvas),
      components: clonePlain(layout.components)
    })

  const clearHistoryTimer = () => {
    if (!historyTimer) return
    window.clearTimeout(historyTimer)
    historyTimer = 0
  }

  const resetLayoutHistory = () => {
    clearHistoryTimer()
    layoutHistory.past.splice(0)
    layoutHistory.future.splice(0)
    layoutHistory.current = serializeLayout()
  }

  const recordLayoutHistory = () => {
    if (suppressHistory) return
    const snapshot = serializeLayout()
    if (!layoutHistory.current) {
      layoutHistory.current = snapshot
      return
    }
    if (snapshot === layoutHistory.current) return

    layoutHistory.past.push(layoutHistory.current)
    if (layoutHistory.past.length > historyLimit) layoutHistory.past.shift()
    layoutHistory.current = snapshot
    layoutHistory.future.splice(0)
  }

  const queueLayoutHistory = () => {
    if (suppressHistory) return
    clearHistoryTimer()
    historyTimer = window.setTimeout(() => {
      historyTimer = 0
      recordLayoutHistory()
    }, 180)
  }

  const flushLayoutHistory = () => {
    if (!historyTimer) return
    clearHistoryTimer()
    recordLayoutHistory()
  }

  const restoreLayoutSnapshot = (snapshot: string) => {
    suppressHistory = true
    const nextLayout = normalizeLayout(JSON.parse(snapshot))
    Object.assign(layout.canvas, nextLayout.canvas)
    layout.components.splice(0, layout.components.length, ...nextLayout.components)
    if (!layout.components.some((component) => component.id === selectedId.value)) {
      selectedId.value = ''
    }
    nextTick(async () => {
      updateAutoZoom()
      await refreshAllComponentData()
      layoutHistory.current = serializeLayout()
      suppressHistory = false
    })
  }

  const undoLayout = () => {
    flushLayoutHistory()
    const previous = layoutHistory.past.pop()
    if (!previous) return
    layoutHistory.future.push(layoutHistory.current)
    layoutHistory.current = previous
    restoreLayoutSnapshot(previous)
  }

  const redoLayout = () => {
    const next = layoutHistory.future.pop()
    if (!next) return
    layoutHistory.past.push(layoutHistory.current)
    layoutHistory.current = next
    restoreLayoutSnapshot(next)
  }

  const componentRows = (component: BoardComponent) => {
    if (!componentNeedsData(component)) return []
    if (!component.dataset?.queryTemplateId) return sampleRows
    return previewMap[component.id]?.rows || []
  }

  const componentError = (component: BoardComponent) => previewMap[component.id]?.error || ''

  const refreshComponentData = async (component: BoardComponent) => {
    ensureDataset(component)
    if (!componentNeedsData(component)) {
      delete previewMap[component.id]
      return
    }
    const queryTemplateId = Number(component.dataset.queryTemplateId || 0)
    if (!queryTemplateId) {
      delete previewMap[component.id]
      return
    }

    previewMap[component.id] = {
      rows: previewMap[component.id]?.rows || [],
      error: '',
      loading: true
    }
    try {
      const result = await templateApi.preview({ id: queryTemplateId })
      previewMap[component.id] = {
        rows: result.rows || [],
        error: '',
        loading: false
      }
      syncMappingWithRows(component, result.rows || [])
    } catch (error: any) {
      previewMap[component.id] = {
        rows: previewMap[component.id]?.rows || [],
        error: error?.message || '预览失败',
        loading: false
      }
    }
  }

  const refreshAllComponentData = async () => {
    await Promise.all(layout.components.map((component) => refreshComponentData(component)))
  }

  const onTemplateChange = async () => {
    if (!selectedComponent.value) return
    selectedComponent.value.dataset.mapping = {
      labelField: '',
      valueField: '',
      tableFields: [],
      tableColumns: []
    }
    await refreshComponentData(selectedComponent.value)
  }

  const previewSelectedData = async () => {
    if (!selectedComponent.value) return
    await refreshComponentData(selectedComponent.value)
  }

  const syncMappingWithRows = (component: BoardComponent, rows: Record<string, any>[]) => {
    ensureDataset(component)
    const fields = Object.keys(rows[0] || {})
    if (!fields.length) return
    const mapping = component.dataset.mapping!
    if (mapping.labelField && !fields.includes(mapping.labelField)) mapping.labelField = ''
    if (mapping.valueField && !fields.includes(mapping.valueField)) mapping.valueField = ''
    if (Array.isArray(mapping.tableFields)) {
      mapping.tableFields = mapping.tableFields.filter((field) => fields.includes(field))
    } else {
      mapping.tableFields = []
    }
    mapping.tableColumns = normalizeTableColumns(mapping.tableColumns, mapping.tableFields).filter(
      (column) => fields.includes(column.field)
    )
  }

  const syncSelectedTableColumns = () => {
    if (!selectedComponent.value) return
    syncTableColumnConfigs(selectedComponent.value)
  }

  const syncTableColumnConfigs = (component: BoardComponent) => {
    ensureDataset(component)
    const mapping = component.dataset.mapping!
    mapping.tableFields = normalizeTableFields(mapping.tableFields)
    mapping.tableColumns = normalizeTableColumns(mapping.tableColumns, mapping.tableFields)
  }

  const copySelected = () => {
    if (!selectedComponent.value) return
    copiedComponent.value = clonePlain(selectedComponent.value)
    ElMessage.success('已复制组件')
  }

  const pasteCopied = async () => {
    if (!copiedComponent.value) return
    const component = createPastedComponent(copiedComponent.value)
    ensureDataset(component)
    layout.components.push(component)
    selectedId.value = component.id
    if (componentNeedsData(component) && component.dataset.queryTemplateId) {
      await refreshComponentData(component)
    }
  }

  const createPastedComponent = (source: BoardComponent): BoardComponent => {
    const component = clonePlain(source)
    const maxZ = Math.max(0, ...layout.components.map((item) => Number(item.rect.z || 1)))
    const offset = 24
    const maxX = Math.max(0, Number(layout.canvas.width || 0) - Number(component.rect.w || 0))
    const maxY = Math.max(0, Number(layout.canvas.height || 0) - Number(component.rect.h || 0))

    component.id = `w_${Date.now()}_${Math.floor(Math.random() * 1000)}`
    component.title = component.title ? `${component.title}副本` : component.title
    component.rect.x = Math.min(maxX, Math.max(0, Number(component.rect.x || 0) + offset))
    component.rect.y = Math.min(maxY, Math.max(0, Number(component.rect.y || 0) + offset))
    component.rect.z = maxZ + 1

    return normalizeLayout({ canvas: layout.canvas, components: [component] }).components[0]
  }

  const bringToFront = () => {
    if (!selectedComponent.value) return
    const maxZ = Math.max(0, ...layout.components.map((item) => Number(item.rect.z || 1)))
    selectedComponent.value.rect.z = maxZ + 1
  }

  const removeSelected = () => {
    const index = layout.components.findIndex((item) => item.id === selectedId.value)
    if (index >= 0) layout.components.splice(index, 1)
    selectedId.value = ''
  }

  const saveLayout = async () => {
    await api.update({
      id: screen.id,
      name: screen.name,
      code: screen.code,
      width: layout.canvas.width,
      height: layout.canvas.height,
      bg_config: normalizeBgConfig(screen.bg_config),
      is_public: screen.is_public,
      access_token: screen.access_token,
      status: 2
    })
    await api.saveLayout({ id: screen.id, layout })
    ElMessage.success('保存成功')
    await loadData()
  }

  const publish = async () => {
    await saveLayout()
    await api.publish({ id: screen.id })
    ElMessage.success('发布成功')
    await loadData()
  }

  const back = () => {
    window.location.hash = '#/saiboard/screen'
  }

  onMounted(async () => {
    await Promise.all([loadData(), loadTemplates()])
    canvasResizeObserver = observeCanvasShell()
    updateAutoZoom()
  })

  onBeforeUnmount(() => {
    clearHistoryTimer()
    canvasResizeObserver?.disconnect()
  })

  watch(selectedComponent, (component) => {
    if (component) {
      ensureDataset(component)
      if (component.type === 'data-table') syncTableColumnConfigs(component)
    }
  })

  watch(
    () => selectedComponent.value?.type,
    () => {
      if (selectedComponent.value) {
        ensureDataset(selectedComponent.value)
        if (selectedComponent.value.type === 'data-table') {
          syncTableColumnConfigs(selectedComponent.value)
        }
      }
    }
  )

  watch(layout, queueLayoutHistory, { deep: true })

  watch(() => [layout.canvas.width, layout.canvas.height], updateAutoZoom)
</script>

<style scoped lang="scss">
  .saiboard-editor {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 84px);
    min-height: 640px;
    overflow: hidden;
    background: var(--default-bg-color);
  }

  .editor-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 56px;
    padding: 0 16px;
    background: var(--default-box-color);
    border-bottom: 1px solid var(--default-border);
  }

  .editor-main {
    display: grid;
    flex: 1;
    min-height: 0;
    grid-template-columns: 180px minmax(0, 1fr) 300px;
  }

  .widget-panel,
  .property-panel {
    min-height: 0;
    padding: 14px;
    overflow: auto;
    background: var(--default-box-color);
    border-right: 1px solid var(--default-border);
  }

  .property-panel {
    border-right: 0;
    border-left: 1px solid var(--default-border);
  }

  .panel-title {
    margin-bottom: 12px;
    font-size: 14px;
    font-weight: 600;
  }

  .template-bind-row {
    display: grid;
    width: 100%;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
  }

  .table-column-config {
    display: flex;
    flex-direction: column;
    width: 100%;
    gap: 8px;
  }

  .table-column-config__item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 8px;
    background: var(--default-bg-color);
    border: 1px solid var(--default-border);
    border-radius: 6px;
  }

  .table-column-config__field {
    width: 100%;
    font-size: 12px;
  }

  .table-column-config__controls {
    display: grid;
    grid-template-columns: 80px minmax(0, 1fr);
    gap: 6px;
  }

  .table-column-config__width,
  .table-column-config__align {
    width: 100%;
  }

  .widget-button {
    display: flex;
    align-items: center;
    width: 100%;
    gap: 8px;
    height: 38px;
    padding: 0 10px;
    margin-bottom: 8px;
    color: var(--art-gray-800);
    cursor: pointer;
    background: transparent;
    border: 1px solid var(--default-border);
    border-radius: 6px;
  }

  .canvas-shell {
    min-width: 0;
    min-height: 0;
    overflow: auto;
    background:
      linear-gradient(45deg, rgb(255 255 255 / 4%) 25%, transparent 25%),
      linear-gradient(-45deg, rgb(255 255 255 / 4%) 25%, transparent 25%), #111827;
    background-position:
      0 0,
      0 12px;
    background-size: 24px 24px;
  }

  .canvas {
    position: relative;
    margin: 32px;
    overflow: hidden;
    transform-origin: left top;
    box-shadow: 0 20px 60px rgb(0 0 0 / 32%);
  }
</style>
