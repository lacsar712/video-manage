<template>
  <div class="audit-log">
    <el-card>
      <template #header>
        <div class="card-header">
          <h3>操作日志</h3>
        </div>
      </template>

      <div class="filter-bar">
        <el-form :inline="true" :model="queryForm">
          <el-form-item label="时间范围">
            <el-date-picker
              v-model="queryForm.date_range"
              type="datetimerange"
              range-separator="至"
              start-placeholder="开始时间"
              end-placeholder="结束时间"
              value-format="YYYY-MM-DD HH:mm:ss"
              style="width: 380px"
              @change="handleDateChange"
            />
          </el-form-item>
          <el-form-item label="操作人">
            <el-input
              v-model="queryForm.admin_username"
              placeholder="请输入操作人用户名"
              clearable
              style="width: 180px"
              @clear="handleQuery"
            />
          </el-form-item>
          <el-form-item label="动作类型">
            <el-select
              v-model="queryForm.action"
              placeholder="请选择动作"
              clearable
              style="width: 150px"
              @clear="handleQuery"
            >
              <el-option
                v-for="item in actionOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="资源类型">
            <el-select
              v-model="queryForm.resource_type"
              placeholder="请选择资源"
              clearable
              style="width: 150px"
              @clear="handleQuery"
            >
              <el-option
                v-for="item in resourceTypeOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleQuery">查询</el-button>
            <el-button @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-alert
        title="操作日志为只读，不可删除，默认按时间倒序排列"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />

      <el-table :data="tableData" border stripe v-loading="loading">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="created_at" label="操作时间" width="180" />
        <el-table-column prop="admin_username" label="操作人" width="120">
          <template #default="{ row }">
            <span v-if="row.admin_username">{{ row.admin_username }}</span>
            <span v-else class="text-muted">系统</span>
          </template>
        </el-table-column>
        <el-table-column label="动作" width="100">
          <template #default="{ row }">
            <el-tag :type="getActionTagType(row.action)">
              {{ getActionLabel(row.action) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="资源类型" width="100">
          <template #default="{ row }">
            <el-tag type="info" size="small">
              {{ getResourceTypeLabel(row.resource_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="resource_id" label="资源ID" width="100" />
        <el-table-column prop="ip" label="IP地址" width="140" />
        <el-table-column label="详情" min-width="300">
          <template #default="{ row }">
            <el-button
              v-if="row.summary"
              type="primary"
              link
              size="small"
              @click="handleViewDetail(row)"
            >
              查看变更
            </el-button>
            <span v-else class="text-muted">无详情</span>
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
      v-model="detailVisible"
      title="变更详情"
      width="680px"
    >
      <div v-if="currentDetail" class="detail-content">
        <div class="detail-row">
          <span class="detail-label">操作时间：</span>
          <span>{{ currentDetail.created_at }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">操作人：</span>
          <span>{{ currentDetail.admin_username || '系统' }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">动作：</span>
          <el-tag :type="getActionTagType(currentDetail.action)">
            {{ getActionLabel(currentDetail.action) }}
          </el-tag>
        </div>
        <div class="detail-row">
          <span class="detail-label">资源类型：</span>
          <span>{{ getResourceTypeLabel(currentDetail.resource_type) }}</span>
        </div>
        <div class="detail-row" v-if="currentDetail.resource_id">
          <span class="detail-label">资源ID：</span>
          <span>{{ currentDetail.resource_id }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">IP地址：</span>
          <span>{{ currentDetail.ip }}</span>
        </div>
        <div class="detail-row summary-row">
          <span class="detail-label">变更摘要：</span>
        </div>
        <pre class="summary-json">{{ formatSummary(currentDetail.summary) }}</pre>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import {
  getAuditLogList,
  getAuditActions,
  getAuditResourceTypes
} from '../api'
import { loadSystemConfig, getDefaultPageSize } from '../utils/systemConfig'

const loading = ref(false)
const tableData = ref([])
const total = ref(0)
const actionOptions = ref([])
const resourceTypeOptions = ref([])
const detailVisible = ref(false)
const currentDetail = ref(null)

const queryForm = reactive({
  page: 1,
  page_size: 10,
  admin_username: '',
  action: '',
  resource_type: '',
  start_time: '',
  end_time: '',
  date_range: []
})

const fetchOptions = async () => {
  try {
    const [actionsRes, typesRes] = await Promise.all([
      getAuditActions(),
      getAuditResourceTypes()
    ])
    actionOptions.value = actionsRes.data
    resourceTypeOptions.value = typesRes.data
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
    if (queryForm.admin_username) params.admin_username = queryForm.admin_username
    if (queryForm.action) params.action = queryForm.action
    if (queryForm.resource_type) params.resource_type = queryForm.resource_type
    if (queryForm.start_time) params.start_time = queryForm.start_time
    if (queryForm.end_time) params.end_time = queryForm.end_time

    const res = await getAuditLogList(params)
    tableData.value = res.data.list
    total.value = res.data.total
  } catch (error) {
    console.error('获取列表失败：', error)
  } finally {
    loading.value = false
  }
}

const handleDateChange = (val) => {
  if (val && val.length === 2) {
    queryForm.start_time = val[0]
    queryForm.end_time = val[1]
  } else {
    queryForm.start_time = ''
    queryForm.end_time = ''
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
  queryForm.admin_username = ''
  queryForm.action = ''
  queryForm.resource_type = ''
  queryForm.start_time = ''
  queryForm.end_time = ''
  queryForm.date_range = []
  handleQuery()
}

const handleViewDetail = (row) => {
  currentDetail.value = row
  detailVisible.value = true
}

const getActionLabel = (action) => {
  const map = {}
  actionOptions.value.forEach(item => {
    map[item.value] = item.label
  })
  return map[action] || action
}

const getActionTagType = (action) => {
  const typeMap = {
    create: 'success',
    update: 'warning',
    delete: 'danger',
    publish: 'success',
    unpublish: 'info',
    login: '',
    logout: 'info'
  }
  return typeMap[action] || ''
}

const getResourceTypeLabel = (type) => {
  const map = {}
  resourceTypeOptions.value.forEach(item => {
    map[item.value] = item.label
  })
  return map[type] || type
}

const formatSummary = (summary) => {
  if (!summary) return ''
  if (typeof summary === 'string') return summary
  return JSON.stringify(summary, null, 2)
}

onMounted(async () => {
  await loadSystemConfig()
  queryForm.page_size = getDefaultPageSize()
  fetchOptions()
  fetchData()
})
</script>

<style scoped>
.audit-log :deep(.el-card) {
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

.audit-log :deep(.el-table th.el-table__cell) {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  font-size: 13px;
}

.audit-log :deep(.el-button--primary) {
  background: #6366f1;
  border-color: #6366f1;
}

.audit-log :deep(.el-button--primary:hover) {
  background: #4f46e5;
  border-color: #4f46e5;
}

.detail-content {
  font-size: 14px;
}

.detail-row {
  display: flex;
  align-items: center;
  margin-bottom: 12px;
}

.detail-label {
  min-width: 90px;
  font-weight: 600;
  color: #475569;
}

.summary-row {
  align-items: flex-start;
  margin-top: 16px;
}

.summary-json {
  background: #1e293b;
  color: #e2e8f0;
  padding: 16px;
  border-radius: 8px;
  font-size: 13px;
  line-height: 1.6;
  overflow-x: auto;
  margin: 0;
  max-height: 400px;
  overflow-y: auto;
}
</style>
