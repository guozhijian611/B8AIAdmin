<template>
  <div class="art-full-height b8cms-setting-page">
    <ElCard class="art-table-card b8cms-setting-card" shadow="never">
      <ElForm :model="search" inline class="b8cms-setting-search">
        <ElFormItem label="分组">
          <ElSelect v-model="search.group_key" clearable placeholder="全部" style="width: 150px">
            <ElOption label="品牌" value="brand" />
            <ElOption label="SEO" value="seo" />
            <ElOption label="首页" value="home" />
            <ElOption label="联系" value="contact" />
            <ElOption label="媒体" value="media" />
            <ElOption label="底部" value="footer" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="语言">
          <ElSelect v-model="search.lang_code" clearable placeholder="全部" style="width: 150px">
            <ElOption label="全局" value="" />
            <ElOption v-for="item in languages" :key="item.code" :label="item.native_name" :value="item.code" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="标识">
          <ElInput v-model="search.setting_key" clearable />
        </ElFormItem>
        <ElFormItem>
          <ElButton type="primary" @click="loadData">搜索</ElButton>
          <ElButton @click="resetSearch">重置</ElButton>
        </ElFormItem>
      </ElForm>

      <ArtTableHeader :loading="loading" @refresh="loadData">
        <template #left>
          <ElButton v-permission="'b8cms:setting:save'" @click="openDialog()">
            <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
            新增配置
          </ElButton>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id" :height="tableHeight">
        <ElTableColumn prop="group_key" label="分组" width="120" />
        <ElTableColumn prop="setting_key" label="标识" width="180" />
        <ElTableColumn prop="lang_code" label="语言" width="110">
          <template #default="{ row }">{{ row.lang_code || '全局' }}</template>
        </ElTableColumn>
        <ElTableColumn prop="title" label="标题" />
        <ElTableColumn label="配置值" min-width="260">
          <template #default="{ row }">
            <span class="line-clamp-1">{{ formatValue(row.value) }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="input_type" label="组件" width="110" />
        <ElTableColumn prop="sort" label="排序" width="90" />
        <ElTableColumn label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <SaButton v-permission="'b8cms:setting:update'" type="secondary" @click="openDialog(row)" />
              <SaButton v-permission="'b8cms:setting:destroy'" type="error" @click="deleteRow(row.id)" />
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog
      v-model="dialogVisible"
      :title="form.id ? '编辑配置' : '新增配置'"
      width="760px"
      top="6vh"
      append-to-body
      destroy-on-close
      class="b8cms-setting-dialog"
    >
      <ElScrollbar max-height="calc(88vh - 168px)">
        <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px" class="b8cms-setting-form">
          <ElRow :gutter="18">
            <ElCol :span="12">
              <ElFormItem label="分组" prop="group_key">
                <ElInput v-model="form.group_key" placeholder="brand/seo/home/contact/media/footer" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="12">
              <ElFormItem label="标识" prop="setting_key">
                <ElInput v-model="form.setting_key" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="12">
              <ElFormItem label="语言" prop="lang_code">
                <ElSelect v-model="form.lang_code" clearable>
                  <ElOption label="全局" value="" />
                  <ElOption v-for="item in languages" :key="item.code" :label="item.native_name" :value="item.code" />
                </ElSelect>
              </ElFormItem>
            </ElCol>
            <ElCol :span="12">
              <ElFormItem label="组件" prop="input_type">
                <ElSelect v-model="form.input_type">
                  <ElOption label="输入框" value="input" />
                  <ElOption label="多行文本" value="textarea" />
                  <ElOption label="图片" value="image" />
                  <ElOption label="JSON" value="json" />
                </ElSelect>
              </ElFormItem>
            </ElCol>
            <ElCol :span="24">
              <ElFormItem label="标题" prop="title">
                <ElInput v-model="form.title" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="24">
              <ElFormItem label="配置值" prop="value">
                <sa-image-upload
                  v-if="form.input_type === 'image'"
                  v-model="imageValue"
                  :limit="1"
                  :multiple="false"
                />
                <ElInput
                  v-else
                  v-model="textValue"
                  :type="form.input_type === 'input' ? 'text' : 'textarea'"
                  :rows="form.input_type === 'input' ? undefined : 7"
                />
              </ElFormItem>
            </ElCol>
            <ElCol :span="12">
              <ElFormItem label="排序" prop="sort">
                <ElInputNumber v-model="form.sort" :min="0" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="12">
              <ElFormItem label="状态" prop="status">
                <SaRadio v-model="form.status" dict="data_status" />
              </ElFormItem>
            </ElCol>
          </ElRow>
        </ElForm>
      </ElScrollbar>
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
  import api from '../api/setting'
  import languageApi from '../api/language'

  const rows = ref<any[]>([])
  const languages = ref<any[]>([])
  const loading = ref(false)
  const dialogVisible = ref(false)
  const formRef = ref<FormInstance>()
  const tableHeight = 'calc(100vh - 280px)'
  const search = reactive({ group_key: '', setting_key: '', lang_code: '' })
  const textValue = ref('')
  const imageValue = ref('')
  const form = reactive({
    id: undefined as number | undefined,
    group_key: '',
    setting_key: '',
    lang_code: '',
    title: '',
    value: '' as any,
    input_type: 'input',
    options: [],
    sort: 100,
    status: 1
  })

  const rules: FormRules = {
    group_key: [{ required: true, message: '分组必填', trigger: 'blur' }],
    setting_key: [{ required: true, message: '标识必填', trigger: 'blur' }],
    title: [{ required: true, message: '标题必填', trigger: 'blur' }]
  }

  watch([textValue, imageValue], () => {
    form.value = form.input_type === 'image' ? imageValue.value : parseValue(textValue.value)
  })

  const parseValue = (value: string) => {
    if (form.input_type !== 'json') return value
    try {
      return JSON.parse(value)
    } catch {
      return value
    }
  }

  const formatValue = (value: any) => {
    return typeof value === 'string' ? value : JSON.stringify(value)
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
    Object.assign(search, { group_key: '', setting_key: '', lang_code: '' })
    loadData()
  }

  const openDialog = (row?: any) => {
    Object.assign(form, {
      id: undefined,
      group_key: '',
      setting_key: '',
      lang_code: '',
      title: '',
      value: '',
      input_type: 'input',
      options: [],
      sort: 100,
      status: 1
    })
    if (row) Object.assign(form, row)
    textValue.value = formatValue(form.value)
    imageValue.value = typeof form.value === 'string' ? form.value : ''
    dialogVisible.value = true
  }

  const submit = async () => {
    await formRef.value?.validate()
    form.value = form.input_type === 'image' ? imageValue.value : parseValue(textValue.value)
    form.id ? await api.update(form) : await api.save(form)
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadData()
  }

  const deleteRow = async (id: number) => {
    await ElMessageBox.confirm('确定要删除该配置吗？', '删除配置', { type: 'warning' })
    await api.delete({ ids: [id] })
    ElMessage.success('删除成功')
    loadData()
  }

  onMounted(async () => {
    await loadLanguages()
    loadData()
  })
</script>

<style scoped>
  .b8cms-setting-page {
    min-height: 0;
  }

  .b8cms-setting-card {
    height: 100%;
    overflow: hidden;
  }

  .b8cms-setting-card :deep(.el-card__body) {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
  }

  .b8cms-setting-search {
    flex-shrink: 0;
    margin-bottom: 16px;
  }

  .b8cms-setting-card :deep(#art-table-header) {
    flex-shrink: 0;
    margin-bottom: 12px;
  }

  .b8cms-setting-form {
    padding-right: 12px;
  }

  @media (max-width: 768px) {
    .b8cms-setting-card {
      height: auto;
      overflow: visible;
    }
  }
</style>
