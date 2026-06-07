<template>
  <div class="banner-management">
    <el-card>
      <template #header>
        <div class="card-header">
          <h3>轮播图管理</h3>
          <div class="header-actions">
            <el-tag type="info" effect="plain" class="sort-tip">
              <el-icon><Rank /></el-icon>
              拖拽行可调整排序
            </el-tag>
            <el-button type="primary" @click="handleAdd">
              <el-icon><Plus /></el-icon>
              新增轮播图
            </el-button>
          </div>
        </div>
      </template>

      <div class="filter-bar">
        <el-form :inline="true" :model="queryForm">
          <el-form-item label="关键词">
            <el-input
              v-model="queryForm.keyword"
              placeholder="请输入轮播标题"
              clearable
              style="width: 200px"
              @clear="handleQuery"
            />
          </el-form-item>
          <el-form-item label="状态">
            <el-select
              v-model="queryForm.status"
              placeholder="请选择状态"
              clearable
              style="width: 200px"
              @clear="handleQuery"
            >
              <el-option label="启用" value="1" />
              <el-option label="禁用" value="0" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleQuery">查询</el-button>
            <el-button @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table
        ref="tableRef"
        :data="tableData"
        border
        stripe
        v-loading="loading"
        row-key="id"
        :row-class-name="getRowClassName"
        @row-drag-start="onDragStart"
        @row-drag-over="onDragOver"
        @row-drop="onDrop"
        @row-drag-end="onDragEnd"
      >
        <el-table-column type="index" label="序号" width="60" align="center">
          <template #default="{ $index }">
            <span class="drag-handle">
              <el-icon><Rank /></el-icon>
              {{ $index + 1 }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="缩略图" width="160">
          <template #default="{ row }">
            <div v-if="row.image_url" class="image-wrapper" @click="handlePreview(getImageUrl(row.image_url))">
              <img
                :src="getImageUrl(row.image_url)"
                :alt="row.title"
                class="image-thumb"
                loading="lazy"
                @error="handleImageError"
              />
            </div>
            <span v-else class="image-empty">暂无</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="轮播标题" min-width="180" show-overflow-tooltip />
        <el-table-column label="跳转类型" width="110">
          <template #default="{ row }">
            <el-tag :type="row.jump_type === 'video' ? 'primary' : 'success'" size="small">
              {{ row.jump_type === 'video' ? '影片详情' : '外链' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="跳转目标" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            <template v-if="row.jump_type === 'video'">
              <template v-if="row.video_info">
                <el-tag type="primary" effect="plain" size="small">
                  影片ID:{{ row.video_info.id }}
                </el-tag>
                <span class="video-title-text">{{ row.video_info.title }}</span>
                <el-tag v-if="row.video_info.status !== 1" type="danger" size="small" style="margin-left: 4px">
                  已下架
                </el-tag>
              </template>
              <el-tag v-else type="danger" size="small">影片不存在</el-tag>
            </template>
            <el-link
              v-else
              :href="row.jump_target"
              type="primary"
              target="_blank"
              :underline="false"
              class="url-link"
            >
              {{ row.jump_target }}
            </el-link>
          </template>
        </el-table-column>
        <el-table-column label="生效时间" width="320">
          <template #default="{ row }">
            <div v-if="row.start_time || row.end_time" class="time-range">
              <span>{{ row.start_time || '不限' }}</span>
              <span class="time-sep">至</span>
              <span>{{ row.end_time || '不限' }}</span>
            </div>
            <span v-else class="text-muted">永久有效</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'">
              {{ row.status === 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="240" fixed="right">
          <template #default="{ row }">
            <el-button
              size="small"
              :type="row.status === 1 ? 'warning' : 'success'"
              @click="handleToggleStatus(row)"
            >
              {{ row.status === 1 ? '下线' : '上线' }}
            </el-button>
            <el-button size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
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
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑轮播图' : '新增轮播图'"
      width="640px"
      :close-on-click-modal="false"
      destroy-on-close
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="120px"
      >
        <el-form-item label="轮播标题" prop="title">
          <el-input
            v-model="form.title"
            placeholder="请输入轮播标题（1-200个字符）"
            maxlength="200"
            show-word-limit
            clearable
          />
        </el-form-item>

        <el-form-item label="轮播图片" prop="image_url">
          <el-upload
            class="banner-uploader"
            :action="uploadAction"
            :headers="uploadHeaders"
            :show-file-list="false"
            :on-success="handleUploadSuccess"
            :on-error="handleUploadError"
            :before-upload="beforeUpload"
            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
          >
            <img v-if="form.image_url" :src="getImageUrl(form.image_url)" class="banner-image" />
            <div v-else class="banner-uploader-placeholder">
              <el-icon :size="36"><Plus /></el-icon>
              <span>点击上传图片</span>
              <span class="upload-size">建议尺寸 1200x400</span>
            </div>
          </el-upload>
          <div class="upload-tip">支持 JPG、PNG、GIF、WebP 格式，文件大小不超过 5MB</div>
        </el-form-item>

        <el-form-item label="跳转类型" prop="jump_type">
          <el-radio-group v-model="form.jump_type" size="large" @change="handleJumpTypeChange">
            <el-radio label="video" border>影片详情</el-radio>
            <el-radio label="url" border>外链</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item
          v-if="form.jump_type === 'video'"
          label="关联影片"
          prop="jump_target"
        >
          <el-select
            v-model="form.jump_target"
            placeholder="请选择关联影片（仅上架影片）"
            filterable
            style="width: 100%"
          >
            <el-option
              v-for="video in videoOptions"
              :key="video.id"
              :label="video.title"
              :value="String(video.id)"
            />
          </el-select>
        </el-form-item>

        <el-form-item
          v-if="form.jump_type === 'url'"
          label="外链URL"
          prop="jump_target"
        >
          <el-input
            v-model="form.jump_target"
            placeholder="请输入完整的URL地址，例如 https://example.com"
            clearable
          />
        </el-form-item>

        <el-form-item label="生效时间">
          <el-date-picker
            v-model="form.time_range"
            type="datetimerange"
            range-separator="至"
            start-placeholder="开始时间"
            end-placeholder="结束时间"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 100%"
          />
        </el-form-item>

        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="form.status" size="large">
            <el-radio :label="1" border>启用</el-radio>
            <el-radio :label="0" border>禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
          确定
        </el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showViewer" width="960px" :show-close="true">
      <img :src="previewUrl" style="width: 100%; display: block; border-radius: 8px;" />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Rank } from '@element-plus/icons-vue'
import { loadSystemConfig, getDefaultPageSize } from '../utils/systemConfig'
import {
  getBannerList,
  getBannerDetail,
  getBannerVideoOptions,
  createBanner,
  updateBanner,
  deleteBanner,
  updateBannerStatus,
  updateBannerSort
} from '../api'

const tableRef = ref(null)
const formRef = ref(null)
const loading = ref(false)
const tableData = ref([])
const total = ref(0)
const dialogVisible = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const submitLoading = ref(false)
const previewUrl = ref('')
const showViewer = ref(false)
const videoOptions = ref([])
const dragData = reactive({
  dragging: false,
  dragIndex: -1,
  dropIndex: -1
})

const queryForm = reactive({
  page: 1,
  page_size: 10,
  keyword: '',
  status: ''
})

const form = reactive({
  title: '',
  image_url: '',
  jump_type: 'url',
  jump_target: '',
  status: 1,
  sort_order: 0,
  time_range: []
})

const uploadAction = computed(() => {
  const baseURL = import.meta.env.VITE_API_BASE_URL || ''
  return baseURL ? `${baseURL}/api/upload/banner` : '/api/upload/banner'
})

const uploadHeaders = computed(() => {
  const token = localStorage.getItem('token')
  return token ? { Authorization: `Bearer ${token}` } : {}
})

const rules = computed(() => ({
  title: [
    { required: true, message: '请输入轮播标题', trigger: 'blur' },
    { min: 1, max: 200, message: '标题长度必须在1-200个字符之间', trigger: 'blur' }
  ],
  image_url: [{ required: true, message: '请上传轮播图片', trigger: 'change' }],
  jump_type: [{ required: true, message: '请选择跳转类型', trigger: 'change' }],
  status: [{ required: true, message: '请选择状态', trigger: 'change' }],
  jump_target: [
    {
      validator: (_rule, value, callback) => {
        if (!value) {
          if (form.jump_type === 'video') {
            callback(new Error('请选择关联影片'))
          } else {
            callback(new Error('请输入外链URL'))
          }
          return
        }
        if (form.jump_type === 'url') {
          try {
            const url = new URL(value)
            if (url.protocol !== 'http:' && url.protocol !== 'https:') {
              callback(new Error('仅支持 http:// 或 https:// 协议'))
              return
            }
          } catch (e) {
            callback(new Error('请输入合法的URL地址，需以 http:// 或 https:// 开头'))
            return
          }
        }
        if (form.jump_type === 'video') {
          const video = videoOptions.value.find(v => String(v.id) === String(value))
          if (!video) {
            callback(new Error('所选影片不存在'))
            return
          }
          if (video.status !== 1) {
            callback(new Error('所选影片必须是上架状态'))
            return
          }
        }
        callback()
      },
      trigger: 'blur'
    }
  ]
}))

const getImageUrl = (url) => {
  if (!url) return ''
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url
  }
  const baseURL = import.meta.env.VITE_API_BASE_URL || ''
  return baseURL ? `${baseURL}${url}` : url
}

const fetchVideoOptions = async () => {
  try {
    const res = await getBannerVideoOptions()
    videoOptions.value = res.data.list
  } catch (error) {
    console.error('获取影片列表失败：', error)
  }
}

const fetchData = async () => {
  loading.value = true
  try {
    const res = await getBannerList(queryForm)
    tableData.value = res.data.list
    total.value = res.data.total
  } catch (error) {
    console.error('获取列表失败：', error)
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

const handleReset = () => {
  queryForm.keyword = ''
  queryForm.status = ''
  handleQuery()
}

const resetForm = () => {
  form.title = ''
  form.image_url = ''
  form.jump_type = 'url'
  form.jump_target = ''
  form.status = 1
  form.sort_order = 0
  form.time_range = []
  editingId.value = null
}

const handleAdd = async () => {
  isEdit.value = false
  resetForm()
  await fetchVideoOptions()
  dialogVisible.value = true
  await nextTick()
  formRef.value?.clearValidate()
}

const handleEdit = async (row) => {
  isEdit.value = true
  resetForm()
  editingId.value = row.id
  await fetchVideoOptions()

  try {
    loading.value = true
    const res = await getBannerDetail(row.id)
    const data = res.data
    form.title = data.title
    form.image_url = data.image_url
    form.jump_type = data.jump_type
    form.jump_target = data.jump_target || ''
    form.status = parseInt(data.status)
    form.sort_order = parseInt(data.sort_order)
    form.time_range = []
    if (data.start_time || data.end_time) {
      form.time_range = [data.start_time || '', data.end_time || '']
    }
    dialogVisible.value = true
    await nextTick()
    formRef.value?.clearValidate()
  } catch (error) {
    console.error('获取详情失败：', error)
  } finally {
    loading.value = false
  }
}

const handleJumpTypeChange = () => {
  form.jump_target = ''
  nextTick(() => {
    formRef.value?.clearValidate('jump_target')
  })
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
    form.image_url = response.data.url
    ElMessage.success('上传成功')
    nextTick(() => {
      formRef.value?.validateField('image_url')
    })
  } else {
    ElMessage.error(response.message || '上传失败')
  }
}

const handleUploadError = (error) => {
  console.error('上传失败：', error)
  ElMessage.error('上传失败，请重试')
}

const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    submitLoading.value = true
    try {
      const submitData = {
        title: form.title,
        image_url: form.image_url,
        jump_type: form.jump_type,
        jump_target: form.jump_target,
        status: form.status,
        sort_order: form.sort_order
      }
      if (form.time_range && form.time_range.length === 2) {
        submitData.start_time = form.time_range[0]
        submitData.end_time = form.time_range[1]
      }

      if (isEdit.value && editingId.value) {
        await updateBanner(editingId.value, submitData)
        ElMessage.success('更新成功')
      } else {
        await createBanner(submitData)
        ElMessage.success('添加成功')
      }

      dialogVisible.value = false
      fetchData()
    } catch (error) {
      console.error('提交失败：', error)
    } finally {
      submitLoading.value = false
    }
  })
}

const handleToggleStatus = async (row) => {
  const newStatus = row.status === 1 ? 0 : 1
  const action = newStatus === 1 ? '上线' : '下线'

  try {
    await ElMessageBox.confirm(`确定要${action}该轮播图吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    await updateBannerStatus(row.id, newStatus)
    ElMessage.success(`${action}成功`)
    fetchData()
  } catch (error) {
    if (error !== 'cancel') {
      console.error(`${action}失败：`, error)
    }
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该轮播图吗？删除后将无法恢复！', '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'error'
    })

    await deleteBanner(row.id)
    ElMessage.success('删除成功')
    fetchData()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败：', error)
    }
  }
}

const handlePreview = (url) => {
  previewUrl.value = url
  showViewer.value = true
}

const handleImageError = (e) => {
  e.target.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="160" height="90"%3E%3Crect fill="%23f5f5f5" width="160" height="90"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999"%3E加载失败%3C/text%3E%3C/svg%3E'
}

const getRowClassName = ({ row }) => {
  if (dragData.dragging) {
    const dropIndex = dragData.dropIndex
    const rowIndex = tableData.value.findIndex(item => item.id === row.id)
    if (rowIndex === dropIndex && dropIndex !== -1 && dropIndex !== dragData.dragIndex) {
      return 'drop-target-row'
    }
  }
  return ''
}

const onDragStart = (row) => {
  dragData.dragging = true
  dragData.dragIndex = tableData.value.findIndex(item => item.id === row.id)
  dragData.dropIndex = -1
}

const onDragOver = (row) => {
  if (!dragData.dragging) return
  dragData.dropIndex = tableData.value.findIndex(item => item.id === row.id)
}

const onDrop = async (row) => {
  if (!dragData.dragging) return
  const dropIndex = tableData.value.findIndex(item => item.id === row.id)
  const dragIndex = dragData.dragIndex

  if (dragIndex === dropIndex || dragIndex < 0 || dropIndex < 0) {
    dragData.dragging = false
    dragData.dragIndex = -1
    dragData.dropIndex = -1
    return
  }

  const newList = [...tableData.value]
  const [draggedItem] = newList.splice(dragIndex, 1)
  newList.splice(dropIndex, 0, draggedItem)

  const sortList = newList.map((item, idx) => ({
    id: item.id,
    sort_order: idx + 1
  }))

  try {
    await updateBannerSort(sortList)
    ElMessage.success('排序已更新')
    fetchData()
  } catch (error) {
    console.error('排序更新失败：', error)
  } finally {
    dragData.dragging = false
    dragData.dragIndex = -1
    dragData.dropIndex = -1
  }
}

const onDragEnd = () => {
  dragData.dragging = false
  dragData.dragIndex = -1
  dragData.dropIndex = -1
}

onMounted(async () => {
  await loadSystemConfig()
  queryForm.page_size = getDefaultPageSize()
  fetchData()
})
</script>

<style scoped>
.banner-management :deep(.el-card) {
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

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.sort-tip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
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

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.drag-handle {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  cursor: move;
  color: #64748b;
  user-select: none;
}

.drag-handle .el-icon {
  font-size: 14px;
}

.banner-management :deep(.el-table .el-table__row) {
  cursor: move;
}

.image-wrapper {
  cursor: pointer;
  transition: transform 0.2s;
  display: inline-block;
}

.image-wrapper:hover {
  transform: scale(1.05);
}

.image-thumb {
  width: 140px;
  height: 52px;
  border-radius: 6px;
  object-fit: cover;
  display: block;
  border: 1px solid #e2e8f0;
}

.image-empty {
  display: inline-flex;
  width: 140px;
  height: 52px;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  background: #f0f0ff;
  color: #94a3b8;
  font-size: 12px;
}

.video-title-text {
  margin-left: 8px;
  color: #334155;
  font-size: 13px;
}

.url-link {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: inline-block;
  vertical-align: middle;
}

.time-range {
  display: flex;
  flex-direction: column;
  font-size: 13px;
  color: #334155;
  line-height: 1.6;
}

.time-sep {
  display: none;
}

.text-muted {
  color: #94a3b8;
  font-size: 13px;
}

.banner-management :deep(.el-table th.el-table__cell) {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  font-size: 13px;
}

.banner-management :deep(.el-button--primary) {
  background: #6366f1;
  border-color: #6366f1;
}

.banner-management :deep(.el-button--primary:hover) {
  background: #4f46e5;
  border-color: #4f46e5;
}

.banner-uploader :deep(.el-upload) {
  border: 2px dashed #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: all 0.2s;
  width: 400px;
  height: 150px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
}

.banner-uploader :deep(.el-upload:hover) {
  border-color: #6366f1;
  background: #f0f0ff;
}

.banner-uploader-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #94a3b8;
}

.banner-uploader-placeholder .el-icon {
  color: #cbd5e1;
}

.banner-uploader-placeholder .upload-size {
  font-size: 12px;
  color: #cbd5e1;
}

.banner-image {
  width: 400px;
  height: 150px;
  display: block;
  object-fit: cover;
}

.upload-tip {
  margin-top: 8px;
  font-size: 12px;
  color: #94a3b8;
  line-height: 1.5;
}

.banner-management :deep(.el-table .drop-target-row td.el-table__cell) {
  background-color: #eef2ff !important;
  border-left: 3px solid #6366f1;
}
</style>
