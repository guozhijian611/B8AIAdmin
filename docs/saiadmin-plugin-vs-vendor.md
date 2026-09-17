# Saiadmin 插件 vs Vendor 源差异清单

此文档记录了 `server/plugin/saiadmin` (真源 SoT) 与 `server/vendor/saithink/saiadmin/src/plugin/saiadmin` 之间的差异快照。

**关键差别说明**：
- 包含深度定制业务：`utils/DataScope.php`
- 包含基础逻辑修改：`basic/think/BaseLogic.php`
- 包含权限验证定制：`app/middleware/CheckAuth.php`
- 包含安装安全拦截：`app/controller/InstallController.php`
- 以及其它仅存在于 B8 项目里的队列控制器/模型/事件等。

**差异列表** (通过 `diff -rq` 生成)：

```text
Files server/plugin/saiadmin/app/cache/ConfigCache.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/cache/ConfigCache.php differ
Files server/plugin/saiadmin/app/controller/InstallController.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/controller/InstallController.php differ
Only in server/plugin/saiadmin/app/controller/system: AdminerController.php
Only in server/plugin/saiadmin/app/controller/system: DatabaseBackupController.php
Only in server/plugin/saiadmin/app/controller/system: LogReaderController.php
Only in server/plugin/saiadmin/app/controller/system: SystemMailTemplateController.php
Files server/plugin/saiadmin/app/controller/system/SystemRoleController.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/controller/system/SystemRoleController.php differ
Only in server/plugin/saiadmin/app/controller/tool: QueueConfigController.php
Only in server/plugin/saiadmin/app/controller/tool: QueueMessageController.php
Only in server/plugin/saiadmin/app/controller/tool: QueueRuntimeController.php
Only in server/plugin/saiadmin/app/controller/tool: QueueTaskController.php
Files server/plugin/saiadmin/app/event/SystemUser.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/event/SystemUser.php differ
Files server/plugin/saiadmin/app/functions.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/functions.php differ
Only in server/plugin/saiadmin/app/logic/system: DatabaseBackupLogic.php
Files server/plugin/saiadmin/app/logic/system/DatabaseLogic.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/logic/system/DatabaseLogic.php differ
Files server/plugin/saiadmin/app/logic/system/SystemConfigLogic.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/logic/system/SystemConfigLogic.php differ
Files server/plugin/saiadmin/app/logic/system/SystemLoginLogLogic.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/logic/system/SystemLoginLogLogic.php differ
Only in server/plugin/saiadmin/app/logic/system: SystemMailTemplateLogic.php
Files server/plugin/saiadmin/app/logic/system/SystemRoleLogic.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/logic/system/SystemRoleLogic.php differ
Files server/plugin/saiadmin/app/logic/tool/GenerateTablesLogic.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/logic/tool/GenerateTablesLogic.php differ
Only in server/plugin/saiadmin/app/logic/tool: QueueConfigLogic.php
Only in server/plugin/saiadmin/app/logic/tool: QueueMessageLogic.php
Only in server/plugin/saiadmin/app/logic/tool: QueueTaskLogic.php
Files server/plugin/saiadmin/app/middleware/CheckAuth.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/middleware/CheckAuth.php differ
Only in server/plugin/saiadmin/app/middleware: LogReaderAccess.php
Files server/plugin/saiadmin/app/model/system/SystemAttachment.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemAttachment.php differ
Files server/plugin/saiadmin/app/model/system/SystemCategory.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemCategory.php differ
Files server/plugin/saiadmin/app/model/system/SystemConfig.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemConfig.php differ
Files server/plugin/saiadmin/app/model/system/SystemConfigGroup.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemConfigGroup.php differ
Files server/plugin/saiadmin/app/model/system/SystemDept.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemDept.php differ
Files server/plugin/saiadmin/app/model/system/SystemDictData.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemDictData.php differ
Files server/plugin/saiadmin/app/model/system/SystemDictType.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemDictType.php differ
Files server/plugin/saiadmin/app/model/system/SystemLoginLog.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemLoginLog.php differ
Files server/plugin/saiadmin/app/model/system/SystemMail.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemMail.php differ
Only in server/plugin/saiadmin/app/model/system: SystemMailTemplate.php
Files server/plugin/saiadmin/app/model/system/SystemMenu.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemMenu.php differ
Files server/plugin/saiadmin/app/model/system/SystemOperLog.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemOperLog.php differ
Files server/plugin/saiadmin/app/model/system/SystemPost.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemPost.php differ
Files server/plugin/saiadmin/app/model/system/SystemRole.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemRole.php differ
Files server/plugin/saiadmin/app/model/system/SystemRoleDept.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemRoleDept.php differ
Files server/plugin/saiadmin/app/model/system/SystemRoleMenu.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemRoleMenu.php differ
Files server/plugin/saiadmin/app/model/system/SystemUser.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemUser.php differ
Files server/plugin/saiadmin/app/model/system/SystemUserPost.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemUserPost.php differ
Files server/plugin/saiadmin/app/model/system/SystemUserRole.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/system/SystemUserRole.php differ
Files server/plugin/saiadmin/app/model/tool/Crontab.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/tool/Crontab.php differ
Files server/plugin/saiadmin/app/model/tool/CrontabLog.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/model/tool/CrontabLog.php differ
Only in server/plugin/saiadmin/app/model/tool: QueueConfig.php
Only in server/plugin/saiadmin/app/model/tool: QueueMessage.php
Only in server/plugin/saiadmin/app/model/tool: QueueTask.php
Only in server/plugin/saiadmin/app: service
Only in server/plugin/saiadmin/app/validate/system: SystemMailTemplateValidate.php
Only in server/plugin/saiadmin/app/validate/tool: QueueConfigValidate.php
Only in server/plugin/saiadmin/app/validate/tool: QueueMessageValidate.php
Files server/plugin/saiadmin/app/view/install/index.html and server/vendor/saithink/saiadmin/src/plugin/saiadmin/app/view/install/index.html differ
Files server/plugin/saiadmin/basic/eloquent/BaseLogic.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/basic/eloquent/BaseLogic.php differ
Files server/plugin/saiadmin/basic/think/BaseLogic.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/basic/think/BaseLogic.php differ
Files server/plugin/saiadmin/command/SaiPlugin.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/command/SaiPlugin.php differ
Files server/plugin/saiadmin/config/app.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/config/app.php differ
Files server/plugin/saiadmin/config/route.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/config/route.php differ
Files server/plugin/saiadmin/db/data/demo.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/db/data/demo.php differ
Files server/plugin/saiadmin/db/migrations/20260822000000_init_base_tables.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/db/migrations/20260822000000_init_base_tables.php differ
Only in server/plugin/saiadmin/db/migrations: 20260918000000_fix_demo_data_scope_enum.php
Files server/plugin/saiadmin/db/saiadmin-6.0.sql and server/vendor/saithink/saiadmin/src/plugin/saiadmin/db/saiadmin-6.0.sql differ
Files server/plugin/saiadmin/db/saiadmin-pure.sql and server/vendor/saithink/saiadmin/src/plugin/saiadmin/db/saiadmin-pure.sql differ
Only in server/plugin/saiadmin/process: queue
Only in server/plugin/saiadmin: resource
Only in server/plugin/saiadmin: scripts
Files server/plugin/saiadmin/service/EmailService.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/service/EmailService.php differ
Only in server/plugin/saiadmin/utils: DataScope.php
Files server/plugin/saiadmin/utils/code/CodeEngine.php and server/vendor/saithink/saiadmin/src/plugin/saiadmin/utils/code/CodeEngine.php differ
Files server/plugin/saiadmin/utils/code/stub/saiadmin/php/controller.stub and server/vendor/saithink/saiadmin/src/plugin/saiadmin/utils/code/stub/saiadmin/php/controller.stub differ
Files server/plugin/saiadmin/utils/code/stub/saiadmin/php/model.stub and server/vendor/saithink/saiadmin/src/plugin/saiadmin/utils/code/stub/saiadmin/php/model.stub differ
Files server/plugin/saiadmin/utils/code/stub/saiadmin/sql/sql.stub and server/vendor/saithink/saiadmin/src/plugin/saiadmin/utils/code/stub/saiadmin/sql/sql.stub differ
Files server/plugin/saiadmin/utils/code/stub/saiadmin/vue/edit-dialog.stub and server/vendor/saithink/saiadmin/src/plugin/saiadmin/utils/code/stub/saiadmin/vue/edit-dialog.stub differ
Files server/plugin/saiadmin/utils/code/stub/saiadmin/vue/index.stub and server/vendor/saithink/saiadmin/src/plugin/saiadmin/utils/code/stub/saiadmin/vue/index.stub differ
```

## `saipackage` SoT 保护

与 `saiadmin` 类似，本项目的 `saipackage` 插件也受 SoT (Source of Truth) 保护。在执行 `composer install` 或 `composer update` 时，如果 `server/plugin/saipackage` 目录已存在，Composer 将自动跳过 `saithink/saipackage` 的安装/卸载脚本，以防止覆盖或删除本地定制。

若需强制覆盖本地的 `saipackage`，可以在命令前设置环境变量：
```bash
FORCE_SAIPACKAGE_PLUGIN_INSTALL=1 composer update saithink/saipackage
```
