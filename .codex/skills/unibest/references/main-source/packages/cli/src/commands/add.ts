import type minimist from 'minimist'
import { spawn } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'
import { cancel, intro, isCancel, log, multiselect } from '@clack/prompts'
import { bold, green } from 'kolorist'
import { version } from '../../package.json'
import { getFeatureByName } from '../features/interface'
import { getAvailableFeatureNames, loadFeatureHooks } from '../features/loader'
import { injectI18n, injectLimeEchart, injectLogin, injectUcharts } from '../utils/injector'
import { logger } from '../utils/logger'
import { readPackageJson, writePackageJson } from '../utils/readPackageJson'

interface AddOptions {
  path?: string
  feature?: string[]
  force?: boolean
  install?: boolean
}

interface AddFeatureResult {
  success: boolean
  addedDependencies: string[]
}

interface FeatureStatus {
  name: string
  enabled: boolean
  addedAt?: string
}

function getFeatureStatusFromPackageJson(pkgPath: string): Record<string, boolean> {
  try {
    const pkg = readPackageJson(pkgPath)
    return {
      'i18n': pkg.unibest?.i18n === true,
      'login': pkg.unibest?.loginStrategy === true,
      'lime-echart': pkg.unibest?.charts?.limeEchart === true,
      'ucharts': pkg.unibest?.charts?.ucharts === true,
    }
  }
  catch {
    return { 'i18n': false, 'login': false, 'lime-echart': false, 'ucharts': false }
  }
}

function updatePackageJsonForFeature(pkgPath: string, featureName: string): void {
  const pkg = readPackageJson(pkgPath)

  if (!pkg.unibest) {
    pkg.unibest = {}
  }

  switch (featureName) {
    case 'i18n':
      pkg.unibest.i18n = true
      break
    case 'login':
      pkg.unibest.loginStrategy = true
      break
    case 'lime-echart':
      pkg.unibest.charts = {
        ...pkg.unibest.charts,
        limeEchart: true,
      }
      break
    case 'ucharts':
      pkg.unibest.charts = {
        ...pkg.unibest.charts,
        ucharts: true,
      }
      break
  }

  writePackageJson(pkgPath, pkg)
}

function mergeFeatureDependencies(
  pkgPath: string,
  dependencies: Record<string, string> = {},
): string[] {
  const entries = Object.entries(dependencies)
  if (entries.length === 0) {
    return []
  }

  const pkg = readPackageJson(pkgPath)
  if (!pkg.dependencies) {
    pkg.dependencies = {}
  }

  const addedDependencies: string[] = []
  for (const [name, version] of entries) {
    if (pkg.dependencies?.[name] || pkg.devDependencies?.[name]) {
      continue
    }

    pkg.dependencies[name] = version
    addedDependencies.push(name)
  }

  if (addedDependencies.length > 0) {
    writePackageJson(pkgPath, pkg)
  }

  return addedDependencies
}

async function installDependencies(projectPath: string): Promise<boolean> {
  logger.info('正在执行 pnpm install 安装新增依赖...')

  return new Promise((resolve) => {
    const child = spawn('pnpm', ['install'], {
      cwd: projectPath,
      stdio: 'inherit',
      shell: false,
    })

    child.on('error', (error) => {
      logger.error(`执行 pnpm install 失败: ${error.message}`)
      resolve(false)
    })

    child.on('close', (code) => {
      if (code === 0) {
        logger.success('依赖安装完成')
        resolve(true)
      }
      else {
        logger.error(`pnpm install 退出码: ${code}`)
        resolve(false)
      }
    })
  })
}

function normalizeFeatureOption(value: unknown): string[] {
  if (!value) {
    return []
  }

  const values = Array.isArray(value) ? value : [value]
  return values
    .flatMap(item => String(item).split(','))
    .map(item => item.trim())
    .filter(Boolean)
}

async function checkFeatureStatus(projectPath: string): Promise<FeatureStatus[]> {
  const pkgPath = path.join(projectPath, 'package.json')
  const statusFromPkg = getFeatureStatusFromPackageJson(pkgPath)

  return [
    { name: 'i18n', enabled: statusFromPkg.i18n },
    { name: 'login', enabled: statusFromPkg.login },
    { name: 'lime-echart', enabled: statusFromPkg['lime-echart'] },
    { name: 'ucharts', enabled: statusFromPkg.ucharts },
  ]
}

async function addFeature(
  featureName: string,
  projectPath: string,
  options: AddOptions = {},
): Promise<AddFeatureResult> {
  const feature = getFeatureByName(featureName)
  if (!feature) {
    logger.error(`未知的 Feature: ${featureName}`)
    return { success: false, addedDependencies: [] }
  }

  const pkgPath = path.join(projectPath, 'package.json')
  const pkg = readPackageJson(pkgPath)

  // 检查是否已添加过
  let alreadyAdded = false
  if (featureName === 'i18n' && pkg.unibest?.i18n === true) {
    alreadyAdded = true
  }
  else if (featureName === 'login' && pkg.unibest?.loginStrategy === true) {
    alreadyAdded = true
  }
  else if (featureName === 'lime-echart' && pkg.unibest?.charts?.limeEchart === true) {
    alreadyAdded = true
  }
  else if (featureName === 'ucharts' && pkg.unibest?.charts?.ucharts === true) {
    alreadyAdded = true
  }

  if (alreadyAdded && !options.force) {
    logger.warn(`Feature ${featureName} 已添加过，如需重新注入请使用 --force 参数`)
    const addedDependencies = mergeFeatureDependencies(pkgPath, feature.dependencies)
    if (addedDependencies.length > 0) {
      logger.success(`已更新 package.json 依赖: ${addedDependencies.join(', ')}`)
    }
    return { success: true, addedDependencies }
  }

  log.info(`正在添加 Feature: ${green(featureName)} - ${feature.description}`)

  try {
    // 执行注入
    let results
    switch (featureName) {
      case 'i18n':
        results = await injectI18n(projectPath)
        break
      case 'login':
        results = await injectLogin(projectPath)
        break
      case 'lime-echart':
        results = await injectLimeEchart(projectPath)
        break
      case 'ucharts':
        results = await injectUcharts(projectPath)
        break
      default:
        logger.error(`不支持的 Feature: ${featureName}`)
        return { success: false, addedDependencies: [] }
    }

    // 打印注入结果
    const failedResults = []
    for (const result of results) {
      if (result.success) {
        logger.success(result.message)
      }
      else {
        failedResults.push(result)
        logger.warn(result.message)
      }
    }

    if (failedResults.length > 0) {
      logger.error(`Feature ${featureName} 注入未完全成功，未更新 package.json 的 unibest 配置`)
      return { success: false, addedDependencies: [] }
    }

    // 执行 hooks
    const hooks: any = await loadFeatureHooks(featureName)
    if (hooks?.postApply) {
      await hooks.postApply({
        options: { projectName: '', platforms: [], uiLibrary: 'none', i18n: true, loginStrategy: true },
        projectPath,
        featureName,
      })
    }

    // 更新 package.json 的 unibest 字段
    updatePackageJsonForFeature(pkgPath, featureName)
    logger.success(`已更新 package.json 的 unibest 配置`)

    const addedDependencies = mergeFeatureDependencies(pkgPath, feature.dependencies)
    if (addedDependencies.length > 0) {
      logger.success(`已更新 package.json 依赖: ${addedDependencies.join(', ')}`)
    }

    logger.success(`Feature ${featureName} 添加成功！`)
    return { success: true, addedDependencies }
  }
  catch (error) {
    logger.error(`添加 Feature 失败: ${(error as Error).message}`)
    return { success: false, addedDependencies: [] }
  }
}

export async function addCommand(args: minimist.ParsedArgs): Promise<void> {
  const positionalFeatures = normalizeFeatureOption(args._.slice(1))
  const optionFeatures = normalizeFeatureOption(args.feature)
  const options: AddOptions = {
    path: args.path || args.p || '.',
    feature: positionalFeatures.length > 0 ? positionalFeatures : optionFeatures,
    force: args.force === true || args.force === 'true',
    install: args.install === true || args.install === 'true',
  }

  intro(bold(green(`create-unibest@v${version} 添加 Feature`)))

  // 解析项目路径
  const projectPath = path.isAbsolute(options.path!)
    ? options.path!
    : path.join(process.cwd(), options.path!)

  // 验证项目
  const pkgPath = path.join(projectPath, 'package.json')
  if (!fs.existsSync(pkgPath)) {
    logger.error(`项目不存在: ${projectPath}`)
    process.exit(1)
  }

  const pkg = readPackageJson(pkgPath)
  if (pkg.name !== 'unibest') {
    logger.warn(`当前项目可能不是 unibest 项目: ${pkg.name}`)
  }

  // 获取可用的 Feature
  const availableFeatures = getAvailableFeatureNames()

  try {
    const addedDependencies = new Set<string>()

    // 如果没有指定 feature，则让用户选择
    if (!options.feature || options.feature.length === 0) {
      // 检测已启用的 Feature
      const detected = await checkFeatureStatus(projectPath)
      const available = availableFeatures.filter(f => !detected.find(d => d.name === f && d.enabled))

      if (available.length === 0) {
        logger.info('所有可用 Feature 已启用')
        return
      }

      const selectedFeatures = await multiselect({
        message: `请选择要添加的 Feature`,
        options: available.map((name) => {
          const feature = getFeatureByName(name)
          return {
            value: name,
            label: feature?.name || name,
            hint: feature?.description || '',
          }
        }),
        required: false,
      })

      if (isCancel(selectedFeatures)) {
        cancel('操作已取消')
        process.exit(0)
      }

      if (!Array.isArray(selectedFeatures) || selectedFeatures.length === 0) {
        logger.info('未选择任何 Feature')
        return
      }

      // 逐个添加
      for (const featureName of selectedFeatures) {
        const result = await addFeature(featureName as string, projectPath, options)
        if (!result.success) {
          continue
        }
        for (const dependency of result.addedDependencies) {
          addedDependencies.add(dependency)
        }
      }
    }
    else {
      // 添加单个或多个指定 feature
      for (const featureName of options.feature) {
        const result = await addFeature(featureName, projectPath, options)
        if (!result.success) {
          continue
        }
        for (const dependency of result.addedDependencies) {
          addedDependencies.add(dependency)
        }
      }
    }

    if (addedDependencies.size > 0) {
      if (options.install) {
        const installSucceeded = await installDependencies(projectPath)
        if (!installSucceeded) {
          logger.warn('Feature 已写入，依赖安装失败，请手动运行 pnpm install')
        }
      }
      else {
        logger.info('已更新 package.json，请运行 pnpm install 安装新增依赖')
      }
    }
  }
  catch (error) {
    logger.error(`添加 Feature 失败: ${(error as Error).message}`)
    process.exit(1)
  }
}
