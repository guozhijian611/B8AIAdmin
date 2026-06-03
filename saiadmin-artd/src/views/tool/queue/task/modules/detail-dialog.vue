<template>
  <el-dialog
    v-model="visible"
    title="队列任务详情"
    width="900px"
    align-center
    :close-on-click-modal="false"
  >
    <el-descriptions :column="2" border>
      <el-descriptions-item label="任务ID">{{ data?.id }}</el-descriptions-item>
      <el-descriptions-item label="状态">
        <ElTag :type="statusMeta(data?.status).type">{{ statusMeta(data?.status).label }}</ElTag>
      </el-descriptions-item>
      <el-descriptions-item label="驱动">{{ data?.driver }}</el-descriptions-item>
      <el-descriptions-item label="队列">{{ data?.name }}</el-descriptions-item>
      <el-descriptions-item label="连接">{{ data?.connections }}</el-descriptions-item>
      <el-descriptions-item label="来源">{{ data?.source || '-' }}</el-descriptions-item>
      <el-descriptions-item label="执行类" :span="2">{{ data?.class_name }}</el-descriptions-item>
      <el-descriptions-item label="执行方法">{{ data?.method_name }}</el-descriptions-item>
      <el-descriptions-item label="失败次数">{{ data?.err_num }}</el-descriptions-item>
      <el-descriptions-item label="耗时">{{ data?.run_time }} ms</el-descriptions-item>
      <el-descriptions-item label="内存">{{ data?.run_memory }} MB</el-descriptions-item>
    </el-descriptions>

    <el-tabs class="mt-4">
      <el-tab-pane label="请求参数">
        <pre>{{ formatJson(data?.request) }}</pre>
      </el-tab-pane>
      <el-tab-pane label="返回结果">
        <pre>{{ formatJson(data?.response) }}</pre>
      </el-tab-pane>
      <el-tab-pane label="IO日志">
        <pre>{{ data?.io || '' }}</pre>
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
      0: { label: '待消费', type: 'info' },
      1: { label: '消费中', type: 'warning' },
      2: { label: '已完成', type: 'success' },
      3: { label: '消费失败', type: 'danger' },
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
