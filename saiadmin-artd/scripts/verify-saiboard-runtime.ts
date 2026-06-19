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
  expectCode?: number
}

const backendBaseUrl = process.env.SAIBOARD_VERIFY_BACKEND || 'http://127.0.0.1:8787'
const serverDir = new URL('../../server/', import.meta.url)

const log = (message: string) => {
  console.log(`[saiboard-runtime] ${message}`)
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

const generateAdminToken = () => {
  const code = [
    'require __DIR__ . "/vendor/autoload.php";',
    'require __DIR__ . "/support/bootstrap.php";',
    '$token=\\Tinywan\\Jwt\\JwtToken::generateToken([',
    '"access_exp"=>600,',
    '"id"=>1,',
    '"username"=>"admin",',
    '"type"=>"pc",',
    '"plat"=>"saiadmin"',
    ']);',
    'echo $token["access_token"];'
  ].join('')

  return execFileSync('php', ['-r', code], {
    cwd: serverDir,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe']
  }).trim()
}

async function api<T = any>(path: string, options: RequestOptions = {}): Promise<T> {
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
  const payload = (await response.json()) as ApiEnvelope<T>
  const expected = options.expectCode ?? 200
  if (payload.code !== expected) {
    throw new Error(
      `${method} ${path} expected code ${expected}, got ${payload.code}: ${payload.message || ''}`
    )
  }
  return payload.data as T
}

function pruneTemporaryRows(screenId?: number, templateIds: number[] = []) {
  if (!screenId && templateIds.length === 0) return

  const code = [
    'require __DIR__ . "/vendor/autoload.php";',
    'require __DIR__ . "/support/bootstrap.php";',
    '$payload=json_decode($argv[1] ?? "{}", true) ?: [];',
    '$screenId=(int)($payload["screenId"] ?? 0);',
    '$templateIds=array_values(array_filter(array_map("intval", $payload["templateIds"] ?? [])));',
    '$prefix="SAI Board Runtime Smoke ";',
    'if ($screenId > 0) {',
    '    $screen=\\support\\think\\Db::name("saiboard_screen")->where("id", $screenId)->find();',
    '    if ($screen && str_starts_with((string)($screen["name"] ?? ""), $prefix)) {',
    '        \\support\\think\\Db::name("saiboard_screen_token")->where("screen_id", $screenId)->delete();',
    '        \\support\\think\\Db::name("saiboard_screen_version")->where("screen_id", $screenId)->delete();',
    '        \\support\\think\\Db::name("saiboard_screen")->where("id", $screenId)->delete();',
    '    }',
    '}',
    'if ($templateIds) {',
    '    $safeIds=\\support\\think\\Db::name("saiboard_query_template")->whereIn("id", $templateIds)->whereLike("name", $prefix . "%")->column("id");',
    '    if ($safeIds) {',
    '        \\support\\think\\Db::name("saiboard_query_template")->whereIn("id", $safeIds)->delete();',
    '    }',
    '}'
  ].join('')

  execFileSync('php', ['-r', code, JSON.stringify({ screenId: screenId || 0, templateIds })], {
    cwd: serverDir,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe']
  })
  log('临时 smoke 数据已物理清理')
}

function setLegacyAccessToken(screenId: number, accessToken: string) {
  const code = [
    'require __DIR__ . "/vendor/autoload.php";',
    'require __DIR__ . "/support/bootstrap.php";',
    '$payload=json_decode($argv[1] ?? "{}", true) ?: [];',
    '$screenId=(int)($payload["screenId"] ?? 0);',
    '$accessToken=(string)($payload["accessToken"] ?? "");',
    '$prefix="SAI Board Runtime Smoke ";',
    '$screen=\\support\\think\\Db::name("saiboard_screen")->where("id", $screenId)->find();',
    'if (!$screen || !str_starts_with((string)($screen["name"] ?? ""), $prefix)) {',
    '    throw new RuntimeException("只能设置 smoke 大屏的旧访问令牌");',
    '}',
    '\\support\\think\\Db::name("saiboard_screen")->where("id", $screenId)->update(["access_token" => $accessToken]);'
  ].join('')

  execFileSync('php', ['-r', code, JSON.stringify({ screenId, accessToken })], {
    cwd: serverDir,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe']
  })
}

async function cleanup(
  token: string,
  screenId?: number,
  templateIds: number[] = [],
  tokenId?: number
) {
  if (screenId && tokenId) {
    try {
      await api('/app/saiboard/admin/Screen/deleteToken', {
        method: 'DELETE',
        token,
        body: { id: screenId, token_id: tokenId }
      })
      log(`已清理临时访问令牌 #${tokenId}`)
    } catch (error) {
      console.warn(`[saiboard-runtime] 清理临时访问令牌失败 #${tokenId}:`, error)
    }
  }

  if (screenId) {
    try {
      await api('/app/saiboard/admin/Screen/destroy', {
        method: 'DELETE',
        token,
        body: { ids: [screenId] }
      })
      log(`已清理临时大屏 #${screenId}`)
    } catch (error) {
      console.warn(`[saiboard-runtime] 清理临时大屏失败 #${screenId}:`, error)
    }
  }

  if (templateIds.length > 0) {
    try {
      await api('/app/saiboard/admin/QueryTemplate/destroy', {
        method: 'DELETE',
        token,
        body: { ids: templateIds }
      })
      log(`已清理临时查询模板 ${templateIds.join(',')}`)
    } catch (error) {
      console.warn(`[saiboard-runtime] 清理临时查询模板失败 ${templateIds.join(',')}:`, error)
    }
  }

  pruneTemporaryRows(screenId, templateIds)
}

async function main() {
  const token = generateAdminToken()
  assert(token.length > 20, '未能生成后台测试 token')
  log('后台测试 token 已生成')

  const datasourceOptions = await api<Array<{ id: number; name: string; type: string }>>(
    '/app/saiboard/admin/Datasource/options',
    { token }
  )
  const datasource =
    datasourceOptions.find((item) => item.type === 'mysql' && item.name.includes('本地')) ||
    datasourceOptions.find((item) => item.type === 'mysql')
  assert(datasource, '没有可用 MySQL 数据源')
  log(`使用 MySQL 数据源 #${datasource.id} ${datasource.name}`)

  const datasourceTest = await api<{ rows: any[]; diagnostics?: Record<string, any> }>(
    '/app/saiboard/admin/Datasource/test',
    {
      token,
      body: { id: datasource.id }
    }
  )
  assert(Array.isArray(datasourceTest.rows), '数据源测试未返回 rows')
  log('数据源测试通过')

  const schema = await api<{
    tables: Array<{ name: string }>
    columns: Array<{ name: string; kind: string }>
  }>('/app/saiboard/admin/Datasource/schema', {
    token,
    query: { id: datasource.id, table: 'saipay_order' }
  })
  assert(
    schema.tables.some((item) => item.name === 'saipay_order'),
    '数据源 schema 未包含 saipay_order'
  )
  assert(
    schema.columns.some((item) => item.name === 'order_price'),
    'saipay_order 缺少 order_price 字段'
  )
  log('表结构读取通过')

  const preview = await api<{ rows: any[]; total: number }>(
    '/app/saiboard/admin/QueryTemplate/preview',
    {
      token,
      body: {
        datasource_id: datasource.id,
        dataset_type: 'table_count',
        config: {
          table: 'saipay_order',
          conditions: []
        }
      }
    }
  )
  assert(Number(preview.total) >= 0, '查询模板预览未返回 total')
  log(`查询模板新增态预览通过，total=${preview.total}`)

  let screenId: number | undefined
  let templateIds: number[] = []
  let tokenId: number | undefined
  try {
    const suffix = Date.now().toString(36)
    const generated = await api<{
      id: number
      name: string
      template_ids: number[]
      component_count: number
    }>('/app/saiboard/admin/Screen/generateFromTable', {
      token,
      body: {
        datasource_id: datasource.id,
        table: 'saipay_order',
        name: `SAI Board Runtime Smoke ${suffix}`,
        width: 1920,
        height: 1080,
        chart_types: ['count', 'trend', 'rank', 'distribution', 'status', 'raw'],
        date_field: 'create_time',
        metric_field: 'order_price',
        label_field: 'order_name',
        category_field: 'pay_method',
        status_field: 'pay_status',
        order_field: 'create_time',
        raw_fields: [
          'order_no',
          'order_name',
          'order_price',
          'pay_method',
          'pay_status',
          'create_time'
        ]
      }
    })
    screenId = generated.id
    templateIds = generated.template_ids || []
    assert(screenId > 0, '从数据表生成大屏未返回 id')
    assert(templateIds.length > 0, '从数据表生成大屏未返回查询模板 id')
    assert(generated.component_count > 0, '从数据表生成大屏未生成组件')
    log(`临时大屏已生成 #${screenId}，组件数 ${generated.component_count}`)

    await api('/app/saiboard/admin/Screen/publish', {
      token,
      body: { id: screenId }
    })
    log('临时大屏发布通过')

    const createdToken = await api<{ token: string; row: { id: number } }>(
      '/app/saiboard/admin/Screen/createToken',
      {
        token,
        body: { id: screenId, name: `runtime-smoke-${suffix}` }
      }
    )
    assert(createdToken.token, '创建访问令牌未返回明文 token')
    assert(createdToken.row?.id > 0, '创建访问令牌未返回记录 id')
    tokenId = createdToken.row.id
    log('访问令牌创建通过')

    const screen = await api<any>('/app/saiboard/admin/Screen/read', {
      token,
      query: { id: screenId }
    })
    assert(screen?.code, '读取临时大屏未返回 code')

    await api(`/app/saiboard/api/screen/${screen.code}`, {
      expectCode: 401
    })
    log('私有大屏无 token 拒绝访问通过')

    await api(`/app/saiboard/api/screen/${screen.code}`, {
      query: { token: 'invalid-runtime-smoke-token' },
      expectCode: 401
    })
    log('私有大屏错误 token 拒绝访问通过')

    const legacyToken = `legacy-${suffix}`
    setLegacyAccessToken(screenId, legacyToken)
    log('临时旧单令牌已写入 smoke 大屏')

    const runtimeScreen = await api<{ screen: any }>(`/app/saiboard/api/screen/${screen.code}`, {
      query: { token: createdToken.token }
    })
    const components = runtimeScreen.screen?.layout?.components || []
    assert(Array.isArray(components) && components.length > 0, '运行时大屏未返回组件')
    const dataComponent = components.find((component: any) => component?.dataset?.queryTemplateId)
    assert(dataComponent?.id, '运行时大屏没有可取数组件')
    log(`运行时获取大屏配置通过，组件数 ${components.length}`)

    await api(`/app/saiboard/api/screen/${screen.code}`, {
      query: { token: legacyToken },
      expectCode: 401
    })
    log('存在启用子令牌时旧单令牌拒绝访问通过')

    const componentData = await api<{ rows: any[]; total?: number }>('/app/saiboard/api/data', {
      query: {
        code: screen.code,
        cid: dataComponent.id,
        token: createdToken.token
      }
    })
    assert(Array.isArray(componentData.rows), '运行时组件取数未返回 rows')
    log(`运行时组件取数通过，rows=${componentData.rows.length}`)

    await api('/app/saiboard/admin/Screen/changeTokenStatus', {
      token,
      body: { id: screenId, token_id: tokenId, status: 2 }
    })
    await api(`/app/saiboard/api/screen/${screen.code}`, {
      query: { token: createdToken.token },
      expectCode: 401
    })
    const legacyRuntimeScreen = await api<{ screen: any }>(
      `/app/saiboard/api/screen/${screen.code}`,
      {
        query: { token: legacyToken }
      }
    )
    assert(legacyRuntimeScreen.screen?.code === screen.code, '停用子令牌后旧单令牌未生效')
    log('停用子令牌后旧单令牌兜底访问通过')

    await api('/app/saiboard/admin/Screen/changeTokenStatus', {
      token,
      body: { id: screenId, token_id: tokenId, status: 1 }
    })
    const resetToken = await api<{ token: string; row: { id: number } }>(
      '/app/saiboard/admin/Screen/resetToken',
      {
        token,
        body: { id: screenId, token_id: tokenId }
      }
    )
    assert(
      resetToken.token && resetToken.token !== createdToken.token,
      '重置令牌未返回新的明文 token'
    )
    await api(`/app/saiboard/api/screen/${screen.code}`, {
      query: { token: createdToken.token },
      expectCode: 401
    })
    await api(`/app/saiboard/api/screen/${screen.code}`, {
      query: { token: resetToken.token }
    })
    await api(`/app/saiboard/api/screen/${screen.code}`, {
      query: { token: legacyToken },
      expectCode: 401
    })
    log('重置子令牌后新旧 token 切换与旧单令牌屏蔽通过')

    await api('/app/saiboard/admin/Screen/deleteToken', {
      method: 'DELETE',
      token,
      body: { id: screenId, token_id: tokenId }
    })
    tokenId = undefined
    await api(`/app/saiboard/api/screen/${screen.code}`, {
      query: { token: resetToken.token },
      expectCode: 401
    })
    await api(`/app/saiboard/api/screen/${screen.code}`, {
      query: { token: legacyToken }
    })
    log('删除子令牌后子 token 失效且旧单令牌兜底访问通过')

    const metrics = await api<any>('/app/saiboard/admin/Screen/runtimeMetrics', {
      token,
      query: { id: screenId }
    })
    assert(metrics?.screen || metrics?.totals, '运行统计未返回有效数据')
    log('运行统计读取通过')
  } finally {
    await cleanup(token, screenId, templateIds, tokenId)
  }
}

main().catch((error) => {
  console.error('[saiboard-runtime] 验收失败')
  console.error(error)
  process.exit(1)
})
