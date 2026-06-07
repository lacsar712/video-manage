<template>
  <div class="user-feedback">
    <el-card>
      <template #header>
        <div class="card-header">
          <h3>用户反馈</h3>
          <div class="header-actions">
            <el-button type="primary" @click="openCreateDialog">
              <el-icon><Plus /></el-icon>
              手动录入
            </el-button>
          </div>
        </div>
      </template>

      <div class="filter-bar">
        <el-form :inline="true" :model="queryForm">
          <el-form-item label="处理状态">
            <el-select
              v-model="queryForm.status"
              placeholder="请选择状态"
              clearable
              style="width: 150px"
              @clear="handleQuery"
              @change="handleQuery"
            >
              <el-option
                v-for="item in statusOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="来源渠道">
            <el-select
              v-model="queryForm.source_channel"
              placeholder="请选择渠道"
              clearable
              style="width: 150px"
              @clear="handleQuery"
              @change="handleQuery"
            >
              <el-option
                v-for="item in channelOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="关键词">
            <el-input
              v-model="queryForm.keyword"
              placeholder="搜索内容/联系人"
              clearable
              style="width: 200px"
              @clear="handleQuery"
              @keyup.enter="handleQuery"
            />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleQuery">查询</el-button>
            <el-button @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-alert
        title="默认展示待处理反馈，点击记录查看详情并更新处理状态"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />

      <el-table :data="tableData" border stripe v-loading="loading" @row-click="handleRowClick">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="处理状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusTagType(row.status)">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="来源渠道" width="100">
          <template #default="{ row }">
            <el-tag type="info" size="small">
              {{ getChannelLabel(row.source_channel) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="contact_info" label="联系人" width="160">
          <template #default="{ row }">
            <span v-if="row.contact_info">{{ row.contact_info }}</span>
            <span v-else class="text-muted">匿名</span>
          </template>
        </el-table-column>
        <el-table-column prop="content" label="反馈内容" min-width="280" show-overflow-tooltip />
        <el-table-column label="处理人" width="110">
          <template #default="{ row }">
            <span v-if="row.handled_by_username">{{ row.handled_by_username }}</span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="提交时间" width="170" />
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click.stop="handleViewDetail(row)">
              详情
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
    </el-card>

    <el-drawer
      v-model="detailVisible"
      title="反馈详情"
      size="560px"
      :destroy-on-close="true"
    >
      <div v-if="currentDetail" class="detail-content">
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="ID">{{ currentDetail.id }}</el-descriptions-item>
          <el-descriptions-item label="处理状态">
            <el-tag :type="getStatusTagType(currentDetail.status)">
              {{ getStatusLabel(currentDetail.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="来源渠道">
            {{ getChannelLabel(currentDetail.source_channel) }}
          </el-descriptions-item>
          <el-descriptions-item label="联系人">
            {{ currentDetail.contact_info || '匿名' }}
          </el-descriptions-item>
          <el-descriptions-item label="提交时间">
            {{ currentDetail.created_at }}
          </el-descriptions-item>
          <el-descriptions-item label="处理人">
            {{ currentDetail.handled_by_username || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="处理时间">
            {{ currentDetail.handled_at || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="反馈内容">
            <div class="feedback-content">{{ currentDetail.content }}</div>
          </el-descriptions-item>
          <el-descriptions-item v-if="currentDetail.handle_note" label="当前备注">
            <div class="feedback-note">{{ currentDetail.handle_note }}</div>
          </el-descriptions-item>
        </el-descriptions>

        <div class="update-section">
          <h4>更新处理</h4>
          <el-form :model="updateForm" label-width="80px">
            <el-form-item label="处理状态">
              <el-select v-model="updateForm.status" placeholder="选择状态" style="width: 100%">
                <el-option
                  v-for="item in statusOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="处理备注">
              <el-input
                v-model="updateForm.handle_note"
                type="textarea"
                :rows="3"
                placeholder="请输入处理备注..."
              />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="updating" @click="handleUpdate">
                提交更新
              </el-button>
            </el-form-item>
          </el-form>
        </div>

        <div class="timeline-section">
          <h4>处理时间线</h4>
          <el-timeline>
            <el-timeline-item
              v-for="(item, index) in displayTimeline"
              :key="index"
              :timestamp="item.created_at"
              :type="item.timelineType"
              :icon="item.icon"
              size="large"
            >
              <div class="timeline-item">
                <div class="timeline-title">{{ item.title }}</div>
                <div class="timeline-operator" v-if="item.admin_username">
                  操作人：{{ item.admin_username }}
                </div>
                <div class="timeline-note" v-if="item.note">{{ item.note }}</div>
              </div>
            </el-timeline-item>
          </el-timeline>
        </div>
      </div>
    </el-drawer>

    <el-dialog
      v-model="createVisible"
      title="手动录入反馈"
      width="500px"
      :destroy-on-close="true"
    >
      <el-form :model="createForm" :rules="createRules" ref="createFormRef" label-width="90px">
        <el-form-item label="来源渠道" prop="source_channel">
          <el-select v-model="createForm.source_channel" style="width: 100%">
            <el-option
              v-for="item in channelOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="联系人">
          <el-input
            v-model="createForm.contact_info"
            placeholder="手机号/邮箱等（选填）"
          />
        </el-form-item>
        <el-form-item label="反馈内容" prop="content">
          <el-input
            v-model="createForm.content"
            type="textarea"
            :rows="4"
            placeholder="请输入反馈内容..."
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="handleCreate">确认录入</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getFeedbackList,
  getFeedbackDetail,
  getFeedbackStatusOptions,
  getFeedbackChannelOptions,
  createFeedback,
  updateFeedback
} from '../api'
import { loadSystemConfig, getDefaultPageSize } from '../utils/systemConfig'

const loading = ref(false)
const tableData = ref([])
const total = ref(0)
const statusOptions = ref([])
const channelOptions = ref([])

const detailVisible = ref(false)
const currentDetail = ref(null)
const updating = ref(false)
const updateForm = reactive({
  status: '',
  handle_note: ''
})

const createVisible = ref(false)
const creating = ref(false)
const createFormRef = ref(null)
const createForm = reactive({
  source_channel: 'app',
  contact_info: '',
  content: ''
})
const createRules = {
  source_channel: [{ required: true, message: '请选择来源渠道', trigger: 'change' }],
  content: [{ required: true, message: '请输入反馈内容', trigger: 'blur' }]
}

const queryForm = reactive({
  page: 1,
  page_size: 10,
  status: 'pending',
  source_channel: '',
  keyword: ''
})

const displayTimeline = computed(() => {
  if (!currentDetail.value) return []
  const timeline = []
  const history = currentDetail.value.history || []
  const isManuallyCreated = history.some(h => h.action === 'create')
  if (!isManuallyCreated) {
    timeline.push({
      created_at: currentDetail.value.created_at,
      title: '反馈提交',
      note: currentDetail.value.content,
      admin_username: null,
      timelineType: 'primary',
      icon: null
    })
  }
  history.forEach(h => {
    let title = ''
    let type = ''
    let note = h.note
    if (h.action === 'create') {
      title = '手动录入反馈'
      type = 'success'
      note = note || currentDetail.value.content
    } else if (h.action === 'status_update') {
      title = `状态变更：${getStatusLabel(h.old_status)} → ${getStatusLabel(h.new_status)}`
      type = 'warning'
    } else if (h.action === 'note_update') {
      title = '添加处理备注'
      type = 'info'
    }
    timeline.push({
      created_at: h.created_at,
      title,
      note,
      admin_username: h.admin_username,
      timelineType: type,
      icon: null
    })
  })
  return timeline
})

const fetchOptions = async () => {
  try {
    const [statusRes, channelRes] = await Promise.all([
      getFeedbackStatusOptions(),
      getFeedbackChannelOptions()
    ])
    statusOptions.value = statusRes.data
    channelOptions.value = channelRes.data
  } catch (error) {
    console.error('获取选项失败：', error)
  }
}

const fetchData = async () => {
  loading.value = true
  try {
    const params = {
      page: queryForm.page,
      page_size: queryForm.page_size
    }
    if (queryForm.status) params.status = queryForm.status
    if (queryForm.source_channel) params.source_channel = queryForm.source_channel
    if (queryForm.keyword) params.keyword = queryForm.keyword

    const res = await getFeedbackList(params)
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

const handleReset = () => {
  queryForm.status = 'pending'
  queryForm.source_channel = ''
  queryForm.keyword = ''
  handleQuery()
}

const handlePageChange = () => {
  fetchData()
}

const handleSizeChange = () => {
  queryForm.page = 1
  fetchData()
}

const handleRowClick = (row) => {
  handleViewDetail(row)
}

const handleViewDetail = async (row) => {
  try {
    const res = await getFeedbackDetail(row.id)
    currentDetail.value = res.data
    updateForm.status = res.data.status
    updateForm.handle_note = ''
    detailVisible.value = true
  } catch (error) {
    console.error('获取详情失败：', error)
  }
}

const handleUpdate = async () => {
  if (!updateForm.status && !updateForm.handle_note) {
    ElMessage.warning('请选择状态或输入备注')
    return
  }
  try {
    await ElMessageBox.confirm('确认提交本次更新？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
  } catch {
    return
  }
  updating.value = true
  try {
    const data = {}
    if (updateForm.status) data.status = updateForm.status
    if (updateForm.handle_note) data.handle_note = updateForm.handle_note
    await updateFeedback(currentDetail.value.id, data)
    ElMessage.success('更新成功')
    detailVisible.value = false
    fetchData()
  } catch (error) {
    console.error('更新失败：', error)
  } finally {
    updating.value = false
  }
}

const openCreateDialog = () => {
  createForm.source_channel = 'app'
  createForm.contact_info = ''
  createForm.content = ''
  createVisible.value = true
}

const handleCreate = async () => {
  if (!createFormRef.value) return
  try {
    await createFormRef.value.validate()
  } catch {
    return
  }
  creating.value = true
  try {
    await createFeedback(createForm)
    ElMessage.success('录入成功')
    createVisible.value = false
    fetchData()
  } catch (error) {
    console.error('录入失败：', error)
  } finally {
    creating.value = false
  }
}

const getStatusLabel = (status) => {
  const map = {}
  statusOptions.value.forEach(item => {
    map[item.value] = item.label
  })
  return map[status] || status
}

const getStatusTagType = (status) => {
  const typeMap = {
    pending: 'danger',
    processing: 'warning',
    closed: 'success'
  }
  return typeMap[status] || ''
}

const getChannelLabel = (channel) => {
  const map = {}
  channelOptions.value.forEach(item => {
    map[item.value] = item.label
  })
  return map[channel] || channel
}

onMounted(async () => {
  await loadSystemConfig()
  queryForm.page_size = getDefaultPageSize()
  fetchOptions()
  fetchData()
})
</script>

<style scoped>
.user-feedback :deep(.el-card) {
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

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.text-muted {
  color: #94a3b8;
  font-size: 13px;
}

.user-feedback :deep(.el-table th.el-table__cell) {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  font-size: 13px;
}

.user-feedback :deep(.el-table tr) {
  cursor: pointer;
}

.user-feedback :deep(.el-button--primary) {
  background: #6366f1;
  border-color: #6366f1;
}

.user-feedback :deep(.el-button--primary:hover) {
  background: #4f46e5;
  border-color: #4f46e5;
}

.detail-content {
  padding-right: 8px;
}

.feedback-content {
  line-height: 1.7;
  color: #334155;
  white-space: pre-wrap;
  word-break: break-all;
}

.feedback-note {
  line-height: 1.7;
  color: #0369a1;
  background: #f0f9ff;
  padding: 8px 12px;
  border-radius: 6px;
  white-space: pre-wrap;
}

.update-section {
  margin-top: 24px;
  padding: 16px;
  background: #fafafa;
  border-radius: 8px;
}

.update-section h4 {
  margin: 0 0 12px 0;
  font-size: 15px;
  color: #1e293b;
}

.timeline-section {
  margin-top: 24px;
}

.timeline-section h4 {
  margin: 0 0 16px 0;
  font-size: 15px;
  color: #1e293b;
}

.timeline-item {
  padding-bottom: 4px;
}

.timeline-title {
  font-weight: 500;
  color: #1e293b;
  font-size: 14px;
}

.timeline-operator {
  font-size: 12px;
  color: #64748b;
  margin-top: 2px;
}

.timeline-note {
  margin-top: 6px;
  padding: 8px 12px;
  background: #f8fafc;
  border-radius: 6px;
  color: #334155;
  font-size: 13px;
  line-height: 1.6;
  white-space: pre-wrap;
}
</style>
