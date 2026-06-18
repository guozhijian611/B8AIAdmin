<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ElForm :model="search" inline class="mb-4">
        <ElFormItem label="名称"><ElInput v-model="search.name" clearable /></ElFormItem>
        <ElFormItem label="数据源">
          <ElSelect v-model="search.datasource_id" clearable style="width: 180px">
            <ElOption v-for="item in datasourceOptions" :key="item.id" :label="item.name" :value="item.id" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem><ElButton type="primary" @click="loadData">搜索</ElButton></ElFormItem>
      </ElForm>

      <ArtTableHeader :loading="loading" @refresh="loadData">
        <template #left>
          <ElButton v-permission="'saiboard:query_template:save'" @click="openDialog()">
            <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
            新增模板
          </ElButton>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="id" label="ID" width="90" />
        <ElTableColumn prop="name" label="名称" min-width="180" />
        <ElTableColumn label="数据源" min-width="160">
          <template #default="{ row }">{{ datasourceName(row.datasource_id) }}</template>
        </ElTableColumn>
        <ElTableColumn prop="dataset_type" label="类型" width="160" />
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
              <ElButton size="small" @click="preview(row)">预览</ElButton>
              <SaButton v-permission="'saiboard:query_template:update'" type="secondary" @click="openDialog(row)" />
              <SaButton v-permission="'saiboard:query_template:destroy'" type="error" @click="deleteRow(row)" />
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog v-model="dialogVisible" :title="form.id ? '编辑查询模板' : '新增查询模板'" width="760px">
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="120px">
        <ElFormItem label="名称" prop="name"><ElInput v-model="form.name" /></ElFormItem>
        <ElFormItem label="数据源" prop="datasource_id">
          <ElSelect v-model="form.datasource_id" style="width: 100%">
            <ElOption v-for="item in datasourceOptions" :key="item.id" :label="item.name" :value="item.id" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="取数类型" prop="dataset_type">
          <ElSelect v-model="form.dataset_type" style="width: 100%" @change="resetConfig">
            <ElOption label="表原始行" value="table_raw" />
            <ElOption label="表计数" value="table_count" />
            <ElOption label="HTTP 透传" value="http_passthrough" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="配置 JSON">
          <ElInput v-model="configText" type="textarea" :rows="12" />
        </ElFormItem>
        <ElFormItem label="状态">
          <ElRadioGroup v-model="form.status">
            <ElRadioButton :label="1">启用</ElRadioButton>
            <ElRadioButton :label="2">停用</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton @click="preview(form)">预览</ElButton>
        <ElButton type="primary" @click="submit">提交</ElButton>
      </template>
    </ElDialog>

    <ElDialog v-model="previewVisible" title="预览结果" width="720px">
      <SaCode :code="previewText" language="json" />
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import datasourceApi from '../api/datasource'
  import api from '../api/query-template'

  const rows = ref<any[]>([])
  const datasourceOptions = ref<any[]>([])
  const loading = ref(false)
  const dialogVisible = ref(false)
  const previewVisible = ref(false)
  const previewText = ref('')
  const configText = ref('')
  const formRef = ref<FormInstance>()
  const search = reactive({ name: '', datasource_id: undefined as number | undefined })
  const form = reactive<any>({
    id: undefined,
    datasource_id: undefined,
    name: '',
    dataset_type: 'table_raw',
    config: {},
    status: 1
  })

  const rules: FormRules = {
    name: [{ required: true, message: '名称必填', trigger: 'blur' }],
    datasource_id: [{ required: true, message: '数据源必选', trigger: 'change' }],
    dataset_type: [{ required: true, message: '取数类型必选', trigger: 'change' }]
  }

  const defaultConfig = (type: string) => {
    if (type === 'table_count') return { table: '', conditions: [] }
    if (type === 'http_passthrough') return { path: '', params: {} }
    return { table: '', fields: [], conditions: [], order: [], limit: 100 }
  }

  const loadOptions = async () => {
    datasourceOptions.value = await datasourceApi.options()
  }

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
      datasource_id: datasourceOptions.value[0]?.id,
      name: '',
      dataset_type: 'table_raw',
      config: defaultConfig('table_raw'),
      status: 1
    })
    if (row) Object.assign(form, { ...row, config: row.config || {} })
    configText.value = JSON.stringify(form.config || defaultConfig(form.dataset_type), null, 2)
    dialogVisible.value = true
  }

  const resetConfig = () => {
    configText.value = JSON.stringify(defaultConfig(form.dataset_type), null, 2)
  }

  const buildPayload = () => ({ ...form, config: parseConfig() })

  const submit = async () => {
    await formRef.value?.validate()
    const payload = buildPayload()
    form.id ? await api.update(payload) : await api.save(payload)
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadData()
  }

  const preview = async (row: any) => {
    const payload = row.id ? { id: row.id } : buildPayload()
    const result = await api.preview(payload)
    previewText.value = JSON.stringify(result, null, 2)
    previewVisible.value = true
  }

  const changeStatus = async (row: any, status: number) => {
    await api.changeStatus({ id: row.id, status })
    ElMessage.success('状态已更新')
    loadData()
  }

  const deleteRow = async (row: any) => {
    await ElMessageBox.confirm(`确定删除「${row.name}」吗？`, '删除查询模板', { type: 'warning' })
    await api.delete({ ids: [row.id] })
    ElMessage.success('删除成功')
    loadData()
  }

  const datasourceName = (id: number) => datasourceOptions.value.find((item) => item.id === id)?.name || id

  function parseConfig() {
    try {
      return JSON.parse(configText.value || '{}')
    } catch {
      ElMessage.error('配置 JSON 格式不正确')
      throw new Error('配置 JSON 格式不正确')
    }
  }

  onMounted(async () => {
    await loadOptions()
    loadData()
  })
</script>
