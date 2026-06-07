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
            <ElOption label="首页" value="home" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="标题">
          <ElInput v-model="search.title" clearable placeholder="请输入标题" />
        </ElFormItem>
        <ElFormItem label="状态">
          <ElSelect v-model="search.status" clearable placeholder="全部" style="width: 120px">
            <ElOption label="启用" :value="1" />
            <ElOption label="停用" :value="2" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem>
          <ElButton type="primary" @click="loadData">
            <template #icon><ArtSvgIcon icon="ri:search-line" /></template>
            搜索
          </ElButton>
          <ElButton @click="resetSearch">重置</ElButton>
        </ElFormItem>
      </ElForm>

      <ArtTableHeader :loading="loading" @refresh="loadData">
        <template #left>
          <ElButton v-permission="'b8cms:carousel:save'" @click="openDialog()">
            <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
            新增轮播图
          </ElButton>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="lang_code" label="语言" width="110" />
        <ElTableColumn prop="position" label="位置" width="90">
          <template #default="{ row }">{{ positionText(row.position) }}</template>
        </ElTableColumn>
        <ElTableColumn label="图片" width="120">
          <template #default="{ row }">
            <ElImage
              v-if="row.image"
              :src="row.image"
              fit="cover"
              :preview-src-list="[row.image]"
              preview-teleported
              class="carousel-thumb"
            />
            <ElTag v-else type="info">占位图</ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="title" label="标题" min-width="220" />
        <ElTableColumn prop="button_text" label="主按钮" width="150" />
        <ElTableColumn prop="button_url" label="主链接" min-width="220" />
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
              <SaButton v-permission="'b8cms:carousel:update'" type="secondary" @click="openDialog(row)" />
              <SaButton v-permission="'b8cms:carousel:destroy'" type="error" @click="deleteRow(row.id)" />
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog
      v-model="dialogVisible"
      :title="form.id ? '编辑轮播图' : '新增轮播图'"
      width="880px"
      :close-on-click-modal="false"
    >
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="118px">
        <ElRow :gutter="18">
          <ElCol :span="8">
            <ElFormItem label="语言" prop="lang_code">
              <ElSelect v-model="form.lang_code">
                <ElOption v-for="item in languages" :key="item.code" :label="item.native_name" :value="item.code" />
              </ElSelect>
            </ElFormItem>
          </ElCol>
          <ElCol :span="8">
            <ElFormItem label="位置" prop="position">
              <ElSelect v-model="form.position">
                <ElOption label="首页" value="home" />
              </ElSelect>
            </ElFormItem>
          </ElCol>
          <ElCol :span="8">
            <ElFormItem label="排序" prop="sort">
              <ElInputNumber v-model="form.sort" :min="0" style="width: 100%" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="24">
            <ElFormItem label="标题" prop="title">
              <ElInput v-model="form.title" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="副标题" prop="subtitle">
              <ElInput v-model="form.subtitle" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="图片 Alt" prop="image_alt">
              <ElInput v-model="form.image_alt" placeholder="留空时前台使用标题" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="24">
            <ElFormItem label="描述" prop="description">
              <ElInput v-model="form.description" type="textarea" :rows="3" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="桌面图片" prop="image">
              <sa-image-upload v-model="form.image" :limit="1" :multiple="false" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="移动图片" prop="mobile_image">
              <sa-image-upload v-model="form.mobile_image" :limit="1" :multiple="false" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="主按钮文案" prop="button_text">
              <ElInput v-model="form.button_text" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="主按钮链接" prop="button_url">
              <ElInput v-model="form.button_url" placeholder="/page/products" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="次按钮文案" prop="secondary_button_text">
              <ElInput v-model="form.secondary_button_text" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="次按钮链接" prop="secondary_button_url">
              <ElInput v-model="form.secondary_button_url" placeholder="/page/contact" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="打开方式" prop="target">
              <ElSelect v-model="form.target">
                <ElOption label="当前窗口" value="_self" />
                <ElOption label="新窗口" value="_blank" />
              </ElSelect>
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="状态" prop="status">
              <SaRadio v-model="form.status" dict="data_status" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="24">
            <ElFormItem label="备注" prop="remark">
              <ElInput v-model="form.remark" type="textarea" :rows="2" />
            </ElFormItem>
          </ElCol>
        </ElRow>
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
  import api from '../api/carousel'
  import languageApi from '../api/language'

  const rows = ref<any[]>([])
  const languages = ref<any[]>([])
  const loading = ref(false)
  const dialogVisible = ref(false)
  const formRef = ref<FormInstance>()
  const search = reactive({
    lang_code: '',
    position: '',
    title: '',
    status: '' as number | string
  })
  const form = reactive({
    id: undefined as number | undefined,
    lang_code: 'zh-CN',
    position: 'home',
    title: '',
    subtitle: '',
    description: '',
    image: '',
    mobile_image: '',
    image_alt: '',
    button_text: '',
    button_url: '',
    secondary_button_text: '',
    secondary_button_url: '',
    target: '_self',
    sort: 100,
    status: 1,
    remark: ''
  })

  const rules: FormRules = {
    lang_code: [{ required: true, message: '语言必填', trigger: 'change' }],
    position: [{ required: true, message: '位置必填', trigger: 'change' }],
    title: [{ required: true, message: '标题必填', trigger: 'blur' }],
    target: [{ required: true, message: '打开方式必填', trigger: 'change' }]
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
    Object.assign(search, { lang_code: '', position: '', title: '', status: '' })
    loadData()
  }

  const resetForm = () => {
    Object.assign(form, {
      id: undefined,
      lang_code: languages.value[0]?.code || 'zh-CN',
      position: 'home',
      title: '',
      subtitle: '',
      description: '',
      image: '',
      mobile_image: '',
      image_alt: '',
      button_text: '',
      button_url: '',
      secondary_button_text: '',
      secondary_button_url: '',
      target: '_self',
      sort: 100,
      status: 1,
      remark: ''
    })
  }

  const openDialog = (row?: any) => {
    resetForm()
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
    await ElMessageBox.confirm('确定要删除该轮播图吗？', '删除轮播图', { type: 'warning' })
    await api.delete({ ids: [id] })
    ElMessage.success('删除成功')
    loadData()
  }

  const positionText = (position: string) => {
    const map: Record<string, string> = {
      home: '首页'
    }

    return map[position] || position
  }

  onMounted(async () => {
    await loadLanguages()
    loadData()
  })
</script>

<style scoped>
  .carousel-thumb {
    width: 84px;
    height: 48px;
    overflow: hidden;
    border-radius: 6px;
  }
</style>
