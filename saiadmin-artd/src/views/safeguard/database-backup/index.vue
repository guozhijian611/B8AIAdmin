<template>
  <div class="database-backup-page">
    <ElCard class="art-table-card" shadow="never">
      <div class="page-header">
        <div>
          <h2>数据库导入导出</h2>
          <p>当前数据库：{{ overview.database || '-' }}</p>
        </div>
        <ElSpace wrap>
          <ElSelect v-model="query.source" class="source-select" @change="loadOverview">
            <ElOption
              v-for="source in overview.sources"
              :key="source"
              :label="source"
              :value="source"
            />
          </ElSelect>
          <ElButton :loading="loading" @click="loadOverview" v-ripple>
            <template #icon>
              <ArtSvgIcon icon="ri:refresh-line" />
            </template>
            刷新
          </ElButton>
        </ElSpace>
      </div>

      <ElRow :gutter="16" class="overview-row">
        <ElCol :xs="24" :sm="12" :lg="6">
          <div class="metric-item">
            <span>连接地址</span>
            <strong>{{ overview.host || '-' }}</strong>
          </div>
        </ElCol>
        <ElCol :xs="24" :sm="12" :lg="6">
          <div class="metric-item">
            <span>数据表</span>
            <strong>{{ overview.table_count || 0 }}</strong>
          </div>
        </ElCol>
        <ElCol :xs="24" :sm="12" :lg="6">
          <div class="metric-item">
            <span>字符集</span>
            <strong>{{ overview.charset || '-' }}</strong>
          </div>
        </ElCol>
        <ElCol :xs="24" :sm="12" :lg="6">
          <div class="metric-item">
            <span>表前缀</span>
            <strong>{{ overview.prefix || '无' }}</strong>
          </div>
        </ElCol>
      </ElRow>
    </ElCard>

    <ElRow :gutter="16" class="action-row">
      <ElCol :xs="24" :lg="12">
        <ElCard class="art-table-card action-card" shadow="never">
          <template #header>
            <div class="card-title">
              <ArtSvgIcon icon="ri:download-cloud-2-line" />
              <span>导出 SQL</span>
            </div>
          </template>

          <ElForm label-width="110px">
            <ElFormItem label="导出内容">
              <ElRadioGroup v-model="exportForm.with_data">
                <ElRadioButton :value="true">结构和数据</ElRadioButton>
                <ElRadioButton :value="false">仅表结构</ElRadioButton>
              </ElRadioGroup>
            </ElFormItem>
            <ElFormItem label="外键检查">
              <ElSwitch
                v-model="exportForm.disable_foreign_key_checks"
                active-text="导出时禁用"
                inactive-text="不处理"
              />
            </ElFormItem>
            <ElFormItem label="数据表">
              <ElSelect
                v-model="exportForm.tables"
                multiple
                filterable
                collapse-tags
                collapse-tags-tooltip
                placeholder="不选择则导出全部数据表"
              >
                <ElOption v-for="table in overview.tables" :key="table" :label="table" :value="table" />
              </ElSelect>
            </ElFormItem>
            <ElFormItem>
              <ElButton
                v-permission="'core:database-backup:export'"
                type="primary"
                :loading="exporting"
                @click="handleExport"
                v-ripple
              >
                <template #icon>
                  <ArtSvgIcon icon="ri:download-2-line" />
                </template>
                一键导出
              </ElButton>
            </ElFormItem>
          </ElForm>
        </ElCard>
      </ElCol>

      <ElCol :xs="24" :lg="12">
        <ElCard class="art-table-card action-card" shadow="never">
          <template #header>
            <div class="card-title">
              <ArtSvgIcon icon="ri:upload-cloud-2-line" />
              <span>导入 SQL</span>
            </div>
          </template>

          <ElAlert
            title="导入会直接写入当前数据库，请先确认 SQL 来源可信；大体积数据建议使用专业备份工具处理。"
            type="warning"
            :closable="false"
            show-icon
          />
          <ElForm label-width="110px" class="import-form">
            <ElFormItem label="覆盖已有表">
              <ElSwitch
                v-model="importForm.drop_table_if_exists"
                active-text="先删除同名表"
                inactive-text="保留同名表"
              />
            </ElFormItem>
            <ElFormItem label="SQL 文件">
              <ElUpload
                drag
                action="#"
                accept=".sql"
                :limit="1"
                :auto-upload="false"
                :on-change="handleFileChange"
                :on-remove="handleFileRemove"
              >
                <ArtSvgIcon icon="ri:file-upload-line" class="upload-icon" />
                <div class="el-upload__text">将 SQL 文件拖到此处，或点击选择</div>
                <template #tip>
                  <div class="el-upload__tip">仅支持 .sql 文件</div>
                </template>
              </ElUpload>
            </ElFormItem>
            <ElFormItem>
              <ElButton
                v-permission="'core:database-backup:import'"
                type="danger"
                :loading="importing"
                @click="handleImport"
                v-ripple
              >
                <template #icon>
                  <ArtSvgIcon icon="ri:upload-2-line" />
                </template>
                一键导入
              </ElButton>
            </ElFormItem>
          </ElForm>
        </ElCard>
      </ElCol>
    </ElRow>

    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader :loading="loading" layout="refresh" @refresh="loadOverview">
        <template #left>
          <span class="backup-title">本地备份文件</span>
        </template>
      </ArtTableHeader>
      <ArtTable :loading="loading" :data="overview.backups" :columns="backupColumns">
        <template #size="{ row }">
          {{ formatFileSize(row.size) }}
        </template>
        <template #operation="{ row }">
          <div class="flex gap-2">
            <SaButton
              v-permission="'core:database-backup:export'"
              type="primary"
              icon="ri:download-line"
              tool-tip="下载"
              @click="handleDownload(row)"
            />
            <SaButton
              v-permission="'core:database-backup:delete'"
              type="error"
              icon="ri:delete-bin-line"
              tool-tip="删除"
              @click="handleDelete(row)"
            />
          </div>
        </template>
      </ArtTable>
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import type { UploadFile } from 'element-plus'
  import type { ColumnOption } from '@/types/component'
  import { ElMessageBox } from 'element-plus'
  import api from '@/api/safeguard/databaseBackup'
  import { downloadFile } from '@/utils/tool'

  defineOptions({ name: 'DatabaseBackup' })

  const loading = ref(false)
  const exporting = ref(false)
  const importing = ref(false)
  const uploadFile = ref<File | null>(null)

  const query = reactive({
    source: 'mysql'
  })

  const overview = reactive({
    source: 'mysql',
    database: '',
    host: '',
    charset: '',
    prefix: '',
    table_count: 0,
    sources: [] as string[],
    tables: [] as string[],
    backups: [] as Record<string, any>[]
  })

  const exportForm = reactive({
    with_data: true,
    disable_foreign_key_checks: true,
    tables: [] as string[]
  })

  const importForm = reactive({
    drop_table_if_exists: false
  })

  const backupColumns: ColumnOption[] = [
    { prop: 'filename', label: '文件名', minWidth: 260, showOverflowTooltip: true },
    { prop: 'size', label: '文件大小', width: 120, useSlot: true },
    { prop: 'create_time', label: '创建时间', width: 180 },
    { prop: 'operation', label: '操作', width: 110, fixed: 'right', useSlot: true }
  ]

  const loadOverview = async () => {
    loading.value = true
    try {
      const data = await api.overview({ source: query.source })
      Object.assign(overview, data)
      query.source = data.source || query.source
    } finally {
      loading.value = false
    }
  }

  const handleExport = async () => {
    exporting.value = true
    try {
      const response = await api.exportSql({
        source: query.source,
        with_data: exportForm.with_data,
        disable_foreign_key_checks: exportForm.disable_foreign_key_checks,
        tables: exportForm.tables
      })
      downloadFile(response, `${query.source}_${exportForm.with_data ? 'full' : 'structure'}.sql`)
      ElMessage.success('导出成功')
      loadOverview()
    } finally {
      exporting.value = false
    }
  }

  const handleFileChange = (file: UploadFile) => {
    uploadFile.value = file.raw || null
  }

  const handleFileRemove = () => {
    uploadFile.value = null
  }

  const handleImport = async () => {
    if (!uploadFile.value) {
      ElMessage.warning('请选择要导入的 SQL 文件')
      return
    }

    const message = importForm.drop_table_if_exists
      ? '确定要导入 SQL 吗？同名表会先被删除。'
      : '确定要导入 SQL 吗？同名数据会使用 INSERT IGNORE 跳过。'

    await ElMessageBox.confirm(message, '导入确认', {
      confirmButtonText: '确定导入',
      cancelButtonText: '取消',
      type: 'warning'
    })

    importing.value = true
    try {
      const formData = new FormData()
      formData.append('file', uploadFile.value)
      formData.append('source', query.source)
      formData.append('drop_table_if_exists', importForm.drop_table_if_exists ? '1' : '0')
      await api.importSql(formData)
      ElMessage.success('导入成功')
      uploadFile.value = null
      loadOverview()
    } finally {
      importing.value = false
    }
  }

  const handleDownload = async (row: Record<string, any>) => {
    const response = await api.downloadBackup({ filename: row.filename })
    downloadFile(response, row.filename)
  }

  const handleDelete = async (row: Record<string, any>) => {
    await ElMessageBox.confirm(`确定删除备份文件 ${row.filename} 吗？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await api.deleteBackup({ filename: row.filename })
    ElMessage.success('删除成功')
    loadOverview()
  }

  const formatFileSize = (size: number) => {
    if (!size) return '0 B'
    const units = ['B', 'KB', 'MB', 'GB']
    let value = size
    let index = 0
    while (value >= 1024 && index < units.length - 1) {
      value /= 1024
      index += 1
    }
    return `${value.toFixed(index === 0 ? 0 : 2)} ${units[index]}`
  }

  onMounted(() => {
    loadOverview()
  })
</script>

<style scoped lang="scss">
  .database-backup-page {
    .page-header {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      align-items: flex-start;

      h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 600;
      }

      p {
        margin: 8px 0 0;
        color: var(--el-text-color-secondary);
      }
    }

    .source-select {
      width: 180px;
    }

    .overview-row,
    .action-row {
      margin-top: 16px;
    }

    .metric-item {
      padding: 16px;
      border: 1px solid var(--el-border-color-light);
      border-radius: 8px;
      display: flex;
      flex-direction: column;
      gap: 8px;

      span {
        color: var(--el-text-color-secondary);
      }

      strong {
        font-size: 18px;
        font-weight: 600;
        word-break: break-all;
      }
    }

    .action-card {
      height: 100%;

      :deep(.el-select),
      :deep(.el-upload),
      :deep(.el-upload-dragger) {
        width: 100%;
      }
    }

    .card-title,
    .backup-title {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
    }

    .import-form {
      margin-top: 16px;
    }

    .upload-icon {
      margin-top: 12px;
      font-size: 34px;
      color: var(--el-color-primary);
    }
  }
</style>
