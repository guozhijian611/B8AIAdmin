<template>
  <div class="art-full-height">
    <TableSearch v-model="searchForm" @search="handleSearch" @reset="resetSearchParams" />

    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="refreshData">
        <template #left>
          <ElButton v-permission="'tool:queue-config:edit'" @click="showDialog('add')" v-ripple>
            <template #icon>
              <ArtSvgIcon icon="ri:add-fill" />
            </template>
            新增
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
        <template #message_mode="{ row }">
          <ElTag :type="row.message_mode === 'external_message' ? 'primary' : 'info'">
            {{ row.message_mode === 'external_message' ? '外部消息' : '内部任务' }}
          </ElTag>
        </template>
        <template #rabbit="{ row }">
          <span v-if="row.driver === 'rabbitmq'">
            {{ row.exchange_type }} / {{ row.exchange_name || '-' }} / {{ row.routing_key || '-' }}
          </span>
          <span v-else>-</span>
        </template>
        <template #status="{ row }">
          <ElSwitch
            v-permission="'tool:queue-config:status'"
            :model-value="row.status === 1"
            @change="(checked) => handleStatus(row, checked)"
          />
        </template>
        <template #operation="{ row }">
          <div class="flex gap-2">
            <SaButton
              v-permission="'tool:queue-config:edit'"
              type="primary"
              icon="ri:edit-line"
              toolTip="编辑"
              @click="showDialog('edit', row)"
            />
            <SaButton
              v-permission="'tool:queue-config:edit'"
              type="error"
              @click="deleteRow(row, api.delete, refreshData)"
            />
          </div>
        </template>
      </ArtTable>
    </ElCard>

    <EditDialog
      v-model="dialogVisible"
      :dialog-type="dialogType"
      :data="dialogData"
      @success="refreshData"
    />
  </div>
</template>

<script setup lang="ts">
  import { useTable } from '@/hooks/core/useTable'
  import { useSaiAdmin } from '@/composables/useSaiAdmin'
  import { ElMessage } from 'element-plus'
  import api from '@/api/tool/queueConfig'
  import TableSearch from './modules/table-search.vue'
  import EditDialog from './modules/edit-dialog.vue'

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
        { prop: 'queue_name', label: '队列名称', minWidth: 150 },
        { prop: 'rabbit', label: 'RabbitMQ', minWidth: 260, useSlot: true },
        { prop: 'consumer_count', label: '进程数', width: 90, align: 'center' },
        { prop: 'status', label: '状态', width: 100, useSlot: true },
        { prop: 'update_time', label: '更新日期', width: 180, sortable: true },
        { prop: 'operation', label: '操作', width: 120, fixed: 'right', useSlot: true }
      ]
    }
  })

  const { dialogType, dialogVisible, dialogData, showDialog, deleteRow, handleSelectionChange } =
    useSaiAdmin()

  const handleStatus = async (row: any, checked: string | number | boolean) => {
    await api.changeStatus({ id: row.id, status: checked === true ? 1 : 2 })
    ElMessage.success('状态已更新，重载后消费者进程生效')
    refreshData()
  }
</script>
