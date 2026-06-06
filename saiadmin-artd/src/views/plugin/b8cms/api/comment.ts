import request from '@/utils/http'
import { createCrudApi } from './common'

const api = createCrudApi('/app/b8cms/admin/Comment')

export default {
  ...api,
  handle(data: Record<string, any>) {
    return request.post<any>({ url: '/app/b8cms/admin/Comment/handle', data })
  }
}
