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
        <ElButton :disabled="!canGroup" @click="groupSelected">组合</ElButton>
        <ElButton :disabled="!canUngroup" @click="ungroupSelected">取消组合</ElButton>
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
        <div class="panel-title panel-title--layers">图层</div>
        <div class="layer-list">
          <ElText v-if="!layerItems.length" type="info" size="small">暂无组件</ElText>
          <div
            v-for="component in layerItems"
            :key="component.id"
            class="layer-item"
            :class="{ 'is-active': selectedIds.includes(component.id) }"
            @click="selectLayer(component.id, $event)"
          >
            <span class="layer-item__name">{{ component.title || componentName(component) }}</span>
            <span class="layer-item__type">{{ componentName(component) }}</span>
            <span v-if="componentGroupId(component)" class="layer-item__tag">组</span>
            <span class="layer-item__actions">
              <button
                type="button"
                title="上移"
                :disabled="isLayerTop(component)"
                @click.stop="moveLayer(component, 'up')"
              >
                ↑
              </button>
              <button
                type="button"
                title="下移"
                :disabled="isLayerBottom(component)"
                @click.stop="moveLayer(component, 'down')"
              >
                ↓
              </button>
              <button type="button" title="置顶" @click.stop="sendLayerToTop(component)">顶</button>
              <button type="button" title="置底" @click.stop="sendLayerToBottom(component)">
                底
              </button>
            </span>
          </div>
        </div>
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
          @mousedown.self="clearSelection"
        >
          <DraggableItem
            v-for="component in layout.components"
            :key="component.id"
            :component="component"
            :rows="componentRows(component)"
            :error="componentError(component)"
            :selected="selectedIds.includes(component.id)"
            :resizable="selectedComponents.length <= 1"
            @select="selectComponent"
            @resize-start="startComponentResize"
            @update="updateComponent"
          />
          <Vue3DraggableResizable
            v-if="selectionBox"
            :x="selectionBox.x"
            :y="selectionBox.y"
            :w="selectionBox.w"
            :h="selectionBox.h"
            :z="10000"
            :active="true"
            :parent="true"
            :draggable="true"
            :resizable="true"
            :min-w="selectionResizeMin.w"
            :min-h="selectionResizeMin.h"
            class-name="saiboard-selection-box"
            class-name-active="saiboard-selection-box-active"
            @resize-start="startSelectionResize"
            @dragging="dragSelectionBox"
            @resizing="resizeSelectionBox"
            @resize-end="finishSelectionResize"
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
            <template v-if="selectedComponent.type === 'art-dual-bar-compare-chart'">
              <ElFormItem label="正向字段">
                <ElSelect
                  v-model="selectedComponent.option!.positiveField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!numericFieldOptions.length"
                >
                  <ElOption
                    v-for="field in numericFieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="负向字段">
                <ElSelect
                  v-model="selectedComponent.option!.negativeField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!numericFieldOptions.length"
                >
                  <ElOption
                    v-for="field in numericFieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="正向名称">
                <ElInput v-model="selectedComponent.option!.positiveName" />
              </ElFormItem>
              <ElFormItem label="负向名称">
                <ElInput v-model="selectedComponent.option!.negativeName" />
              </ElFormItem>
              <ElFormItem label="显示图例">
                <ElSwitch v-model="selectedComponent.option!.showLegend" />
              </ElFormItem>
              <ElFormItem label="数值标签">
                <ElSwitch v-model="selectedComponent.option!.showDataLabel" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'image-carousel'">
              <ElFormItem label="图片字段">
                <ElSelect
                  v-model="selectedComponent.option!.imageField"
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
              <ElFormItem label="标题字段">
                <ElSelect
                  v-model="selectedComponent.option!.titleField"
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
              <ElFormItem label="切换毫秒">
                <ElInputNumber
                  v-model="selectedComponent.option!.interval"
                  :min="1000"
                  :max="60000"
                  :step="500"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="图片适配">
                <ElSelect v-model="selectedComponent.option!.imageFit">
                  <ElOption
                    v-for="item in imageFitOptions"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="指示点">
                <ElSwitch v-model="selectedComponent.option!.showDots" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'geo-point-map'">
              <ElFormItem label="经度字段">
                <ElSelect
                  v-model="selectedComponent.option!.lngField"
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
              <ElFormItem label="纬度字段">
                <ElSelect
                  v-model="selectedComponent.option!.latField"
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
              <ElFormItem label="名称字段">
                <ElSelect
                  v-model="selectedComponent.option!.nameField"
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
              <ElFormItem label="数值字段">
                <ElSelect
                  v-model="selectedComponent.option!.valueField"
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
              <ElFormItem label="地图范围">
                <ElSelect v-model="selectedComponent.option!.region">
                  <ElOption
                    v-for="item in mapRegionOptions"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="点大小">
                <ElInputNumber
                  v-model="selectedComponent.option!.pointSize"
                  :min="6"
                  :max="28"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="点标签">
                <ElSwitch v-model="selectedComponent.option!.showLabel" />
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
            <template v-if="selectedComponent.type === 'decor-scanline'">
              <ElFormItem label="方向">
                <ElSelect v-model="selectedComponent.option!.direction">
                  <ElOption label="横向" value="horizontal" />
                  <ElOption label="纵向" value="vertical" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="强调色">
                <ElColorPicker v-model="selectedComponent.option!.accent" />
              </ElFormItem>
              <ElFormItem label="速度秒">
                <ElInputNumber
                  v-model="selectedComponent.option!.speed"
                  :min="1"
                  :max="12"
                  :step="0.5"
                  step-strictly
                />
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
                <ElButton @click="sendSelectedToBottom">置底</ElButton>
                <ElButton type="danger" @click="removeSelected">删除</ElButton>
              </ElSpace>
            </ElFormItem>
          </ElForm>
        </template>
        <template v-else-if="selectedComponents.length > 1">
          <div class="panel-title">批量操作</div>
          <ElText type="info" size="small"> 已选择 {{ selectedComponents.length }} 个组件 </ElText>
          <ElSpace class="bulk-actions" direction="vertical" alignment="stretch">
            <div class="bulk-actions__section">
              <ElText type="info" size="small">编组</ElText>
              <div class="bulk-action-grid bulk-action-grid--two">
                <ElButton size="small" :disabled="!canGroup" @click="groupSelected">组合</ElButton>
                <ElButton size="small" :disabled="!canUngroup" @click="ungroupSelected">
                  取消组合
                </ElButton>
              </div>
            </div>
            <div class="bulk-actions__section">
              <ElText type="info" size="small">对齐</ElText>
              <div class="bulk-action-grid">
                <ElButton size="small" @click="alignSelected('left')">左</ElButton>
                <ElButton size="small" @click="alignSelected('center')">水平中</ElButton>
                <ElButton size="small" @click="alignSelected('right')">右</ElButton>
                <ElButton size="small" @click="alignSelected('top')">上</ElButton>
                <ElButton size="small" @click="alignSelected('middle')">垂直中</ElButton>
                <ElButton size="small" @click="alignSelected('bottom')">下</ElButton>
              </div>
            </div>
            <div class="bulk-actions__section">
              <ElText type="info" size="small">分布</ElText>
              <div class="bulk-action-grid">
                <ElButton
                  size="small"
                  :disabled="selectedComponents.length < 3"
                  @click="distributeSelected('horizontal')"
                >
                  水平
                </ElButton>
                <ElButton
                  size="small"
                  :disabled="selectedComponents.length < 3"
                  @click="distributeSelected('vertical')"
                >
                  垂直
                </ElButton>
              </div>
            </div>
            <ElButton @click="bringToFront">批量置顶</ElButton>
            <ElButton @click="sendSelectedToBottom">批量置底</ElButton>
            <ElButton type="danger" @click="removeSelected">批量删除</ElButton>
          </ElSpace>
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
  import Vue3DraggableResizable from 'vue3-draggable-resizable'
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
  import type { BoardComponent, BoardLayout, BoardRect, BoardTableColumn } from '../widgets/types'

  interface PreviewState {
    rows: Record<string, any>[]
    error: string
    loading: boolean
  }

  interface DragPayload {
    x: number
    y: number
  }

  interface ResizePayload extends DragPayload {
    w: number
    h: number
  }

  interface SelectionBounds {
    left: number
    top: number
    right: number
    bottom: number
    width: number
    height: number
    centerX: number
    centerY: number
  }

  interface SelectionResizeState {
    bounds: SelectionBounds
    components: Array<{
      id: string
      rect: BoardRect
    }>
  }

  interface LayoutHistoryState {
    past: string[]
    future: string[]
    current: string
  }

  type AlignMode = 'left' | 'center' | 'right' | 'top' | 'middle' | 'bottom'
  type DistributeAxis = 'horizontal' | 'vertical'

  const route = useRoute()
  const zoom = ref<'auto' | number>('auto')
  const autoZoom = ref(0.75)
  const canvasShellRef = ref<HTMLElement>()
  const selectedId = ref('')
  const selectedIds = ref<string[]>([])
  const copiedComponents = ref<BoardComponent[]>([])
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
  let selectionResizeState: SelectionResizeState | undefined
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
  const imageFitOptions = [
    { label: '裁切铺满', value: 'cover' },
    { label: '完整显示', value: 'contain' },
    { label: '拉伸填满', value: 'fill' }
  ]
  const mapRegionOptions = [
    { label: '中国', value: 'china' },
    { label: '世界', value: 'world' }
  ]
  const tableAlignOptions: Array<{ label: string; value: NonNullable<BoardTableColumn['align']> }> =
    [
      { label: '左对齐', value: 'left' },
      { label: '居中', value: 'center' },
      { label: '右对齐', value: 'right' }
    ]
  const minComponentWidth = 120
  const minComponentHeight = 80
  const minRefreshSeconds = 10
  const maxRefreshSeconds = 3600
  const fieldMappingTypes = new Set([
    'art-bar-chart',
    'art-line-chart',
    'art-h-bar-chart',
    'art-dual-bar-compare-chart',
    'art-ring-chart',
    'art-radar-chart',
    'art-scatter-chart',
    'stat-number',
    'data-table',
    'image-carousel',
    'geo-point-map'
  ])
  const decorTypes = new Set(['decor-border', 'decor-scanline'])
  const componentNeedsData = (component: BoardComponent) => !decorTypes.has(component.type)
  const componentGroupId = (component?: BoardComponent) => String(component?.option?.groupId || '')
  const createGroupId = () => `g_${Date.now()}_${Math.floor(Math.random() * 1000)}`

  const selectedComponents = computed(() =>
    layout.components.filter((item) => selectedIds.value.includes(item.id))
  )
  const selectionBox = computed(() => {
    if (selectedComponents.value.length < 2) return undefined
    const bounds = selectedBounds()
    if (!bounds) return undefined

    return {
      x: bounds.left,
      y: bounds.top,
      w: Math.max(1, bounds.width),
      h: Math.max(1, bounds.height)
    }
  })
  const selectionResizeMin = computed(() => {
    const bounds = selectedBounds()
    if (!bounds) return { w: minComponentWidth, h: minComponentHeight }
    const minScaleX = minSelectionScale('x')
    const minScaleY = minSelectionScale('y')

    return {
      w: Math.max(minComponentWidth, Math.round(bounds.width * minScaleX)),
      h: Math.max(minComponentHeight, Math.round(bounds.height * minScaleY))
    }
  })
  const selectedComponent = computed(() =>
    selectedIds.value.length <= 1
      ? layout.components.find((item) => item.id === selectedId.value)
      : undefined
  )
  const layerItems = computed(() => orderedLayerComponents().slice().reverse())
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
  const canCopy = computed(() => selectedComponents.value.length > 0)
  const canPaste = computed(() => copiedComponents.value.length > 0)
  const canGroup = computed(
    () => selectedComponents.value.length > 1 && !selectedComponentsInSingleGroup()
  )
  const canUngroup = computed(() =>
    selectedComponents.value.some((component) => Boolean(componentGroupId(component)))
  )
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
    setSingleSelection(component.id)
  }

  const setSingleSelection = (id: string) => {
    selectedId.value = id
    selectedIds.value = id ? [id] : []
  }

  const clearSelection = () => {
    selectedId.value = ''
    selectedIds.value = []
    selectionResizeState = undefined
  }

  const selectionIdsForComponent = (id: string) => {
    const component = layout.components.find((item) => item.id === id)
    const groupId = componentGroupId(component)
    if (!groupId) return [id]

    return layout.components
      .filter((item) => componentGroupId(item) === groupId)
      .map((item) => item.id)
  }

  const selectedComponentsInSingleGroup = () => {
    const components = selectedComponents.value
    if (components.length < 2) return false
    const groupIds = components.map((component) => componentGroupId(component))

    return groupIds.every(Boolean) && new Set(groupIds).size === 1
  }

  const selectComponent = (id: string, additive = false) => {
    const targetIds = selectionIdsForComponent(id)
    if (!additive) {
      selectedIds.value = targetIds
      selectedId.value = targetIds[targetIds.length - 1] || ''
      return
    }

    const exists = targetIds.every((targetId) => selectedIds.value.includes(targetId))
    selectedIds.value = exists
      ? selectedIds.value.filter((item) => !targetIds.includes(item))
      : Array.from(new Set([...selectedIds.value, ...targetIds]))
    selectedId.value = exists
      ? selectedIds.value[selectedIds.value.length - 1] || ''
      : targetIds[targetIds.length - 1] || ''
  }

  const updateComponent = (component: BoardComponent) => {
    ensureDataset(component)
    const index = layout.components.findIndex((item) => item.id === component.id)
    if (index < 0) return

    const current = layout.components[index]
    const deltaX = Number(component.rect.x || 0) - Number(current.rect.x || 0)
    const deltaY = Number(component.rect.y || 0) - Number(current.rect.y || 0)
    const isPositionOnlyUpdate =
      Number(component.rect.w || 0) === Number(current.rect.w || 0) &&
      Number(component.rect.h || 0) === Number(current.rect.h || 0)
    const shouldMoveSelection =
      isPositionOnlyUpdate &&
      selectedIds.value.includes(component.id) &&
      selectedComponents.value.length > 1 &&
      (deltaX !== 0 || deltaY !== 0)

    if (shouldMoveSelection) {
      moveSelectedBy(deltaX, deltaY)
      return
    }

    layout.components.splice(index, 1, component)
  }

  const startComponentResize = () => {
    selectionResizeState = undefined
  }

  const startSelectionResize = () => {
    const bounds = selectedBounds()
    if (!bounds || selectedComponents.value.length < 2) return
    flushLayoutHistory()
    selectionResizeState = {
      bounds,
      components: selectedComponents.value.map((component) => ({
        id: component.id,
        rect: clonePlain(component.rect)
      }))
    }
    suppressHistory = true
  }

  const finishSelectionResize = () => {
    if (!selectionResizeState) return
    selectionResizeState = undefined
    suppressHistory = false
    recordLayoutHistory()
  }

  const dragSelectionBox = (payload: DragPayload) => {
    const bounds = selectedBounds()
    if (!bounds) return
    moveSelectedBy(Number(payload.x || 0) - bounds.left, Number(payload.y || 0) - bounds.top)
  }

  const resizeSelectionBox = (payload: ResizePayload) => {
    if (!selectionResizeState) startSelectionResize()
    if (!selectionResizeState) return
    const bounds = normalizeSelectionResizeBounds(selectionResizeState, payload)
    const scaleX = bounds.width / selectionResizeState.bounds.width
    const scaleY = bounds.height / selectionResizeState.bounds.height

    for (const item of selectionResizeState.components) {
      const component = layout.components.find((target) => target.id === item.id)
      if (!component) continue
      component.rect.x = Math.round(
        bounds.left + (item.rect.x - selectionResizeState.bounds.left) * scaleX
      )
      component.rect.y = Math.round(
        bounds.top + (item.rect.y - selectionResizeState.bounds.top) * scaleY
      )
      component.rect.w = Math.max(minComponentWidth, Math.round(item.rect.w * scaleX))
      component.rect.h = Math.max(minComponentHeight, Math.round(item.rect.h * scaleY))
      clampComponentRect(component)
    }
  }

  const normalizeSelectionResizeBounds = (
    state: SelectionResizeState,
    payload: ResizePayload
  ): SelectionBounds => {
    const canvasWidth = Math.max(1, Number(layout.canvas.width || 0))
    const canvasHeight = Math.max(1, Number(layout.canvas.height || 0))
    const rawLeft = Math.round(Number(payload.x || 0))
    const rawTop = Math.round(Number(payload.y || 0))
    const rawWidth = Math.round(Number(payload.w || state.bounds.width))
    const rawHeight = Math.round(Number(payload.h || state.bounds.height))
    const rawRight = rawLeft + rawWidth
    const rawBottom = rawTop + rawHeight
    const minWidth = Math.round(state.bounds.width * minSelectionScale('x', state.components))
    const minHeight = Math.round(state.bounds.height * minSelectionScale('y', state.components))
    const width = Math.min(canvasWidth, Math.max(minComponentWidth, minWidth, rawWidth))
    const height = Math.min(canvasHeight, Math.max(minComponentHeight, minHeight, rawHeight))
    const leftMoved = rawLeft !== state.bounds.left
    const rightMoved = rawRight !== state.bounds.right
    const topMoved = rawTop !== state.bounds.top
    const bottomMoved = rawBottom !== state.bounds.bottom
    const maxLeft = Math.max(0, canvasWidth - width)
    const maxTop = Math.max(0, canvasHeight - height)
    const left = clampNumber(leftMoved && !rightMoved ? rawRight - width : rawLeft, 0, maxLeft)
    const top = clampNumber(topMoved && !bottomMoved ? rawBottom - height : rawTop, 0, maxTop)

    return createSelectionBounds(left, top, left + width, top + height)
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
    pruneSelection()
    nextTick(async () => {
      updateAutoZoom()
      await refreshAllComponentData()
      layoutHistory.current = serializeLayout()
      suppressHistory = false
    })
  }

  const pruneSelection = () => {
    const ids = new Set(layout.components.map((component) => component.id))
    selectedIds.value = selectedIds.value.filter((id) => ids.has(id))
    if (!ids.has(selectedId.value)) {
      selectedId.value = selectedIds.value[selectedIds.value.length - 1] || ''
    }
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
    if (selectedComponent.value.type === 'art-dual-bar-compare-chart') {
      selectedComponent.value.option!.positiveField = ''
      selectedComponent.value.option!.negativeField = ''
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
    if (component.type === 'art-dual-bar-compare-chart') {
      if (component.option?.positiveField && !fields.includes(component.option.positiveField)) {
        component.option.positiveField = ''
      }
      if (component.option?.negativeField && !fields.includes(component.option.negativeField)) {
        component.option.negativeField = ''
      }
    }
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
    if (!selectedComponents.value.length) return
    copiedComponents.value = selectedComponents.value.map((component) => clonePlain(component))
    ElMessage.success(`已复制 ${copiedComponents.value.length} 个组件`)
  }

  const pasteCopied = async () => {
    if (!copiedComponents.value.length) return
    const groupIdMap = new Map<string, string>()
    const components = copiedComponents.value.map((component, index) =>
      createPastedComponent(component, index, groupIdMap)
    )
    components.forEach(ensureDataset)
    layout.components.push(...components)
    selectedIds.value = components.map((component) => component.id)
    selectedId.value = components[components.length - 1]?.id || ''
    await Promise.all(
      components
        .filter((component) => componentNeedsData(component) && component.dataset.queryTemplateId)
        .map((component) => refreshComponentData(component))
    )
  }

  const createPastedComponent = (
    source: BoardComponent,
    index = 0,
    groupIdMap: Map<string, string> = new Map()
  ): BoardComponent => {
    const component = clonePlain(source)
    const maxZ = Math.max(0, ...layout.components.map((item) => Number(item.rect.z || 1)))
    const offset = 24 + index * 12
    const maxX = Math.max(0, Number(layout.canvas.width || 0) - Number(component.rect.w || 0))
    const maxY = Math.max(0, Number(layout.canvas.height || 0) - Number(component.rect.h || 0))
    const groupId = componentGroupId(component)

    component.id = `w_${Date.now()}_${Math.floor(Math.random() * 1000)}`
    component.title = component.title ? `${component.title}副本` : component.title
    component.rect.x = Math.min(maxX, Math.max(0, Number(component.rect.x || 0) + offset))
    component.rect.y = Math.min(maxY, Math.max(0, Number(component.rect.y || 0) + offset))
    component.rect.z = maxZ + index + 1
    if (groupId) {
      if (!groupIdMap.has(groupId)) groupIdMap.set(groupId, createGroupId())
      component.option = { ...(component.option || {}), groupId: groupIdMap.get(groupId) }
    }

    return normalizeLayout({ canvas: layout.canvas, components: [component] }).components[0]
  }

  const groupSelected = () => {
    if (!canGroup.value) return
    flushLayoutHistory()
    const groupId = createGroupId()
    selectedComponents.value.forEach((component) => {
      component.option = { ...(component.option || {}), groupId }
    })
    recordLayoutHistory()
  }

  const ungroupSelected = () => {
    if (!canUngroup.value) return
    flushLayoutHistory()
    selectedComponents.value.forEach((component) => {
      if (!component.option) return
      delete component.option.groupId
    })
    recordLayoutHistory()
  }

  const componentName = (component: BoardComponent) =>
    getWidgetMeta(component.type)?.name || component.type || '组件'

  const selectLayer = (id: string, event?: MouseEvent) => {
    selectComponent(id, Boolean(event?.shiftKey || event?.metaKey || event?.ctrlKey))
  }

  const orderedLayerComponents = () =>
    layout.components.slice().sort((a, b) => {
      const diff = Number(a.rect.z || 1) - Number(b.rect.z || 1)
      if (diff !== 0) return diff
      return layout.components.indexOf(a) - layout.components.indexOf(b)
    })

  const normalizeLayerOrder = (ordered: BoardComponent[]) => {
    ordered.forEach((component, index) => {
      component.rect.z = index + 1
    })
  }

  const normalizedLayoutForSave = (): BoardLayout => {
    const payloadLayout = clonePlain(layout)
    const ordered = payloadLayout.components.slice().sort((a, b) => {
      const diff = Number(a.rect.z || 1) - Number(b.rect.z || 1)
      if (diff !== 0) return diff
      return payloadLayout.components.indexOf(a) - payloadLayout.components.indexOf(b)
    })
    ordered.forEach((component, index) => {
      component.rect.z = index + 1
    })

    return payloadLayout
  }

  const isLayerTop = (component: BoardComponent) => {
    const ordered = orderedLayerComponents()
    const selected = new Set(selectionIdsForComponent(component.id))
    const positions = ordered
      .map((item, index) => (selected.has(item.id) ? index : -1))
      .filter((index) => index >= 0)
    if (!positions.length) return true
    const topIndex = Math.max(...positions)

    return !ordered.slice(topIndex + 1).some((item) => !selected.has(item.id))
  }

  const isLayerBottom = (component: BoardComponent) => {
    const ordered = orderedLayerComponents()
    const selected = new Set(selectionIdsForComponent(component.id))
    const positions = ordered
      .map((item, index) => (selected.has(item.id) ? index : -1))
      .filter((index) => index >= 0)
    if (!positions.length) return true
    const bottomIndex = Math.min(...positions)

    return !ordered.slice(0, bottomIndex).some((item) => !selected.has(item.id))
  }

  const moveLayer = (component: BoardComponent, direction: 'up' | 'down') => {
    flushLayoutHistory()
    const ordered = orderedLayerComponents()
    const moveIds = selectionIdsForComponent(component.id)
    const moveSet = new Set(moveIds)
    if (moveSet.size <= 1) {
      const index = ordered.findIndex((item) => item.id === component.id)
      const targetIndex = direction === 'up' ? index + 1 : index - 1
      if (index < 0 || targetIndex < 0 || targetIndex >= ordered.length) {
        selectComponent(component.id)
        return
      }
      ;[ordered[index], ordered[targetIndex]] = [ordered[targetIndex], ordered[index]]
      normalizeLayerOrder(ordered)
      selectComponent(component.id)
      recordLayoutHistory()
      return
    }

    const nextOrdered = moveLayerBlock(ordered, moveSet, direction)
    if (!nextOrdered) {
      selectComponent(component.id)
      return
    }
    normalizeLayerOrder(nextOrdered)
    selectComponent(component.id)
    recordLayoutHistory()
  }

  const moveLayerBlock = (
    ordered: BoardComponent[],
    moveSet: Set<string>,
    direction: 'up' | 'down'
  ) => {
    const positions = ordered
      .map((item, index) => (moveSet.has(item.id) ? index : -1))
      .filter((index) => index >= 0)
    if (!positions.length) return undefined

    const moving = ordered.filter((item) => moveSet.has(item.id))
    const others = ordered.filter((item) => !moveSet.has(item.id))
    if (direction === 'up') {
      const topIndex = Math.max(...positions)
      const next = ordered.slice(topIndex + 1).find((item) => !moveSet.has(item.id))
      if (!next) return undefined
      const insertIndex = others.findIndex((item) => item.id === next.id) + 1
      others.splice(insertIndex, 0, ...moving)
      return others
    }

    const bottomIndex = Math.min(...positions)
    const previous = ordered
      .slice(0, bottomIndex)
      .reverse()
      .find((item) => !moveSet.has(item.id))
    if (!previous) return undefined
    const insertIndex = others.findIndex((item) => item.id === previous.id)
    others.splice(insertIndex, 0, ...moving)

    return others
  }

  const sendLayerGroupToEdge = (component: BoardComponent, edge: 'top' | 'bottom') => {
    flushLayoutHistory()
    const selected = new Set(selectionIdsForComponent(component.id))
    const ordered = orderedLayerComponents()
    const moving = ordered.filter((item) => selected.has(item.id))
    if (!moving.length) return
    const rest = ordered.filter((item) => !selected.has(item.id))
    const nextOrdered = edge === 'top' ? [...rest, ...moving] : [...moving, ...rest]
    normalizeLayerOrder(nextOrdered)
    selectComponent(component.id)
    recordLayoutHistory()
  }

  const sendLayerToTop = (component: BoardComponent) => {
    if (componentGroupId(component)) {
      sendLayerGroupToEdge(component, 'top')
      return
    }
    flushLayoutHistory()
    const ordered = orderedLayerComponents().filter((item) => item.id !== component.id)
    ordered.push(component)
    normalizeLayerOrder(ordered)
    selectComponent(component.id)
    recordLayoutHistory()
  }

  const sendLayerToBottom = (component: BoardComponent) => {
    if (componentGroupId(component)) {
      sendLayerGroupToEdge(component, 'bottom')
      return
    }
    flushLayoutHistory()
    const ordered = orderedLayerComponents().filter((item) => item.id !== component.id)
    ordered.unshift(component)
    normalizeLayerOrder(ordered)
    selectComponent(component.id)
    recordLayoutHistory()
  }

  const bringToFront = () => {
    if (!selectedComponents.value.length) return
    if (selectedComponents.value.length === 1) {
      sendLayerToTop(selectedComponents.value[0])
      return
    }
    flushLayoutHistory()
    const selected = new Set(selectedIds.value)
    const ordered = orderedLayerComponents()
    normalizeLayerOrder([
      ...ordered.filter((component) => !selected.has(component.id)),
      ...ordered.filter((component) => selected.has(component.id))
    ])
    recordLayoutHistory()
  }

  const sendSelectedToBottom = () => {
    if (!selectedComponents.value.length) return
    if (selectedComponents.value.length === 1) {
      sendLayerToBottom(selectedComponents.value[0])
      return
    }
    flushLayoutHistory()
    const selected = new Set(selectedIds.value)
    const ordered = orderedLayerComponents()
    normalizeLayerOrder([
      ...ordered.filter((component) => selected.has(component.id)),
      ...ordered.filter((component) => !selected.has(component.id))
    ])
    recordLayoutHistory()
  }

  const moveSelectedBy = (deltaX: number, deltaY: number) => {
    const selected = new Set(selectedIds.value)
    const delta = clampSelectedDelta(deltaX, deltaY)
    if (delta.x === 0 && delta.y === 0) return

    for (const component of layout.components) {
      if (!selected.has(component.id)) continue
      component.rect.x += delta.x
      component.rect.y += delta.y
      clampComponentRect(component)
    }
  }

  const clampSelectedDelta = (deltaX: number, deltaY: number) => {
    const components = selectedComponents.value
    if (!components.length) return { x: 0, y: 0 }

    const minDeltaX = Math.max(...components.map((component) => -Number(component.rect.x || 0)))
    const maxDeltaX = Math.min(
      ...components.map(
        (component) =>
          Math.max(0, Number(layout.canvas.width || 0) - Number(component.rect.w || 0)) -
          Number(component.rect.x || 0)
      )
    )
    const minDeltaY = Math.max(...components.map((component) => -Number(component.rect.y || 0)))
    const maxDeltaY = Math.min(
      ...components.map(
        (component) =>
          Math.max(0, Number(layout.canvas.height || 0) - Number(component.rect.h || 0)) -
          Number(component.rect.y || 0)
      )
    )

    return {
      x: clampNumber(Math.round(deltaX), minDeltaX, maxDeltaX),
      y: clampNumber(Math.round(deltaY), minDeltaY, maxDeltaY)
    }
  }

  const alignSelected = (mode: AlignMode) => {
    if (selectedComponents.value.length < 2) return
    flushLayoutHistory()
    const bounds = selectedBounds()
    if (!bounds) return

    for (const component of selectedComponents.value) {
      if (mode === 'left') {
        component.rect.x = bounds.left
      } else if (mode === 'center') {
        component.rect.x = Math.round(bounds.centerX - component.rect.w / 2)
      } else if (mode === 'right') {
        component.rect.x = bounds.right - component.rect.w
      } else if (mode === 'top') {
        component.rect.y = bounds.top
      } else if (mode === 'middle') {
        component.rect.y = Math.round(bounds.centerY - component.rect.h / 2)
      } else if (mode === 'bottom') {
        component.rect.y = bounds.bottom - component.rect.h
      }
      clampComponentRect(component)
    }

    recordLayoutHistory()
  }

  const distributeSelected = (axis: DistributeAxis) => {
    if (selectedComponents.value.length < 3) return
    flushLayoutHistory()
    const sorted = selectedComponents.value.slice().sort((a, b) => {
      const aCenter = axis === 'horizontal' ? a.rect.x + a.rect.w / 2 : a.rect.y + a.rect.h / 2
      const bCenter = axis === 'horizontal' ? b.rect.x + b.rect.w / 2 : b.rect.y + b.rect.h / 2
      return aCenter - bCenter
    })
    const first = sorted[0]
    const last = sorted[sorted.length - 1]
    const firstCenter =
      axis === 'horizontal' ? first.rect.x + first.rect.w / 2 : first.rect.y + first.rect.h / 2
    const lastCenter =
      axis === 'horizontal' ? last.rect.x + last.rect.w / 2 : last.rect.y + last.rect.h / 2
    const gap = (lastCenter - firstCenter) / (sorted.length - 1)

    sorted.slice(1, -1).forEach((component, middleIndex) => {
      const index = middleIndex + 1
      const center = firstCenter + gap * index
      if (axis === 'horizontal') {
        component.rect.x = Math.round(center - component.rect.w / 2)
      } else {
        component.rect.y = Math.round(center - component.rect.h / 2)
      }
      clampComponentRect(component)
    })
    recordLayoutHistory()
  }

  const selectedBounds = (): SelectionBounds | undefined => {
    const components = selectedComponents.value
    if (!components.length) return undefined
    const left = Math.min(...components.map((component) => component.rect.x))
    const top = Math.min(...components.map((component) => component.rect.y))
    const right = Math.max(...components.map((component) => component.rect.x + component.rect.w))
    const bottom = Math.max(...components.map((component) => component.rect.y + component.rect.h))

    return createSelectionBounds(left, top, right, bottom)
  }

  const createSelectionBounds = (
    left: number,
    top: number,
    right: number,
    bottom: number
  ): SelectionBounds => {
    const width = Math.max(1, Math.round(right - left))
    const height = Math.max(1, Math.round(bottom - top))

    return {
      left: Math.round(left),
      top: Math.round(top),
      right: Math.round(left) + width,
      bottom: Math.round(top) + height,
      width,
      height,
      centerX: Math.round(left) + width / 2,
      centerY: Math.round(top) + height / 2
    }
  }

  const minSelectionScale = (
    axis: 'x' | 'y',
    components: Array<{ rect: BoardRect }> = selectedComponents.value
  ) => {
    if (!components.length) return 1
    const minSize = axis === 'x' ? minComponentWidth : minComponentHeight
    const field = axis === 'x' ? 'w' : 'h'

    return Math.max(
      0,
      ...components.map((component) => {
        const size = Number(component.rect[field] || minSize)
        if (size <= 0) return 1
        return minSize / size
      })
    )
  }

  const clampComponentRect = (component: BoardComponent) => {
    const maxX = Math.max(0, Number(layout.canvas.width || 0) - Number(component.rect.w || 0))
    const maxY = Math.max(0, Number(layout.canvas.height || 0) - Number(component.rect.h || 0))
    component.rect.x = Math.min(maxX, Math.max(0, Math.round(component.rect.x)))
    component.rect.y = Math.min(maxY, Math.max(0, Math.round(component.rect.y)))
  }

  const clampNumber = (value: number, min: number, max: number) =>
    Math.min(max, Math.max(min, value))

  const removeSelected = () => {
    if (!selectedIds.value.length) return
    flushLayoutHistory()
    const selected = new Set(selectedIds.value)
    for (let index = layout.components.length - 1; index >= 0; index--) {
      if (selected.has(layout.components[index].id)) {
        delete previewMap[layout.components[index].id]
        layout.components.splice(index, 1)
      }
    }
    clearSelection()
    recordLayoutHistory()
  }

  const saveLayout = async () => {
    const payloadLayout = normalizedLayoutForSave()
    await api.update({
      id: screen.id,
      name: screen.name,
      code: screen.code,
      width: payloadLayout.canvas.width,
      height: payloadLayout.canvas.height,
      bg_config: normalizeBgConfig(screen.bg_config),
      is_public: screen.is_public,
      access_token: screen.access_token,
      status: 2
    })
    await api.saveLayout({ id: screen.id, layout: payloadLayout })
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

  .bulk-actions {
    width: 100%;
    margin-top: 12px;
  }

  .bulk-actions__section {
    display: flex;
    flex-direction: column;
    width: 100%;
    gap: 8px;
  }

  .bulk-action-grid {
    display: grid;
    width: 100%;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
  }

  .bulk-action-grid--two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .bulk-action-grid :deep(.el-button) {
    width: 100%;
    margin-left: 0;
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

  .panel-title--layers {
    padding-top: 8px;
    margin-top: 10px;
    border-top: 1px solid var(--default-border);
  }

  .layer-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .layer-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 4px 8px;
    padding: 8px;
    cursor: pointer;
    border: 1px solid var(--default-border);
    border-radius: 6px;
  }

  .layer-item.is-active {
    color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
    border-color: var(--el-color-primary-light-5);
  }

  .layer-item__name,
  .layer-item__type {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .layer-item__name {
    font-size: 13px;
    font-weight: 600;
  }

  .layer-item__type {
    grid-column: 1;
    font-size: 12px;
    color: var(--art-gray-500);
  }

  .layer-item__tag {
    grid-row: 1 / span 2;
    grid-column: 2;
    align-self: center;
    padding: 1px 5px;
    font-size: 11px;
    color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
    border: 1px solid var(--el-color-primary-light-5);
    border-radius: 4px;
  }

  .layer-item__actions {
    display: grid;
    grid-row: 1 / span 2;
    grid-column: 3;
    grid-template-columns: repeat(2, 24px);
    gap: 4px;
    align-self: center;
  }

  .layer-item__actions button {
    width: 24px;
    height: 22px;
    padding: 0;
    font-size: 12px;
    color: var(--art-gray-700);
    cursor: pointer;
    background: var(--default-box-color);
    border: 1px solid var(--default-border);
    border-radius: 4px;
  }

  .layer-item__actions button:disabled {
    cursor: not-allowed;
    opacity: 0.42;
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

  :global(.saiboard-selection-box) {
    position: absolute;
    background: rgb(78 161 255 / 6%);
    border: 1px dashed var(--el-color-primary);
  }

  :global(.saiboard-selection-box-active) {
    outline: none;
    border: 1px dashed var(--el-color-primary);
  }
</style>
