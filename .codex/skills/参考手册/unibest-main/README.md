<p align="center">
  <a href="https://github.com/feige996/unibest">
    <img width="160" src="./src/static/logo.svg">
  </a>
</p>

<h1 align="center">
  <a href="https://github.com/feige996/unibest" target="_blank">unibest - 最好的 uniapp 开发框架</a>
</h1>

<div align="center">
旧仓库 codercup 进不去了，star 也拿不回来，这里也展示一下那个地址的 star.

[![GitHub Repo stars](https://img.shields.io/github/stars/codercup/unibest?style=flat&logo=github)](https://github.com/codercup/unibest)
[![GitHub forks](https://img.shields.io/github/forks/codercup/unibest?style=flat&logo=github)](https://github.com/codercup/unibest)

</div>

<div align="center">

[![GitHub Repo stars](https://img.shields.io/github/stars/feige996/unibest?style=flat&logo=github)](https://github.com/feige996/unibest)
[![GitHub forks](https://img.shields.io/github/forks/feige996/unibest?style=flat&logo=github)](https://github.com/feige996/unibest)
[![star](https://gitee.com/feige996/unibest/badge/star.svg?theme=dark)](https://gitee.com/feige996/unibest/stargazers)
[![fork](https://gitee.com/feige996/unibest/badge/fork.svg?theme=dark)](https://gitee.com/feige996/unibest/members)
![node version](https://img.shields.io/badge/node-%3E%3D18-green)
![pnpm version](https://img.shields.io/badge/pnpm-%3E%3D7.30-green)
![GitHub package.json version (subfolder of monorepo)](https://img.shields.io/github/package-json/v/feige996/unibest)
![GitHub License](https://img.shields.io/github/license/feige996/unibest)

</div>

`unibest` —— 最好的 `uniapp` 开发模板，由 `uniapp` + `Vue3` + `Ts` + `Vite5` + `UnoCss` + `wot-ui` + `z-paging` 构成，使用了最新的前端技术栈，无需依靠 `HBuilderX`，通过命令行方式运行 `web`、`小程序` 和 `App`（编辑器推荐 `VSCode`，可选 `webstorm`）。

`unibest` 内置了 `约定式路由`、`layout布局`、`请求封装`、`请求拦截`、`登录拦截`、`UnoCSS`、`i18n多语言` 等基础功能，提供了 `代码提示`、`自动格式化`、`统一配置`、`代码片段` 等辅助功能，让你编写 `uniapp` 拥有 `best` 体验 （ `unibest 的由来`）。

![](https://raw.githubusercontent.com/andreasbm/readme/master/screenshots/lines/rainbow.png)

<p align="center">
  <a href="https://unibest.tech/" target="_blank">📖 文档地址(new)</a>
  <span style="margin:0 10px;">|</span>
  <a href="https://unibest-tech.github.io/hello-unibest" target="_blank">📱 DEMO 地址</a>
</p>

---

注意旧的地址 [codercup](https://github.com/codercup/unibest) 我进不去了，使用新的 [feige996](https://github.com/feige996/unibest)。PR和 issue 也请使用新地址，否则无法合并。

## 平台兼容性

| H5  | IOS | 安卓 | 微信小程序 | 字节小程序 | 快手小程序 | 支付宝小程序 | 钉钉小程序 | 百度小程序 |
| --- | --- | ---- | ---------- | ---------- | ---------- | ------------ | ---------- | ---------- |
| √   | √   | √    | √          | √          | √          | √            | √          | √          |

注意每种 `UI框架` 支持的平台有所不同，详情请看各 `UI框架` 的官网，也可以看 `unibest` 文档。

## ⚙️ 环境

- node>=18
- pnpm>=9
- Vue Official>=3.4
- TypeScript>=5.0

## 🏗️ 项目结构

### 本仓库结构（CLI 开发者）

```
unibest/                    # 主仓库（main 分支）
├── packages/
│   └── cli/                # CLI 脚手架工具（发布到 npm）
├── src/                    # 模板源码（同base分支）
└── 其他配置文件
```

> cli 会从 base 分支拉取基础代码。

### 用户项目结构（创建项目后）

```
my-project/                 # 用户项目
├── src/                    # 源码
├── pages.json              # 页面配置
├── manifest.json           # 应用配置
├── App.vue                 # 应用入口
├── main.ts                 # 入口文件
└── 其他配置文件
```

**注意：** 用户项目不包含 `packages/` 目录。

### 双重用途

本仓库既是**基础模板**，也是 **CLI 脚手架**的源码仓库：

- **作为模板**：直接克隆 `git clone` 后即可开发
- **作为 CLI**：通过 `pnpm create unibest` 创建新项目

### CLI 发布

`packages/cli/` 目录会发布到 npm 包 `create-unibest`，用户安装后从 Git `base` 分支克隆模板。

分支职责：`main` 是完整开发与发布分支，包含 CLI 源码和文档；`base` 是用户项目模板分支，也是 CLI 创建项目时的模板来源。

## 📦 使用方式

### 方式一：通过 CLI 创建新项目（推荐）

```bash
# 全局安装 CLI
pnpm add -g create-unibest

# 创建项目（从 base 分支克隆模板）
pnpm create unibest my-project
cd my-project

pnpm install
pnpm dev
```

### 方式二：创建时选择 Feature

```bash
# 创建项目并选择功能
pnpm create unibest my-project

# 或通过命令行参数直接指定
pnpm create unibest my-project --i18n --login
```

### 方式三：创建后添加 Feature

```bash
cd my-project

# 添加多语言
pnpm create unibest add i18n

# 添加登录策略
pnpm create unibest add login

# 同时添加多个
pnpm create unibest add i18n login
```

### 方式四：直接克隆开发（备选）

```bash
# 克隆本仓库作为基础模板
git clone https://github.com/feige996/unibest.git my-project
cd my-project

# 安装依赖并运行
pnpm install
pnpm dev        # 运行 H5
pnpm dev:mp     # 运行微信小程序
pnpm dev:app    # 运行 App
```

## 📦 运行（支持热更新）

- web平台： `pnpm dev:h5`, 然后打开 [http://localhost:9000/](http://localhost:9000/)。
- weixin平台：`pnpm dev:mp` 然后打开微信开发者工具，导入本地文件夹，选择本项目的`dist/dev/mp-weixin` 文件。
- APP平台：`pnpm dev:app`, 然后打开 `HBuilderX`，导入刚刚生成的`dist/dev/app` 文件夹，选择运行到模拟器(开发时优先使用)，或者运行的安卓/ios基座。(如果是 `安卓` 和 `鸿蒙` 平台，则不用这个方式，可以把整个unibest项目导入到hbx，通过hbx的菜单来运行到对应的平台。)

## 🔗 发布

- web平台： `pnpm build:h5`，打包后的文件在 `dist/build/h5`，可以放到web服务器，如nginx运行。如果最终不是放在根目录，可以在 `manifest.config.ts` 文件的 `h5.router.base` 属性进行修改。
- weixin平台：`pnpm build:mp`, 打包后的文件在 `dist/build/mp-weixin`，然后通过微信开发者工具导入，并点击右上角的“上传”按钮进行上传。
- APP平台：`pnpm build:app`, 然后打开 `HBuilderX`，导入刚刚生成的`dist/build/app` 文件夹，选择发行 - APP云打包。(如果是 `安卓` 和 `鸿蒙` 平台，则不用这个方式，可以把整个unibest项目导入到hbx，通过hbx的菜单来发行到对应的平台。)

## 📄 License

[MIT](https://opensource.org/license/mit/)

Copyright (c) 2025 菲鸽

## 捐赠

<p align='center'>
<img alt="special sponsor appwrite" src="https://oss.laf.run/ukw0y1-site/pay/wepay.png" height="330" style="display:inline-block; height:330px;">
<img alt="special sponsor appwrite" src="https://oss.laf.run/ukw0y1-site/pay/alipay.jpg" height="330" style="display:inline-block; height:330px; margin-left:10px;">
</p>
