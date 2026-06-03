<template>
  <div class="art-full-height">
    <ElRow :gutter="12" class="mb-3">
      <ElCol :xs="12" :sm="8" :md="4" v-for="item in statCards" :key="item.key">
        <ElCard shadow="never" class="queue-stat">
          <div class="queue-stat__label">{{ item.label }}</div>
          <div class="queue-stat__value">{{ item.value }}</div>
        </ElCard>
      </ElCol>
    </ElRow>

    <TableSearch
      v-model="searchForm"
      :config-options="configOptions"
      @search="handleSearch"
      @reset="resetSearchParams"
    />

    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="refreshAll">
        <template #left>
          <ElButton v-permission="'tool:queue-message:publish'" @click="publishVisible = true" v-ripple>
            <template #icon>
              <ArtSvgIcon icon="ri:send-plane-line" />
            </template>
            发布消息
          </ElButton>
          <ElButton v-permission="'tool:queue-message:clear'" @click="handleClearPublished" v-ripple>
            <template #icon>
              <ArtSvgIcon icon="ri:delete-bin-6-line" />
            </template>
            清理已发布
          </ElButton>
        </template>
      </ArtTableHeader>

      <ArtTable
        rowKey="id"
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @sort-change="handleSortChange"
        @selection-change="handleSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      >
        <template #driver="{ row }">
          <ElTag :type="row.driver === 'redis' ? 'success' : 'warning'">
            {{ row.driver === 'redis' ? 'Redis' : 'RabbitMQ' }}
          </ElTag>
        </template>
        <template #status="{ row }">
          <ElTag :type="statusMeta(row.status).type">{{ statusMeta(row.status).label }}</ElTag>
        </template>
        <template #operation="{ row }">
          <div class="flex gap-2">
            <SaButton
              v-permission="'tool:queue-message:read'"
              type="primary"
              icon="ri:file-search-line"
              toolTip="详情"
              @click="showDetail(row)"
            />
            <SaButton
              v-if="[0, 3, 4].includes(Number(row.status))"
              v-permission="'tool:queue-message:retry'"
              type="primary"
              icon="ri:restart-line"
              toolTip="重试"
              @click="handleRetry(row)"
            />
            <SaButton
              v-if="[0, 3].includes(Number(row.status))"
              v-permission="'tool:queue-message:cancel'"
              type="secondary"
              icon="ri:stop-circle-line"
              toolTip="取消"
              @click="handleCancel(row)"
            />
            <SaButton
              v-permission="'tool:queue-message:destroy'"
              type="error"
              @click="deleteRow(row, api.delete, refreshAll)"
            />
          </div>
        </template>
      </ArtTable>
    </ElCard>

    <PublishDialog v-model="publishVisible" :config-options="configOptions" @success="refreshAll" />
    <DetailDialog v-model="detailVisible" :data="detailData" />
  </div>
</template>

<script setup lang="ts">
  import { useTable } from '@/hooks/core/useTable'
  import { useSaiAdmin } from '@/composables/useSaiAdmin'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import api from '@/api/tool/queueMessage'
  import configApi from '@/api/tool/queueConfig'
  import TableSearch from './modules/table-search.vue'
  import DetailDialog from './modules/detail-dialog.vue'
  import PublishDialog from './modules/publish-dialog.vue'

  const searchForm = ref({
    config_id: undefined,
    driver: undefined,
    status: undefined,
    event_name: undefined
  })
  const configOptions = ref<Record<string, any>[]>([])
  const stats = ref<Record<string, any>>({})
  const publishVisible = ref(false)
  const detailVisible = ref(false)
  const detailData = ref<Record<string, any>>({})

  const statusMeta = (status: number) => {
    const map: Record<number, any> = {
      0: { label: '待发布', type: 'info' },
      1: { label: '发布中', type: 'warning' },
      2: { label: '已发布', type: 'success' },
      3: { label: '发布失败', type: 'danger' },
      4: { label: '已取消', type: 'info' }
    }
    return map[Number(status)] || { label: '-', type: 'info' }
  }

  const statCards = computed(() => [
    { key: 'pending', label: '待发布', value: stats.value.pending ?? 0 },
    { key: 'publishing', label: '发布中', value: stats.value.publishing ?? 0 },
    { key: 'published', label: '已发布', value: stats.value.published ?? 0 },
    { key: 'failed', label: '失败', value: stats.value.failed ?? 0 },
    { key: 'cancelled', label: '已取消', value: stats.value.cancelled ?? 0 }
  ])

  const handleSearch = (params: Record<string, any>) => {
    Object.assign(searchParams, params)
    getData()
  }

  const {
    columns,
    columnChecks,
    data,
    loading,
    getData,
    searchParams,
    pagination,
    resetSearchParams,
    handleSortChange,
    handleSizeChange,
    handleCurrentChange,
    refreshData
  } = useTable({
    core: {
      apiFn: api.list,
      columnsFactory: () => [
        { prop: 'id', label: '编号', width: 90, align: 'center', sortable: true },
        { prop: 'driver', label: '驱动', width: 110, useSlot: true },
        { prop: 'name', label: '队列名称', minWidth: 130 },
        { prop: 'event_name', label: '事件名称', minWidth: 160, showOverflowTooltip: true },
        { prop: 'message_key', label: '业务键', minWidth: 140, showOverflowTooltip: true },
        { prop: 'routing_key', label: 'Routing Key', minWidth: 140, showOverflowTooltip: true },
        { prop: 'status', label: '状态', width: 110, useSlot: true },
        { prop: 'err_num', label: '失败次数', width: 100, align: 'center' },
        { prop: 'source', label: '来源', width: 120 },
        { prop: 'publish_time', label: '发布时间', width: 180, sortable: true },
        { prop: 'operation', label: '操作', width: 190, fixed: 'right', useSlot: true }
      ]
    }
  })

  const { deleteRow, handleSelectionChange } = useSaiAdmin()

  const loadOptions = async () => {
    const res: any = await configApi.options({ message_mode: 'external_message' })
    configOptions.value = res.data || []
  }

  const loadStats = async () => {
    const res: any = await api.stats()
    stats.value = res.data || {}
  }

  const refreshAll = () => {
    refreshData()
    loadStats()
  }

  const showDetail = async (row: any) => {
    const res: any = await api.read(row.id)
    detailData.value = res.data || row
    detailVisible.value = true
  }

  const handleRetry = (row: any) => {
    ElMessageBox.confirm(`确定要重试消息 #${row.id} 吗？`, '重试消息', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }).then(async () => {
      await api.retry({ id: row.id })
      ElMessage.success('重试发布成功')
      refreshAll()
    })
  }

  const handleCancel = (row: any) => {
    ElMessageBox.confirm(`确定要取消消息 #${row.id} 吗？`, '取消消息', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }).then(async () => {
      await api.cancel({ id: row.id })
      ElMessage.success('取消成功')
      refreshAll()
    })
  }

  const handleClearPublished = () => {
    ElMessageBox.confirm('确定要清理已发布消息吗？', '清理消息', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }).then(async () => {
      await api.clearPublished({})
      ElMessage.success('清理成功')
      refreshAll()
    })
  }

  onMounted(() => {
    loadOptions()
    loadStats()
  })
</script>

<style scoped>
  .queue-stat {
    border-radius: 6px;
  }

  .queue-stat__label {
    color: var(--el-text-color-secondary);
    font-size: 13px;
    line-height: 20px;
  }

  .queue-stat__value {
    margin-top: 4px;
    font-size: 24px;
    font-weight: 600;
    line-height: 30px;
  }
</style>
