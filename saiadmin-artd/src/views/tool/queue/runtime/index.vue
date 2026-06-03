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
      @search="handleSearch"
      @reset="resetSearchParams"
    />

    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="refreshData" />

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
        <template #message_mode="{ row }">
          <ElTag :type="row.message_mode === 'external_message' ? 'primary' : 'info'">
            {{ row.message_mode === 'external_message' ? '外部消息' : '内部任务' }}
          </ElTag>
        </template>
        <template #broker="{ row }">
          <div class="broker-cell">
            <ElTag :type="row.broker_status === 'ok' ? 'success' : 'danger'">
              {{ row.broker_status === 'ok' ? '可访问' : '异常' }}
            </ElTag>
            <span v-if="row.broker_error" class="broker-cell__error">{{ row.broker_error }}</span>
          </div>
        </template>
        <template #db="{ row }">
          <span>{{ row.db_pending }} / {{ row.db_processing }} / {{ row.db_failed }}</span>
        </template>
        <template #broker_count="{ row }">
          <span>{{ row.broker_ready }} / {{ row.broker_delayed }} / {{ row.broker_unacked }}</span>
        </template>
        <template #status="{ row }">
          <ElTag :type="row.status === 1 ? 'success' : 'info'">
            {{ row.status === 1 ? '启用' : '禁用' }}
          </ElTag>
        </template>
        <template #operation="{ row }">
          <div class="flex gap-2">
            <SaButton
              v-permission="'tool:queue-runtime:purge'"
              type="error"
              icon="ri:delete-bin-7-line"
              toolTip="清空队列"
              :disabled="row.broker_status !== 'ok'"
              @click="handlePurge(row)"
            />
            <SaButton
              v-permission="'tool:queue-config:edit'"
              type="error"
              @click="deleteRow(row, queueConfigApi.delete, refreshData)"
            />
          </div>
        </template>
      </ArtTable>
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import { useTable } from '@/hooks/core/useTable'
  import { useSaiAdmin } from '@/composables/useSaiAdmin'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import api from '@/api/tool/queueRuntime'
  import queueConfigApi from '@/api/tool/queueConfig'
  import TableSearch from './modules/table-search.vue'

  const searchForm = ref({
    name: undefined,
    driver: undefined,
    message_mode: undefined,
    queue_name: undefined,
    status: undefined
  })

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
        { prop: 'name', label: '配置名称', minWidth: 140 },
        { prop: 'driver', label: '驱动', width: 110, useSlot: true },
        { prop: 'message_mode', label: '用途', width: 110, useSlot: true },
        { prop: 'connection', label: '连接名', width: 110 },
        { prop: 'queue_name', label: '队列名称', minWidth: 150, showOverflowTooltip: true },
        { prop: 'broker', label: 'Broker', minWidth: 220, useSlot: true },
        { prop: 'broker_count', label: '待消费/延迟/未确认', width: 170, useSlot: true },
        { prop: 'broker_consumers', label: '消费者', width: 90, align: 'center' },
        { prop: 'db', label: '记录待处理/处理中/失败', width: 180, useSlot: true },
        { prop: 'status', label: '配置状态', width: 100, useSlot: true },
        { prop: 'operation', label: '操作', width: 130, fixed: 'right', useSlot: true }
      ]
    }
  })

  const { deleteRow, handleSelectionChange } = useSaiAdmin()

  const statCards = computed(() => {
    const rows = (data.value || []) as Record<string, any>[]
    return [
      { key: 'total', label: '当前配置', value: rows.length },
      { key: 'ready', label: '待消费', value: sum(rows, 'broker_ready') },
      { key: 'delayed', label: '延迟', value: sum(rows, 'broker_delayed') },
      { key: 'unacked', label: '未确认', value: sum(rows, 'broker_unacked') },
      { key: 'failed', label: '失败记录', value: sum(rows, 'db_failed') }
    ]
  })

  const sum = (rows: Record<string, any>[], key: string) => {
    return rows.reduce((total, row) => total + Number(row[key] || 0), 0)
  }

  const handlePurge = (row: any) => {
    ElMessageBox.confirm(
      `确定要清空队列「${row.name} / ${row.queue_name}」中的待消费消息吗？该操作不会删除后台配置，但会删除 broker 中尚未消费的消息。`,
      '清空实时队列',
      {
        confirmButtonText: '确定清空',
        cancelButtonText: '取消',
        type: 'error'
      }
    ).then(async () => {
      const res: any = await api.purge({ id: row.id })
      const count = res?.purged === null || res?.purged === undefined ? '' : `，共 ${res.purged} 条`
      ElMessage.success(`队列已清空${count}`)
      refreshData()
    })
  }
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

  .broker-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
  }

  .broker-cell__error {
    min-width: 0;
    overflow: hidden;
    color: var(--el-color-danger);
    text-overflow: ellipsis;
    white-space: nowrap;
  }
</style>
