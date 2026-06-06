import { createCrudApi } from './common'
import request from '@/utils/http'

const baseUrl = '/app/b8cms/admin/Content'

export default {
  ...createCrudApi(baseUrl),
  templateOptions(params: Record<string, any>) {
    return request.get<any>({ url: `${baseUrl}/templateOptions`, params })
  },
  batchSeoRobots(data: Record<string, any>) {
    return request.post<any>({ url: `${baseUrl}/batchSeoRobots`, data })
  }
}
