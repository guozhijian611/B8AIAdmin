<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ElForm :model="search" inline class="mb-4">
        <ElFormItem label="类型">
          <ElSelect v-model="search.rule_type" clearable placeholder="全部" style="width: 140px">
            <ElOption label="屏蔽词" value="word" />
            <ElOption label="邮箱" value="email" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="匹配">
          <ElSelect v-model="search.match_type" clearable placeholder="全部" style="width: 140px">
            <ElOption label="包含" value="contains" />
            <ElOption label="完全匹配" value="exact" />
            <ElOption label="邮箱域名" value="domain" />
            <ElOption label="正则" value="regex" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="规则值">
          <ElInput v-model="search.value" clearable />
        </ElFormItem>
        <ElFormItem label="状态">
          <ElSelect v-model="search.status" clearable placeholder="全部" style="width: 120px">
            <ElOption label="启用" :value="1" />
            <ElOption label="禁用" :value="2" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem>
          <ElButton type="primary" @click="loadData">搜索</ElButton>
          <ElButton @click="resetSearch">重置</ElButton>
        </ElFormItem>
      </ElForm>

      <ArtTableHeader :loading="loading" @refresh="loadData">
        <template #left>
          <ElButton v-permission="'b8cms:comment-filter:save'" @click="openDialog()">
            <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
            新增规则
          </ElButton>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn label="类型" width="110">
          <template #default="{ row }">{{ ruleTypeText(row.rule_type) }}</template>
        </ElTableColumn>
        <ElTableColumn label="匹配方式" width="120">
          <template #default="{ row }">{{ matchTypeText(row.match_type) }}</template>
        </ElTableColumn>
        <ElTableColumn prop="value" label="规则值" min-width="220" />
        <ElTableColumn prop="description" label="说明" min-width="220" />
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
        <ElTableColumn prop="create_time" label="创建时间" width="180" />
        <ElTableColumn label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <SaButton v-permission="'b8cms:comment-filter:update'" type="secondary" @click="openDialog(row)" />
              <SaButton v-permission="'b8cms:comment-filter:destroy'" type="error" @click="deleteRow(row.id)" />
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>

      <div class="mt-4 flex justify-end">
        <ElPagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.limit"
          :total="pagination.total"
          layout="total, prev, pager, next"
          @current-change="loadData"
        />
      </div>
    </ElCard>

    <ElDialog v-model="dialogVisible" :title="form.id ? '编辑屏蔽规则' : '新增屏蔽规则'" width="680px">
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
        <ElFormItem label="规则类型" prop="rule_type">
          <ElRadioGroup v-model="form.rule_type">
            <ElRadioButton label="word">屏蔽词</ElRadioButton>
            <ElRadioButton label="email">邮箱</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <ElFormItem label="匹配方式" prop="match_type">
          <ElSelect v-model="form.match_type">
            <ElOption label="包含" value="contains" />
            <ElOption label="完全匹配" value="exact" />
            <ElOption v-if="form.rule_type === 'email'" label="邮箱域名" value="domain" />
            <ElOption label="正则" value="regex" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="规则值" prop="value">
          <ElInput v-model="form.value" />
        </ElFormItem>
        <ElFormItem label="说明">
          <ElInput v-model="form.description" type="textarea" :rows="3" />
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
  import api from '../api/comment-filter'

  const rows = ref<any[]>([])
  const loading = ref(false)
  const dialogVisible = ref(false)
  const formRef = ref<FormInstance>()
  const search = reactive({
    rule_type: '',
    match_type: '',
    value: '',
    status: '' as number | string
  })
  const pagination = reactive({ page: 1, limit: 10, total: 0 })
  const form = reactive({
    id: undefined as number | undefined,
    rule_type: 'word',
    match_type: 'contains',
    value: '',
    description: '',
    status: 1
  })

  const rules: FormRules = {
    rule_type: [{ required: true, message: '规则类型必填', trigger: 'change' }],
    match_type: [{ required: true, message: '匹配方式必填', trigger: 'change' }],
    value: [{ required: true, message: '规则值必填', trigger: 'blur' }]
  }

  watch(
    () => form.rule_type,
    () => {
      if (form.rule_type !== 'email' && form.match_type === 'domain') {
        form.match_type = 'contains'
      }
    }
  )

  const loadData = async () => {
    loading.value = true
    try {
      const data = await api.list({ ...search, page: pagination.page, limit: pagination.limit })
      rows.value = data?.data || []
      pagination.total = data?.total || 0
    } finally {
      loading.value = false
    }
  }

  const resetSearch = () => {
    Object.assign(search, { rule_type: '', match_type: '', value: '', status: '' })
    pagination.page = 1
    loadData()
  }

  const openDialog = (row?: any) => {
    Object.assign(form, {
      id: undefined,
      rule_type: 'word',
      match_type: 'contains',
      value: '',
      description: '',
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
    await ElMessageBox.confirm('确定要删除该屏蔽规则吗？', '删除规则', { type: 'warning' })
    await api.delete({ ids: [id] })
    ElMessage.success('删除成功')
    loadData()
  }

  const ruleTypeText = (type: string) => {
    const map: Record<string, string> = { word: '屏蔽词', email: '邮箱' }
    return map[type] || type
  }
  const matchTypeText = (type: string) => {
    const map: Record<string, string> = { contains: '包含', exact: '完全匹配', domain: '邮箱域名', regex: '正则' }
    return map[type] || type
  }

  onMounted(loadData)
</script>
