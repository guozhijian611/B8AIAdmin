import request from '@/utils/http'
import { createCrudApi } from './common'

const api = createCrudApi('/app/b8cms/admin/Template')

export default {
  ...api,
  activate(data: Record<string, any>) {
    return request.post<any>({ url: '/app/b8cms/admin/Template/activate', data })
  }
}
