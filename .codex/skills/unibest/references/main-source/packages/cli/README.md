# create-unibest

跨平台 uniapp 项目脚手架工具。

## 功能特性

- 🚀 **快速创建** - 通过命令行快速生成 unibest 项目
- 🎯 **灵活配置** - 支持选择平台、UI库、Feature
- 🔧 **Feature 注入** - 创建后可动态添加 i18n、login 等功能
- 📦 **多平台支持** - H5、小程序、App 一键生成

## 使用方式

### 安装

```bash
# 全局安装（可选）
npm i -g create-unibest

# 或直接使用
pnpm create unibest
```

### 创建项目

```bash
# 交互式创建
pnpm create unibest my-project

# 命令行参数创建
pnpm create unibest my-project \
  --platform h5,mp-weixin \
  --ui none \
  --i18n \
  --login
```

### 添加 Feature

```bash
cd my-project

# 添加多语言
pnpm create unibest add i18n

# 添加登录策略
pnpm create unibest add login

# 强制重新注入（覆盖已有配置）
pnpm create unibest add i18n --force

# 写入依赖后自动安装
pnpm create unibest add i18n --install
```

## 参数说明

### 创建项目参数

| 参数             | 说明                                                                            |
| ---------------- | ------------------------------------------------------------------------------- |
| `-p, --platform` | 平台类型，支持 `h5`, `mp-weixin`, `app`, `mp-alipay`, `mp-toutiao`              |
| `-u, --ui`       | UI 库，支持 `none`, `wot-ui`, `wot-ui-v2`, `uview-pro`, `sard-uniapp`, `uv-ui`, `uview-plus`, `tdesign` |
| `-l, --login`    | 启用登录策略                                                                    |
| `-i, --i18n`     | 启用多语言                                                                      |

### 添加 Feature 参数

| 参数      | 说明                   |
| --------- | ---------------------- |
| `--path`  | 项目路径，默认当前目录 |
| `--feature` | 指定要添加的 Feature，支持逗号分隔 |
| `--force` | 强制重新注入 Feature   |
| `--install` | 写入依赖后自动执行 `pnpm install` |

## 与 unibest 模板版本对应关系

| create-unibest 版本 | unibest 模板版本 |
| ------------------- | ---------------- |
| 4.0.0               | 4.3.0+           |

## 发布npm

```sh
npm login --registry=https://registry.npmjs.org
npm publish --no-workspaces --registry=https://registry.npmjs.org
```

## License

MIT
