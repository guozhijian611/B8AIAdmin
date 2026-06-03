<template>
  <sa-search-bar
    ref="searchBarRef"
    v-model="formData"
    label-width="90px"
    :showExpand="false"
    @reset="handleReset"
    @search="handleSearch"
  >
    <el-col v-bind="setSpan(6)">
      <el-form-item label="配置名称" prop="name">
        <el-input v-model="formData.name" placeholder="请输入配置名称" clearable />
      </el-form-item>
    </el-col>
    <el-col v-bind="setSpan(6)">
      <el-form-item label="驱动" prop="driver">
        <el-select v-model="formData.driver" placeholder="请选择驱动" clearable>
          <el-option label="Redis" value="redis" />
          <el-option label="RabbitMQ" value="rabbitmq" />
        </el-select>
      </el-form-item>
    </el-col>
    <el-col v-bind="setSpan(6)">
      <el-form-item label="队列名" prop="queue_name">
        <el-input v-model="formData.queue_name" placeholder="请输入队列名" clearable />
      </el-form-item>
    </el-col>
    <el-col v-bind="setSpan(6)">
      <el-form-item label="状态" prop="status">
        <sa-select v-model="formData.status" dict="data_status" clearable />
      </el-form-item>
    </el-col>
  </sa-search-bar>
</template>

<script setup lang="ts">
  interface Props {
    modelValue: Record<string, any>
  }

  const props = defineProps<Props>()
  const emit = defineEmits(['update:modelValue', 'search', 'reset'])
  const searchBarRef = ref()

  const formData = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
  })

  function handleReset() {
    searchBarRef.value?.ref.resetFields()
    emit('reset')
  }

  function handleSearch() {
    emit('search', formData.value)
  }

  const setSpan = (span: number) => ({
    span,
    xs: 24,
    sm: 12,
    md: 8,
    lg: span,
    xl: span
  })
</script>
