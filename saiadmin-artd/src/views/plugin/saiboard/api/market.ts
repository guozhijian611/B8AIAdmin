import request from '@/utils/http'

export interface MarketItem {
  id?: number
  type: 'screen' | 'component'
  name: string
  category?: string
  description?: string
  cover_image?: string
  content?: Record<string, any>
  component_count?: number
  is_public: 1 | 2
  status: 1 | 2
  created_by?: number
  create_time?: string
  update_time?: string
}

export default {
  list(params: Record<string, any>) {
    return request.get<any>({ url: '/app/saiboard/admin/MarketItem/index', params })
  },
  read(id: number | string) {
    return request.get<MarketItem>({ url: '/app/saiboard/admin/MarketItem/read', params: { id } })
  },
  save(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/MarketItem/save', data })
  },
  update(data: Record<string, any>) {
    return request.put<any>({ url: '/app/saiboard/admin/MarketItem/update', data })
  },
  delete(data: Record<string, any>) {
    return request.del<any>({ url: '/app/saiboard/admin/MarketItem/destroy', data })
  },
  changeStatus(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/MarketItem/changeStatus', data })
  },
  options(params: Record<string, any> = {}) {
    return request.get<MarketItem[]>({ url: '/app/saiboard/admin/MarketItem/options', params })
  }
}
