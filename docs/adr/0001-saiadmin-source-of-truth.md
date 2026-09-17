# ADR 0001: Saiadmin 运行时真源 (Source of Truth) 收敛策略

## 状态
已接受 (Accepted)

## 背景
B8AIAdmin 包含对 `saithink/saiadmin` 的大量深度定制代码，例如 DataScope（数据权限控制）、CheckAuth（权限检查）、队列功能、InstallController 安全校验等。
原生 Composer 包 `saithink/saiadmin` 的 `Install.php` 会在 `composer install` 或 `composer update` 时通过 webman 插件机制执行 `copy_dir`，将 `vendor` 内的 `plugin/saiadmin` 强制覆盖到项目侧的 `server/plugin/saiadmin`。这会导致项目本地的业务定制代码被冲洗掉。同时卸载也会直接移除项目源文件。

## 决策
为了保证框架更新与本地定制代码不发生冲突，确立以下规则：

1. **运行时真源 (SoT)**：`server/plugin/saiadmin` 目录被视作唯一业务运行时真源与开发修改点。
2. **Vendor 的定位**：`server/vendor/saithink/saiadmin` 仅仅作为 Composer 下载的安装源和只读对照组副本，**绝对禁止**将其当做运行时修改点。
3. **安装覆盖拦截**：
   - 优先通过项目级的 `server/support/ComposerScripts.php` 钩子拦截包的自动安装/卸载事件。若发现 `server/plugin/saiadmin` 已存在，则拒绝执行 `Plugin::install/uninstall`，保护本地 SoT 不受侵犯。
   - 辅助通过修改 `vendor/saithink/saiadmin/src/Install.php` 作为纵深防御（尽管在后续 composer 更新时可能会被覆盖）。
4. **强制安装策略**：如确有必要覆盖，必须明确设置环境变量 `FORCE_SAIADMIN_PLUGIN_INSTALL=1` 后再执行安装/更新。务必提前做好代码备份。
5. **升级上游逻辑**：上游 `saithink/saiadmin` 有版本更新时，可正常 `composer update`。更新后的新文件会进入 `vendor` 目录，此时开发者需对 `vendor` 和 `plugin` 的代码进行 diff 比对，手工挑选安全有用的更改合并至 `server/plugin/saiadmin`。

## 影响
- 团队不再会因为执行 `composer update` 意外丢失本地化改造（特别是 `DataScope.php`、`CheckAuth.php` 等核心业务代码）。
- 修改此模块的业务代码必须且只能在 `server/plugin/saiadmin` 内进行。
