<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader :loading="loading" @refresh="loadData">
        <template #left>
          <ElButton v-permission="'b8cms:template:save'" @click="openDialog()">
            <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
            新增模板
          </ElButton>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="template_key" label="模板标识" width="160" />
        <ElTableColumn prop="name" label="模板名称" />
        <ElTableColumn prop="description" label="说明" />
        <ElTableColumn label="启用" width="100">
          <template #default="{ row }">
            <ElTag :type="row.is_active === 1 ? 'success' : 'info'">
              {{ row.is_active === 1 ? '启用' : '未启用' }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="sort" label="排序" width="100" />
        <ElTableColumn label="状态" width="120">
          <template #default="{ row }">
            <ElSwitch
              v-model="row.status"
              :active-value="1"
              :inactive-value="2"
              @change="(status) => changeStatus(row.id, Number(status))"
            />
          </template>
        </ElTableColumn>
        <ElTableColumn label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <SaButton
                v-permission="'b8cms:template:activate'"
                type="primary"
                icon="ri:checkbox-circle-line"
                tool-tip="启用模板"
                @click="activate(row.id)"
              />
              <SaButton
                v-permission="'b8cms:template:update'"
                type="secondary"
                @click="openDialog(row)"
              />
              <SaButton
                v-permission="'b8cms:template:destroy'"
                type="error"
                @click="deleteRow(row.id)"
              />
            </ElSpace>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog
      v-model="dialogVisible"
      :title="form.id ? '编辑模板' : '新增模板'"
      width="720px"
      :close-on-click-modal="false"
    >
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
        <ElFormItem label="模板标识" prop="template_key">
          <ElInput v-model="form.template_key" placeholder="default" />
        </ElFormItem>
        <ElFormItem label="模板名称" prop="name">
          <ElInput v-model="form.name" />
        </ElFormItem>
        <ElFormItem label="说明" prop="description">
          <ElInput v-model="form.description" type="textarea" :rows="3" />
        </ElFormItem>
        <ElFormItem label="预览图" prop="preview_image">
          <SaImageUpload v-model="form.preview_image" :limit="1" :multiple="false" />
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
  import api from '../api/template'

  const rows = ref<any[]>([])
  const loading = ref(false)
  const dialogVisible = ref(false)
  const formRef = ref<FormInstance>()
  const form = reactive({
    id: undefined as number | undefined,
    template_key: '',
    name: '',
    description: '',
    preview_image: '',
    options: {},
    sort: 100,
    status: 1
  })

  const rules: FormRules = {
    template_key: [{ required: true, message: '模板标识必填', trigger: 'blur' }],
    name: [{ required: true, message: '模板名称必填', trigger: 'blur' }]
  }

  const resetForm = () => {
    Object.assign(form, {
      id: undefined,
      template_key: '',
      name: '',
      description: '',
      preview_image: '',
      options: {},
      sort: 100,
      status: 1
    })
  }

  const loadData = async () => {
    loading.value = true
    try {
      const data = await api.list({ saiType: 'all' })
      rows.value = Array.isArray(data) ? data : data?.data || []
    } finally {
      loading.value = false
    }
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

  const activate = async (id: number) => {
    await api.activate({ id })
    ElMessage.success('模板已启用')
    loadData()
  }

  const deleteRow = async (id: number) => {
    await ElMessageBox.confirm('确定要删除该模板吗？', '删除模板', { type: 'warning' })
    await api.delete({ ids: [id] })
    ElMessage.success('删除成功')
    loadData()
  }

  onMounted(loadData)
</script>
