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
              v-permission="'saiboard:datasource:changeStatus'"
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
              <ElButton v-permission="'saiboard:datasource:test'" size="small" @click="test(row)">
                测试
              </ElButton>
              <SaButton
                v-permission="'saiboard:datasource:update'"
                type="secondary"
                @click="openDialog(row)"
              />
              <SaButton
                v-permission="'saiboard:datasource:destroy'"
                type="error"
                @click="deleteRow(row)"
              />
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog v-model="dialogVisible" :title="form.id ? '编辑数据源' : '新增数据源'" width="860px">
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
        <ElFormItem label="名称" prop="name"><ElInput v-model="form.name" /></ElFormItem>
        <ElFormItem label="类型" prop="type">
          <ElRadioGroup v-model="form.type" @change="onDatasourceTypeChange">
            <ElRadioButton label="mysql">MySQL</ElRadioButton>
            <ElRadioButton label="http">HTTP</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <template v-if="form.type === 'mysql'">
          <ElFormItem label="主机" prop="config.host"
            ><ElInput v-model="form.config.host"
          /></ElFormItem>
          <ElFormItem label="端口"
            ><ElInputNumber v-model="form.config.port" :min="1" :max="65535"
          /></ElFormItem>
          <ElFormItem label="数据库" prop="config.database"
            ><ElInput v-model="form.config.database"
          /></ElFormItem>
          <ElFormItem label="用户名" prop="config.username"
            ><ElInput v-model="form.config.username"
          /></ElFormItem>
          <ElFormItem label="密码"
            ><ElInput v-model="form.config.password" show-password
          /></ElFormItem>
          <ElFormItem label="字符集"><ElInput v-model="form.config.charset" /></ElFormItem>
        </template>
        <template v-else>
          <ElFormItem label="URL" prop="config.url"
            ><ElInput v-model="form.config.url"
          /></ElFormItem>
          <ElFormItem label="请求头">
            <ElInput
              v-model="headersText"
              type="textarea"
              :rows="4"
              placeholder='例如 {"Authorization":"Bearer xxx"}'
            />
          </ElFormItem>
          <ElFormItem label="默认参数">
            <ElInput
              v-model="paramsText"
              type="textarea"
              :rows="4"
              placeholder='例如 {"range":"7d"}'
            />
          </ElFormItem>
          <ElDivider content-position="left">测试配置</ElDivider>
          <ElFormItem label="测试方法">
            <ElRadioGroup v-model="httpTestConfig.method">
              <ElRadioButton label="GET">GET</ElRadioButton>
              <ElRadioButton label="POST">POST JSON</ElRadioButton>
            </ElRadioGroup>
          </ElFormItem>
          <ElFormItem label="测试路径">
            <ElInput
              v-model="httpTestConfig.path"
              clearable
              placeholder="例如 /metrics/orders，可留空直接请求 URL"
            />
          </ElFormItem>
          <ElFormItem label="测试参数">
            <ElInput
              v-model="testParamsText"
              type="textarea"
              :rows="4"
              placeholder='例如 {"range":"7d"}'
            />
          </ElFormItem>
          <ElFormItem v-if="httpTestConfig.method === 'POST'" label="测试 Body">
            <ElInput
              v-model="testBodyText"
              type="textarea"
              :rows="4"
              placeholder='例如 {"range":"7d","tenant":"b8"}'
            />
          </ElFormItem>
          <ElFormItem label="响应路径">
            <ElInput
              v-model="httpTestConfig.response_path"
              clearable
              placeholder="例如 data.items，可留空自动识别 rows 或 data"
            />
          </ElFormItem>
          <ElFormItem label="总数路径">
            <ElInput
              v-model="httpTestConfig.total_path"
              clearable
              placeholder="例如 data.total，可留空使用 total 或行数"
            />
          </ElFormItem>
        </template>
        <ElFormItem label="缓存秒"
          ><ElInputNumber v-model="form.cache_ttl" :min="0" :max="86400"
        /></ElFormItem>
        <ElFormItem label="状态">
          <ElRadioGroup v-model="form.status">
            <ElRadioButton :label="1">启用</ElRadioButton>
            <ElRadioButton :label="2">停用</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton
          v-permission="'saiboard:datasource:test'"
          :loading="testLoading"
          @click="test(form)"
          >测试</ElButton
        >
        <ElButton
          v-permission="form.id ? 'saiboard:datasource:update' : 'saiboard:datasource:save'"
          type="primary"
          @click="submit"
        >
          提交
        </ElButton>
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
  const testLoading = ref(false)
  const dialogVisible = ref(false)
  const formRef = ref<FormInstance>()
  const search = reactive({ name: '', type: '' })
  const headersText = ref('{}')
  const paramsText = ref('{}')
  const testParamsText = ref('{}')
  const testBodyText = ref('{}')
  const httpTestConfig = reactive<any>({
    path: '',
    method: 'GET',
    response_path: '',
    total_path: ''
  })
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
    type: [{ required: true, message: '类型必选', trigger: 'change' }],
    'config.host': [{ required: true, message: '主机必填', trigger: 'blur' }],
    'config.database': [{ required: true, message: '数据库必填', trigger: 'blur' }],
    'config.username': [{ required: true, message: '用户名必填', trigger: 'blur' }],
    'config.url': [{ required: true, message: 'URL 必填', trigger: 'blur' }]
  }

  const mysqlDefaults = () => ({
    host: '127.0.0.1',
    port: 3306,
    database: '',
    username: '',
    password: '',
    charset: 'utf8mb4'
  })

  const httpDefaults = () => ({
    url: '',
    method: 'GET',
    headers: {},
    params: {}
  })

  const httpTestDefaults = () => ({
    path: '',
    method: 'GET',
    response_path: '',
    total_path: ''
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
    resetHttpTestConfig()
    if (row) {
      Object.assign(form, { ...row, config: { ...(row.config || {}) } })
      headersText.value = stringifyJsonObject(row.config?.headers)
      paramsText.value = stringifyJsonObject(row.config?.params)
    }
    dialogVisible.value = true
  }

  const buildPayload = () => {
    const config = { ...(form.config || {}) }
    if (form.type === 'http') {
      config.headers = parseJsonObject(headersText.value, '请求头')
      config.params = parseJsonObject(paramsText.value, '默认参数')
      config.method = 'GET'
    }
    return { ...form, config }
  }

  const buildTestPayload = () => {
    const payload = buildPayload()
    if (payload.type === 'http') {
      payload.test_config = buildHttpTestConfig()
    }
    return payload
  }

  const buildHttpTestConfig = () => {
    const method = normalizeHttpMethod(httpTestConfig.method)
    return {
      path: String(httpTestConfig.path || '').trim(),
      method,
      params: parseJsonObject(testParamsText.value, '测试参数'),
      body: method === 'POST' ? parseJsonObject(testBodyText.value, '测试 Body') : {},
      response_path: String(httpTestConfig.response_path || '').trim(),
      total_path: String(httpTestConfig.total_path || '').trim()
    }
  }

  const onDatasourceTypeChange = () => {
    form.config = form.type === 'mysql' ? mysqlDefaults() : httpDefaults()
    headersText.value = '{}'
    paramsText.value = '{}'
    resetHttpTestConfig()
    formRef.value?.clearValidate()
  }

  const submit = async () => {
    await formRef.value?.validate()
    const payload = buildPayload()
    if (form.id) {
      await api.update(payload)
    } else {
      await api.save(payload)
    }
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadData()
  }

  const test = async (row: any) => {
    const isFormTesting = row === form
    if (isFormTesting) {
      await formRef.value?.validate()
      testLoading.value = true
    }
    try {
      const result = await api.test(isFormTesting ? buildTestPayload() : { id: row.id })
      ElMessage.success(testSuccessMessage(result))
      if (!isFormTesting) loadData()
    } finally {
      if (isFormTesting) testLoading.value = false
    }
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

  function parseJsonObject(text: string, label: string) {
    try {
      const value = JSON.parse(text || '{}')
      if (!value || typeof value !== 'object' || Array.isArray(value)) {
        throw new Error(`${label}必须是 JSON 对象`)
      }
      return value
    } catch {
      ElMessage.error(`${label}必须是 JSON 对象`)
      throw new Error(`${label}必须是 JSON 对象`)
    }
  }

  function testSuccessMessage(result: any) {
    const diagnostics = result?.diagnostics || {}
    const parts: string[] = []
    const total = Number(result?.total ?? result?.rows?.length ?? 0)
    if (Number.isFinite(total)) parts.push(`返回 ${total} 行`)
    if (diagnostics.method) parts.push(`方法 ${diagnostics.method}`)
    if (diagnostics.host) parts.push(`主机 ${diagnostics.host}`)
    if (diagnostics.status) parts.push(`状态码 ${diagnostics.status}`)
    return parts.length ? `连接成功（${parts.join('，')}）` : '连接成功'
  }

  function normalizeHttpMethod(method: unknown) {
    return String(method || '').toUpperCase() === 'POST' ? 'POST' : 'GET'
  }

  function resetHttpTestConfig() {
    Object.assign(httpTestConfig, httpTestDefaults())
    testParamsText.value = '{}'
    testBodyText.value = '{}'
  }

  function stringifyJsonObject(value: any) {
    const objectValue =
      value && typeof value === 'object' && (!Array.isArray(value) || value.length === 0)
        ? value
        : {}
    return JSON.stringify(Array.isArray(objectValue) ? {} : objectValue, null, 2)
  }

  onMounted(loadData)
</script>
