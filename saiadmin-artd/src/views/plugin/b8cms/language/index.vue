<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader :loading="loading" @refresh="loadData">
        <template #left>
          <ElSpace wrap>
            <ElButton v-permission="'b8cms:language:save'" @click="openDialog()">
              <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
              新增语言
            </ElButton>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="code" label="语言标识" width="140" />
        <ElTableColumn prop="name" label="语言名称" />
        <ElTableColumn prop="native_name" label="本地化名称" />
        <ElTableColumn prop="locale" label="Locale" width="120" />
        <ElTableColumn label="默认" width="100">
          <template #default="{ row }">
            <ElTag :type="row.is_default === 1 ? 'success' : 'info'">
              {{ row.is_default === 1 ? '默认' : '普通' }}
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
                v-permission="'b8cms:language:setDefault'"
                type="primary"
                icon="ri:star-line"
                tool-tip="设为默认"
                @click="setDefault(row.id)"
              />
              <SaButton
                v-permission="'b8cms:language:update'"
                type="secondary"
                @click="openDialog(row)"
              />
              <SaButton
                v-permission="'b8cms:language:destroy'"
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
      :title="form.id ? '编辑语言' : '新增语言'"
      width="560px"
      :close-on-click-modal="false"
    >
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
        <ElFormItem label="语言标识" prop="code">
          <ElInput v-model="form.code" placeholder="zh-CN" />
        </ElFormItem>
        <ElFormItem label="语言名称" prop="name">
          <ElInput v-model="form.name" placeholder="Chinese" />
        </ElFormItem>
        <ElFormItem label="本地化名称" prop="native_name">
          <ElInput v-model="form.native_name" placeholder="简体中文" />
        </ElFormItem>
        <ElFormItem label="Locale" prop="locale">
          <ElInput v-model="form.locale" placeholder="zh_CN" />
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
  import api from '../api/language'

  const rows = ref<any[]>([])
  const loading = ref(false)
  const dialogVisible = ref(false)
  const formRef = ref<FormInstance>()
  const form = reactive({
    id: undefined as number | undefined,
    code: '',
    name: '',
    native_name: '',
    locale: '',
    sort: 100,
    status: 1
  })

  const rules: FormRules = {
    code: [{ required: true, message: '语言标识必填', trigger: 'blur' }],
    name: [{ required: true, message: '语言名称必填', trigger: 'blur' }],
    native_name: [{ required: true, message: '本地化名称必填', trigger: 'blur' }]
  }

  const resetForm = () => {
    Object.assign(form, {
      id: undefined,
      code: '',
      name: '',
      native_name: '',
      locale: '',
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

  const setDefault = async (id: number) => {
    await api.setDefault({ id })
    ElMessage.success('默认语言已更新')
    loadData()
  }

  const deleteRow = async (id: number) => {
    await ElMessageBox.confirm('确定要删除该语言吗？', '删除语言', { type: 'warning' })
    await api.delete({ ids: [id] })
    ElMessage.success('删除成功')
    loadData()
  }

  onMounted(loadData)
</script>
