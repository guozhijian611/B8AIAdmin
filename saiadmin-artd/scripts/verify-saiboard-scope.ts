import { execFileSync } from 'node:child_process'

interface ApiEnvelope<T = any> {
  code: number
  message?: string
  data?: T
}

interface RequestOptions {
  method?: string
  token?: string
  body?: Record<string, any>
  query?: Record<string, any>
}

interface ScopeUser {
  id: number
  username: string
}

interface ScopeFixture {
  suffix: string
  rowPrefix: string
  roleId: number
  users: {
    owner: ScopeUser
    outsider: ScopeUser
  }
  dbConfig: {
    host: string
    port: number
    database: string
    username: string
    password: string
    charset: string
  }
}

interface SmokeRow {
  id: number
  name?: string
  created_by?: number
}

const backendBaseUrl = process.env.SAIBOARD_VERIFY_BACKEND || 'http://127.0.0.1:8787'
const serverDir = new URL('../../server/', import.meta.url)
const marker = '__SAIBOARD_SCOPE_JSON__'

const log = (message: string) => {
  console.log(`[saiboard-scope] ${message}`)
}

const assert = (condition: unknown, message: string): asserts condition => {
  if (!condition) {
    throw new Error(message)
  }
}

const buildUrl = (path: string, query: Record<string, any> = {}) => {
  const url = new URL(path, backendBaseUrl)
  Object.entries(query).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    url.searchParams.set(key, String(value))
  })
  return url
}

const runPhp = (code: string, payload?: Record<string, any>) => {
  const args = ['-r', code]
  if (payload) {
    args.push(JSON.stringify(payload))
  }

  return execFileSync('php', args, {
    cwd: serverDir,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe']
  })
}

const markedJson = <T>(output: string): T => {
  const index = output.lastIndexOf(marker)
  if (index < 0) {
    throw new Error(`PHP 输出缺少结果标记：${output}`)
  }

  return JSON.parse(output.slice(index + marker.length).trim()) as T
}

const generateToken = (user: ScopeUser) => {
  const code = [
    'require __DIR__ . "/vendor/autoload.php";',
    'require __DIR__ . "/support/bootstrap.php";',
    '$payload=json_decode($argv[1] ?? "{}", true) ?: [];',
    '$token=\\Tinywan\\Jwt\\JwtToken::generateToken([',
    '"access_exp"=>600,',
    '"id"=>(int)$payload["id"],',
    '"username"=>(string)$payload["username"],',
    '"type"=>"pc",',
    '"plat"=>"saiadmin"',
    ']);',
    'echo $token["access_token"];'
  ].join('')

  return runPhp(code, user).trim()
}

async function requestApi<T = any>(
  path: string,
  options: RequestOptions = {}
): Promise<ApiEnvelope<T>> {
  const method = options.method || (options.body ? 'POST' : 'GET')
  const response = await fetch(buildUrl(path, options.query), {
    method,
    headers: {
      Accept: 'application/json',
      ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
      ...(options.body ? { 'Content-Type': 'application/json' } : {})
    },
    body: options.body ? JSON.stringify(options.body) : undefined
  })
  const text = await response.text()
  try {
    return JSON.parse(text) as ApiEnvelope<T>
  } catch {
    throw new Error(`${method} ${path} 返回非 JSON 响应，HTTP ${response.status}: ${text}`)
  }
}

async function api<T = any>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method || (options.body ? 'POST' : 'GET')
  const payload = await requestApi<T>(path, options)
  if (payload.code !== 200) {
    throw new Error(
      `${method} ${path} expected code 200, got ${payload.code}: ${payload.message || ''}`
    )
  }
  return payload.data as T
}

async function expectDenied(label: string, path: string, options: RequestOptions = {}) {
  const payload = await requestApi(path, options)
  assert(payload.code !== 200, `${label} 意外成功，数据权限未拦截`)
  log(`${label} 已拒绝：${payload.message || payload.code}`)
}

const setupFixture = (suffix: string): ScopeFixture => {
  const code = `
require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/support/bootstrap.php";

use plugin\\saiadmin\\app\\cache\\UserAuthCache;
use plugin\\saiadmin\\app\\cache\\UserInfoCache;
use plugin\\saiadmin\\app\\cache\\UserMenuCache;
use support\\think\\Db;

$payload = json_decode($argv[1] ?? "{}", true) ?: [];
$suffix = preg_replace('/[^A-Za-z0-9_]/', '', (string)($payload['suffix'] ?? ''));
if ($suffix === '') {
    throw new RuntimeException('缺少 suffix');
}

$rowPrefix = 'SAI Board Scope Smoke ' . $suffix;
$roleCode = 'saiboard_scope_smoke_' . strtolower($suffix);
$ownerUsername = 'saiboard_scope_owner_' . strtolower($suffix);
$outsiderUsername = 'saiboard_scope_outsider_' . strtolower($suffix);
$now = date('Y-m-d H:i:s');

$cleanup = static function () use ($rowPrefix, $roleCode, $ownerUsername, $outsiderUsername): void {
    $screenIds = array_map('intval', Db::name('saiboard_screen')->where('name', 'like', $rowPrefix . '%')->column('id'));
    if ($screenIds) {
        Db::name('saiboard_screen_token')->whereIn('screen_id', $screenIds)->delete();
        Db::name('saiboard_screen_version')->whereIn('screen_id', $screenIds)->delete();
        Db::name('saiboard_screen')->whereIn('id', $screenIds)->delete();
    }
    Db::name('saiboard_query_template')->where('name', 'like', $rowPrefix . '%')->delete();
    Db::name('saiboard_datasource')->where('name', 'like', $rowPrefix . '%')->delete();

    $userIds = array_map('intval', Db::name('sa_system_user')->whereIn('username', [$ownerUsername, $outsiderUsername])->column('id'));
    $roleIds = array_map('intval', Db::name('sa_system_role')->where('code', $roleCode)->column('id'));
    if ($userIds) {
        Db::name('sa_system_user_role')->whereIn('user_id', $userIds)->delete();
        Db::name('sa_system_user')->whereIn('id', $userIds)->delete();
        foreach ($userIds as $userId) {
            UserInfoCache::clearUserInfo($userId);
            UserAuthCache::clearUserAuth($userId);
            UserMenuCache::clearUserMenu($userId);
        }
    }
    if ($roleIds) {
        Db::name('sa_system_role_menu')->whereIn('role_id', $roleIds)->delete();
        Db::name('sa_system_role')->whereIn('id', $roleIds)->delete();
        UserInfoCache::clearUserInfoByRoleId($roleIds);
        UserAuthCache::clearUserAuthByRoleId($roleIds);
        UserMenuCache::clearMenuCache();
    }
};

$cleanup();

$menuIds = array_map('intval', Db::name('sa_system_menu')->where('slug', 'like', 'saiboard:%')->where('status', 1)->column('id'));
if (!$menuIds) {
    throw new RuntimeException('未找到 SAI Board 菜单按钮权限');
}

$roleId = (int) Db::name('sa_system_role')->insertGetId([
    'name' => $rowPrefix . ' 仅本人角色',
    'code' => $roleCode,
    'level' => 1,
    'data_scope' => 5,
    'remark' => 'SAI Board 数据权限自动验收临时角色',
    'sort' => 1,
    'status' => 1,
    'created_by' => 1,
    'updated_by' => 1,
    'create_time' => $now,
    'update_time' => $now,
]);
foreach ($menuIds as $menuId) {
    Db::name('sa_system_role_menu')->insert([
        'role_id' => $roleId,
        'menu_id' => $menuId,
    ]);
}

$deptId = Db::name('sa_system_dept')->order('id', 'asc')->value('id');
$password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
$baseUser = [
    'password' => $password,
    'gender' => '0',
    'dashboard' => 'work',
    'dept_id' => $deptId ?: null,
    'is_super' => 0,
    'status' => 1,
    'remark' => 'SAI Board 数据权限自动验收临时用户',
    'created_by' => 1,
    'updated_by' => 1,
    'create_time' => $now,
    'update_time' => $now,
];
$ownerId = (int) Db::name('sa_system_user')->insertGetId($baseUser + [
    'username' => $ownerUsername,
    'realname' => $rowPrefix . ' 用户A',
]);
$outsiderId = (int) Db::name('sa_system_user')->insertGetId($baseUser + [
    'username' => $outsiderUsername,
    'realname' => $rowPrefix . ' 用户B',
]);
Db::name('sa_system_user_role')->insert(['user_id' => $ownerId, 'role_id' => $roleId]);
Db::name('sa_system_user_role')->insert(['user_id' => $outsiderId, 'role_id' => $roleId]);

foreach ([$ownerId, $outsiderId] as $userId) {
    UserInfoCache::clearUserInfo($userId);
    UserAuthCache::clearUserAuth($userId);
    UserMenuCache::clearUserMenu($userId);
}

$db = config('database.connections.mysql');
$result = [
    'suffix' => $suffix,
    'rowPrefix' => $rowPrefix,
    'roleId' => $roleId,
    'users' => [
        'owner' => ['id' => $ownerId, 'username' => $ownerUsername],
        'outsider' => ['id' => $outsiderId, 'username' => $outsiderUsername],
    ],
    'dbConfig' => [
        'host' => (string)($db['host'] ?? '127.0.0.1'),
        'port' => (int)($db['port'] ?? 3306),
        'database' => (string)($db['database'] ?? ''),
        'username' => (string)($db['username'] ?? ''),
        'password' => (string)($db['password'] ?? ''),
        'charset' => (string)($db['charset'] ?? 'utf8mb4'),
    ],
];
echo '${marker}' . json_encode($result, JSON_UNESCAPED_UNICODE);
`

  return markedJson<ScopeFixture>(runPhp(code, { suffix }))
}

const cleanupFixture = (suffix: string) => {
  const code = `
require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/support/bootstrap.php";

use plugin\\saiadmin\\app\\cache\\UserAuthCache;
use plugin\\saiadmin\\app\\cache\\UserInfoCache;
use plugin\\saiadmin\\app\\cache\\UserMenuCache;
use support\\think\\Db;

$payload = json_decode($argv[1] ?? "{}", true) ?: [];
$suffix = preg_replace('/[^A-Za-z0-9_]/', '', (string)($payload['suffix'] ?? ''));
if ($suffix === '') {
    throw new RuntimeException('缺少 suffix');
}

$rowPrefix = 'SAI Board Scope Smoke ' . $suffix;
$roleCode = 'saiboard_scope_smoke_' . strtolower($suffix);
$ownerUsername = 'saiboard_scope_owner_' . strtolower($suffix);
$outsiderUsername = 'saiboard_scope_outsider_' . strtolower($suffix);

$screenIds = array_map('intval', Db::name('saiboard_screen')->where('name', 'like', $rowPrefix . '%')->column('id'));
if ($screenIds) {
    Db::name('saiboard_screen_token')->whereIn('screen_id', $screenIds)->delete();
    Db::name('saiboard_screen_version')->whereIn('screen_id', $screenIds)->delete();
    Db::name('saiboard_screen')->whereIn('id', $screenIds)->delete();
}
Db::name('saiboard_query_template')->where('name', 'like', $rowPrefix . '%')->delete();
Db::name('saiboard_datasource')->where('name', 'like', $rowPrefix . '%')->delete();

$userIds = array_map('intval', Db::name('sa_system_user')->whereIn('username', [$ownerUsername, $outsiderUsername])->column('id'));
$roleIds = array_map('intval', Db::name('sa_system_role')->where('code', $roleCode)->column('id'));
if ($userIds) {
    Db::name('sa_system_user_role')->whereIn('user_id', $userIds)->delete();
    Db::name('sa_system_user')->whereIn('id', $userIds)->delete();
    foreach ($userIds as $userId) {
        UserInfoCache::clearUserInfo($userId);
        UserAuthCache::clearUserAuth($userId);
        UserMenuCache::clearUserMenu($userId);
    }
}
if ($roleIds) {
    Db::name('sa_system_role_menu')->whereIn('role_id', $roleIds)->delete();
    Db::name('sa_system_role')->whereIn('id', $roleIds)->delete();
    UserInfoCache::clearUserInfoByRoleId($roleIds);
    UserAuthCache::clearUserAuthByRoleId($roleIds);
    UserMenuCache::clearMenuCache();
}

$residue = [
    'datasources' => Db::name('saiboard_datasource')->where('name', 'like', $rowPrefix . '%')->count(),
    'templates' => Db::name('saiboard_query_template')->where('name', 'like', $rowPrefix . '%')->count(),
    'screens' => Db::name('saiboard_screen')->where('name', 'like', $rowPrefix . '%')->count(),
    'users' => Db::name('sa_system_user')->whereIn('username', [$ownerUsername, $outsiderUsername])->count(),
    'roles' => Db::name('sa_system_role')->where('code', $roleCode)->count(),
];
echo '${marker}' . json_encode($residue, JSON_UNESCAPED_UNICODE);
`

  return markedJson<Record<string, number>>(runPhp(code, { suffix }))
}

const lookupByName = (table: string, name: string): SmokeRow => {
  const code = `
require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/support/bootstrap.php";

use support\\think\\Db;

$payload = json_decode($argv[1] ?? "{}", true) ?: [];
$table = (string)($payload['table'] ?? '');
$name = (string)($payload['name'] ?? '');
$allowed = ['saiboard_datasource', 'saiboard_screen', 'saiboard_query_template'];
if (!in_array($table, $allowed, true) || $name === '') {
    throw new RuntimeException('查询参数不正确');
}

$row = Db::name($table)
    ->where('name', $name)
    ->whereNull('delete_time')
    ->field('id,name,created_by')
    ->find();
echo '${marker}' . json_encode($row ?: [], JSON_UNESCAPED_UNICODE);
`

  return markedJson<SmokeRow>(runPhp(code, { table, name }))
}

const rowsFromList = (payload: any): any[] => {
  if (Array.isArray(payload)) {
    return payload
  }
  if (Array.isArray(payload?.data)) {
    return payload.data
  }
  return []
}

async function main() {
  const suffix = Date.now().toString(36)
  let fixture: ScopeFixture | undefined

  try {
    fixture = setupFixture(suffix)
    log(
      `临时普通角色已创建 #${fixture.roleId}，用户A #${fixture.users.owner.id}，用户B #${fixture.users.outsider.id}`
    )

    const ownerToken = generateToken(fixture.users.owner)
    const outsiderToken = generateToken(fixture.users.outsider)
    assert(ownerToken.length > 20 && outsiderToken.length > 20, '普通用户测试 token 生成失败')
    log('普通用户测试 token 已生成')

    const datasourceName = `${fixture.rowPrefix} 数据源`
    await api('/app/saiboard/admin/Datasource/save', {
      token: ownerToken,
      body: {
        name: datasourceName,
        type: 'mysql',
        config: fixture.dbConfig,
        cache_ttl: 0,
        status: 1,
        remark: ''
      }
    })
    const datasource = lookupByName('saiboard_datasource', datasourceName)
    assert(datasource.id > 0, '用户A数据源创建后未找到记录')
    assert(
      Number(datasource.created_by) === fixture.users.owner.id,
      '用户A数据源 created_by 不正确'
    )
    log(`用户A数据源已创建 #${datasource.id}`)

    const ownerOptions = await api<Array<{ id: number }>>(
      '/app/saiboard/admin/Datasource/options',
      { token: ownerToken }
    )
    assert(
      ownerOptions.some((item) => Number(item.id) === datasource.id),
      '用户A数据源选项未包含自己创建的数据源'
    )

    const testResult = await api<{ rows: any[] }>('/app/saiboard/admin/Datasource/test', {
      token: ownerToken,
      body: { id: datasource.id }
    })
    assert(Array.isArray(testResult.rows), '用户A数据源测试未返回 rows')

    const schema = await api<{ tables: Array<{ name: string }> }>(
      '/app/saiboard/admin/Datasource/schema',
      {
        token: ownerToken,
        query: { id: datasource.id, table: 'saipay_order' }
      }
    )
    assert(
      schema.tables.some((item) => item.name === 'saipay_order'),
      '用户A数据源表结构未包含 saipay_order'
    )
    log('用户A数据源测试和表结构读取通过')

    const screenName = `${fixture.rowPrefix} 用户A大屏`
    const generated = await api<{
      id: number
      template_ids: number[]
      component_count: number
    }>('/app/saiboard/admin/Screen/generateFromTable', {
      token: ownerToken,
      body: {
        datasource_id: datasource.id,
        table: 'saipay_order',
        name: screenName,
        width: 1280,
        height: 720,
        chart_types: ['count', 'raw'],
        raw_fields: ['order_no', 'order_name', 'order_price', 'pay_method', 'create_time'],
        order_field: 'create_time'
      }
    })
    assert(generated.id > 0, '用户A从表生成大屏未返回 id')
    assert(generated.template_ids.length > 0, '用户A从表生成大屏未返回查询模板')
    assert(generated.component_count > 0, '用户A从表生成大屏未生成组件')
    log(`用户A自动生成大屏 #${generated.id}，查询模板 ${generated.template_ids.join(',')}`)

    await api('/app/saiboard/admin/Screen/publish', {
      token: ownerToken,
      body: { id: generated.id }
    })
    log('用户A大屏发布通过')

    const templateId = generated.template_ids[0]
    const preview = await api<{ rows: any[]; total?: number }>(
      '/app/saiboard/admin/QueryTemplate/preview',
      {
        token: ownerToken,
        body: { id: templateId }
      }
    )
    assert(Array.isArray(preview.rows), '用户A查询模板预览未返回 rows')

    const ownerMetrics = await api<any>('/app/saiboard/admin/Screen/runtimeMetrics', {
      token: ownerToken,
      query: { id: generated.id }
    })
    assert(ownerMetrics?.screen || ownerMetrics?.totals, '用户A运行统计未返回有效数据')

    const ownerScreen = await api<any>('/app/saiboard/admin/Screen/read', {
      token: ownerToken,
      query: { id: generated.id }
    })
    assert(ownerScreen?.code, '用户A大屏未返回访问编码')

    const runtimeScreen = await api<{ screen: any }>(
      `/app/saiboard/api/screen/${ownerScreen.code}`,
      { token: ownerToken }
    )
    const runtimeComponents = runtimeScreen.screen?.layout?.components || []
    const dataComponent = runtimeComponents.find(
      (component: any) => component?.dataset?.queryTemplateId
    )
    assert(dataComponent?.id, '用户A运行时大屏没有可取数组件')
    const runtimeData = await api<{ rows: any[] }>('/app/saiboard/api/data', {
      token: ownerToken,
      body: {
        code: ownerScreen.code,
        cid: dataComponent.id
      }
    })
    assert(Array.isArray(runtimeData.rows), '用户A运行时取数未返回 rows')
    log('用户A读取自己查询模板、运行统计和私有运行页通过')

    const outsiderScreenName = `${fixture.rowPrefix} 用户B大屏`
    await api('/app/saiboard/admin/Screen/save', {
      token: outsiderToken,
      body: {
        name: outsiderScreenName,
        code: `scopeb${suffix}`.slice(0, 32),
        width: 1280,
        height: 720,
        is_public: 2,
        bg_config: {
          color: '#07111f',
          theme: 'midnight',
          fit_mode: 'contain',
          fit_align: 'top'
        },
        draft_layout: {
          canvas: { width: 1280, height: 720 },
          components: []
        },
        layout: {
          canvas: { width: 1280, height: 720 },
          components: []
        },
        remark: ''
      }
    })
    const outsiderScreen = lookupByName('saiboard_screen', outsiderScreenName)
    assert(outsiderScreen.id > 0, '用户B大屏创建后未找到记录')
    assert(
      Number(outsiderScreen.created_by) === fixture.users.outsider.id,
      '用户B大屏 created_by 不正确'
    )

    const outsiderScreens = rowsFromList(
      await api<any>('/app/saiboard/admin/Screen/index', {
        token: outsiderToken,
        query: { saiType: 'all' }
      })
    )
    const outsiderScreenSummary = outsiderScreens.map((item) => ({
      id: Number(item.id),
      created_by: Number(item.created_by || 0),
      name: item.name
    }))
    assert(
      !outsiderScreens.some((item) => Number(item.id) === generated.id),
      `用户B大屏列表看到了用户A大屏：${JSON.stringify(outsiderScreenSummary)}`
    )
    assert(
      outsiderScreens.some((item) => Number(item.id) === outsiderScreen.id),
      '用户B大屏列表未包含自己创建的大屏'
    )
    log('普通角色列表数据范围通过')

    await expectDenied('用户B读取用户A数据源', '/app/saiboard/admin/Datasource/read', {
      token: outsiderToken,
      query: { id: datasource.id }
    })
    await expectDenied('用户B读取用户A数据源表结构', '/app/saiboard/admin/Datasource/schema', {
      token: outsiderToken,
      query: { id: datasource.id, table: 'saipay_order' }
    })
    await expectDenied('用户B读取用户A查询模板', '/app/saiboard/admin/QueryTemplate/read', {
      token: outsiderToken,
      query: { id: templateId }
    })
    await expectDenied('用户B预览用户A查询模板', '/app/saiboard/admin/QueryTemplate/preview', {
      token: outsiderToken,
      body: { id: templateId }
    })
    await expectDenied('用户B读取用户A运行统计', '/app/saiboard/admin/Screen/runtimeMetrics', {
      token: outsiderToken,
      query: { id: generated.id }
    })
    await expectDenied(
      '用户B后台 token 访问用户A私有运行配置',
      `/app/saiboard/api/screen/${ownerScreen.code}`,
      {
        token: outsiderToken
      }
    )
    await expectDenied('用户B后台 token 访问用户A私有运行取数', '/app/saiboard/api/data', {
      token: outsiderToken,
      body: {
        code: ownerScreen.code,
        cid: dataComponent.id
      }
    })
    await expectDenied('用户B草稿预览用户A大屏', `/app/saiboard/api/screen/${ownerScreen.code}`, {
      token: outsiderToken,
      query: { admin_preview: 1, draft: 1 }
    })
    await expectDenied(
      '用户B使用用户A数据源生成大屏',
      '/app/saiboard/admin/Screen/generateFromTable',
      {
        token: outsiderToken,
        body: {
          datasource_id: datasource.id,
          table: 'saipay_order',
          name: `${fixture.rowPrefix} 越权生成`,
          width: 1280,
          height: 720,
          chart_types: ['count']
        }
      }
    )

    await expectDenied(
      '用户B绑定用户A查询模板到自己的大屏',
      '/app/saiboard/admin/Screen/saveLayout',
      {
        token: outsiderToken,
        body: {
          id: outsiderScreen.id,
          layout: {
            canvas: { width: 1280, height: 720 },
            components: [
              {
                id: 'w_cross_scope',
                type: 'art-kpi-card',
                title: '越权绑定',
                rect: { x: 40, y: 40, w: 280, h: 120, z: 1 },
                dataset: {
                  queryTemplateId: templateId,
                  refresh: 30,
                  mapping: {
                    labelField: 'label',
                    valueField: 'value'
                  }
                },
                option: {}
              }
            ]
          }
        }
      }
    )
    log('普通角色越权读取、预览、生成、运行统计和 layout 绑定均已拦截')
  } finally {
    const residue = cleanupFixture(suffix)
    const totalResidue = Object.values(residue).reduce((sum, value) => sum + Number(value), 0)
    assert(totalResidue === 0, `临时数据清理后仍有残留：${JSON.stringify(residue)}`)
    log('临时普通用户、角色、权限和 SAI Board smoke 数据已物理清理')
  }
}

main().catch((error) => {
  console.error('[saiboard-scope] 验收失败')
  console.error(error)
  process.exit(1)
})
