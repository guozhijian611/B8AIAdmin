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
          <ElButton v-permission="'saiboard:screen:save'" @click="openDialog()">
            <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
            新增大屏
          </ElButton>
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
              <ElButton size="small" @click="openRuntime(row)">预览</ElButton>
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
          <ElInput v-model="form.code" placeholder="留空自动生成" />
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
        <ElFormItem v-if="form.is_public === 2" label="访问令牌">
          <ElInput v-model="form.access_token" show-password />
        </ElFormItem>
        <ElFormItem label="状态" prop="status">
          <ElRadioGroup v-model="form.status">
            <ElRadioButton :label="1">已发布</ElRadioButton>
            <ElRadioButton :label="2">草稿</ElRadioButton>
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
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import api from '../api/screen'
  import {
    backgroundFitOptions,
    boardThemeOptions,
    normalizeBgConfig,
    normalizeFitMode
  } from '../widgets/theme'

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
  const formRef = ref<FormInstance>()
  const search = reactive({ name: '', code: '', status: undefined as number | undefined })
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
    access_token: '',
    status: 2
  })

  const fitModeOptions = [
    { label: '完整显示', value: 'contain' },
    { label: '裁切铺满', value: 'cover' },
    { label: '非等比拉伸', value: 'stretch' }
  ]
  const versionSourceMap: Record<string, string> = {
    publish: '发布',
    restore_before: '恢复前',
    save_layout: '保存'
  }

  const rules: FormRules = {
    name: [{ required: true, message: '名称必填', trigger: 'blur' }],
    width: [{ required: true, message: '设计宽度必填', trigger: 'blur' }],
    height: [{ required: true, message: '设计高度必填', trigger: 'blur' }]
  }

  const currentMetrics = computed(() => metrics.value?.screen || metrics.value?.totals || {})
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
      access_token: '',
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
    window.open(
      `#/screen/${row.code}${row.access_token ? `?token=${row.access_token}` : ''}`,
      '_blank'
    )
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

  onMounted(loadData)
</script>

<style scoped lang="scss">
  .metrics-panel {
    min-height: 160px;
  }
</style>
