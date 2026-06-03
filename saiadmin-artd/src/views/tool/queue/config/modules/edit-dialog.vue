<template>
  <el-dialog
    v-model="visible"
    :title="dialogType === 'add' ? '新增队列配置' : '编辑队列配置'"
    width="860px"
    align-center
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <el-form ref="formRef" :model="formData" :rules="rules" label-width="130px">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="配置名称" prop="name">
            <el-input v-model="formData.name" placeholder="请输入配置名称" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="驱动" prop="driver">
            <el-select v-model="formData.driver" placeholder="请选择驱动">
              <el-option label="Redis" value="redis" />
              <el-option label="RabbitMQ" value="rabbitmq" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="队列用途" prop="message_mode">
            <el-select v-model="formData.message_mode" placeholder="请选择用途">
              <el-option label="内部任务" value="internal_job" />
              <el-option label="外部消息" value="external_message" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="连接名" prop="connection">
            <el-input v-model="formData.connection" placeholder="default" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="队列名称" prop="queue_name">
            <el-input v-model="formData.queue_name" placeholder="请输入队列名称" />
          </el-form-item>
        </el-col>
      </el-row>

      <template v-if="formData.driver === 'rabbitmq'">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="交换机名称" prop="exchange_name">
              <el-input v-model="formData.exchange_name" placeholder="请输入交换机名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="交换机类型" prop="exchange_type">
              <el-select v-model="formData.exchange_type">
                <el-option label="Direct" value="direct" />
                <el-option label="Topic" value="topic" />
                <el-option label="Fanout" value="fanout" />
                <el-option label="Header" value="header" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Routing Key" prop="routing_key">
              <el-input v-model="formData.routing_key" placeholder="请输入路由键" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="延迟队列" prop="is_delayed">
              <sa-radio v-model="formData.is_delayed" dict="yes_or_no" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="延迟模式" prop="delay_mode">
              <el-select v-model="formData.delay_mode">
                <el-option label="无" value="none" />
                <el-option label="x-delay" value="x_delay" />
                <el-option label="TTL + DLX" value="ttl_dlx" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="预取数量" prop="prefetch_count">
              <el-input-number v-model="formData.prefetch_count" :min="0" controls-position="right" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="死信交换机" prop="dead_letter_exchange">
              <el-input v-model="formData.dead_letter_exchange" placeholder="可选" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="死信路由键" prop="dead_letter_routing_key">
              <el-input v-model="formData.dead_letter_routing_key" placeholder="可选" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="扩展参数" prop="arguments">
          <el-input
            v-model="formData.arguments"
            type="textarea"
            :rows="4"
            placeholder='例如 {"x-max-priority": 10}'
          />
        </el-form-item>
      </template>

      <el-row :gutter="16">
        <el-col v-if="formData.message_mode === 'internal_job'" :span="12">
          <el-form-item label="消费者进程数" prop="consumer_count">
            <el-input-number v-model="formData.consumer_count" :min="1" controls-position="right" />
          </el-form-item>
        </el-col>
        <el-col v-if="formData.message_mode === 'internal_job'" :span="12">
          <el-form-item label="最大重试次数" prop="max_attempts">
            <el-input-number v-model="formData.max_attempts" :min="1" controls-position="right" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="重试间隔秒" prop="retry_delay_seconds">
            <el-input-number v-model="formData.retry_delay_seconds" :min="0" controls-position="right" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="排序" prop="sort">
            <el-input-number v-model="formData.sort" :min="0" controls-position="right" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="状态" prop="status">
            <sa-radio v-model="formData.status" dict="data_status" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="备注" prop="remark">
        <el-input v-model="formData.remark" type="textarea" :rows="2" placeholder="请输入备注" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" @click="handleSubmit">提交</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
  import api from '@/api/tool/queueConfig'
  import { ElMessage } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'

  interface Props {
    modelValue: boolean
    dialogType: string
    data?: Record<string, any>
  }

  const props = withDefaults(defineProps<Props>(), {
    modelValue: false,
    dialogType: 'add',
    data: undefined
  })
  const emit = defineEmits(['update:modelValue', 'success'])

  const formRef = ref<FormInstance>()
  const visible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
  })

  const initialFormData = {
    id: undefined as number | undefined,
    name: '',
    driver: 'redis',
    message_mode: 'internal_job',
    connection: 'default',
    queue_name: '',
    exchange_name: '',
    exchange_type: 'direct',
    routing_key: '',
    is_delayed: 2,
    delay_mode: 'none',
    dead_letter_exchange: '',
    dead_letter_routing_key: '',
    prefetch_count: 0,
    consumer_count: 1,
    max_attempts: 3,
    retry_delay_seconds: 5,
    arguments: '{}',
    sort: 100,
    status: 1,
    remark: ''
  }
  const formData = reactive({ ...initialFormData })

  const rules = reactive<FormRules>({
    name: [{ required: true, message: '配置名称不能为空', trigger: 'blur' }],
    driver: [{ required: true, message: '驱动不能为空', trigger: 'change' }],
    message_mode: [{ required: true, message: '队列用途不能为空', trigger: 'change' }],
    connection: [{ required: true, message: '连接名不能为空', trigger: 'blur' }],
    queue_name: [{ required: true, message: '队列名称不能为空', trigger: 'blur' }]
  })

  watch(
    () => props.modelValue,
    (newVal) => {
      if (newVal) initPage()
    }
  )

  watch(
    () => formData.driver,
    (driver) => {
      if (driver === 'redis') {
        formData.exchange_name = ''
        formData.routing_key = ''
        formData.is_delayed = 2
        formData.delay_mode = 'none'
      }
    }
  )

  watch(
    () => formData.message_mode,
    (mode) => {
      if (mode === 'external_message') {
        formData.consumer_count = 0
      } else if (formData.consumer_count < 1) {
        formData.consumer_count = 1
      }
    }
  )

  const initPage = async () => {
    Object.assign(formData, initialFormData)
    if (props.data) {
      await nextTick()
      Object.assign(formData, props.data)
      formData.arguments =
        typeof props.data.arguments === 'object'
          ? JSON.stringify(props.data.arguments, null, 2)
          : props.data.arguments || '{}'
    }
  }

  const handleClose = () => {
    visible.value = false
    formRef.value?.resetFields()
  }

  const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()
    const data = { ...formData }
    if (props.dialogType === 'add') {
      await api.save(data)
      ElMessage.success('添加成功')
    } else {
      await api.update(data)
      ElMessage.success('修改成功')
    }
    emit('success')
    handleClose()
  }
</script>
