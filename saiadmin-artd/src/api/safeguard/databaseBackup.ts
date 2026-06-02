import request from '@/utils/http'

/**
 * 数据库导入导出API
 */
export default {
  /**
   * 数据库概览
   */
  overview(params: Record<string, any> = {}) {
    return request.get<any>({
      url: '/core/database-backup/index',
      params
    })
  },

  /**
   * 导出 SQL
   */
  exportSql(data: Record<string, any>) {
    return request.request<any>({
      url: '/core/database-backup/export',
      method: 'post',
      data,
      timeout: 0,
      responseType: 'blob'
    })
  },

  /**
   * 导入 SQL
   */
  importSql(data: FormData) {
    return request.post<any>({
      url: '/core/database-backup/import',
      data,
      timeout: 0
    })
  },

  /**
   * 下载本地备份
   */
  downloadBackup(data: Record<string, any>) {
    return request.request<any>({
      url: '/core/database-backup/download',
      method: 'post',
      data,
      timeout: 0,
      responseType: 'blob'
    })
  },

  /**
   * 删除本地备份
   */
  deleteBackup(data: Record<string, any>) {
    return request.del<any>({
      url: '/core/database-backup/delete',
      data
    })
  }
}
