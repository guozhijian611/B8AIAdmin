import request from '@/utils/http'
import { createCrudApi } from './common'

const api = createCrudApi('/app/b8cms/admin/Language')

export default {
  ...api,
  setDefault(data: Record<string, any>) {
    return request.post<any>({ url: '/app/b8cms/admin/Language/setDefault', data })
  }
}
