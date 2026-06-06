import { reactive } from 'vue'
import { getPublicSystemConfig } from '../api'

const defaults = {
  site_name: '影视管理平台',
  support_email: 'support@example.com',
  default_page_size: 20,
  enable_recommend_sort: 1
}

const systemConfigState = reactive({
  loaded: false,
  loading: false,
  ...defaults
})

let fetchPromise = null

async function loadSystemConfig(force = false) {
  if (!force && systemConfigState.loaded) {
    return systemConfigState
  }
  if (!force && fetchPromise) {
    return fetchPromise
  }
  systemConfigState.loading = true
  fetchPromise = (async () => {
    try {
      const res = await getPublicSystemConfig()
      Object.assign(systemConfigState, res.data)
      systemConfigState.loaded = true
      systemConfigState.loading = false
      fetchPromise = null
    } catch (error) {
      console.error('加载系统配置失败：', error)
      systemConfigState.loading = false
      fetchPromise = null
    }
    return systemConfigState
  })()
  return fetchPromise
}

function getDefaultPageSize() {
  const val = systemConfigState.default_page_size
  if (!val || val <= 0) return 20
  return val
}

function isRecommendSortEnabled() {
  return systemConfigState.enable_recommend_sort === 1 || systemConfigState.enable_recommend_sort === true
}

function refreshSystemConfig() {
  return loadSystemConfig(true)
}

export {
  systemConfigState,
  loadSystemConfig,
  getDefaultPageSize,
  isRecommendSortEnabled,
  refreshSystemConfig
}
