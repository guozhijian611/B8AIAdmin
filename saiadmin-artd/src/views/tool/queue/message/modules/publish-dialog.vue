<template>
  <el-dialog
    v-model="visible"
    title="发布队列消息"
    width="820px"
    align-center
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <el-form ref="formRef" :model="formData" :rules="rules" label-width="110px">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="队列配置" prop="config_id">
            <el-select v-model="formData.config_id" placeholder="请选择外部消息配置" filterable>
              <el-option
                v-for="item in configOptions"
                :key="item.id"
                :label="`${item.name} / ${item.queue_name}`"
                :value="item.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="事件名称" prop="event_name">
            <el-input v-model="formData.event_name" placeholder="order.paid" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="业务键" prop="message_key">
            <el-input v-model="formData.message_key" placeholder="order_123" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="延迟秒数" prop="delay">
            <el-input-number v-model="formData.delay" :min="0" controls-position="right" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="消息载荷" prop="payload">
        <el-input v-model="formData.payload" type="textarea" :rows="8" />
      </el-form-item>
      <el-form-item label="消息头" prop="headers">
        <el-input v-model="formData.headers" type="textarea" :rows="4" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" @click="handleSubmit">发布</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
  import api from '@/api/tool/queueMessage'
  import { ElMessage } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'

  interface Props {
    modelValue: boolean
    configOptions: Record<string, any>[]
  }

  const props = withDefaults(defineProps<Props>(), {
    modelValue: false,
    configOptions: () => []
  })
  const emit = defineEmits(['update:modelValue', 'success'])

  const formRef = ref<FormInstance>()
  const visible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
  })

  const initialFormData = {
    config_id: undefined as number | undefined,
    event_name: '',
    message_key: '',
    delay: 0,
    payload: '{\n  "id": 123\n}',
    headers: '{}',
    source: 'saiadmin'
  }
  const formData = reactive({ ...initialFormData })

  const validJson = (_rule: any, value: string, callback: (error?: Error) => void) => {
    try {
      JSON.parse(value || '{}')
      callback()
    } catch {
      callback(new Error('必须是合法 JSON'))
    }
  }

  const rules = reactive<FormRules>({
    config_id: [{ required: true, message: '队列配置不能为空', trigger: 'change' }],
    event_name: [{ required: true, message: '事件名称不能为空', trigger: 'blur' }],
    payload: [
      { required: true, message: '消息载荷不能为空', trigger: 'blur' },
      { validator: validJson, trigger: 'blur' }
    ],
    headers: [{ validator: validJson, trigger: 'blur' }]
  })

  watch(
    () => props.modelValue,
    (newVal) => {
      if (newVal) {
        Object.assign(formData, initialFormData)
      }
    }
  )

  const handleClose = () => {
    visible.value = false
    formRef.value?.resetFields()
  }

  const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()
    await api.publish({ ...formData })
    ElMessage.success('发布成功')
    emit('success')
    handleClose()
  }
</script>
