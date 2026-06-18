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
        <ElSelect v-model="zoom" style="width: 110px">
          <ElOption label="50%" :value="0.5" />
          <ElOption label="75%" :value="0.75" />
          <ElOption label="100%" :value="1" />
        </ElSelect>
        <ElButton type="primary" @click="saveLayout">保存</ElButton>
        <ElButton type="success" @click="publish">发布</ElButton>
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

      <main class="canvas-shell">
        <div
          class="canvas"
          :style="{
            width: layout.canvas.width + 'px',
            height: layout.canvas.height + 'px',
            transform: `scale(${zoom})`,
            background: screen.bg_config?.color || '#07111f'
          }"
          @mousedown.self="selectedId = ''"
        >
          <DraggableItem
            v-for="component in layout.components"
            :key="component.id"
            :component="component"
            :rows="sampleRows"
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
            <ElFormItem label="查询模板">
              <ElSelect v-model="selectedComponent.dataset.queryTemplateId" clearable filterable>
                <ElOption
                  v-for="item in templateOptions"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem label="刷新秒">
              <ElInputNumber v-model="selectedComponent.dataset.refresh" :min="5" :max="3600" />
            </ElFormItem>
            <ElFormItem label="位置">
              <ElSpace wrap>
                <ElInputNumber v-model="selectedComponent.rect.x" :min="0" :max="layout.canvas.width" />
                <ElInputNumber v-model="selectedComponent.rect.y" :min="0" :max="layout.canvas.height" />
              </ElSpace>
            </ElFormItem>
            <ElFormItem label="尺寸">
              <ElSpace wrap>
                <ElInputNumber v-model="selectedComponent.rect.w" :min="120" :max="layout.canvas.width" />
                <ElInputNumber v-model="selectedComponent.rect.h" :min="80" :max="layout.canvas.height" />
              </ElSpace>
            </ElFormItem>
            <ElFormItem label="层级">
              <ElInputNumber v-model="selectedComponent.rect.z" :min="1" :max="999" />
            </ElFormItem>
            <ElFormItem label="单位" v-if="selectedComponent.type === 'stat-number'">
              <ElInput v-model="selectedComponent.option!.unit" />
            </ElFormItem>
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
  import { createDefaultComponent, widgetRegistry } from '../widgets/registry'
  import type { BoardComponent, BoardLayout } from '../widgets/types'

  const route = useRoute()
  const zoom = ref(0.75)
  const selectedId = ref('')
  const templateOptions = ref<any[]>([])
  const screen = reactive<any>({
    id: 0,
    name: '',
    status: 2,
    bg_config: { color: '#07111f' }
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

  const selectedComponent = computed(() => layout.components.find((item) => item.id === selectedId.value))

  const loadData = async () => {
    const id = Number(route.params.id)
    const data = await api.read(id)
    Object.assign(screen, data, { bg_config: data.bg_config || { color: '#07111f' } })
    const draft = normalizeLayout(data.draft_layout || data.layout || {})
    Object.assign(layout.canvas, draft.canvas)
    layout.components.splice(0, layout.components.length, ...draft.components)
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
      ? value.components.map((component: any, index: number) => ({
          id: component.id || `w_${Date.now()}_${index}`,
          type: component.type || 'art-bar-chart',
          title: component.title || '组件',
          rect: {
            x: Number(component.rect?.x || 0),
            y: Number(component.rect?.y || 0),
            w: Number(component.rect?.w || 320),
            h: Number(component.rect?.h || 180),
            z: Number(component.rect?.z || 1)
          },
          dataset: component.dataset || { refresh: 30 },
          option: component.option || {}
        }))
      : []
  })

  const addWidget = (type: string) => {
    const component = createDefaultComponent(type, layout.components.length) as BoardComponent
    layout.components.push(component)
    selectedId.value = component.id
  }

  const updateComponent = (component: BoardComponent) => {
    const index = layout.components.findIndex((item) => item.id === component.id)
    if (index >= 0) layout.components.splice(index, 1, component)
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
      bg_config: screen.bg_config,
      is_public: screen.is_public,
      access_token: screen.access_token,
      status: 2
    })
    await api.saveLayout({ id: screen.id, layout })
    ElMessage.success('保存成功')
    loadData()
  }

  const publish = async () => {
    await saveLayout()
    await api.publish({ id: screen.id })
    ElMessage.success('发布成功')
    loadData()
  }

  const back = () => {
    window.location.hash = '#/saiboard/screen'
  }

  onMounted(async () => {
    await Promise.all([loadData(), loadTemplates()])
  })
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
      linear-gradient(-45deg, rgb(255 255 255 / 4%) 25%, transparent 25%),
      #111827;
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
