<template>
  <div class="art-full-height">
    <ElCard class="art-table-card" shadow="never">
      <ElForm :model="search" inline class="mb-4">
        <ElFormItem label="关键词">
          <ElInput v-model="search.keyword" clearable placeholder="昵称/邮箱/内容/文章" />
        </ElFormItem>
        <ElFormItem label="状态">
          <ElSelect v-model="search.status" clearable placeholder="全部" style="width: 140px">
            <ElOption label="已通过" :value="1" />
            <ElOption label="待审核" :value="2" />
            <ElOption label="已屏蔽" :value="3" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="邮箱">
          <ElInput v-model="search.email" clearable />
        </ElFormItem>
        <ElFormItem label="IP">
          <ElInput v-model="search.ip" clearable />
        </ElFormItem>
        <ElFormItem>
          <ElButton type="primary" @click="loadData">搜索</ElButton>
          <ElButton @click="resetSearch">重置</ElButton>
        </ElFormItem>
      </ElForm>

      <ArtTableHeader :loading="loading" @refresh="loadData" />

      <ElTable v-loading="loading" :data="rows" row-key="id">
        <ElTableColumn prop="content_title" label="文章" min-width="180" />
        <ElTableColumn prop="nickname" label="昵称" width="120" />
        <ElTableColumn prop="email" label="邮箱" width="190" />
        <ElTableColumn label="评论内容" min-width="260">
          <template #default="{ row }">
            <ElTooltip :content="row.comment" placement="top" :show-after="400">
              <span class="line-clamp-1">{{ row.comment }}</span>
            </ElTooltip>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="ip" label="IP" width="140" />
        <ElTableColumn prop="create_time" label="提交时间" width="180" />
        <ElTableColumn label="状态" width="110">
          <template #default="{ row }">
            <ElTag :type="statusTag(row.status)">{{ statusText(row.status) }}</ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="操作" width="190" fixed="right">
          <template #default="{ row }">
            <ElSpace>
              <SaButton type="success" @click="openDrawer(row)" />
              <ElDropdown trigger="click" @command="(status) => handleStatus(row, Number(status))">
                <ElButton text>
                  <ArtSvgIcon icon="ri:more-2-fill" />
                </ElButton>
                <template #dropdown>
                  <ElDropdownMenu>
                    <ElDropdownItem :command="1">设为已通过</ElDropdownItem>
                    <ElDropdownItem :command="2">设为待审核</ElDropdownItem>
                    <ElDropdownItem :command="3">设为已屏蔽</ElDropdownItem>
                  </ElDropdownMenu>
                </template>
              </ElDropdown>
              <SaButton v-permission="'b8cms:comment:destroy'" type="error" @click="deleteRow(row.id)" />
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

    <ElDrawer v-model="drawerVisible" size="620px" title="评论审计">
      <ElDescriptions :column="1" border>
        <ElDescriptionsItem label="文章">{{ detail.content_title }}</ElDescriptionsItem>
        <ElDescriptionsItem label="语言">{{ detail.lang_code }}</ElDescriptionsItem>
        <ElDescriptionsItem label="昵称">{{ detail.nickname }}</ElDescriptionsItem>
        <ElDescriptionsItem label="邮箱">{{ detail.email }}</ElDescriptionsItem>
        <ElDescriptionsItem label="个人网站">{{ detail.website }}</ElDescriptionsItem>
        <ElDescriptionsItem label="评论内容">{{ detail.comment }}</ElDescriptionsItem>
        <ElDescriptionsItem label="状态">{{ statusText(detail.status) }}</ElDescriptionsItem>
        <ElDescriptionsItem label="屏蔽原因">{{ detail.block_reason }}</ElDescriptionsItem>
        <ElDescriptionsItem label="命中规则">{{ detail.matched_rule }}</ElDescriptionsItem>
        <ElDescriptionsItem label="IP">{{ detail.ip }}</ElDescriptionsItem>
        <ElDescriptionsItem label="浏览器指纹">{{ detail.browser_fingerprint }}</ElDescriptionsItem>
        <ElDescriptionsItem label="UA">{{ detail.user_agent }}</ElDescriptionsItem>
        <ElDescriptionsItem label="来源页面">{{ detail.source_url }}</ElDescriptionsItem>
        <ElDescriptionsItem label="提交时间">{{ detail.create_time }}</ElDescriptionsItem>
        <ElDescriptionsItem label="审核时间">{{ detail.reviewed_at }}</ElDescriptionsItem>
      </ElDescriptions>
      <ElDivider />
      <ElForm label-width="90px">
        <ElFormItem label="处理状态">
          <ElSelect v-model="handleForm.status">
            <ElOption label="已通过" :value="1" />
            <ElOption label="待审核" :value="2" />
            <ElOption label="已屏蔽" :value="3" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem v-if="handleForm.status === 3" label="屏蔽原因">
          <ElInput v-model="handleForm.block_reason" type="textarea" :rows="3" />
        </ElFormItem>
        <ElFormItem>
          <ElButton v-permission="'b8cms:comment:handle'" type="primary" @click="submitHandle">保存处理结果</ElButton>
        </ElFormItem>
      </ElForm>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import api from '../api/comment'

  const rows = ref<any[]>([])
  const loading = ref(false)
  const drawerVisible = ref(false)
  const search = reactive({ keyword: '', status: '' as number | string, email: '', ip: '' })
  const pagination = reactive({ page: 1, limit: 10, total: 0 })
  const detail = reactive<any>({})
  const handleForm = reactive({ id: 0, status: 1, block_reason: '' })

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
    Object.assign(search, { keyword: '', status: '', email: '', ip: '' })
    pagination.page = 1
    loadData()
  }

  const openDrawer = async (row: any) => {
    const data = await api.read(row.id)
    Object.assign(detail, data)
    Object.assign(handleForm, {
      id: data.id,
      status: data.status || 1,
      block_reason: data.block_reason || ''
    })
    drawerVisible.value = true
  }

  const submitHandle = async () => {
    await api.handle(handleForm)
    ElMessage.success('处理成功')
    drawerVisible.value = false
    loadData()
  }

  const handleStatus = async (row: any, status: number) => {
    await api.handle({
      id: row.id,
      status,
      block_reason: status === 3 ? row.block_reason || '后台手动屏蔽' : ''
    })
    ElMessage.success('状态已更新')
    loadData()
  }

  const deleteRow = async (id: number) => {
    await ElMessageBox.confirm('确定要删除该评论吗？', '删除评论', { type: 'warning' })
    await api.delete({ ids: [id] })
    ElMessage.success('删除成功')
    loadData()
  }

  const statusText = (status: number) => ({ 1: '已通过', 2: '待审核', 3: '已屏蔽' })[status] || '未知'
  const statusTag = (status: number): 'success' | 'warning' | 'danger' | 'info' => {
    const tagMap: Record<number, 'success' | 'warning' | 'danger' | 'info'> = {
      1: 'success',
      2: 'warning',
      3: 'danger'
    }

    return tagMap[status] || 'info'
  }

  onMounted(loadData)
</script>
