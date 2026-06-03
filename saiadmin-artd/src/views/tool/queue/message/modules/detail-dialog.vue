<template>
  <el-dialog
    v-model="visible"
    title="队列消息详情"
    width="900px"
    align-center
    :close-on-click-modal="false"
  >
    <el-descriptions :column="2" border>
      <el-descriptions-item label="消息ID">{{ data?.id }}</el-descriptions-item>
      <el-descriptions-item label="状态">
        <ElTag :type="statusMeta(data?.status).type">{{ statusMeta(data?.status).label }}</ElTag>
      </el-descriptions-item>
      <el-descriptions-item label="驱动">{{ data?.driver }}</el-descriptions-item>
      <el-descriptions-item label="队列">{{ data?.name }}</el-descriptions-item>
      <el-descriptions-item label="连接">{{ data?.connections }}</el-descriptions-item>
      <el-descriptions-item label="来源">{{ data?.source || '-' }}</el-descriptions-item>
      <el-descriptions-item label="事件名称">{{ data?.event_name }}</el-descriptions-item>
      <el-descriptions-item label="业务键">{{ data?.message_key || '-' }}</el-descriptions-item>
      <el-descriptions-item label="消息编号" :span="2">{{ data?.message_id }}</el-descriptions-item>
      <el-descriptions-item label="Routing Key">{{ data?.routing_key || '-' }}</el-descriptions-item>
      <el-descriptions-item label="延迟">{{ data?.delay }} 秒</el-descriptions-item>
      <el-descriptions-item label="发布时间">{{ data?.publish_time || '-' }}</el-descriptions-item>
      <el-descriptions-item label="失败次数">{{ data?.err_num }}</el-descriptions-item>
    </el-descriptions>

    <el-tabs class="mt-4">
      <el-tab-pane label="消息载荷">
        <pre>{{ formatJson(data?.payload) }}</pre>
      </el-tab-pane>
      <el-tab-pane label="消息头">
        <pre>{{ formatJson(data?.headers) }}</pre>
      </el-tab-pane>
      <el-tab-pane label="发布结果">
        <pre>{{ formatJson(data?.response) }}</pre>
      </el-tab-pane>
    </el-tabs>
  </el-dialog>
</template>

<script setup lang="ts">
  interface Props {
    modelValue: boolean
    data?: Record<string, any>
  }

  const props = withDefaults(defineProps<Props>(), {
    modelValue: false,
    data: undefined
  })
  const emit = defineEmits(['update:modelValue'])

  const visible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
  })

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

  const formatJson = (value?: string) => {
    if (!value) return ''
    try {
      return JSON.stringify(JSON.parse(value), null, 2)
    } catch {
      return value
    }
  }
</script>

<style scoped>
  pre {
    max-height: 360px;
    overflow: auto;
    padding: 12px;
    margin: 0;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
    background: var(--el-fill-color-light);
    border-radius: 6px;
  }
</style>
