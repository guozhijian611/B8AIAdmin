# unibest 排错索引

遇到具体错误时先搜索错误文本，再按当前项目版本核对。修复前优先读相关配置文件和实际日志。

## pages.json / manifest.json 被覆盖

症状：手动改 `pages.json` 或 `manifest.json` 后重新运行丢失。

原因：`@uni-helper/vite-plugin-uni-pages` 和 `@uni-helper/vite-plugin-uni-manifest` 会生成这些文件。

修复：

- 页面全局配置改 `pages.config.ts`。
- 页面自身配置用 `definePage`。
- manifest 配置改 `manifest.config.ts`。

## 找不到 src/manifest.json

症状：首次运行非 H5 端时报 `ENOENT ... src/manifest.json`。

修复：先执行 `pnpm install`，让 `prepare` / `init-baseFiles` 生成基础文件。

## 微信开发者工具 timeout

症状：`pnpm dev:mp` 或 `pnpm dev:mp-weixin` 编译后提示自动打开微信开发者工具 timeout。

原因：通常是微信开发者工具 CLI 路径不对或服务端口未开启，不等于项目编译失败。

修复：

- 在 `env/.env` 配置 `WECHAT_DEVTOOLS_CLI_PATH`。
- 手动打开微信开发者工具并导入 `dist/dev/mp-weixin`。
- 确认微信开发者工具服务端口已开启。
- 上传或 CI 中可用 `SKIP_OPEN_DEVTOOLS=true` 跳过自动打开。

## WXSS unexpected backslash

症状：

```text
[ WXSS 文件编译错误]
./app.wxss(...): unexpected `\`
```

修复顺序：

1. 升级 Node 到 22+。
2. 仍失败则回退微信开发者工具到文档建议的稳定版本。
3. 检查是否使用模板 lock 文件，避免包小版本漂移。
4. 检查 `uno.config.ts` 是否启用了不兼容的小程序 CSS 产物。

## ERR PNPM INVALID WORKSPACE CONFIGURATION

症状：

```text
ERR PNPM INVALID WORKSPACE CONFIGURATION packages field missing or empty
```

常见原因：Node 18 + pnpm 9 组合。

修复：优先升级 Node 22；仍失败升级 pnpm 10。

## esbuild host/binary version mismatch

症状：

```text
Cannot start service: Host version "0.20.2" does not match binary version "0.25.5"
```

修复：按 FAQ 将 `package.json` 中 `esbuild` 版本对齐到 `0.20.2`，然后重新安装依赖。具体版本仍以当前项目 lock 和 SDK 匹配为准。

## 支付宝小程序运行报错

症状：支付宝开发工具默认运行失败。

修复：在支付宝开发工具勾选“本地开发跳过 ES5 转译”。模板 `manifest.config.ts` 已设置 `globalObjectMode` 和 `transpile.ignore`，仍需开发工具侧配合。

## defineModel 在小程序或 App 报错

原因：uni-app 的 Vue 版本可能支持 `defineModel`，但非 H5 端不一定支持。

修复：跨端代码避免默认使用 `defineModel`；仅 H5 专属分支可用。

## App 白屏

常见原因：顶层直接调用 `useXxxStore()`，Pinia 尚未完成初始化。

修复：

- 把 store 调用移入函数、生命周期、事件回调或初始化后逻辑。
- 检查 `src/main.ts` 是否先 `app.use(store)`。

## 自定义 tabbar 出现两个 tabbar

原因：原生 tabbar 未隐藏。

修复：

- 确认 `selectedTabbarStrategy = TABBAR_STRATEGY_MAP.CUSTOM_TABBAR`。
- 微信小程序依赖 `custom: true`。
- 非微信/支付宝在 `src/tabbar/index.vue` 的 `onLoad` 里 `uni.hideTabBar()`。
- 支付宝要在 `onMounted` 调 `uni.hideTabBar()`。

## tabbar 状态错位

症状：分享进入、H5 直达、登录返回后高亮项不对。

修复：

- 不要手动改 `tabbarStore.curIdx`。
- 用 `uni.switchTab` 跳 tabbar 页。
- 在恢复场景调用 `tabbarStore.syncCurIdxByCurrentPageAsync()`。
- 检查 `App.vue onShow` 是否调用 `navigateToInterceptor.invoke`。

## tabbar 图标不显示

UnoCSS 图标：

- 使用 `i-carbon-home` 这种连字符类名，不用 `i-carbon:home`。
- 动态图标必须写进 `uno.config.ts` 的 `safelist` 或页面注释。

iconfont：

- 使用 Font class。
- 小程序/App 端字体资源转 base64。
- 把 `//at.alicdn.com` 改成 `https://at.alicdn.com`。

SVG：

- 小程序/App 不支持 SVG 标签，跨端用 `<image src="...">`。

## 请求没有带 token

排查：

1. `src/main.ts` 是否 `app.use(requestInterceptor)`。
2. `src/http/interceptor.ts` 是否设置了 `options.header.Authorization`。
3. `useTokenStore().updateNowTime().validToken` 是否为空。
4. token 是否过期，`accessTokenExpireTime` / `refreshTokenExpireTime` 是否正确写入 storage。
5. 后端返回结构是否匹配 `VITE_AUTH_MODE`。

## 401 后没有刷新 token

排查：

- `env/.env` 中 `VITE_AUTH_MODE` 是否为 `double`。
- 后端是否返回 `refreshToken` 和 `refreshExpiresIn`。
- `src/api/login.ts` 的 `refreshToken()` 地址和参数是否匹配后端。
- `src/http/http.ts` 刷新队列是否被改坏。

## H5 请求代理不生效

排查：

- `env/.env` 的 `VITE_APP_PROXY_ENABLE` 是否为 `true`。
- `VITE_APP_PROXY_PREFIX` 是否与接口路径匹配。
- `vite.config.ts` 的 `server.proxy` target 是否是 `VITE_SERVER_BASEURL`。
- 非 H5 端不走 Vite devServer proxy，要直接配置真实 baseURL。

## OpenAPI 生成后调用报类型或参数错误

排查：

- schema 是否是 OpenAPI / Swagger 格式，而不是普通接口文档。
- `operationId` 是否稳定且唯一。
- `openapi-ts-request.config.ts` 的 `serversPath` 是否和 import 路径一致。
- `requestLibPath` 是否指向 `@/http/vue-query` 或项目实际 request adapter。
- `requestOptionsType` 是否为 `CustomRequestOptions_`。
- 生成器产生的 `params` / `headers` 是否被 adapter 转成了 `query` / `header`。

## wot-ui toast / message-box 不生效

修复：在 layout 中挂载：

```vue
<template>
  <view>
    <slot />
    <wd-toast />
    <wd-message-box />
  </view>
</template>
```

`unibest@2.1.0` 起默认已有，但旧项目或替换 layout 后要检查。

## wot-ui-v2 H5 样式不生效

排查：

- 是否安装 `@wot-ui/ui`。
- `pages.config.ts` easycom 是否有 `^wd-(.*)`。
- `tsconfig.json` types 是否包含 `@wot-ui/ui/global`。
- 是否存在 `wot-ui-resolver.ts`。
- `vite.config.ts` 的 `UniComponents` 是否包含 `resolvers: [WotResolver()]`。

## Vue Official 插件报错

文档曾记录最新可用版本为 `v2.2.8`，`v2.2.10` 可能报错。遇到编辑器类型服务异常时，先尝试回退 Vue Official 插件版本并关闭自动更新。

## [plugin:uni:mp-using-component] Unexpected token S in JSON

旧项目可能是 `@uni-helper/vite-plugin-uni-pages` 小版本问题。

修复：尝试回退到文档记录的 `0.2.20`，再重新安装依赖。新项目则先对照 lock 文件和当前模板版本。

## Git commit 被 husky / commitlint 拦住

修复：

- 按 `commitlint.config.ts` 使用规范提交信息。
- 临时跳过：`git commit -m "feat: xxx" --no-verify`。
- 不需要严格检测时，可删除或注释 `.husky`。
