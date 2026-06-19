<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ElForm :model="search" inline class="mb-4">
        <ElFormItem label="名称">
          <ElInput v-model="search.name" clearable />
        </ElFormItem>
        <ElFormItem label="类型">
          <ElSelect v-model="search.type" clearable style="width: 140px">
            <ElOption label="大屏模板" value="screen" />
            <ElOption label="组件模板" value="component" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="分类">
          <ElInput v-model="search.category" clearable />
        </ElFormItem>
        <ElFormItem>
          <ElButton type="primary" @click="loadData">搜索</ElButton>
          <ElButton @click="resetSearch">重置</ElButton>
        </ElFormItem>
      </ElForm>

      <ArtTableHeader :loading="loading" @refresh="loadData">
        <template #left>
          <ElButton v-permission="'saiboard:market_item:save'" @click="openDialog()">
            <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
            新增模板
          </ElButton>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="id" label="ID" width="90" />
        <ElTableColumn prop="name" label="名称" min-width="180" show-overflow-tooltip />
        <ElTableColumn label="类型" width="120">
          <template #default="{ row }">{{ typeLabel(row.type) }}</template>
        </ElTableColumn>
        <ElTableColumn prop="category" label="分类" width="140" show-overflow-tooltip />
        <ElTableColumn prop="component_count" label="组件数" width="100" />
        <ElTableColumn label="公开" width="100">
          <template #default="{ row }">
            <ElTag :type="row.is_public === 1 ? 'success' : 'info'">
              {{ row.is_public === 1 ? '公开' : '私有' }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="状态" width="110">
          <template #default="{ row }">
            <ElSwitch
              v-permission="'saiboard:market_item:changeStatus'"
              v-model="row.status"
              :active-value="1"
              :inactive-value="2"
              @change="(status) => changeStatus(row, Number(status))"
            />
          </template>
        </ElTableColumn>
        <ElTableColumn prop="update_time" label="更新时间" width="180" />
        <ElTableColumn label="操作" width="170" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <SaButton
                v-permission="'saiboard:market_item:read'"
                type="secondary"
                @click="openDialog(row)"
              />
              <SaButton
                v-permission="'saiboard:market_item:destroy'"
                type="error"
                @click="deleteRow(row)"
              />
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog v-model="dialogVisible" :title="form.id ? '编辑模板' : '新增模板'" width="760px">
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="100px">
        <ElFormItem label="名称" prop="name">
          <ElInput v-model="form.name" maxlength="80" />
        </ElFormItem>
        <ElFormItem label="类型" prop="type">
          <ElRadioGroup v-model="form.type">
            <ElRadioButton label="screen">大屏模板</ElRadioButton>
            <ElRadioButton label="component">组件模板</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <ElFormItem label="分类">
          <ElInput v-model="form.category" maxlength="60" />
        </ElFormItem>
        <ElFormItem label="说明">
          <ElInput v-model="form.description" type="textarea" :rows="2" maxlength="255" />
        </ElFormItem>
        <ElFormItem label="公开">
          <ElRadioGroup v-model="form.is_public">
            <ElRadioButton :label="2">私有</ElRadioButton>
            <ElRadioButton :label="1">公开</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <ElFormItem label="状态">
          <ElRadioGroup v-model="form.status">
            <ElRadioButton :label="1">启用</ElRadioButton>
            <ElRadioButton :label="2">停用</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <ElFormItem label="模板 JSON">
          <ElInput v-model="contentText" type="textarea" :rows="12" />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton
          v-permission="form.id ? 'saiboard:market_item:update' : 'saiboard:market_item:save'"
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
  import api from '../api/market'

  const rows = ref<any[]>([])
  const loading = ref(false)
  const dialogVisible = ref(false)
  const formRef = ref<FormInstance>()
  const search = reactive({ name: '', type: '', category: '' })
  const contentText = ref('{}')
  const form = reactive<any>({
    id: undefined,
    type: 'screen',
    name: '',
    category: '',
    description: '',
    cover_image: '',
    content: {},
    is_public: 2,
    status: 1
  })
  const rules: FormRules = {
    name: [{ required: true, message: '名称必填', trigger: 'blur' }],
    type: [{ required: true, message: '类型必选', trigger: 'change' }]
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

  const resetSearch = () => {
    Object.assign(search, { name: '', type: '', category: '' })
    loadData()
  }

  const openDialog = async (row?: any) => {
    Object.assign(form, {
      id: undefined,
      type: 'screen',
      name: '',
      category: '',
      description: '',
      cover_image: '',
      content: {},
      is_public: 2,
      status: 1
    })
    contentText.value = JSON.stringify(
      { layout: { canvas: { width: 1920, height: 1080 }, components: [] } },
      null,
      2
    )
    if (row) {
      const detail = await api.read(row.id)
      Object.assign(form, { ...detail, content: detail.content || {} })
      contentText.value = JSON.stringify(detail.content || {}, null, 2)
    }
    dialogVisible.value = true
  }

  const submit = async () => {
    await formRef.value?.validate()
    const content = parseJson(contentText.value)
    if (!content) return

    const payload = { ...form, content }
    if (form.id) {
      await api.update(payload)
    } else {
      await api.save(payload)
    }
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadData()
  }

  const changeStatus = async (row: any, status: number) => {
    await api.changeStatus({ id: row.id, status })
    ElMessage.success('操作成功')
    loadData()
  }

  const deleteRow = async (row: any) => {
    await ElMessageBox.confirm(`确定删除「${row.name}」吗？`, '删除模板', { type: 'warning' })
    await api.delete({ ids: [row.id] })
    ElMessage.success('删除成功')
    loadData()
  }

  const parseJson = (text: string) => {
    try {
      return JSON.parse(text || '{}')
    } catch {
      ElMessage.error('模板 JSON 格式不正确')
      return undefined
    }
  }

  const typeLabel = (type: string) => (type === 'component' ? '组件模板' : '大屏模板')

  onMounted(loadData)
</script>
