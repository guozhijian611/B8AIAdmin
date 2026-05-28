<template>
  <ElDialog
    v-model="visible"
    title="数据权限"
    width="600px"
    align-center
    class="el-dialog-border"
    @close="handleClose"
  >
    <ElForm :model="form" label-width="100px" class="mt-4">
      <ElFormItem label="角色名称">
        <ElInput v-model="form.name" disabled />
      </ElFormItem>
      <ElFormItem label="角色标识">
        <ElInput v-model="form.code" disabled />
      </ElFormItem>
      <ElFormItem label="数据边界">
        <ElSelect v-model="form.data_scope" class="w-full">
          <ElOption
            v-for="item in scopeList"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </ElSelect>
      </ElFormItem>
      <ElFormItem v-show="form.data_scope === 2" label="部门列表">
        <div class="w-full">
          <div class="flex gap-4 mb-2">
            <ElCheckbox v-model="isExpandAll" @change="toggleExpandAll">展开/折叠</ElCheckbox>
            <ElCheckbox v-model="isSelectAll" @change="toggleSelectAll">全选/全不选</ElCheckbox>
            <ElCheckbox v-model="checkCascade">父子联动</ElCheckbox>
          </div>
          <div class="border border-gray-200 rounded p-2" style="height: 300px; overflow: auto">
            <ElTree
              ref="treeRef"
              :data="deptList"
              show-checkbox
              node-key="id"
              :check-strictly="!checkCascade"
              :props="{ label: 'label' }"
            />
          </div>
        </div>
      </ElFormItem>
    </ElForm>
    <template #footer>
      <ElButton @click="handleClose">取消</ElButton>
      <ElButton type="primary" @click="savePermission">保存</ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import roleApi from '@/api/system/role'
  import deptApi from '@/api/system/dept'

  interface Props {
    modelValue: boolean
    dialogType: string
    data?: Record<string, any>
  }

  interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
  }

  const props = withDefaults(defineProps<Props>(), {
    modelValue: false,
    dialogType: 'edit',
    data: () => ({})
  })

  const emit = defineEmits<Emits>()

  const deptList = ref<Api.Common.ApiData[]>([])
  const treeRef = ref()
  const form = ref({
    id: 0,
    name: '',
    code: '',
    data_scope: 1
  })

  const isExpandAll = ref(false)
  const isSelectAll = ref(false)
  const checkCascade = ref(true)

  const scopeList = [
    { value: 1, label: '全部数据权限' },
    { value: 2, label: '自定义数据权限' },
    { value: 3, label: '本部门数据权限' },
    { value: 4, label: '本部门及以下数据权限' },
    { value: 5, label: '本人数据权限' }
  ]

  const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
  })

  watch(
    () => props.modelValue,
    (newVal) => {
      if (newVal) {
        initPage()
      }
    }
  )

  const initPage = async () => {
    form.value = {
      id: props.data?.id || 0,
      name: props.data?.name || '',
      code: props.data?.code || '',
      data_scope: props.data?.data_scope || 1
    }

    deptList.value = await deptApi.list({ tree: true })
    const res = await roleApi.deptByRole({ id: props.data?.id })
    if (res?.depts) {
      const ids = res.depts.map((item: any) => item.id)
      treeRef.value?.setCheckedKeys(ids)
    }
  }

  const handleClose = () => {
    visible.value = false
    treeRef.value?.setCheckedKeys([])
    isExpandAll.value = false
    isSelectAll.value = false
    checkCascade.value = true
  }

  const savePermission = async () => {
    const dept_ids = form.value.data_scope === 2 ? treeRef.value?.getCheckedKeys() || [] : []
    try {
      await roleApi.dataPermission({
        id: form.value.id,
        data_scope: form.value.data_scope,
        dept_ids
      })
      ElMessage.success('保存成功')
      emit('success')
      handleClose()
    } catch (error) {
      console.error(error)
    }
  }

  const toggleExpandAll = () => {
    const nodes = treeRef.value?.store.nodesMap
    Object.values(nodes || {}).forEach((node: any) => {
      node.expanded = isExpandAll.value
    })
  }

  const toggleSelectAll = () => {
    if (isSelectAll.value) {
      treeRef.value?.setCheckedNodes(deptList.value)
    } else {
      treeRef.value?.setCheckedKeys([])
    }
  }
</script>
