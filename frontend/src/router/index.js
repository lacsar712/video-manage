import { createRouter, createWebHistory } from 'vue-router'
import { ElMessage } from 'element-plus'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/Login.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    component: () => import('../views/Layout.vue'),
    meta: { requiresAuth: true },
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('../views/Dashboard.vue')
      },
      {
        path: 'categories',
        name: 'CategoryManagement',
        component: () => import('../views/CategoryManagement.vue')
      },
      {
        path: 'videos',
        name: 'VideoList',
        component: () => import('../views/VideoList.vue')
      },
      {
        path: 'videos/new',
        name: 'VideoNew',
        component: () => import('../views/VideoForm.vue')
      },
      {
        path: 'videos/:id/edit',
        name: 'VideoEdit',
        component: () => import('../views/VideoForm.vue')
      },
      {
        path: 'videos/:id/sources',
        name: 'VideoSources',
        component: () => import('../views/VideoSources.vue')
      },
      {
        path: 'videos/:id/episodes',
        name: 'VideoEpisodes',
        component: () => import('../views/VideoEpisodes.vue')
      },
      {
        path: 'admin-users',
        name: 'AdminUserManagement',
        component: () => import('../views/AdminUserManagement.vue'),
        meta: { requiresSuper: true }
      },
      {
        path: 'banners',
        name: 'BannerManagement',
        component: () => import('../views/BannerManagement.vue')
      },
      {
        path: 'audit-logs',
        name: 'AuditLog',
        component: () => import('../views/AuditLog.vue')
      },
      {
        path: 'feedback',
        name: 'UserFeedback',
        component: () => import('../views/UserFeedback.vue')
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory('/admin/'),
  routes
})

// 路由守卫
router.beforeEach((to, _from, next) => {
  const token = localStorage.getItem('token')
  const role = localStorage.getItem('role')

  if (to.meta.requiresAuth && !token) {
    ElMessage.warning('请先登录')
    next('/login')
  } else if (to.meta.requiresSuper && role !== 'super') {
    ElMessage.warning('无权访问，需要超级管理员权限')
    next('/')
  } else if (to.path === '/login' && token) {
    next('/')
  } else {
    next()
  }
})

export default router
