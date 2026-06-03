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
      <el-form-item label="队列配置" prop="config_id">
        <el-select v-model="formData.config_id" placeholder="请选择配置" clearable filterable>
          <el-option
            v-for="item in configOptions"
            :key="item.id"
            :label="`${item.name} / ${item.queue_name}`"
            :value="item.id"
          />
        </el-select>
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
      <el-form-item label="状态" prop="status">
        <el-select v-model="formData.status" placeholder="请选择状态" clearable>
          <el-option label="待发布" :value="0" />
          <el-option label="发布中" :value="1" />
          <el-option label="已发布" :value="2" />
          <el-option label="发布失败" :value="3" />
          <el-option label="已取消" :value="4" />
        </el-select>
      </el-form-item>
    </el-col>
    <el-col v-bind="setSpan(6)">
      <el-form-item label="事件名称" prop="event_name">
        <el-input v-model="formData.event_name" placeholder="请输入事件名称" clearable />
      </el-form-item>
    </el-col>
  </sa-search-bar>
</template>

<script setup lang="ts">
  interface Props {
    modelValue: Record<string, any>
    configOptions: Record<string, any>[]
  }

  const props = withDefaults(defineProps<Props>(), {
    configOptions: () => []
  })
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
