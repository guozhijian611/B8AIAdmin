---
name: SaiAdmin 开发指南
description: 基于 Webman 的 SaiAdmin 后台管理系统开发规范，包含前后端开发模板、SAI组件库使用和分层架构指南
---

# SaiAdmin 开发指南

SaiAdmin 是基于 Webman 的高性能后台管理系统框架，采用 MVC + Logic 分层架构，支持 ThinkORM 和 Eloquent ORM。

## 通用项目经验与需确认项

进入任意 SaiAdmin/Webman 项目后，先找项目级约定文件，例如 `AGENTS.MD`、`README.md`、部署脚本或团队文档。以下经验是通用排查原则，但目录、数据库、部署目标、生成策略等项目差异必须先确认；无法从仓库判断时，直接询问用户。

- 确认后端目录、前端目录、插件目录和当前数据库，不要把一个项目的路径套到另一个项目。
- 确认业务代码边界，通常不要把业务功能直接写进 SaiAdmin 核心插件，除非是在修复框架集成问题或用户明确要求。
- 确认项目是否使用插件安装 SQL。若项目不走安装流程，不要新增或编辑 `db/install.sql`；如需沉淀数据库变更，询问用户应放在补丁 SQL、迁移文件还是直接以当前数据库为准。
- 确认用户讨论的是本地环境、测试环境还是线上环境；涉及“刚刚生成”“当前数据”“截图里的记录”时，不要自动联想到远端库。
- 确认是否允许运行前端构建、依赖安装、数据库同步、核心升级等耗时或高影响操作；不确定就先问。
- `php webman sai:orm`、`php webman sai:upgrade`、生产部署、数据库覆盖等操作必须有明确用户授权。

### 通用常见误判

- 菜单或按钮权限写入数据库后仍不显示，通常先检查 SaiAdmin 用户菜单/权限缓存和旧登录会话。
- SaiCode 生成基础 CRUD 后，仍必须人工检查菜单、按钮权限、`Permission` 注解、前端 API 路径和 git diff。
- Webman 是常驻进程；改路由、配置、控制器、Logic、服务类后，通常需要重启进程再验证。
- 前端代理前缀不等于后端真实路由前缀；以后端 `php webman route:list` 和前端请求配置能对上为准。
- 部署目标、静态资源目录、`.env`、`runtime/`、上传目录、存储目录都必须按当前项目确认，不能沿用其他项目经验。

## 📁 目录结构

### 后端目录结构 (插件模式)

```
plugin/[module]/
├── app/
│   ├── admin/                  # 管理后台
│   │   ├── controller/         # 控制器
│   │   │   └── [business]/     # 业务分组
│   │   │       ├── [Table1]Controller.php
│   │   │       └── [Table2]Controller.php
│   │   ├── logic/              # 逻辑层
│   │   │   └── [business]/
│   │   │       ├── [Table1]Logic.php
│   │   │       └── [Table2]Logic.php
│   │   └── validate/           # 验证器
│   │       └── [business]/
│   │           ├── [Table1]Validate.php
│   │           └── [Table2]Validate.php
│   ├── api/                    # API接口
│   └── model/                  # 数据模型 (可共用)
│       └── [business]/
│           ├── [Table1].php
│           └── [Table2].php
├── config/
│   └── route.php               # 路由配置
└── db/
    └── install.sql             # 可选插件安装脚本；是否使用必须按项目确认
```

### 前端目录结构

```
src/views/plugin/
└── [插件名]/
    ├── api/                    # API 接口层
    │   └── [模块名]/
    │       ├── article.ts
    │       └── category.ts
    └── [模块名]/               # 视图页面层
        └── [功能名]/
            ├── index.vue       # 主页面
            └── modules/
                ├── edit-dialog.vue    # 编辑弹窗
                └── table-search.vue   # 搜索表单
```

---

## 🔧 后端开发模板

### 1. 控制器 (Controller)

```php
<?php
namespace plugin\your_plugin\app\admin\controller\[business];

use plugin\saiadmin\basic\BaseController;
use plugin\your_plugin\app\admin\logic\[business]\[Table]Logic;
use plugin\your_plugin\app\admin\validate\[business]\[Table]Validate;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

class [Table]Controller extends BaseController
{
    public function __construct()
    {
        $this->logic = new [Table]Logic();
        $this->validate = new [Table]Validate();
        parent::__construct();
    }

    /**
     * 数据列表
     */
    #[Permission('数据列表', '[module]:[business]:[table]:index')]
    public function index(Request $request): Response
    {
        $where = $request->more([
            ['name', ''],
            ['status', ''],
        ]);
        $query = $this->logic->search($where);
        $data = $this->logic->getList($query);
        return $this->success($data);
    }

    /**
     * 读取数据
     */
    #[Permission('数据读取', '[module]:[business]:[table]:read')]
    public function read(Request $request): Response
    {
        $id = $request->input('id');
        $model = $this->logic->read($id);
        $data = is_array($model) ? $model : $model->toArray();
        return $this->success($data);
    }

    /**
     * 保存数据
     */
    #[Permission('数据添加', '[module]:[business]:[table]:save')]
    public function save(Request $request): Response
    {
        $data = $request->post();
        $this->validate('save', $data);
        $result = $this->logic->add($data);
        return $result ? $this->success('添加成功') : $this->fail('添加失败');
    }

    /**
     * 更新数据
     */
    #[Permission('数据修改', '[module]:[business]:[table]:update')]
    public function update(Request $request): Response
    {
        $data = $request->post();
        $this->validate('update', $data);
        $result = $this->logic->edit($data['id'], $data);
        return $result ? $this->success('修改成功') : $this->fail('修改失败');
    }

    /**
     * 删除数据
     */
    #[Permission('数据删除', '[module]:[business]:[table]:destroy')]
    public function destroy(Request $request): Response
    {
        $ids = $request->post('ids');
        if (empty($ids)) {
            return $this->fail('请选择要删除的数据');
        }
        $result = $this->logic->destroy($ids);
        return $result ? $this->success('删除成功') : $this->fail('删除失败');
    }
}
```

### 2. 逻辑层 (Logic)

```php
<?php
namespace plugin\your_plugin\app\admin\logic\[business];

use plugin\your_plugin\app\model\[business]\[Table];
use plugin\saiadmin\basic\think\BaseLogic;  // 或 eloquent\BaseLogic

class [Table]Logic extends BaseLogic
{
    // 若当前项目启用了数据权限，且该业务表需要按创建人/部门过滤，则开启：
    // protected bool $scope = true;

    public function __construct()
    {
        $this->model = new [Table]();
        $this->orderField = 'sort';  // 排序字段
        $this->orderType = 'ASC';    // 排序方式
    }

    /**
     * 自定义业务方法
     */
    public function customMethod(array $params): array
    {
        $query = $this->search($params);
        return $this->getAll($query);
    }

    /**
     * 事务操作示例
     */
    public function batchOperation(array $data): bool
    {
        return $this->transaction(function () use ($data) {
            foreach ($data as $item) {
                $this->add($item);
            }
            return true;
        });
    }
}
```

### 3. 模型层 (Model)

```php
<?php
namespace plugin\your_plugin\app\model\[business];

use plugin\saiadmin\basic\think\BaseModel;  // 或 eloquent\BaseModel

/**
 * 模型
 * @property int $id
 * @property string $name
 * @property int $status
 */
class [Table] extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'sa_your_table';

    /**
     * 名称搜索器 - 模糊搜索
     */
    public function searchNameAttr($query, $value)
    {
        $query->where('name', 'like', '%' . $value . '%');
    }

    /**
     * 状态搜索器 - 精确匹配
     */
    public function searchStatusAttr($query, $value)
    {
        $query->where('status', $value);
    }

    /**
     * 时间范围搜索器
     */
    public function searchCreateTimeAttr($query, $value)
    {
        $query->whereBetween('create_time', $value);
    }
}
```

### 4. 验证器 (Validate)

```php
<?php
namespace plugin\your_plugin\app\admin\validate\[business];

use plugin\saiadmin\basic\BaseValidate;

class [Table]Validate extends BaseValidate
{
    protected $rule = [
        'name'   => 'require|max:100',
        'code'   => 'require|alphaDash',
        'status' => 'require|in:1,2',
        'email'  => 'email',
        'mobile' => 'mobile',
    ];

    protected $message = [
        'name.require'   => '名称必须填写',
        'name.max'       => '名称最多100个字符',
        'code.require'   => '标识必须填写',
        'status.require' => '状态必须填写',
    ];

    protected $scene = [
        'save'   => ['name', 'code', 'status'],
        'update' => ['name', 'code', 'status'],
    ];
}
```

### 5. 路由注册

```php
// plugin/your_plugin/config/route.php
use Webman\Route;

Route::group('/api', function () {
    fastRoute('article', \plugin\your_plugin\app\admin\controller\[business]\[Table]Controller::class);
});
```

> 注意：不要把前端代理前缀直接等同为后端业务路由前缀。新增或修改路由后，在后端目录执行 `php webman route:list`，以前端 API 文件里的 URL 和路由列表能对上为准。

---

## 🎨 前端开发模板

### 1. API 接口层

```typescript
// src/views/plugin/{插件名}/api/{模块名}/{功能名}.ts
import request from "@/utils/http";

export default {
  /**
   * 获取数据列表
   */
  list(params: Record<string, any>) {
    return request.get<Api.Common.ApiPage>({
      url: "/app/[插件名]/admin/[模块名]/[控制器]/index",
      params,
    });
  },

  /**
   * 读取数据
   */
  read(id: number | string) {
    return request.get<Api.Common.ApiData>({
      url: "/app/[插件名]/admin/[模块名]/[控制器]/read?id=" + id,
    });
  },

  /**
   * 创建数据
   */
  save(params: Record<string, any>) {
    return request.post<any>({
      url: "/app/[插件名]/admin/[模块名]/[控制器]/save",
      data: params,
    });
  },

  /**
   * 更新数据
   */
  update(params: Record<string, any>) {
    return request.put<any>({
      url: "/app/[插件名]/admin/[模块名]/[控制器]/update",
      data: params,
    });
  },

  /**
   * 删除数据
   */
  delete(params: Record<string, any>) {
    return request.del<any>({
      url: "/app/[插件名]/admin/[模块名]/[控制器]/destroy",
      data: params,
    });
  },
};
```

### 2. 主页面 (index.vue)

```vue
<template>
  <div class="art-full-height">
    <!-- 搜索面板 -->
    <TableSearch
      v-model="searchForm"
      @search="handleSearch"
      @reset="resetSearchParams"
    />

    <ElCard class="art-table-card" shadow="never">
      <!-- 表格头部 -->
      <ArtTableHeader
        v-model:columns="columnChecks"
        :loading="loading"
        @refresh="refreshData"
      >
        <template #left>
          <ElSpace wrap>
            <ElButton
              v-permission="'[插件]:[模块]:[功能]:save'"
              @click="showDialog('add')"
              v-ripple
            >
              <template #icon><ArtSvgIcon icon="ri:add-fill" /></template>
              新增
            </ElButton>
            <ElButton
              v-permission="'[插件]:[模块]:[功能]:destroy'"
              :disabled="selectedRows.length === 0"
              @click="deleteSelectedRows(api.delete, refreshData)"
              v-ripple
            >
              <template #icon
                ><ArtSvgIcon icon="ri:delete-bin-5-line"
              /></template>
              删除
            </ElButton>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <!-- 表格 -->
      <ArtTable
        ref="tableRef"
        rowKey="id"
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @sort-change="handleSortChange"
        @selection-change="handleSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      >
        <template #operation="{ row }">
          <div class="flex gap-2">
            <SaButton
              v-permission="'[插件]:[模块]:[功能]:update'"
              type="secondary"
              @click="showDialog('edit', row)"
            />
            <SaButton
              v-permission="'[插件]:[模块]:[功能]:destroy'"
              type="error"
              @click="deleteRow(row, api.delete, refreshData)"
            />
          </div>
        </template>
      </ArtTable>
    </ElCard>

    <!-- 编辑弹窗 -->
    <EditDialog
      v-model="dialogVisible"
      :dialog-type="dialogType"
      :data="dialogData"
      @success="refreshData"
    />
  </div>
</template>

<script setup lang="ts">
import { useTable } from "@/hooks/core/useTable";
import { useSaiAdmin } from "@/composables/useSaiAdmin";
import api from "../../api/[模块]/[功能]";
import TableSearch from "./modules/table-search.vue";
import EditDialog from "./modules/edit-dialog.vue";

// 搜索表单
const searchForm = ref({
  title: undefined,
});

const handleSearch = (params: Record<string, any>) => {
  Object.assign(searchParams, params);
  getData();
};

// 表格配置
const {
  columns,
  columnChecks,
  data,
  loading,
  getData,
  searchParams,
  pagination,
  resetSearchParams,
  handleSortChange,
  handleSizeChange,
  handleCurrentChange,
  refreshData,
} = useTable({
  core: {
    apiFn: api.list,
    columnsFactory: () => [
      { type: "selection" },
      { prop: "title", label: "标题" },
      {
        prop: "status",
        label: "状态",
        saiType: "dict",
        saiDict: "data_status",
      },
      {
        prop: "operation",
        label: "操作",
        width: 100,
        fixed: "right",
        useSlot: true,
      },
    ],
  },
});

// 弹窗与操作
const {
  dialogType,
  dialogVisible,
  dialogData,
  showDialog,
  deleteRow,
  deleteSelectedRows,
  handleSelectionChange,
  selectedRows,
} = useSaiAdmin();
</script>
```

### 3. 搜索表单组件 (table-search.vue)

```vue
<template>
  <sa-search-bar
    ref="searchBarRef"
    v-model="formData"
    label-width="100px"
    :showExpand="false"
    @reset="handleReset"
    @search="handleSearch"
  >
    <el-col v-bind="setSpan(6)">
      <el-form-item label="标题" prop="title">
        <el-input v-model="formData.title" placeholder="请输入标题" clearable />
      </el-form-item>
    </el-col>
    <el-col v-bind="setSpan(6)">
      <el-form-item label="状态" prop="status">
        <sa-select v-model="formData.status" dict="data_status" />
      </el-form-item>
    </el-col>
  </sa-search-bar>
</template>

<script setup lang="ts">
interface Props {
  modelValue: Record<string, any>;
}
interface Emits {
  (e: "update:modelValue", value: Record<string, any>): void;
  (e: "search", params: Record<string, any>): void;
  (e: "reset"): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const searchBarRef = ref();
const formData = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

function handleReset() {
  searchBarRef.value?.ref.resetFields();
  emit("reset");
}

function handleSearch() {
  emit("search", formData.value);
}

const setSpan = (span: number) => ({
  span,
  xs: 24,
  sm: span >= 12 ? span : 12,
  md: span >= 8 ? span : 8,
  lg: span,
  xl: span,
});
</script>
```

### 4. 编辑弹窗组件 (edit-dialog.vue)

```vue
<template>
  <el-dialog
    v-model="visible"
    :title="dialogType === 'add' ? '新增' : '编辑'"
    width="800px"
    align-center
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <el-form ref="formRef" :model="formData" :rules="rules" label-width="120px">
      <el-form-item label="标题" prop="title">
        <el-input v-model="formData.title" placeholder="请输入标题" />
      </el-form-item>
      <el-form-item label="图片" prop="image">
        <sa-image-upload v-model="formData.image" :limit="1" />
      </el-form-item>
      <el-form-item label="内容" prop="content">
        <sa-editor v-model="formData.content" height="400px" />
      </el-form-item>
      <el-form-item label="状态" prop="status">
        <sa-radio v-model="formData.status" dict="data_status" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" @click="handleSubmit">提交</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import api from "../../../api/[模块]/[功能]";
import { ElMessage } from "element-plus";
import type { FormInstance, FormRules } from "element-plus";

interface Props {
  modelValue: boolean;
  dialogType: string;
  data?: Record<string, any>;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: false,
  dialogType: "add",
  data: undefined,
});

const emit = defineEmits<{
  (e: "update:modelValue", value: boolean): void;
  (e: "success"): void;
}>();

const formRef = ref<FormInstance>();

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const rules = reactive<FormRules>({
  title: [{ required: true, message: "标题必须填写", trigger: "blur" }],
});

const initialFormData = {
  id: null,
  title: "",
  image: "",
  content: "",
  status: 1,
};

const formData = reactive({ ...initialFormData });

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) initPage();
  },
);

const initPage = async () => {
  Object.assign(formData, initialFormData);
  if (props.data) {
    await nextTick();
    for (const key in formData) {
      if (props.data[key] != null) {
        (formData as any)[key] = props.data[key];
      }
    }
  }
};

const handleClose = () => {
  visible.value = false;
  formRef.value?.resetFields();
};

const handleSubmit = async () => {
  if (!formRef.value) return;
  try {
    await formRef.value.validate();
    if (props.dialogType === "add") {
      await api.save(formData);
      ElMessage.success("新增成功");
    } else {
      await api.update(formData);
      ElMessage.success("修改成功");
    }
    emit("success");
    handleClose();
  } catch (error) {
    console.log("表单验证失败:", error);
  }
};
</script>
```

---

## 🎛️ SAI 组件库速查

### 表单类组件

| 组件            | 用途             | 示例                                                    |
| --------------- | ---------------- | ------------------------------------------------------- |
| `SaButton`      | 表格操作按钮     | `<SaButton type="secondary" @click="..." />`            |
| `SaSelect`      | 字典下拉框       | `<sa-select v-model="form.type" dict="article_type" />` |
| `SaRadio`       | 字典单选框       | `<sa-radio v-model="form.status" dict="data_status" />` |
| `SaCheckbox`    | 字典复选框       | `<SaCheckbox v-model="form.tags" dict="tag_list" />`    |
| `SaSwitch`      | 开关             | `<sa-switch v-model="form.status" />`                   |
| `SaDict`        | 字典标签展示     | `<SaDict dict="system_status" :value="row.status" />`   |
| `SaLabel`       | 带提示的表单标签 | `<SaLabel label="排序" tooltip="数值越小越靠前" />`     |
| `SaUser`        | 用户选择器       | `<SaUser v-model="form.userId" />`                      |
| `SaIconPicker`  | 图标选择器       | `<SaIconPicker v-model="form.icon" />`                  |
| `SaSearchBar`   | 搜索栏           | `<SaSearchBar v-model="form" @search="..." />`          |
| `SaEditor`      | 富文本编辑器     | `<sa-editor v-model="form.content" height="400px" />`   |
| `SaCode`        | 代码高亮         | `<SaCode :code="codeStr" language="php" />`             |
| `SaImagePicker` | 图片选择器       | `<SaImagePicker v-model="form.avatar" round />`         |
| `SaImageDialog` | 图片弹窗         | `<SaImageDialog v-model="visible" @confirm="..." />`    |
| `SaImageUpload` | 图片上传         | `<sa-image-upload v-model="form.image" :limit="1" />`   |
| `SaFileUpload`  | 文件上传         | `<SaFileUpload v-model="form.file" />`                  |
| `SaChunkUpload` | 分片上传         | `<SaChunkUpload v-model="form.video" :maxSize="500" />` |

### 组件详细配置

#### SaSelect 字典下拉框 Props

| 属性           | 类型                   | 默认值     | 说明               |
| -------------- | ---------------------- | ---------- | ------------------ |
| `dict`         | `string`               | -          | 字典编码 (必填)    |
| `valueType`    | `'number' \| 'string'` | `'number'` | 值类型             |
| `placeholder`  | `string`               | `'请选择'` | 占位文本           |
| `clearable`    | `boolean`              | `true`     | 是否可清空         |
| `filterable`   | `boolean`              | `false`    | 是否可搜索         |
| `multiple`     | `boolean`              | `false`    | 是否多选           |
| `collapseTags` | `boolean`              | `false`    | 多选时是否折叠标签 |

#### SaRadio/SaCheckbox 字典选择框 Props

| 属性        | 类型                              | 默认值      | 说明            |
| ----------- | --------------------------------- | ----------- | --------------- |
| `dict`      | `string`                          | -           | 字典编码 (必填) |
| `type`      | `'radio' \| 'button' \| 'border'` | `'radio'`   | 样式类型        |
| `valueType` | `'number' \| 'string'`            | `'number'`  | 值类型          |
| `size`      | `'large' \| 'default' \| 'small'` | `'default'` | 尺寸            |

#### SaSwitch 开关 Props

| 属性            | 类型                          | 默认值   | 说明         |
| --------------- | ----------------------------- | -------- | ------------ |
| `activeValue`   | `string \| number \| boolean` | `1`      | 激活时的值   |
| `inactiveValue` | `string \| number \| boolean` | `2`      | 未激活时的值 |
| `activeText`    | `string`                      | `'启用'` | 激活时文本   |
| `inactiveText`  | `string`                      | `'禁用'` | 未激活时文本 |
| `showText`      | `boolean`                     | `true`   | 是否显示文本 |
| `inlinePrompt`  | `boolean`                     | `true`   | 文本是否内联 |

#### SaEditor 富文本编辑器

**Props:**
| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `height` | `string` | `'500px'` | 编辑器高度 |
| `mode` | `'default' \| 'simple'` | `'default'` | 编辑器模式 |
| `placeholder` | `string` | `'请输入内容...'` | 占位文本 |
| `excludeKeys` | `string[]` | `['fontFamily']` | 排除的工具栏按钮 |

**Expose 方法:**
| 方法 | 说明 |
|------|------|
| `getEditor()` | 获取编辑器实例 |
| `setHtml(html)` | 设置HTML内容 |
| `getHtml()` | 获取HTML内容 |
| `clear()` | 清空内容 |
| `focus()` | 聚焦编辑器 |
| `openImageDialog()` | 打开图片选择弹窗 |

#### SaImageUpload 图片上传 Props

| 属性       | 类型      | 默认值      | 说明             |
| ---------- | --------- | ----------- | ---------------- |
| `multiple` | `boolean` | `false`     | 是否多图         |
| `limit`    | `number`  | `1`         | 最大上传数量     |
| `maxSize`  | `number`  | `5`         | 最大文件大小(MB) |
| `accept`   | `string`  | `'image/*'` | 接受文件类型     |
| `width`    | `number`  | `148`       | 预览宽度         |
| `height`   | `number`  | `148`       | 预览高度         |
| `round`    | `boolean` | `false`     | 是否圆形         |
| `showTips` | `boolean` | `true`      | 是否显示提示     |

#### SaChunkUpload 分片上传 Props

| 属性         | 类型      | 默认值  | 说明             |
| ------------ | --------- | ------- | ---------------- |
| `maxSize`    | `number`  | `1024`  | 最大文件大小(MB) |
| `chunkSize`  | `number`  | `5`     | 分片大小(MB)     |
| `drag`       | `boolean` | `true`  | 是否拖拽上传     |
| `autoUpload` | `boolean` | `false` | 是否自动上传     |

#### SaSearchBar 搜索栏 Props

| 属性              | 类型                         | 默认值    | 说明             |
| ----------------- | ---------------------------- | --------- | ---------------- |
| `gutter`          | `number`                     | `12`      | 栅格间距         |
| `labelPosition`   | `'left' \| 'right' \| 'top'` | `'right'` | 标签位置         |
| `showExpand`      | `boolean`                    | `true`    | 是否显示展开按钮 |
| `defaultExpanded` | `boolean`                    | `false`   | 默认是否展开     |
| `showReset`       | `boolean`                    | `true`    | 是否显示重置按钮 |
| `showSearch`      | `boolean`                    | `true`    | 是否显示搜索按钮 |

### SaButton 预设类型

| type        | 颜色 | 图标                   | 用途 |
| ----------- | ---- | ---------------------- | ---- |
| `primary`   | 蓝色 | `ri:add-fill`          | 新增 |
| `secondary` | 紫色 | `ri:pencil-line`       | 编辑 |
| `error`     | 红色 | `ri:delete-bin-5-line` | 删除 |
| `success`   | 绿色 | `ri:eye-line`          | 查看 |
| `info`      | 灰色 | `ri:more-2-fill`       | 更多 |

### useTable 列配置

| 属性      | 类型                                 | 说明       |
| --------- | ------------------------------------ | ---------- |
| `type`    | `'selection' \| 'index' \| 'expand'` | 特殊列类型 |
| `prop`    | `string`                             | 字段名     |
| `label`   | `string`                             | 列标题     |
| `width`   | `number`                             | 列宽度     |
| `fixed`   | `'left' \| 'right'`                  | 固定位置   |
| `saiType` | `'image' \| 'dict' \| 'switch'`      | SAI类型    |
| `saiDict` | `string`                             | 字典编码   |
| `useSlot` | `boolean`                            | 使用插槽   |

### 导入导出组件

```vue
<!-- 数据导出 -->
<SaExport
  url="/api/user/export"
  :params="searchForm"
  fileName="用户列表.xlsx"
/>

<!-- 数据导入 -->
<SaImport
  title="导入用户"
  uploadUrl="/api/user/import"
  downloadUrl="/api/user/template"
  @success="handleImportSuccess"
/>
```

### 树形数据处理

```typescript
// 1. API 获取树形结构
const data = await api.list({ tree: true });

// 2. 编辑弹窗中的树形选择器
const optionData = reactive({ treeData: <any[]>[] });

const initPage = async () => {
  const data = await api.list({ tree: true });
  optionData.treeData = [
    { id: 0, value: 0, label: "无上级分类", children: data },
  ];
};

// 3. 表格展开/收起
const isExpanded = ref(false);
const tableRef = ref();

const toggleExpand = (): void => {
  isExpanded.value = !isExpanded.value;
  nextTick(() => {
    if (tableRef.value?.elTableRef && data.value) {
      const processRows = (rows: any[]) => {
        rows.forEach((row) => {
          if (row.children?.length) {
            tableRef.value.elTableRef.toggleRowExpansion(row, isExpanded.value);
            processRows(row.children);
          }
        });
      };
      processRows(data.value);
    }
  });
};
```

---

## 🏗️ 后端基类详解

### BaseController 属性和方法

```php
// 属性
protected $adminInfo;     // 当前登录用户信息
protected int $adminId;   // 当前登录用户 ID
protected string $adminName; // 当前登录用户名
protected $logic;         // 逻辑层实例
protected $validate;      // 验证器实例

// 方法
public function success($data = [], $msg = 'success');  // 成功响应
public function fail($msg = 'fail');                    // 失败响应
protected function validate(string $scene, $data);     // 调用验证器
```

### LogicInterface 接口方法

| 方法                          | 说明           |
| ----------------------------- | -------------- |
| `init($user)`                 | 初始化用户信息 |
| `add(array $data)`            | 新增数据       |
| `edit($id, array $data)`      | 编辑数据       |
| `read($id)`                   | 读取单条数据   |
| `destroy($ids)`               | 删除数据       |
| `search(array $where)`        | 搜索查询       |
| `getList($query)`             | 获取分页列表   |
| `getAll($query)`              | 获取全部数据   |
| `transaction(callable, bool)` | 事务操作       |

### 验证器唯一性验证

```php
protected $rule = [
    // 格式：unique:模型类,字段名,排除ID,主键名
    'code' => 'require|unique:\\plugin\\your_plugin\\app\\model\\YourModel,code',
];

// 更新时排除当前记录
$data['id'] = $request->input('id');
$this->validate('update', $data);
```

### 常用验证规则速查

| 规则         | 示例                           | 说明           |
| ------------ | ------------------------------ | -------------- |
| `require`    | `'name' => 'require'`          | 必填           |
| `max`        | `'name' => 'max:100'`          | 最大长度       |
| `min`        | `'name' => 'min:2'`            | 最小长度       |
| `in`         | `'status' => 'in:1,2'`         | 枚举值         |
| `email`      | `'email' => 'email'`           | 邮箱格式       |
| `mobile`     | `'mobile' => 'mobile'`         | 手机号         |
| `number`     | `'sort' => 'number'`           | 数字           |
| `integer`    | `'id' => 'integer'`            | 整数           |
| `alphaDash`  | `'code' => 'alphaDash'`        | 字母数字下划线 |
| `url`        | `'link' => 'url'`              | URL格式        |
| `dateFormat` | `'date' => 'dateFormat:Y-m-d'` | 日期格式       |

---

## 🔧 命令行工具

```bash
# 创建插件
php webman sai:plugin {插件标识}

# 切换 ORM
php webman sai:orm

# 升级框架
php webman sai:upgrade
```

`sai:orm`、`sai:upgrade` 会影响项目基础行为，只有在用户明确要求或项目文档明确授权时才执行。

---

## 通用 QA 与排查清单

### 后端文件检查

- 新增或修改 PHP 文件后，对改动文件执行 `php -l path/to/file.php`。
- 在后端目录执行 `php webman route:list`，确认接口已被 Webman 装载。
- Controller 只做权限入口、参数接收、validate 调用和 logic 调用。
- Logic 放业务查询、事务、导入导出和复杂处理。
- Model 继承 `plugin\saiadmin\basic\think\BaseModel`，表名、主键、搜索器要清晰。
- Validate 分 `save`、`update` 等场景。

### 菜单、按钮和权限

- 权限 slug 统一使用 `<namespace>:<package>:<business>:<action>`。
- 数据库菜单和角色关系正确但页面不显示时，先检查缓存和会话。
- 重点关注 `UserMenuCache::getUserMenu($uid)`、`UserAuthCache::getUserAuth($uid)`、`UserInfoCache`。
- 可通过 SaiAdmin 内置清缓存逻辑或重新保存角色权限触发缓存更新。
- 清缓存后重新登录验证，避免旧 token 或旧会话误导判断。

### DataScope 数据权限

- DataScope 不是普通业务插件，通常会改 SaiAdmin 核心能力：`plugin/saiadmin/basic/think/BaseLogic.php`、角色控制器/逻辑层、角色前端页面和角色按钮权限。安装前先读插件 README 和 diff，不要整文件覆盖核心文件。
- 安装前确认当前项目目录：后端通常在 `server/plugin/saiadmin`，前端通常在 `saiadmin-artd/src`；拷贝来的插件包可能在项目根目录，不能直接按包内相对路径覆盖。
- 后端接入点：
  - `BaseLogic` 增加 `$scope` 开关、数据范围常量和 `userDataScope($query)`，并在 `getList`、`getAll`、`read`、`edit`、`destroy` 中按需调用。
  - `SystemRoleController` 增加 `getDeptByRole`、`dataPermission`，权限 slug 通常为 `core:role:data`。
  - `SystemRoleLogic` 增加 `getDeptByRole`、`saveDeptPermission`，保存后清理 `UserInfoCache::clearUserInfoByRoleId($id)`。
  - `plugin/saiadmin/config/route.php` 手动注册 `/core/role/getDeptByRole` 和 `/core/role/dataPermission`，不要只改控制器后就假设路由已自动装载。
- 前端接入点：
  - `src/api/system/role.ts` 确认有 `deptByRole`、`dataPermission`；`deptByRole` 返回包含 `depts` 的对象，不是数组。
  - `src/views/system/role/index.vue` 增加数据权限列、下拉菜单入口和 `DataDialog`。
  - `src/views/system/role/modules/data-dialog.vue` 通过部门树配置自定义数据权限。
- 数据库按钮权限：在 `sa_system_menu` 的角色管理菜单下增加按钮 `数据权限 / core:role:data`。优先使用幂等 SQL，安装后查询确认；如果项目维护基础库 SQL，也要同步到对应 SQL 文件。
- 写入按钮权限后如果页面不显示，清理 `UserMenuCache`、`UserAuthCache`、`UserInfoCache` 或文件缓存，并重新登录。Webman 是常驻进程，改路由/控制器/Logic 后必须重启再验证。
- 需要数据权限控制的业务表必须有 `created_by` 字段。
- 对应 Logic 必须继承 ThinkORM 版 `BaseLogic`。
- 如果项目已接入 DataScope，且该业务模块需要受数据权限控制，对应 Logic 必须显式开启 `protected bool $scope = true;`。
- 角色 `data_scope` 和 `sa_system_role_dept` 要同时检查。
- 不能只用超级管理员验证数据范围；必须用普通角色账号验证。
- SaiCode 重新生成后，重点检查 `$scope` 是否被覆盖。
- 推荐验证：`php -l` 检查改动 PHP 文件，`php webman route:list` 确认新接口，`pnpm exec vue-tsc --noEmit` 做前端类型检查；避免默认跑完整前端构建，除非用户明确要求。

### SaiCode 生成后检查

- 生成前先预览，不要直接假设生成结果可用。
- 业务生成优先使用插件模式；具体 `namespace`、`package_name`、`business_name` 按项目命名规范或用户确认值填写。
- `generate_path`、前后端相对位置、生成目标目录必须按当前项目确认。
- `generateFile` 依赖后端 `config/app.php` 的 `debug=true`，生产或非 debug 模式不可用。
- 生成后检查后端 Controller/Logic/Model/Validate、前端 API、菜单、按钮权限和 git diff。
- 不要认为 SaiCode 会完整补齐按钮权限点。

### Webman 常驻进程

- 改路由、配置、控制器、Logic、服务类后，重启 Webman 再验证。
- 本地通常使用 `php webman restart`；线上部署命令、工作目录和守护模式参数必须按项目部署文档或用户确认执行。
- 代码已修改但接口仍表现为旧逻辑时，优先确认进程是否已重启。
- 仍异常时查看项目约定的 runtime/log 目录。

### 前后端路径

- 前端 API 使用 `import request from '@/utils/http'`。
- 前端代理前缀、Vite 代理目标和后端真实路由必须按当前项目配置确认。
- 权限失败和 404 要分开判断：先看 HTTP 状态和响应 message，再看路由列表、菜单权限和缓存。

### 部署相关经验

- 部署目标目录、静态资源目录和 Web 根目录必须先从部署脚本、服务器配置或用户确认中核实。
- 同步后检查目标目录的 `index.html`、静态资源目录和关键入口文件，不能只相信同步命令成功。
- 保护 `.env`、`runtime/`、上传目录、存储目录和日志目录，除非用户明确要求覆盖或清理。
- 使用 `rsync` 前确认本机/服务器 rsync 兼容性；不确定时选择更通用的参数或先询问用户。

---

## 📋 快速开发清单

### 新增功能模块步骤

1. **创建后端文件**
   - 模型：`app/model/[business]/[Table].php`
   - 验证器：`app/admin/validate/[business]/[Table]Validate.php`
   - 逻辑层：`app/admin/logic/[business]/[Table]Logic.php`
   - 控制器：`app/admin/controller/[business]/[Table]Controller.php`
   - 路由：`config/route.php`

2. **创建前端文件**
   - API：`api/[模块]/[功能].ts`
   - 主页面：`[模块]/[功能]/index.vue`
   - 搜索组件：`[模块]/[功能]/modules/table-search.vue`
   - 编辑弹窗：`[模块]/[功能]/modules/edit-dialog.vue`

3. **配置权限菜单** (后台系统配置)

4. **验证与缓存**
   - `php -l` 检查改动过的 PHP 文件
   - 在后端目录执行 `php webman route:list`
   - 涉及权限时清理或刷新用户菜单/权限缓存
   - 涉及数据权限时用普通角色账号验证
   - 修改路由、配置、服务类后重启 Webman

---

## ⚡ 最佳实践

1. **权限编码格式**：`{插件名}:{模块名}:{功能名}:{操作}`
2. **搜索器命名**：`search{FieldName}Attr` (如 `searchNameAttr`)
3. **表单验证**：必填字段使用 `required` 规则
4. **初始数据**：定义 `initialFormData` 便于重置
5. **组件复用**：优先使用 SAI 组件库
6. **类型安全**：使用 TypeScript 接口定义

---

## 🎯 前端开发规范

### 组件命名规范

- 统一使用 `Sa` 前缀命名组件
- 使用 `defineOptions` 定义组件名称

```vue
<script setup lang="ts">
defineOptions({ name: "SaYourComponent" });
</script>
```

### 属性继承控制

对于需要透传属性的组件，使用 `inheritAttrs: false` 配合 `v-bind="$attrs"`:

```vue
<script setup lang="ts">
defineOptions({
  name: "SaWrapper",
  inheritAttrs: false,
});
</script>

<template>
  <div class="sa-wrapper">
    <el-input v-bind="$attrs" />
  </div>
</template>
```

### defineModel 双向绑定

Vue 3.4+ 使用 `defineModel` 简化 v-model 实现：

```vue
<script setup lang="ts">
const modelValue = defineModel<string>();
// 等价于
// const props = defineProps<{ modelValue: string }>();
// const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
</script>
```

### TypeScript 类型定义

使用接口定义 Props 和 Emits:

```vue
<script setup lang="ts">
interface Props {
  dict: string;
  valueType?: "number" | "string";
  disabled?: boolean;
}

interface Emits {
  (e: "change", value: string | number): void;
  (e: "update:modelValue", value: any): void;
}

const props = withDefaults(defineProps<Props>(), {
  valueType: "number",
  disabled: false,
});

const emit = defineEmits<Emits>();
</script>
```

### 权限控制指令

使用 `v-permission` 指令控制按钮显示：

```vue
<template>
  <!-- 单个权限 -->
  <el-button v-permission="'system:user:save'">新增</el-button>

  <!-- 多个权限 (满足任一即可) -->
  <el-button v-permission="['system:user:save', 'system:user:update']"
    >操作</el-button
  >
</template>
```

### 字典依赖加载

在 `index.vue` 使用 `useDictStore` 预加载字典：

```typescript
import { useDictStore } from "@/stores/modules/dict";

const dictStore = useDictStore();

onMounted(async () => {
  // 预加载页面需要的字典
  await dictStore.loadDict(["data_status", "article_type"]);
});
```

### 值类型转换

字典组件的 `valueType` 属性用于控制值类型：

```vue
<!-- 数值类型 (默认，适用于数据库 int 字段) -->
<SaSelect v-model="form.status" dict="data_status" valueType="number" />

<!-- 字符串类型 (适用于数据库 varchar 字段) -->
<SaSelect v-model="form.code" dict="code_list" valueType="string" />
```

---

## 🔓 OpenController 无需登录控制器

### 继承关系

```
OpenController  # 无需登录验证
    ↓
BaseController  # 需要登录验证
    ↓
YourController  # 业务控制器
```

### 使用场景

- 公开 API 接口（如：获取公告列表、验证码、登录接口等）
- 无需用户身份验证的接口

### OpenController 模板

```php
<?php
namespace plugin\your_plugin\app\api\controller;

use plugin\saiadmin\basic\OpenController;
use plugin\your_plugin\app\logic\ArticleLogic;
use support\Request;
use support\Response;

class ArticleController extends OpenController
{
    protected $logic;

    public function __construct()
    {
        $this->logic = new ArticleLogic();
    }

    /**
     * 获取公开文章列表 (无需登录)
     */
    public function index(Request $request): Response
    {
        $where = $request->more([
            ['category_id', ''],
            ['status', 1],  // 只查询已发布的
        ]);
        $query = $this->logic->search($where);
        $data = $this->logic->getList($query);
        return $this->success($data);
    }

    /**
     * 获取文章详情 (无需登录)
     */
    public function detail(Request $request): Response
    {
        $id = $request->input('id');
        if (empty($id)) {
            return $this->fail('参数错误');
        }
        $data = $this->logic->read($id);
        if (empty($data) || $data->status != 1) {
            return $this->fail('文章不存在');
        }
        return $this->success($data->toArray());
    }
}
```

### 路由配置

```php
// plugin/your_plugin/config/route.php
use Webman\Route;

// 公开接口 (无需登录)
Route::group('/api/open', function () {
    Route::get('/article/index', [\plugin\your_plugin\app\api\controller\ArticleController::class, 'index']);
    Route::get('/article/detail', [\plugin\your_plugin\app\api\controller\ArticleController::class, 'detail']);
});

// 需要登录的接口
Route::group('/api', function () {
    fastRoute('article', \plugin\your_plugin\app\admin\controller\ArticleController::class);
});
```

### BaseController vs OpenController

| 特性         | OpenController | BaseController          |
| ------------ | -------------- | ----------------------- |
| 登录验证     | ❌ 不需要      | ✅ 需要                 |
| 权限验证     | ❌ 不支持      | ✅ 支持 `#[Permission]` |
| `$adminInfo` | ❌ 不可用      | ✅ 当前用户信息         |
| `$adminId`   | ❌ 不可用      | ✅ 当前用户ID           |
| 使用场景     | 公开API        | 后台管理接口            |

---

## 📚 参考资料

- [Webman 官方文档](https://www.workerman.net/doc/webman)
- [ThinkORM 文档](https://doc.thinkphp.cn/@think-orm/v3_0/default.html)
- [Laravel Eloquent 文档](https://laravel.com/docs/eloquent)
- [SaiAdmin 官方文档](https://saithink.top/documents/v6/)
