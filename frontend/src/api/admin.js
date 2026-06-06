import request from '../utils/request'

// 管理员登录
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

// 管理员退出
export function logout() {
  return request({
    url: '/admin/logout',
    method: 'post'
  })
}

// 获取当前管理员信息
export function getAdminInfo() {
  return request({
    url: '/admin/info',
    method: 'get'
  })
}

// 获取管理员用户列表
export function getAdminUserList() {
  return request({
    url: '/admin/users',
    method: 'get'
  })
}

// 创建管理员用户
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

// 更新管理员用户状态（启用/禁用）
export function updateAdminUserStatus(id, status) {
  const formData = new FormData()
  formData.append('status', status)

  return request({
    url: `/admin/users/${id}/status`,
    method: 'post',
    data: formData
  })
}

// 重置管理员用户密码
export function resetAdminUserPassword(id, password) {
  const formData = new FormData()
  formData.append('password', password)

  return request({
    url: `/admin/users/${id}/password`,
    method: 'post',
    data: formData
  })
}

// 删除管理员用户
export function deleteAdminUser(id) {
  return request({
    url: `/admin/users/${id}`,
    method: 'delete'
  })
}
