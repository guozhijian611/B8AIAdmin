<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ElForm :model="search" inline class="mb-4">
        <ElFormItem label="语言">
          <ElSelect v-model="search.lang_code" clearable placeholder="全部" style="width: 150px">
            <ElOption v-for="item in languages" :key="item.code" :label="item.native_name" :value="item.code" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="位置">
          <ElSelect v-model="search.position" clearable placeholder="全部" style="width: 140px">
            <ElOption label="头部" value="header" />
            <ElOption label="底部" value="footer" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="标题">
          <ElInput v-model="search.title" clearable />
        </ElFormItem>
        <ElFormItem>
          <ElButton type="primary" @click="loadData">搜索</ElButton>
          <ElButton @click="resetSearch">重置</ElButton>
        </ElFormItem>
      </ElForm>

      <ArtTableHeader :loading="loading" @refresh="loadData">
        <template #left>
          <ElButton v-permission="'b8cms:navigation:save'" @click="openDialog()">
            <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
            新增导航
          </ElButton>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="lang_code" label="语言" width="110" />
        <ElTableColumn prop="position" label="位置" width="100">
          <template #default="{ row }">{{ row.position === 'header' ? '头部' : '底部' }}</template>
        </ElTableColumn>
        <ElTableColumn prop="title" label="标题" />
        <ElTableColumn prop="url" label="链接" min-width="240" />
        <ElTableColumn prop="target" label="打开方式" width="110" />
        <ElTableColumn prop="sort" label="排序" width="90" />
        <ElTableColumn label="状态" width="110">
          <template #default="{ row }">
            <ElSwitch
              v-model="row.status"
              :active-value="1"
              :inactive-value="2"
              @change="(status) => changeStatus(row.id, Number(status))"
            />
          </template>
        </ElTableColumn>
        <ElTableColumn label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <SaButton v-permission="'b8cms:navigation:update'" type="secondary" @click="openDialog(row)" />
              <SaButton v-permission="'b8cms:navigation:destroy'" type="error" @click="deleteRow(row.id)" />
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog v-model="dialogVisible" :title="form.id ? '编辑导航' : '新增导航'" width="680px">
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
        <ElFormItem label="语言" prop="lang_code">
          <ElSelect v-model="form.lang_code">
            <ElOption v-for="item in languages" :key="item.code" :label="item.native_name" :value="item.code" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="位置" prop="position">
          <ElRadioGroup v-model="form.position">
            <ElRadioButton label="header">头部</ElRadioButton>
            <ElRadioButton label="footer">底部</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <ElFormItem label="标题" prop="title">
          <ElInput v-model="form.title" />
        </ElFormItem>
        <ElFormItem label="链接" prop="url">
          <ElInput v-model="form.url" />
        </ElFormItem>
        <ElFormItem label="打开方式" prop="target">
          <ElSelect v-model="form.target">
            <ElOption label="当前窗口" value="_self" />
            <ElOption label="新窗口" value="_blank" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="排序" prop="sort">
          <ElInputNumber v-model="form.sort" :min="0" />
        </ElFormItem>
        <ElFormItem label="状态" prop="status">
          <SaRadio v-model="form.status" dict="data_status" />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton type="primary" @click="submit">提交</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import api from '../api/navigation'
  import languageApi from '../api/language'

  const rows = ref<any[]>([])
  const languages = ref<any[]>([])
  const loading = ref(false)
  const dialogVisible = ref(false)
  const formRef = ref<FormInstance>()
  const search = reactive({ lang_code: '', position: '', title: '' })
  const form = reactive({
    id: undefined as number | undefined,
    parent_id: 0,
    lang_code: 'zh-CN',
    position: 'header',
    title: '',
    url: '',
    target: '_self',
    content_type: 'custom',
    content_id: 0,
    sort: 100,
    status: 1
  })

  const rules: FormRules = {
    lang_code: [{ required: true, message: '语言必填', trigger: 'change' }],
    position: [{ required: true, message: '位置必填', trigger: 'change' }],
    title: [{ required: true, message: '标题必填', trigger: 'blur' }],
    url: [{ required: true, message: '链接必填', trigger: 'blur' }]
  }

  const loadLanguages = async () => {
    const data = await languageApi.list({ saiType: 'all' })
    languages.value = Array.isArray(data) ? data : data?.data || []
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
    Object.assign(search, { lang_code: '', position: '', title: '' })
    loadData()
  }

  const openDialog = (row?: any) => {
    Object.assign(form, {
      id: undefined,
      parent_id: 0,
      lang_code: languages.value[0]?.code || 'zh-CN',
      position: 'header',
      title: '',
      url: '',
      target: '_self',
      content_type: 'custom',
      content_id: 0,
      sort: 100,
      status: 1
    })
    if (row) Object.assign(form, row)
    dialogVisible.value = true
  }

  const submit = async () => {
    await formRef.value?.validate()
    form.id ? await api.update(form) : await api.save(form)
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadData()
  }

  const changeStatus = async (id: number, status: number) => {
    await api.changeStatus({ id, status })
    ElMessage.success('状态已更新')
    loadData()
  }

  const deleteRow = async (id: number) => {
    await ElMessageBox.confirm('确定要删除该导航吗？', '删除导航', { type: 'warning' })
    await api.delete({ ids: [id] })
    ElMessage.success('删除成功')
    loadData()
  }

  onMounted(async () => {
    await loadLanguages()
    loadData()
  })
</script>
