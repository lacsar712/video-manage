<template>
  <div class="actor-management">
    <el-card>
      <template #header>
        <div class="card-header">
          <h3>演员库管理</h3>
          <el-button type="primary" @click="handleAdd">
            <el-icon><Plus /></el-icon>
            新增演员
          </el-button>
        </div>
      </template>

      <div class="filter-bar">
        <el-form :inline="true" :model="queryForm">
          <el-form-item label="关键词">
            <el-input
              v-model="queryForm.keyword"
              placeholder="请输入演员姓名"
              clearable
              style="width: 200px"
              @clear="handleQuery"
              @keyup.enter="handleQuery"
            >
              <template #prefix>
                <el-icon><Search /></el-icon>
              </template>
            </el-input>
          </el-form-item>
          <el-form-item label="状态">
            <el-select
              v-model="queryForm.status"
              placeholder="请选择状态"
              clearable
              style="width: 200px"
              @clear="handleQuery"
              @change="handleQuery"
            >
              <el-option label="启用" value="1" />
              <el-option label="禁用" value="0" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleQuery">查询</el-button>
            <el-button @click="handleFilterReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <div class="content-wrapper">
        <div class="left-panel">
          <el-table
            :data="tableData"
            border
            stripe
            v-loading="loading"
            highlight-current-row
            @current-change="handleRowSelect"
            @row-click="handleRowClick"
          >
            <el-table-column label="头像" width="90">
              <template #default="{ row }">
                <div v-if="row.avatar_url" class="avatar-wrapper" @click.stop="handlePreview(getAvatarUrl(row.avatar_url))">
                  <img
                    :src="getAvatarUrl(row.avatar_url)"
                    :alt="row.name"
                    class="avatar-image"
                    @error="handleImageError"
                  />
                </div>
                <div v-else class="avatar-empty">
                  <el-icon :size="24"><UserFilled /></el-icon>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column prop="name" label="姓名" min-width="120" />
            <el-table-column prop="bio" label="简介" min-width="200" show-overflow-tooltip />
            <el-table-column label="关联影片" width="100">
              <template #default="{ row }">
                <el-link
                  v-if="row.video_count > 0"
                  type="primary"
                  :underline="false"
                  @click.stop="handleJumpToVideos(row)"
                >
                  {{ row.video_count }} 部
                </el-link>
                <span v-else class="text-muted">0 部</span>
              </template>
            </el-table-column>
            <el-table-column prop="status" label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status == 1 ? 'success' : 'info'" size="small">
                  {{ row.status == 1 ? '启用' : '禁用' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="创建时间" width="160" />
            <el-table-column label="操作" width="180" fixed="right">
              <template #default="{ row }">
                <el-button
                  size="small"
                  :type="row.status == 1 ? 'warning' : 'success'"
                  @click.stop="handleToggleStatus(row)"
                >
                  {{ row.status == 1 ? '禁用' : '启用' }}
                </el-button>
                <el-button size="small" type="danger" @click.stop="handleDelete(row)">
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>

          <div class="pagination">
            <el-pagination
              v-model:current-page="queryForm.page"
              v-model:page-size="queryForm.page_size"
              :page-sizes="[10, 20, 50, 100]"
              :total="total"
              layout="total, sizes, prev, pager, next, jumper"
              @size-change="handleSizeChange"
              @current-change="handlePageChange"
            />
          </div>
        </div>

        <div class="right-panel">
          <el-card shadow="never" class="form-card">
            <template #header>
              <div class="form-header">
                <h4>{{ isEdit ? '编辑演员' : (form.id ? '编辑演员' : '新增演员') }}</h4>
              </div>
            </template>

            <el-form
              ref="formRef"
              :model="form"
              :rules="rules"
              label-width="100px"
            >
              <el-form-item label="头像">
                <el-upload
                  class="avatar-uploader"
                  :action="uploadAction"
                  :headers="uploadHeaders"
                  :show-file-list="false"
                  :on-success="handleUploadSuccess"
                  :on-error="handleUploadError"
                  :before-upload="beforeUpload"
                  accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                >
                  <img v-if="form.avatar_url" :src="getAvatarUrl(form.avatar_url)" class="avatar-preview" />
                  <div v-else class="avatar-uploader-placeholder">
                    <el-icon :size="28"><Plus /></el-icon>
                    <div class="upload-text">上传头像</div>
                  </div>
                </el-upload>
                <div class="upload-tip">支持 JPG、PNG、GIF、WebP 格式，文件大小不超过 5MB</div>
              </el-form-item>

              <el-form-item label="姓名" prop="name">
                <el-input
                  v-model="form.name"
                  placeholder="请输入演员姓名（1-100个字符）"
                  maxlength="100"
                  show-word-limit
                  clearable
                />
              </el-form-item>

              <el-form-item label="简介" prop="bio">
                <el-input
                  v-model="form.bio"
                  type="textarea"
                  :rows="4"
                  placeholder="请输入演员简介（选填，最多1000个字符）"
                  maxlength="1000"
                  show-word-limit
                  clearable
                />
              </el-form-item>

              <el-form-item label="状态" prop="status">
                <el-radio-group v-model="form.status" size="large">
                  <el-radio :label="1" border>启用</el-radio>
                  <el-radio :label="0" border>禁用</el-radio>
                </el-radio-group>
              </el-form-item>

              <el-form-item>
                <el-button type="primary" :loading="submitting" @click="handleSubmit">
                  保存
                </el-button>
                <el-button @click="handleReset">重置</el-button>
              </el-form-item>
            </el-form>
          </el-card>
        </div>
      </div>
    </el-card>

    <el-dialog v-model="showViewer" width="600px" :show-close="true">
      <img :src="previewUrl" style="width: 100%; display: block; border-radius: 8px;" />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, UserFilled } from '@element-plus/icons-vue'
import {
  getActorList,
  createActor,
  updateActor,
  deleteActor,
  updateActorStatus
} from '../api'
import { loadSystemConfig, getDefaultPageSize } from '../utils/systemConfig'

const router = useRouter()
const loading = ref(false)
const submitting = ref(false)
const tableData = ref([])
const total = ref(0)
const isEdit = ref(false)
const formRef = ref(null)
const showViewer = ref(false)
const previewUrl = ref('')

const uploadAction = computed(() => {
  const baseURL = import.meta.env.VITE_API_BASE_URL || ''
  return baseURL ? `${baseURL}/api/upload/avatar` : '/api/upload/avatar'
})

const uploadHeaders = computed(() => {
  const token = localStorage.getItem('token')
  return token ? { Authorization: `Bearer ${token}` } : {}
})

const queryForm = reactive({
  page: 1,
  page_size: 10,
  keyword: '',
  status: ''
})

const defaultForm = {
  id: null,
  name: '',
  avatar_url: '',
  bio: '',
  status: 1
}

const form = reactive({ ...defaultForm })

const rules = {
  name: [
    { required: true, message: '请输入演员姓名', trigger: 'blur' },
    { min: 1, max: 100, message: '姓名长度必须在1-100个字符之间', trigger: 'blur' }
  ],
  bio: [
    { max: 1000, message: '简介最多1000个字符', trigger: 'blur' }
  ],
  status: [
    { required: true, message: '请选择状态', trigger: 'change' }
  ]
}

const getAvatarUrl = (url) => {
  if (!url) return ''
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url
  }
  const baseURL = import.meta.env.VITE_API_BASE_URL || ''
  return baseURL ? `${baseURL}${url}` : url
}

const beforeUpload = (file) => {
  const isImage = /^image\/(jpeg|jpg|png|gif|webp)$/.test(file.type)
  const isLt5M = file.size / 1024 / 1024 < 5

  if (!isImage) {
    ElMessage.error('只能上传 JPG、PNG、GIF、WebP 格式的图片')
    return false
  }
  if (!isLt5M) {
    ElMessage.error('图片大小不能超过 5MB')
    return false
  }
  return true
}

const handleUploadSuccess = (response) => {
  if (response.code === 0) {
    form.avatar_url = response.data.url
    ElMessage.success('上传成功')
  } else {
    ElMessage.error(response.message || '上传失败')
  }
}

const handleUploadError = (error) => {
  console.error('上传失败：', error)
  ElMessage.error('上传失败，请重试')
}

const handleImageError = (e) => {
  e.target.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="60" height="60"%3E%3Crect fill="%23f5f5f5" width="60" height="60"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-size="12"%3E加载失败%3C/text%3E%3C/svg%3E'
}

const handlePreview = (url) => {
  previewUrl.value = url
  showViewer.value = true
}

const fetchData = async () => {
  loading.value = true
  try {
    const res = await getActorList(queryForm)
    tableData.value = res.data.list
    total.value = res.data.total
  } catch (error) {
    console.error('获取演员列表失败：', error)
  } finally {
    loading.value = false
  }
}

const handleQuery = () => {
  queryForm.page = 1
  fetchData()
}

const handlePageChange = () => {
  fetchData()
}

const handleSizeChange = () => {
  queryForm.page = 1
  fetchData()
}

const handleFilterReset = () => {
  queryForm.keyword = ''
  queryForm.status = ''
  handleQuery()
}

const handleRowClick = (row) => {
  handleRowSelect(row)
}

const handleRowSelect = (row) => {
  if (!row) return
  isEdit.value = true
  Object.assign(form, {
    id: row.id,
    name: row.name,
    avatar_url: row.avatar_url || '',
    bio: row.bio || '',
    status: parseInt(row.status)
  })
}

const handleAdd = () => {
  isEdit.value = false
  Object.assign(form, defaultForm)
  formRef.value?.clearValidate()
}

const handleReset = () => {
  if (isEdit.value && form.id) {
    const row = tableData.value.find(item => item.id === form.id)
    if (row) {
      Object.assign(form, {
        id: row.id,
        name: row.name,
        avatar_url: row.avatar_url || '',
        bio: row.bio || '',
        status: parseInt(row.status)
      })
    }
  } else {
    Object.assign(form, defaultForm)
  }
  formRef.value?.clearValidate()
}

const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    submitting.value = true
    try {
      if (isEdit.value && form.id) {
        await updateActor(form.id, form)
        ElMessage.success('更新成功')
      } else {
        await createActor(form)
        ElMessage.success('添加成功')
      }
      await fetchData()
      if (!isEdit.value) {
        Object.assign(form, defaultForm)
        formRef.value?.clearValidate()
      }
    } catch (error) {
      console.error('提交失败：', error)
    } finally {
      submitting.value = false
    }
  })
}

const handleToggleStatus = async (row) => {
  const newStatus = row.status == 1 ? 0 : 1
  const action = newStatus == 1 ? '启用' : '禁用'

  try {
    await ElMessageBox.confirm(`确定要${action}该演员吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    await updateActorStatus(row.id, newStatus)
    ElMessage.success(`${action}成功`)
    await fetchData()
    if (form.id === row.id) {
      form.status = newStatus
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error(`${action}失败：`, error)
    }
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该演员吗？删除后将无法恢复！', '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'error'
    })

    await deleteActor(row.id)
    ElMessage.success('删除成功')
    if (form.id === row.id) {
      Object.assign(form, defaultForm)
      isEdit.value = false
    }
    await fetchData()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败：', error)
    }
  }
}

const handleJumpToVideos = (row) => {
  router.push({
    path: '/videos',
    query: { actor_id: row.id }
  })
}

onMounted(async () => {
  await loadSystemConfig()
  queryForm.page_size = getDefaultPageSize()
  fetchData()
})
</script>

<style scoped>
.actor-management :deep(.el-card) {
  border-radius: 12px;
  border: 1px solid #f0f0f0;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
}

.filter-bar {
  margin-bottom: 20px;
  padding: 16px 20px;
  background: #f8fafc;
  border-radius: 8px;
}

.filter-bar :deep(.el-form-item) {
  margin-bottom: 0;
}

.content-wrapper {
  display: flex;
  gap: 20px;
  min-height: 500px;
}

.left-panel {
  flex: 1.3;
  min-width: 0;
}

.right-panel {
  flex: 1;
  min-width: 0;
}

.form-card {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  height: 100%;
}

.form-header h4 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: #334155;
}

.left-panel :deep(.el-table) {
  border-radius: 8px;
}

.left-panel :deep(.el-table th.el-table__cell) {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  font-size: 13px;
}

.left-panel :deep(.el-table tr.current-row > td.el-table__cell) {
  background: #eef2ff;
}

.avatar-wrapper {
  cursor: pointer;
  transition: transform 0.2s;
}

.avatar-wrapper:hover {
  transform: scale(1.1);
}

.avatar-image {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  object-fit: cover;
  display: block;
  border: 2px solid #e2e8f0;
}

.avatar-empty {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: #f0f0ff;
  color: #94a3b8;
  display: flex;
  align-items: center;
  justify-content: center;
}

.avatar-uploader :deep(.el-upload) {
  border: 2px dashed #e2e8f0;
  border-radius: 50%;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: all 0.2s;
  width: 120px;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
}

.avatar-uploader :deep(.el-upload:hover) {
  border-color: #6366f1;
  background: #f0f0ff;
}

.avatar-preview {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  display: block;
  object-fit: cover;
}

.avatar-uploader-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  gap: 4px;
}

.upload-text {
  font-size: 12px;
}

.upload-tip {
  margin-top: 8px;
  font-size: 12px;
  color: #94a3b8;
  line-height: 1.5;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.text-muted {
  color: #94a3b8;
  font-size: 13px;
}

.actor-management :deep(.el-button--primary) {
  background: #6366f1;
  border-color: #6366f1;
}

.actor-management :deep(.el-button--primary:hover) {
  background: #4f46e5;
  border-color: #4f46e5;
}
</style>
