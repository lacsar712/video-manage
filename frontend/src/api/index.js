import request from '../utils/request'

export function login(data) {
  const formData = new FormData()
  formData.append('username', data.username)
  formData.append('password', data.password)

  return request({
    url: '/admin/login',
    method: 'post',
    data: formData
  })
}

export function logout() {
  return request({
    url: '/admin/logout',
    method: 'post'
  })
}

export function getAdminInfo() {
  return request({
    url: '/admin/info',
    method: 'get'
  })
}

export function getAdminUserList() {
  return request({
    url: '/admin/users',
    method: 'get'
  })
}

export function createAdminUser(data) {
  const formData = new FormData()
  formData.append('username', data.username)
  formData.append('password', data.password)
  formData.append('role', data.role || 'editor')
  formData.append('status', data.status ?? 1)

  return request({
    url: '/admin/users',
    method: 'post',
    data: formData
  })
}

export function updateAdminUserStatus(id, status) {
  const formData = new FormData()
  formData.append('status', status)

  return request({
    url: `/admin/users/${id}/status`,
    method: 'post',
    data: formData
  })
}

export function resetAdminUserPassword(id, password) {
  const formData = new FormData()
  formData.append('password', password)

  return request({
    url: `/admin/users/${id}/password`,
    method: 'post',
    data: formData
  })
}

export function deleteAdminUser(id) {
  return request({
    url: `/admin/users/${id}`,
    method: 'delete'
  })
}

export function getCategoryList(params) {
  return request({
    url: '/categories',
    method: 'get',
    params
  })
}

export function getCategoryDetail(id) {
  return request({
    url: `/categories/${id}`,
    method: 'get'
  })
}

export function createCategory(data) {
  const formData = new FormData()
  formData.append('name', data.name)
  formData.append('slug', data.slug)
  formData.append('sort_order', data.sort_order ?? 0)
  formData.append('status', data.status ?? 1)

  return request({
    url: '/categories',
    method: 'post',
    data: formData
  })
}

export function updateCategory(id, data) {
  const formData = new FormData()
  formData.append('name', data.name)
  formData.append('slug', data.slug)
  formData.append('sort_order', data.sort_order ?? 0)
  formData.append('status', data.status)

  return request({
    url: `/categories/${id}`,
    method: 'post',
    data: formData
  })
}

export function deleteCategory(id) {
  return request({
    url: `/categories/${id}`,
    method: 'delete'
  })
}

export function updateCategoryStatus(id, status) {
  const formData = new FormData()
  formData.append('status', status)

  return request({
    url: `/categories/${id}/status`,
    method: 'post',
    data: formData
  })
}

export function getVideoList(params) {
  return request({
    url: '/videos',
    method: 'get',
    params
  })
}

export function getVideoDetail(id) {
  return request({
    url: `/videos/${id}`,
    method: 'get'
  })
}

export function createVideo(data) {
  const formData = new FormData()
  formData.append('title', data.title)
  formData.append('cover_url', data.cover_url)
  formData.append('description', data.description || '')
  formData.append('type', data.type || 'movie')
  formData.append('status', data.status)
  if (data.category_id !== undefined && data.category_id !== null && data.category_id !== '') {
    formData.append('category_id', data.category_id)
  }
  if (data.actors !== undefined && data.actors !== null) {
    formData.append('actors', data.actors)
  }
  if (data.region_ids !== undefined && data.region_ids !== null) {
    formData.append('region_ids', data.region_ids)
  }
  if (data.language_ids !== undefined && data.language_ids !== null) {
    formData.append('language_ids', data.language_ids)
  }

  return request({
    url: '/videos',
    method: 'post',
    data: formData
  })
}

export function updateVideo(id, data) {
  const formData = new FormData()
  formData.append('title', data.title)
  formData.append('cover_url', data.cover_url)
  formData.append('description', data.description || '')
  formData.append('type', data.type || 'movie')
  formData.append('status', data.status)
  if (data.category_id !== undefined && data.category_id !== null && data.category_id !== '') {
    formData.append('category_id', data.category_id)
  }
  if (data.actors !== undefined && data.actors !== null) {
    formData.append('actors', data.actors)
  }
  if (data.region_ids !== undefined && data.region_ids !== null) {
    formData.append('region_ids', data.region_ids)
  }
  if (data.language_ids !== undefined && data.language_ids !== null) {
    formData.append('language_ids', data.language_ids)
  }

  return request({
    url: `/videos/${id}`,
    method: 'post',
    data: formData
  })
}

export function deleteVideo(id) {
  return request({
    url: `/videos/${id}`,
    method: 'delete'
  })
}

export function updateVideoStatus(id, status) {
  const formData = new FormData()
  formData.append('status', status)

  return request({
    url: `/videos/${id}/status`,
    method: 'post',
    data: formData
  })
}

export function getSourceList(videoId) {
  return request({
    url: '/sources',
    method: 'get',
    params: { video_id: videoId }
  })
}

export function createSource(data) {
  const formData = new FormData()
  formData.append('video_id', data.video_id)
  formData.append('source_name', data.source_name)
  formData.append('m3u8_url', data.m3u8_url)

  return request({
    url: '/sources',
    method: 'post',
    data: formData
  })
}

export function updateSource(id, data) {
  const formData = new FormData()
  formData.append('source_name', data.source_name)
  formData.append('m3u8_url', data.m3u8_url)

  return request({
    url: `/sources/${id}`,
    method: 'post',
    data: formData
  })
}

export function deleteSource(id) {
  return request({
    url: `/sources/${id}`,
    method: 'delete'
  })
}

export function getAuditLogList(params) {
  return request({
    url: '/audit-logs',
    method: 'get',
    params
  })
}

export function getAuditLogDetail(id) {
  return request({
    url: `/audit-logs/${id}`,
    method: 'get'
  })
}

export function getAuditActions() {
  return request({
    url: '/audit-logs/actions',
    method: 'get'
  })
}

export function getAuditResourceTypes() {
  return request({
    url: '/audit-logs/resource-types',
    method: 'get'
  })
}

export function getBannerList(params) {
  return request({
    url: '/banners',
    method: 'get',
    params
  })
}

export function getBannerDetail(id) {
  return request({
    url: `/banners/${id}`,
    method: 'get'
  })
}

export function getBannerVideoOptions() {
  return request({
    url: '/banners/video-options',
    method: 'get'
  })
}

export function createBanner(data) {
  const formData = new FormData()
  formData.append('title', data.title)
  formData.append('image_url', data.image_url)
  formData.append('jump_type', data.jump_type || 'url')
  formData.append('jump_target', data.jump_target || '')
  formData.append('sort_order', data.sort_order ?? 0)
  formData.append('status', data.status)
  if (data.start_time) formData.append('start_time', data.start_time)
  if (data.end_time) formData.append('end_time', data.end_time)

  return request({
    url: '/banners',
    method: 'post',
    data: formData
  })
}

export function updateBanner(id, data) {
  const formData = new FormData()
  formData.append('title', data.title)
  formData.append('image_url', data.image_url)
  formData.append('jump_type', data.jump_type)
  formData.append('jump_target', data.jump_target || '')
  formData.append('sort_order', data.sort_order ?? 0)
  formData.append('status', data.status)
  if (data.start_time) formData.append('start_time', data.start_time)
  if (data.end_time) formData.append('end_time', data.end_time)

  return request({
    url: `/banners/${id}`,
    method: 'post',
    data: formData
  })
}

export function deleteBanner(id) {
  return request({
    url: `/banners/${id}`,
    method: 'delete'
  })
}

export function updateBannerStatus(id, status) {
  const formData = new FormData()
  formData.append('status', status)

  return request({
    url: `/banners/${id}/status`,
    method: 'post',
    data: formData
  })
}

export function updateBannerSort(sortList) {
  return request({
    url: '/banners/sort',
    method: 'post',
    data: { sort_list: sortList },
    headers: { 'Content-Type': 'application/json' }
  })
}

export function getEpisodeList(videoId) {
  return request({
    url: '/episodes',
    method: 'get',
    params: { video_id: videoId }
  })
}

export function createEpisode(data) {
  const formData = new FormData()
  formData.append('video_id', data.video_id)
  formData.append('episode_no', data.episode_no)
  if (data.title !== undefined && data.title !== null && data.title !== '') {
    formData.append('title', data.title)
  }
  if (data.m3u8_url !== undefined && data.m3u8_url !== null && data.m3u8_url !== '') {
    formData.append('m3u8_url', data.m3u8_url)
  }
  if (data.duration_seconds !== undefined && data.duration_seconds !== null && data.duration_seconds !== '') {
    formData.append('duration_seconds', data.duration_seconds)
  }
  formData.append('status', data.status !== undefined ? data.status : 1)

  return request({
    url: '/episodes',
    method: 'post',
    data: formData
  })
}

export function updateEpisode(id, data) {
  const formData = new FormData()
  formData.append('episode_no', data.episode_no)
  if (data.title !== undefined && data.title !== null) {
    formData.append('title', data.title)
  }
  if (data.m3u8_url !== undefined && data.m3u8_url !== null) {
    formData.append('m3u8_url', data.m3u8_url)
  }
  if (data.duration_seconds !== undefined && data.duration_seconds !== null && data.duration_seconds !== '') {
    formData.append('duration_seconds', data.duration_seconds)
  }
  formData.append('status', data.status)

  return request({
    url: `/episodes/${id}`,
    method: 'post',
    data: formData
  })
}

export function deleteEpisode(id) {
  return request({
    url: `/episodes/${id}`,
    method: 'delete'
  })
}

export function batchImportEpisodes(videoId, episodes) {
  const formData = new FormData()
  formData.append('video_id', videoId)
  formData.append('episodes', JSON.stringify(episodes))

  return request({
    url: '/episodes/batch-import',
    method: 'post',
    data: formData
  })
}

export function getFeedbackList(params) {
  return request({
    url: '/feedback',
    method: 'get',
    params
  })
}

export function getFeedbackDetail(id) {
  return request({
    url: `/feedback/${id}`,
    method: 'get'
  })
}

export function getFeedbackStatusOptions() {
  return request({
    url: '/feedback/status-options',
    method: 'get'
  })
}

export function getFeedbackChannelOptions() {
  return request({
    url: '/feedback/channel-options',
    method: 'get'
  })
}

export function createFeedback(data) {
  const formData = new FormData()
  formData.append('content', data.content)
  if (data.contact_info) formData.append('contact_info', data.contact_info)
  formData.append('source_channel', data.source_channel || 'app')

  return request({
    url: '/feedback',
    method: 'post',
    data: formData
  })
}

export function updateFeedback(id, data) {
  const formData = new FormData()
  if (data.status) formData.append('status', data.status)
  if (data.handle_note) formData.append('handle_note', data.handle_note)

  return request({
    url: `/feedback/${id}`,
    method: 'post',
    data: formData
  })
}

export function getHotKeywordList(params) {
  return request({
    url: '/hot-keywords',
    method: 'get',
    params
  })
}

export function getEnabledHotKeywords() {
  return request({
    url: '/hot-keywords/enabled',
    method: 'get'
  })
}

export function getHotKeywordDetail(id) {
  return request({
    url: `/hot-keywords/${id}`,
    method: 'get'
  })
}

export function createHotKeyword(data) {
  const formData = new FormData()
  formData.append('keyword', data.keyword)
  formData.append('sort_order', data.sort_order ?? 0)
  formData.append('status', data.status ?? 1)
  if (data.click_count !== undefined && data.click_count !== null && data.click_count !== '') {
    formData.append('click_count', data.click_count)
  }

  return request({
    url: '/hot-keywords',
    method: 'post',
    data: formData
  })
}

export function updateHotKeyword(id, data) {
  const formData = new FormData()
  formData.append('keyword', data.keyword)
  formData.append('sort_order', data.sort_order ?? 0)
  formData.append('status', data.status)
  if (data.click_count !== undefined && data.click_count !== null && data.click_count !== '') {
    formData.append('click_count', data.click_count)
  }

  return request({
    url: `/hot-keywords/${id}`,
    method: 'post',
    data: formData
  })
}

export function deleteHotKeyword(id) {
  return request({
    url: `/hot-keywords/${id}`,
    method: 'delete'
  })
}

export function updateHotKeywordStatus(id, status) {
  const formData = new FormData()
  formData.append('status', status)

  return request({
    url: `/hot-keywords/${id}/status`,
    method: 'post',
    data: formData
  })
}

export function updateHotKeywordSort(sortList) {
  return request({
    url: '/hot-keywords/sort',
    method: 'post',
    data: { sort_list: sortList },
    headers: { 'Content-Type': 'application/json' }
  })
}

export function updateHotKeywordClickCount(id, clickCount) {
  const formData = new FormData()
  formData.append('click_count', clickCount)

  return request({
    url: `/hot-keywords/${id}/click-count`,
    method: 'post',
    data: formData
  })
}

export function syncHotKeywordStats() {
  return request({
    url: '/hot-keywords/sync-stats',
    method: 'post'
  })
}

export function getAppHotKeywords() {
  return request({
    url: '/app/hot-keywords',
    method: 'get'
  })
}

export function recordHotKeywordClick(id) {
  return request({
    url: `/app/hot-keywords/${id}/click`,
    method: 'post'
  })
}

export function getSystemConfigList() {
  return request({
    url: '/system-config',
    method: 'get'
  })
}

export function batchUpdateSystemConfig(items) {
  return request({
    url: '/system-config/batch-update',
    method: 'post',
    data: { items },
    headers: { 'Content-Type': 'application/json' }
  })
}

export function getPublicSystemConfig() {
  return request({
    url: '/system-config/public',
    method: 'get'
  })
}

export function getActorList(params) {
  return request({
    url: '/actors',
    method: 'get',
    params
  })
}

export function getActorDetail(id) {
  return request({
    url: `/actors/${id}`,
    method: 'get'
  })
}

export function getActorOptions() {
  return request({
    url: '/actors/options',
    method: 'get'
  })
}

export function createActor(data) {
  const formData = new FormData()
  formData.append('name', data.name)
  formData.append('avatar_url', data.avatar_url || '')
  formData.append('bio', data.bio || '')
  formData.append('status', data.status ?? 1)

  return request({
    url: '/actors',
    method: 'post',
    data: formData
  })
}

export function updateActor(id, data) {
  const formData = new FormData()
  formData.append('name', data.name)
  formData.append('avatar_url', data.avatar_url || '')
  formData.append('bio', data.bio || '')
  formData.append('status', data.status)

  return request({
    url: `/actors/${id}`,
    method: 'post',
    data: formData
  })
}

export function deleteActor(id) {
  return request({
    url: `/actors/${id}`,
    method: 'delete'
  })
}

export function updateActorStatus(id, status) {
  const formData = new FormData()
  formData.append('status', status)

  return request({
    url: `/actors/${id}/status`,
    method: 'post',
    data: formData
  })
}

export function getTagList(params) {
  return request({
    url: '/video-tags',
    method: 'get',
    params
  })
}

export function getTagDetail(id) {
  return request({
    url: `/video-tags/${id}`,
    method: 'get'
  })
}

export function getTagOptions() {
  return request({
    url: '/video-tags/options',
    method: 'get'
  })
}

export function createTag(data) {
  const formData = new FormData()
  formData.append('name', data.name)
  formData.append('type', data.type)
  formData.append('sort_order', data.sort_order ?? 0)
  formData.append('status', data.status ?? 1)

  return request({
    url: '/video-tags',
    method: 'post',
    data: formData
  })
}

export function updateTag(id, data) {
  const formData = new FormData()
  formData.append('name', data.name)
  formData.append('type', data.type)
  formData.append('sort_order', data.sort_order ?? 0)
  formData.append('status', data.status)

  return request({
    url: `/video-tags/${id}`,
    method: 'post',
    data: formData
  })
}

export function deleteTag(id) {
  return request({
    url: `/video-tags/${id}`,
    method: 'delete'
  })
}

export function updateTagStatus(id, status) {
  const formData = new FormData()
  formData.append('status', status)

  return request({
    url: `/video-tags/${id}/status`,
    method: 'post',
    data: formData
  })
}
