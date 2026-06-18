<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ElForm :model="search" inline class="mb-4">
        <ElFormItem label="名称"><ElInput v-model="search.name" clearable /></ElFormItem>
        <ElFormItem label="类型">
          <ElSelect v-model="search.type" clearable style="width: 120px">
            <ElOption label="MySQL" value="mysql" />
            <ElOption label="HTTP" value="http" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem><ElButton type="primary" @click="loadData">搜索</ElButton></ElFormItem>
      </ElForm>

      <ArtTableHeader :loading="loading" @refresh="loadData">
        <template #left>
          <ElButton v-permission="'saiboard:datasource:save'" @click="openDialog()">
            <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
            新增数据源
          </ElButton>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="id" label="ID" width="90" />
        <ElTableColumn prop="name" label="名称" min-width="180" />
        <ElTableColumn prop="type" label="类型" width="110" />
        <ElTableColumn prop="cache_ttl" label="缓存秒" width="100" />
        <ElTableColumn prop="last_error" label="最近错误" min-width="220" show-overflow-tooltip />
        <ElTableColumn label="状态" width="110">
          <template #default="{ row }">
            <ElSwitch
              v-model="row.status"
              :active-value="1"
              :inactive-value="2"
              @change="(status) => changeStatus(row, Number(status))"
            />
          </template>
        </ElTableColumn>
        <ElTableColumn label="操作" width="170" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <ElButton size="small" @click="test(row)">测试</ElButton>
              <SaButton v-permission="'saiboard:datasource:update'" type="secondary" @click="openDialog(row)" />
              <SaButton v-permission="'saiboard:datasource:destroy'" type="error" @click="deleteRow(row)" />
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog v-model="dialogVisible" :title="form.id ? '编辑数据源' : '新增数据源'" width="720px">
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
        <ElFormItem label="名称" prop="name"><ElInput v-model="form.name" /></ElFormItem>
        <ElFormItem label="类型" prop="type">
          <ElRadioGroup v-model="form.type">
            <ElRadioButton label="mysql">MySQL</ElRadioButton>
            <ElRadioButton label="http">HTTP</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <template v-if="form.type === 'mysql'">
          <ElFormItem label="主机"><ElInput v-model="form.config.host" /></ElFormItem>
          <ElFormItem label="端口"><ElInputNumber v-model="form.config.port" :min="1" :max="65535" /></ElFormItem>
          <ElFormItem label="数据库"><ElInput v-model="form.config.database" /></ElFormItem>
          <ElFormItem label="用户名"><ElInput v-model="form.config.username" /></ElFormItem>
          <ElFormItem label="密码"><ElInput v-model="form.config.password" show-password /></ElFormItem>
          <ElFormItem label="字符集"><ElInput v-model="form.config.charset" /></ElFormItem>
        </template>
        <template v-else>
          <ElFormItem label="URL"><ElInput v-model="form.config.url" /></ElFormItem>
          <ElFormItem label="请求头">
            <ElInput v-model="headersText" type="textarea" :rows="4" />
          </ElFormItem>
          <ElFormItem label="默认参数">
            <ElInput v-model="paramsText" type="textarea" :rows="4" />
          </ElFormItem>
        </template>
        <ElFormItem label="缓存秒"><ElInputNumber v-model="form.cache_ttl" :min="0" :max="86400" /></ElFormItem>
        <ElFormItem label="状态">
          <ElRadioGroup v-model="form.status">
            <ElRadioButton :label="1">启用</ElRadioButton>
            <ElRadioButton :label="2">停用</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton @click="test(form)">测试</ElButton>
        <ElButton type="primary" @click="submit">提交</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import api from '../api/datasource'

  const rows = ref<any[]>([])
  const loading = ref(false)
  const dialogVisible = ref(false)
  const formRef = ref<FormInstance>()
  const search = reactive({ name: '', type: '' })
  const headersText = ref('{}')
  const paramsText = ref('{}')
  const form = reactive<any>({
    id: undefined,
    name: '',
    type: 'mysql',
    config: {},
    cache_ttl: 0,
    status: 1
  })

  const rules: FormRules = {
    name: [{ required: true, message: '名称必填', trigger: 'blur' }],
    type: [{ required: true, message: '类型必选', trigger: 'change' }]
  }

  const mysqlDefaults = () => ({
    host: '127.0.0.1',
    port: 3306,
    database: '',
    username: '',
    password: '',
    charset: 'utf8mb4'
  })

  const loadData = async () => {
    loading.value = true
    try {
      const data = await api.list({ ...search, saiType: 'all' })
      rows.value = Array.isArray(data) ? data : data?.data || []
    } finally {
      loading.value = false
    }
  }

  const openDialog = (row?: any) => {
    Object.assign(form, {
      id: undefined,
      name: '',
      type: 'mysql',
      config: mysqlDefaults(),
      cache_ttl: 0,
      status: 1
    })
    headersText.value = '{}'
    paramsText.value = '{}'
    if (row) {
      Object.assign(form, { ...row, config: { ...(row.config || {}) } })
      headersText.value = JSON.stringify(row.config?.headers || {}, null, 2)
      paramsText.value = JSON.stringify(row.config?.params || {}, null, 2)
    }
    dialogVisible.value = true
  }

  const buildPayload = () => {
    const config = { ...(form.config || {}) }
    if (form.type === 'http') {
      config.headers = parseJson(headersText.value)
      config.params = parseJson(paramsText.value)
      config.method = 'GET'
    }
    return { ...form, config }
  }

  const submit = async () => {
    await formRef.value?.validate()
    const payload = buildPayload()
    form.id ? await api.update(payload) : await api.save(payload)
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadData()
  }

  const test = async (row: any) => {
    await api.test(row.id ? { id: row.id } : buildPayload())
    ElMessage.success('连接成功')
  }

  const changeStatus = async (row: any, status: number) => {
    await api.changeStatus({ id: row.id, status })
    ElMessage.success('状态已更新')
    loadData()
  }

  const deleteRow = async (row: any) => {
    await ElMessageBox.confirm(`确定删除「${row.name}」吗？`, '删除数据源', { type: 'warning' })
    await api.delete({ ids: [row.id] })
    ElMessage.success('删除成功')
    loadData()
  }

  function parseJson(text: string) {
    try {
      return JSON.parse(text || '{}')
    } catch {
      ElMessage.error('JSON 格式不正确')
      throw new Error('JSON 格式不正确')
    }
  }

  onMounted(loadData)
</script>
