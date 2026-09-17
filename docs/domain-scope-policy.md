# 域策略数据范围隔离说明

## 背景
本系统采用 `created_by` 字段进行业务数据的范围隔离（即“按域强制 scope”），以限制用户只能查看和管理属于自己创建的业务数据。
**注意：本次更新实现的 scope 是基于 `created_by` 进行过滤，属于短期策略，并非 tenant_id（多租户）模式。**不涉及真正的多租户底层数据隔离。

## 隔离范围与控制
`BaseLogic` 默认关闭隔离 (`protected bool $scope = false;`)。但通过脚手架新生成业务 Logic 将默认开启 `$scope = true`。当开启时，将自动调用 `userDataScope()` 利用 `created_by IN (...)` 进行过滤。`userDataScope` 的合并语义（依赖 widest-wins）保持不变。模型在插入时会自动写入 `created_by` 字段。

只有在包含 `created_by` 字段的表上，才允许开启 scope 功能。如果在没有该字段的表上强制开启，会导致 SQL 查询报错。

## 插件 x 模块 x 隔离/白名单/缺口矩阵

### 强制隔离区（已开启 `$scope = true`）
以下模块具有 `created_by` 字段的业务表，已在对应 Logic 开启了隔离：
- **saiboard**: DatasourceLogic、MarketItemLogic、QueryTemplateLogic、ScreenLogic
- **saiuser cms**: ArticleLogic、ArticleBannerLogic、ArticleCategoryLogic
- **saiai chat**: AiChatLogic、AiChatGroupLogic
- **b8cms**: ContentLogic、CommentLogic、ContactMessageLogic、CarouselLogic
- **saipay admin**: OrderLogic

### 白名单区（全局共享资源，保持 `$scope = false`）
以下为全局配置或共享资源表，不开启数据范围隔离：
- **saiadmin 系统**: Dict、Config、Menu、Dept、Role、Post、User、MailTemplate、Database*、Crontab*、Queue*、Generate*、Attachment、Category、LoginLog、OperLog、Mail
- **saiuser 设置**: MemberLevel、MemberPlatform、MemberProtocol、SiteInfo
- **saiai**: AiConfig（全局模型配置）
- **saisms**: SmsConfig、SmsTag（配置类）
- **b8cms**: Language、SiteSetting、Template、CommentFilter、Navigation（站点级配置/结构）
- **saicode**: 全部逻辑类；saipackage InstallLogic；IndexLogic 无模型

### 缺口区（无 `created_by` 字段，暂无法开启）
经核实，以下表结构缺少 `created_by` 字段，故对应的 Logic 暂时不能硬性开启 scope（已记录在案）：
- **saiuser 会员核心表**: sa_member、sa_member_login_log、sa_member_points_log、sa_member_platform(_rel)、sa_member_protocol、sa_member_level 等对应 Logic (MemberLogic 等)
- **saisms**: SmsRecordLogic 对应的发送记录表
- **saiuser store**: StoreApp、AppVersion、AppDocument （缺少表或缺少相关字段）

## 升级与开发说明
1. **生成代码默认行为**: 新生成的 Logic 默认开启隔离（`$scope = true`），全局或共享资源必须在生成后手动修改为 `$scope = false;`。
2. **已有代码**: 不要强制对上述“缺口”中的 Logic 修改 `$scope`，需在确认相关表已扩展 `created_by` 字段后再考虑开启。
3. **数据合并**: 不影响 `DataScope::resolveWidest` 逻辑及 `userDataScope` 现有语义。
