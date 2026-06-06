<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ElTabs v-model="search.content_type" @tab-change="loadData">
        <ElTabPane label="文章" name="article" />
        <ElTabPane label="产品" name="product" />
        <ElTabPane label="页面" name="page" />
      </ElTabs>

      <ElForm :model="search" inline class="mb-4">
        <ElFormItem label="语言">
          <ElSelect v-model="search.lang_code" clearable placeholder="全部" style="width: 150px">
            <ElOption v-for="item in languages" :key="item.code" :label="item.native_name" :value="item.code" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="标题">
          <ElInput v-model="search.title" clearable placeholder="请输入标题" />
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
          <ElButton v-permission="'b8cms:content:save'" @click="openDialog()">
            <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
            新增{{ currentTypeLabel }}
          </ElButton>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="id" label="ID" width="90" />
        <ElTableColumn prop="lang_code" label="语言" width="110" />
        <ElTableColumn prop="title" label="标题" min-width="220" />
        <ElTableColumn prop="slug" label="访问别名" width="180" />
        <ElTableColumn prop="category" label="分类" width="120" />
        <ElTableColumn v-if="search.content_type === 'product'" prop="price" label="价格" width="120" />
        <ElTableColumn v-if="search.content_type === 'product'" label="参数" width="100">
          <template #default="{ row }">
            <ElTag type="info">{{ getProductParamCount(row.extra) }} 项</ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="推荐" width="90">
          <template #default="{ row }">
            <ElTag :type="row.is_featured === 1 ? 'success' : 'info'">
              {{ row.is_featured === 1 ? '是' : '否' }}
            </ElTag>
          </template>
        </ElTableColumn>
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
              <SaButton v-permission="'b8cms:content:update'" type="secondary" @click="openDialog(row)" />
              <SaButton v-permission="'b8cms:content:destroy'" type="error" @click="deleteRow(row.id)" />
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>

      <div class="mt-4 flex justify-end">
        <ElPagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.limit"
          :total="pagination.total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadData"
          @current-change="loadData"
        />
      </div>
    </ElCard>

    <ElDialog
      v-model="dialogVisible"
      :title="form.id ? `编辑${currentTypeLabel}` : `新增${currentTypeLabel}`"
      width="980px"
      :close-on-click-modal="false"
    >
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
        <ElRow :gutter="18">
          <ElCol :span="8">
            <ElFormItem label="内容类型" prop="content_type">
              <ElSelect v-model="form.content_type">
                <ElOption label="文章" value="article" />
                <ElOption label="产品" value="product" />
                <ElOption label="页面" value="page" />
              </ElSelect>
            </ElFormItem>
          </ElCol>
          <ElCol :span="8">
            <ElFormItem label="语言" prop="lang_code">
              <ElSelect v-model="form.lang_code">
                <ElOption v-for="item in languages" :key="item.code" :label="item.native_name" :value="item.code" />
              </ElSelect>
            </ElFormItem>
          </ElCol>
          <ElCol :span="8">
            <ElFormItem label="访问别名" prop="slug">
              <ElInput v-model="form.slug" placeholder="about" />
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
            <ElFormItem label="分类" prop="category">
              <ElInput v-model="form.category" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="24">
            <ElFormItem label="摘要" prop="summary">
              <ElInput v-model="form.summary" type="textarea" :rows="2" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="24">
            <ElFormItem label="封面图" prop="cover_image">
              <sa-image-upload v-model="form.cover_image" :limit="1" :multiple="false" />
            </ElFormItem>
          </ElCol>
          <template v-if="form.content_type === 'product'">
            <ElCol :span="6">
              <ElFormItem label="价格" prop="price">
                <ElInputNumber v-model="form.price" :min="0" :precision="2" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="6">
              <ElFormItem label="币种" prop="currency">
                <ElInput v-model="form.currency" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="6">
              <ElFormItem label="库存" prop="stock">
                <ElInputNumber v-model="form.stock" :min="0" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="6">
              <ElFormItem label="SKU" prop="sku">
                <ElInput v-model="form.sku" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="24">
              <ElDivider content-position="left">产品扩展参数</ElDivider>
            </ElCol>
            <ElCol :span="24">
              <ElFormItem label="参数配置" prop="extra_schema">
                <ElInput
                  v-model="productParamSchemaText"
                  type="textarea"
                  :rows="7"
                  placeholder='[{"key":"model","label":"型号","type":"text"},{"key":"lead_time","label":"交付周期","type":"text"}]'
                />
              </ElFormItem>
            </ElCol>
            <ElCol :span="24">
              <ElFormItem label="动态表单">
                <ElSpace wrap>
                  <ElButton @click="parseProductParamSchema()">解析 JSON</ElButton>
                  <ElButton @click="useDefaultProductParamSchema">使用默认参数</ElButton>
                </ElSpace>
              </ElFormItem>
            </ElCol>
            <ElCol v-for="field in productParamFields" :key="field.key" :span="field.type === 'textarea' ? 24 : 12">
              <ElFormItem :label="field.label">
                <ElInput
                  v-if="field.type === 'textarea'"
                  v-model="productParamValues[field.key]"
                  type="textarea"
                  :rows="3"
                  :placeholder="field.placeholder"
                />
                <ElInputNumber
                  v-else-if="field.type === 'number'"
                  v-model="productParamValues[field.key]"
                  :min="field.min"
                  :max="field.max"
                  :precision="field.precision"
                  style="width: 100%"
                />
                <ElSwitch v-else-if="field.type === 'switch'" v-model="productParamValues[field.key]" />
                <ElSelect
                  v-else-if="field.type === 'select'"
                  v-model="productParamValues[field.key]"
                  clearable
                  filterable
                  :placeholder="field.placeholder"
                  style="width: 100%"
                >
                  <ElOption
                    v-for="option in field.options"
                    :key="String(option.value)"
                    :label="option.label"
                    :value="option.value"
                  />
                </ElSelect>
                <ElInput v-else v-model="productParamValues[field.key]" :placeholder="field.placeholder">
                  <template v-if="field.unit" #append>{{ field.unit }}</template>
                </ElInput>
              </ElFormItem>
            </ElCol>
          </template>
          <ElCol :span="24">
            <ElFormItem label="正文" prop="content">
              <sa-editor v-model="form.content" height="360px" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="24">
            <ElDivider content-position="left">SEO 设置</ElDivider>
          </ElCol>
          <ElCol :span="24">
            <ElFormItem label="SEO 标题" prop="seo_title">
              <ElInput v-model="form.seo_title" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="SEO 关键词" prop="seo_keywords">
              <ElInput v-model="form.seo_keywords" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="12">
            <ElFormItem label="模板文件" prop="template_file">
              <ElInput v-model="form.template_file" placeholder="article/product/page" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="24">
            <ElFormItem label="SEO 描述" prop="seo_description">
              <ElInput v-model="form.seo_description" type="textarea" :rows="2" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="8">
            <ElFormItem label="排序" prop="sort">
              <ElInputNumber v-model="form.sort" :min="0" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="8">
            <ElFormItem label="推荐" prop="is_featured">
              <SaRadio v-model="form.is_featured" dict="yes_or_no" />
            </ElFormItem>
          </ElCol>
          <ElCol :span="8">
            <ElFormItem label="状态" prop="status">
              <SaRadio v-model="form.status" dict="data_status" />
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
  import api from '../api/content'
  import languageApi from '../api/language'

  const rows = ref<any[]>([])
  const languages = ref<any[]>([])
  const loading = ref(false)
  const dialogVisible = ref(false)
  const formRef = ref<FormInstance>()
  const search = reactive({
    content_type: 'article',
    lang_code: '',
    title: ''
  })
  const pagination = reactive({ page: 1, limit: 10, total: 0 })

  const typeLabels: Record<string, string> = {
    article: '文章',
    product: '产品',
    page: '页面'
  }
  const currentTypeLabel = computed(() => typeLabels[search.content_type] || '内容')

  type ProductParamType = 'text' | 'textarea' | 'number' | 'select' | 'switch'
  type ProductParamOption = {
    label: string
    value: string | number | boolean
  }
  type ProductParamField = {
    key: string
    label: string
    type: ProductParamType
    unit?: string
    placeholder?: string
    options?: ProductParamOption[]
    min?: number
    max?: number
    precision?: number
    default?: any
  }

  const defaultProductParamSchema: ProductParamField[] = [
    { key: 'model', label: '产品型号', type: 'text', placeholder: 'B8CMS-PRO' },
    { key: 'lead_time', label: '交付周期', type: 'text', placeholder: '7 个工作日' },
    { key: 'min_order', label: '起订数量', type: 'number', unit: '套', min: 1, precision: 0, default: 1 },
    {
      key: 'deployment',
      label: '部署方式',
      type: 'select',
      options: [
        { label: 'SaaS 托管', value: 'SaaS 托管' },
        { label: '私有化部署', value: '私有化部署' },
        { label: '混合部署', value: '混合部署' }
      ]
    },
    { key: 'seo_ready', label: '支持 SEO', type: 'switch', default: true }
  ]
  const productParamSchemaText = ref('')
  const productParamFields = ref<ProductParamField[]>([])
  const productParamValues = reactive<Record<string, any>>({})

  const initialForm = {
    id: undefined as number | undefined,
    content_type: 'article',
    lang_code: 'zh-CN',
    slug: '',
    title: '',
    subtitle: '',
    category: '',
    summary: '',
    content: '',
    cover_image: '',
    images: [] as string[],
    price: 0,
    currency: 'USD',
    stock: 0,
    sku: '',
    sort: 100,
    status: 1,
    is_featured: 2,
    template_file: '',
    seo_title: '',
    seo_keywords: '',
    seo_description: '',
    extra: {} as Record<string, any>
  }
  const form = reactive({ ...initialForm })

  const rules: FormRules = {
    content_type: [{ required: true, message: '内容类型必填', trigger: 'change' }],
    lang_code: [{ required: true, message: '语言必填', trigger: 'change' }],
    slug: [{ required: true, message: '访问别名必填', trigger: 'blur' }],
    title: [{ required: true, message: '标题必填', trigger: 'blur' }]
  }

  const loadLanguages = async () => {
    const data = await languageApi.list({ saiType: 'all' })
    languages.value = Array.isArray(data) ? data : data?.data || []
    form.lang_code = languages.value[0]?.code || 'zh-CN'
  }

  const loadData = async () => {
    loading.value = true
    try {
      const data = await api.list({
        ...search,
        page: pagination.page,
        limit: pagination.limit
      })
      rows.value = data?.data || []
      pagination.total = data?.total || 0
    } finally {
      loading.value = false
    }
  }

  const resetSearch = () => {
    search.lang_code = ''
    search.title = ''
    pagination.page = 1
    loadData()
  }

  const normalizeExtra = (value: any): Record<string, any> => {
    if (!value) return {}
    if (typeof value === 'string') {
      try {
        const parsed = JSON.parse(value)
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
      } catch {
        return {}
      }
    }
    return typeof value === 'object' && !Array.isArray(value) ? { ...value } : {}
  }

  const normalizeProductOptions = (options: any): ProductParamOption[] => {
    if (!Array.isArray(options)) return []
    return options
      .map((option) => {
        if (option && typeof option === 'object') {
          const value = option.value ?? option.label
          return {
            label: String(option.label ?? value ?? ''),
            value
          }
        }
        return {
          label: String(option),
          value: option
        }
      })
      .filter((option) => option.label !== '')
  }

  const toOptionalNumber = (value: any): number | undefined => {
    if (value === undefined || value === null || value === '') return undefined
    const numberValue = Number(value)
    return Number.isFinite(numberValue) ? numberValue : undefined
  }

  const defaultValueByType = (type: ProductParamType) => {
    if (type === 'number') return 0
    if (type === 'switch') return false
    return ''
  }

  const normalizeProductParamSchema = (value: any): ProductParamField[] => {
    const source: Record<string, any>[] = Array.isArray(value) ? value : Array.isArray(value?.fields) ? value.fields : []
    return source
      .map((item: Record<string, any>): ProductParamField | null => {
        if (!item || typeof item !== 'object') return null
        const key = String(item.key ?? item.name ?? '').trim()
        if (!key) return null
        const type: ProductParamType = ['text', 'textarea', 'number', 'select', 'switch'].includes(item.type)
          ? item.type
          : 'text'
        return {
          key,
          label: String(item.label ?? key),
          type,
          unit: item.unit ? String(item.unit) : undefined,
          placeholder: item.placeholder ? String(item.placeholder) : undefined,
          options: normalizeProductOptions(item.options),
          min: toOptionalNumber(item.min),
          max: toOptionalNumber(item.max),
          precision: toOptionalNumber(item.precision),
          default: item.default
        }
      })
      .filter((item: ProductParamField | null): item is ProductParamField => Boolean(item))
  }

  const clearProductParamValues = () => {
    Object.keys(productParamValues).forEach((key) => {
      delete productParamValues[key]
    })
  }

  const syncProductParamValues = (fields: ProductParamField[], values: Record<string, any> = {}) => {
    clearProductParamValues()
    fields.forEach((field) => {
      if (Object.prototype.hasOwnProperty.call(values, field.key)) {
        productParamValues[field.key] = values[field.key]
        return
      }
      productParamValues[field.key] = field.default ?? defaultValueByType(field.type)
    })
  }

  const setupProductExtraEditor = (extraValue: any = form.extra) => {
    const extra = normalizeExtra(extraValue)
    const schema = normalizeProductParamSchema(extra.product_params_schema)
    const fields = schema.length > 0 ? schema : defaultProductParamSchema
    productParamFields.value = fields
    productParamSchemaText.value = JSON.stringify(fields, null, 2)
    syncProductParamValues(fields, normalizeExtra(extra.product_params))
    form.extra = extra
  }

  const parseProductParamSchema = (showMessage = true): ProductParamField[] | null => {
    try {
      const parsed = JSON.parse(productParamSchemaText.value || '[]')
      const fields = normalizeProductParamSchema(parsed)
      if (fields.length === 0) {
        throw new Error('empty schema')
      }
      productParamFields.value = fields
      syncProductParamValues(fields, { ...productParamValues })
      if (showMessage) ElMessage.success('参数表单已生成')
      return fields
    } catch {
      if (showMessage) ElMessage.error('参数配置 JSON 格式不正确')
      return null
    }
  }

  const useDefaultProductParamSchema = () => {
    productParamSchemaText.value = JSON.stringify(defaultProductParamSchema, null, 2)
    parseProductParamSchema()
  }

  const createProductExtra = (fields: ProductParamField[]) => {
    const extra = normalizeExtra(form.extra)
    const values: Record<string, any> = {}
    fields.forEach((field) => {
      const value = productParamValues[field.key]
      if (value === undefined || value === null || value === '') return
      values[field.key] = field.type === 'number' ? Number(value) : value
    })
    return {
      ...extra,
      product_params_schema: fields,
      product_params: values
    }
  }

  const getProductParamCount = (extraValue: any) => {
    const extra = normalizeExtra(extraValue)
    const values = normalizeExtra(extra.product_params)
    return Object.values(values).filter((value) => value !== undefined && value !== null && value !== '').length
  }

  const resetForm = () => {
    Object.assign(form, { ...initialForm, images: [], extra: {}, content_type: search.content_type })
    form.lang_code = languages.value[0]?.code || 'zh-CN'
    form.template_file = search.content_type
    productParamFields.value = []
    productParamSchemaText.value = ''
    clearProductParamValues()
    if (form.content_type === 'product') {
      setupProductExtraEditor()
    }
  }

  const openDialog = async (row?: any) => {
    resetForm()
    if (row?.id) {
      const detail = await api.read(row.id)
      Object.assign(form, { ...detail, extra: normalizeExtra(detail.extra) })
      if (form.content_type === 'product') {
        setupProductExtraEditor(form.extra)
      }
    }
    dialogVisible.value = true
  }

  const submit = async () => {
    await formRef.value?.validate()
    const fields = form.content_type === 'product' ? parseProductParamSchema(false) : []
    if (form.content_type === 'product' && !fields) {
      ElMessage.error('参数配置 JSON 格式不正确')
      return
    }
    const payload = {
      ...JSON.parse(JSON.stringify(form)),
      extra: form.content_type === 'product' ? createProductExtra(fields || []) : normalizeExtra(form.extra)
    }
    form.id ? await api.update(payload) : await api.save(payload)
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadData()
  }

  watch(
    () => form.content_type,
    (type) => {
      if (type === 'product' && productParamFields.value.length === 0) {
        setupProductExtraEditor()
      }
    }
  )

  const changeStatus = async (id: number, status: number) => {
    await api.changeStatus({ id, status })
    ElMessage.success('状态已更新')
    loadData()
  }

  const deleteRow = async (id: number) => {
    await ElMessageBox.confirm('确定要删除该内容吗？', '删除内容', { type: 'warning' })
    await api.delete({ ids: [id] })
    ElMessage.success('删除成功')
    loadData()
  }

  onMounted(async () => {
    await loadLanguages()
    loadData()
  })
</script>
