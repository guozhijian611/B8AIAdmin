<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ElForm :model="search" inline class="mb-4">
        <ElFormItem label="关键词">
          <ElInput v-model="search.keyword" clearable placeholder="姓名/邮箱/电话/主题" />
        </ElFormItem>
        <ElFormItem label="状态">
          <ElSelect v-model="search.status" clearable placeholder="全部" style="width: 140px">
            <ElOption label="待处理" :value="1" />
            <ElOption label="已处理" :value="2" />
            <ElOption label="已忽略" :value="3" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem>
          <ElButton type="primary" @click="loadData">搜索</ElButton>
          <ElButton @click="resetSearch">重置</ElButton>
        </ElFormItem>
      </ElForm>

      <ArtTableHeader :loading="loading" @refresh="loadData" />

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="name" label="姓名" width="120" />
        <ElTableColumn prop="email" label="邮箱" width="180" />
        <ElTableColumn prop="phone" label="电话" width="140" />
        <ElTableColumn prop="subject" label="主题" />
        <ElTableColumn prop="lang_code" label="语言" width="100" />
        <ElTableColumn prop="create_time" label="提交时间" width="180" />
        <ElTableColumn label="状态" width="110">
          <template #default="{ row }">
            <ElTag :type="statusTag(row.status)">{{ statusText(row.status) }}</ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <SaButton type="success" @click="openDialog(row)" />
              <SaButton v-permission="'b8cms:contact:destroy'" type="error" @click="deleteRow(row.id)" />
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

    <ElDrawer v-model="drawerVisible" size="560px" title="联系留言">
      <ElDescriptions :column="1" border>
        <ElDescriptionsItem label="姓名">{{ detail.name }}</ElDescriptionsItem>
        <ElDescriptionsItem label="邮箱">{{ detail.email }}</ElDescriptionsItem>
        <ElDescriptionsItem label="电话">{{ detail.phone }}</ElDescriptionsItem>
        <ElDescriptionsItem label="公司">{{ detail.company }}</ElDescriptionsItem>
        <ElDescriptionsItem label="主题">{{ detail.subject }}</ElDescriptionsItem>
        <ElDescriptionsItem label="内容">{{ detail.message }}</ElDescriptionsItem>
        <ElDescriptionsItem label="来源">{{ detail.source }}</ElDescriptionsItem>
        <ElDescriptionsItem label="IP">{{ detail.ip }}</ElDescriptionsItem>
      </ElDescriptions>
      <ElDivider />
      <ElForm label-width="90px">
        <ElFormItem label="处理状态">
          <ElSelect v-model="handleForm.status">
            <ElOption label="已处理" :value="2" />
            <ElOption label="已忽略" :value="3" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="处理备注">
          <ElInput v-model="handleForm.reply_content" type="textarea" :rows="4" />
        </ElFormItem>
        <ElFormItem>
          <ElButton type="primary" @click="submitHandle">保存处理结果</ElButton>
        </ElFormItem>
      </ElForm>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import api from '../api/contact'

  const rows = ref<any[]>([])
  const loading = ref(false)
  const drawerVisible = ref(false)
  const search = reactive({ keyword: '', status: '' as number | string })
  const pagination = reactive({ page: 1, limit: 10, total: 0 })
  const detail = reactive<any>({})
  const handleForm = reactive({ id: 0, status: 2, reply_content: '' })

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
    Object.assign(search, { keyword: '', status: '' })
    pagination.page = 1
    loadData()
  }

  const openDialog = async (row: any) => {
    const data = await api.read(row.id)
    Object.assign(detail, data)
    Object.assign(handleForm, {
      id: data.id,
      status: data.status === 3 ? 3 : 2,
      reply_content: data.reply_content || ''
    })
    drawerVisible.value = true
  }

  const submitHandle = async () => {
    await api.handle(handleForm)
    ElMessage.success('处理成功')
    drawerVisible.value = false
    loadData()
  }

  const deleteRow = async (id: number) => {
    await ElMessageBox.confirm('确定要删除该留言吗？', '删除留言', { type: 'warning' })
    await api.delete({ ids: [id] })
    ElMessage.success('删除成功')
    loadData()
  }

  const statusText = (status: number) => ({ 1: '待处理', 2: '已处理', 3: '已忽略' })[status] || '未知'
  const statusTag = (status: number): 'warning' | 'success' | 'info' => {
    const tagMap: Record<number, 'warning' | 'success' | 'info'> = {
      1: 'warning',
      2: 'success',
      3: 'info'
    }

    return tagMap[status] || 'info'
  }

  onMounted(loadData)
</script>
