# unibest 常见开发工作流

本参考用于把用户需求转成具体修改步骤。执行前先检查用户项目的实际目录和版本，不要假设一定与模板完全一致。

## 新建项目

```bash
pnpm create unibest my-project -t base
cd my-project
pnpm install
pnpm dev
```

常见参数：

```bash
pnpm create unibest my-project -p h5,mp-weixin -u wot-ui-v2
pnpm create unibest my-project --i18n --login --lime-echart --ucharts
```

CLI 当前支持的平台：`h5`、`mp-weixin`、`app`、`mp-alipay`、`mp-toutiao`。

CLI 当前支持的 UI 库：`none`、`wot-ui-v2`、`wot-ui`、`uview-pro`、`sard-uniapp`、`uv-ui`、`uview-plus`、`tdesign`。

## 新增页面

1. 在 `src/pages/<module>/<name>.vue` 新建页面。
2. 在页面中写 `definePage`，不要编辑 `pages.json`。
3. 如果要作为首页，确保只有一个页面配置 `type: 'home'`。
4. 如果是分包页面，放到 `src/pages-demo` 或自定义分包目录，并在 `vite.config.ts` 的 `UniPages({ subPackages: [...] })` 中配置。

页面模板：

```vue
<script setup lang="ts">
defineOptions({ name: 'OrderListPage' })

definePage({
  style: {
    navigationBarTitleText: '订单列表',
  },
})
</script>

<template>
  <view class="min-h-100vh bg-#f7f8fa p-24rpx">
    <view class="rounded-12rpx bg-white p-24rpx">
      订单列表
    </view>
  </view>
</template>
```

## 新增手写 API

1. 类型写在当前 API 文件或 `src/api/types/<module>.ts`。
2. 请求函数写在 `src/api/<module>.ts`。
3. 使用 `http.get/post/put/delete<T>` 时一定带响应类型。
4. GET 的第二个参数是 query，POST 的第二个参数是 body，第三个参数才是 query，第四个参数是 header。

示例：

```ts
import { http } from '@/http/http'

export interface IOrderItem {
  id: string
  title: string
}

export interface IOrderQuery {
  page: number
  pageSize: number
  keyword?: string
}

export function getOrderList(query: IOrderQuery) {
  return http.get<IOrderItem[]>('/orders', query)
}

export function createOrder(data: { title: string }) {
  return http.post<IOrderItem>('/orders', data)
}
```

页面使用：

```ts
import useRequest from '@/hooks/useRequest'
import { getOrderList } from '@/api/order'

const { loading, data, run } = useRequest(getOrderList, {
  initialData: [],
})

onLoad(() => {
  run({ page: 1, pageSize: 20 })
})
```

## OpenAPI 自动生成 API

unibest 使用 `openapi-ts-request`。生成器需要机器可读的 OpenAPI / Swagger schema，不是普通文字接口文档。

schema 来源优先级：

1. 后端框架自动生成的 `openapi.json` / `swagger.json`。
2. Apifox、SwaggerHub、YApi 等工具导出的 OpenAPI 地址。
3. 手写 `openapi.yaml` / `openapi.json`。

配置入口：

```ts
// openapi-ts-request.config.ts
import { defineConfig } from 'openapi-ts-request'

export default defineConfig([
  {
    uniqueKey: 'app-api',
    describe: 'app-api',
    schemaPath: 'https://example.com/openapi.json',
    serversPath: './src/service',
    requestLibPath: `import request from '@/http/vue-query';\n import { CustomRequestOptions_ } from '@/http/types';`,
    requestOptionsType: 'CustomRequestOptions_',
    isGenReactQuery: false,
    reactQueryMode: 'vue',
    isGenJavaScript: false,
  },
])
```

运行：

```bash
pnpm openapi
```

固定生成某一个 APIDOC app 时，`-u/--uniqueKey` 匹配的是配置项里的 `uniqueKey`，不是 `describe`。例如新增 `justai-api` 时要同时写：

```ts
{
  uniqueKey: 'justai-api',
  describe: 'justai-api',
  schemaPath: `${apidocBaseUrl}/apidoc/openapi/justai-api`,
  serversPath: './src/service/justai',
}
```

生成后：

- 类型通常在 `src/service/types.ts` 或生成目录内导出。
- 请求函数按模块生成到 `src/service`。
- `@/http/vue-query` 会把 OpenAPI 的 `params` 转成模板的 `query`，把 `headers` 转成 `header`。
- 如果后端响应结构不是 `{ code, data, message/msg }`，优先改 `src/http/http.ts` 的成功判定或 OpenAPI request adapter，不要逐个改生成文件。

最小手写 OpenAPI 示例：

```yaml
openapi: 3.0.3
info:
  title: App API
  version: 1.0.0
paths:
  /orders:
    get:
      operationId: getOrders
      summary: 获取订单列表
      parameters:
        - in: query
          name: page
          schema:
            type: integer
        - in: query
          name: pageSize
          schema:
            type: integer
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  code:
                    type: integer
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/Order'
                  message:
                    type: string
components:
  schemas:
    Order:
      type: object
      properties:
        id:
          type: string
        title:
          type: string
```

## 调整请求拦截器

后端地址：

- 改 `env/.env` 的 `VITE_SERVER_BASEURL`。
- 微信小程序分环境可改 `VITE_SERVER_BASEURL__WEIXIN_DEVELOP`、`VITE_SERVER_BASEURL__WEIXIN_TRIAL`、`VITE_SERVER_BASEURL__WEIXIN_RELEASE`。
- H5 代理改 `VITE_APP_PROXY_ENABLE` 和 `VITE_APP_PROXY_PREFIX`，代理细节在 `vite.config.ts`。

鉴权头：

- 默认在 `src/http/interceptor.ts` 设置 `Authorization: Bearer ${token}`。
- token 来自 `useTokenStore().updateNowTime().validToken`。
- 如果后端要求其他 header 名，例如 `token` 或 `X-Token`，改这里。

响应结构：

- 默认 `src/http/http.ts` 认为 `code === 0` 或 `code === 200` 成功。
- 错误提示优先读 `responseData.msg` 或 `responseData.message`。
- 401 会触发单 token 退出登录或双 token 刷新队列。

## 登录与 token

1. 后端必须返回与 `VITE_AUTH_MODE` 匹配的 token 结构。
2. 单 token：`{ token, expiresIn }`。
3. 双 token：`{ accessToken, accessExpiresIn, refreshToken, refreshExpiresIn }`。
4. 登录成功后 `tokenStore.login()` 会保存 token 并调用 `userStore.fetchUserInfo()`。
5. 微信小程序登录先 `uni.login({ provider: 'weixin' })` 获取 code，再调用后端 `wxLogin({ code })`，后端仍应签发 token。

如果要启用登录策略：

```bash
pnpm create unibest add login
```

或创建时：

```bash
pnpm create unibest my-project --login
```

追加 Feature 会更新 `package.json.unibest.loginStrategy = true`。如已注入过但要覆盖，使用 `--force`。

## 新增 tabbar 页面

1. 新建页面，例如 `src/pages/order/index.vue`。
2. 在 `src/tabbar/config.ts` 中按当前策略加入对应 list。
3. 使用原生 tabbar 时配置 `nativeTabbarList`，必须有 `iconPath` 和 `selectedIconPath`。
4. 使用自定义 tabbar 时配置 `customTabbarList`，设置 `iconType`、`icon`，可选 `badge`、`isBulge`、`roles`。
5. 动态 UnoCSS 图标加入 `uno.config.ts` 的 `safelist`。
6. 重新运行项目，让 `pages.json` 更新。

自定义 tabbar 示例：

```ts
{
  pagePath: 'pages/order/index',
  text: '订单',
  iconType: 'unocss',
  icon: 'i-carbon-list',
  roles: ['admin'],
}
```

不要直接修改 `tabbarStore.curIdx`。跳转使用：

```ts
uni.switchTab({ url: '/pages/order/index' })
```

直达、分享、登录返回后状态不对时调用：

```ts
tabbarStore.syncCurIdxByCurrentPageAsync()
```

## 接入或替换 UI 库

创建项目时优先：

```bash
pnpm create unibest my-project -u wot-ui-v2
```

CLI 会做这些事：

- 更新 `package.json` 依赖。
- 更新 `pages.config.ts` easycom。
- 更新 `tsconfig.json` types。
- 必要时更新 `src/main.ts`、`src/uni.scss`、`src/App.vue`。
- `wot-ui-v2` 会额外生成 `wot-ui-resolver.ts` 并更新 `vite.config.ts` 的 `UniComponents` resolver。

手工接入时必须至少检查：

- easycom 规则。
- 类型声明。
- 样式入口。
- 是否需要 `app.use()`。
- H5 样式是否需要 resolver。

## App 打包与原生插件

1. 设置 `env/.env` 的 `VITE_UNI_APPID`。
2. 在 `manifest.config.ts` 配置 App 权限、SDK、图标和 `app-plus.distribute`。
3. 原生插件放根目录 `nativeplugins`。
4. App 构建时如需复制原生资源，设置 `VITE_COPY_NATIVE_RES_ENABLE=true`。
5. 使用原生插件时需要自定义基座，标准基座不会包含插件。
6. iOS 模拟器常用 `pnpm dev:app` 后导入 `dist/dev/app`。
7. Android / 鸿蒙建议把整个 unibest 项目导入 HBuilderX。

## 新增 create-unibest Feature

只在 `main` 分支做 CLI 改动。

1. 在 `packages/cli/features/<feature-name>` 创建 feature 资源。
2. 添加 `package.json` 描述依赖。
3. 如需交互或注入后处理，添加 `hooks.js` / `schema.json`。
4. 在 `packages/cli/src/utils/injector.ts` 中增加注入函数，或复用 `FeatureInjector`。
5. 在 `packages/cli/src/features/interface.ts` / loader 可识别位置注册。
6. 在 `create/generate.ts` 和 `add.ts` 接入创建时注入与追加注入。
7. 本地测试：

```bash
cd packages/cli
pnpm install
pnpm dev
LOCAL_TEMPLATE=true pnpm start -- my-test-project
pnpm start -- add <feature-name> --path ../../my-test-project --force
```

## 选择验证方式

按变更范围选择：

- 只改 TypeScript：优先 `pnpm type-check`。
- 改 lint 风格：`pnpm lint` 或 `pnpm lint:fix`。
- 改 H5 页面：`pnpm dev:h5` 手动检查。
- 改微信小程序：`pnpm dev:mp-weixin`，导入 `dist/dev/mp-weixin`。
- 改 App：`pnpm dev:app` 或按平台导入 HBuilderX。
- 改 OpenAPI：`pnpm openapi` 后检查生成文件 diff 和调用处类型。
- 改 CLI：在 `packages/cli` 执行 `pnpm dev`，用 `pnpm start -- ...` 测试生成。
