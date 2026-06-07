<template>
  <div class="hot-keyword-management">
    <el-row :gutter="20">
      <el-col :span="16">
        <el-card>
          <template #header>
            <div class="card-header">
              <h3>热搜关键词管理</h3>
              <div class="header-actions">
                <el-tag type="info" effect="plain" class="sort-tip">
                  <el-icon><Rank /></el-icon>
                  拖拽行可调整排序
                </el-tag>
                <el-button type="success" @click="handleSyncStats" :loading="syncLoading">
                  <el-icon><Refresh /></el-icon>
                  同步统计
                </el-button>
                <el-button type="primary" @click="handleAdd">
                  <el-icon><Plus /></el-icon>
                  新增关键词
                </el-button>
              </div>
            </div>
          </template>

          <div class="filter-bar">
            <el-form :inline="true" :model="queryForm">
              <el-form-item label="关键词">
                <el-input
                  v-model="queryForm.keyword"
                  placeholder="请输入关键词"
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
            @row-drag-start="onDragStart"
            @row-drag-over="onDragOver"
            @row-drop="onDrop"
            @row-drag-end="onDragEnd"
          >
            <el-table-column type="index" label="序号" width="70" align="center">
              <template #default="{ $index }">
                <span class="drag-handle">
                  <el-icon><Rank /></el-icon>
                  {{ $index + 1 }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column prop="keyword" label="关键词" min-width="160" show-overflow-tooltip>
              <template #default="{ row }">
                <el-tag type="primary" effect="plain">{{ row.keyword }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="点击次数" width="160">
              <template #default="{ row }">
                <div class="click-count-wrapper">
                  <el-input-number
                    v-model="row.editClickCount"
                    :min="0"
                    size="small"
                    controls-position="right"
                    style="width: 120px"
                    @change="(val) => handleClickCountChange(row, val)"
                  />
                  <el-button
                    v-if="row.clickCount !== row.editClickCount"
                    type="primary"
                    link
                    size="small"
                    @click="handleSaveClickCount(row)"
                  >
                    保存
                  </el-button>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'info'">
                  {{ row.status === 1 ? '启用' : '禁用' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="创建时间" width="180" />
            <el-table-column label="操作" width="220" fixed="right">
              <template #default="{ row }">
                <el-button
                  size="small"
                  :type="row.status === 1 ? 'warning' : 'success'"
                  @click="handleToggleStatus(row)"
                >
                  {{ row.status === 1 ? '禁用' : '启用' }}
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
      </el-col>

      <el-col :span="8">
        <el-card>
          <template #header>
            <div class="card-header">
              <h3>预览效果</h3>
              <el-tag type="success" effect="plain" size="small">实时预览</el-tag>
            </div>
          </template>

          <div class="preview-section">
            <div class="preview-label">APP端热搜词展示</div>
            <div class="preview-container">
              <div class="preview-search">
                <el-input
                  v-model="previewSearch"
                  placeholder="搜索影片"
                  clearable
                >
                  <template #prefix>
                    <el-icon><Search /></el-icon>
                  </template>
                </el-input>
              </div>

              <div v-if="enabledKeywords.length > 0" class="hot-keywords-preview">
                <div class="hot-title">
                  <el-icon color="#f56c6c"><HotWater /></el-icon>
                  <span>热门搜索</span>
                </div>
                <div class="hot-list">
                  <div
                    v-for="(item, index) in enabledKeywords"
                    :key="item.id"
                    class="hot-item"
                  >
                    <span class="hot-rank" :class="{ 'top-three': index < 3 }">
                      {{ index + 1 }}
                    </span>
                    <span class="hot-keyword">{{ item.keyword }}</span>
                    <span class="hot-count">{{ formatClickCount(item.click_count) }}</span>
                  </div>
                </div>
              </div>
              <el-empty v-else description="暂无启用的热搜词" />
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑关键词' : '新增关键词'"
      width="520px"
      :close-on-click-modal="false"
      destroy-on-close
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="100px"
      >
        <el-form-item label="关键词" prop="keyword">
          <el-input
            v-model="form.keyword"
            placeholder="请输入关键词（1-100个字符）"
            maxlength="100"
            show-word-limit
            clearable
          />
        </el-form-item>

        <el-form-item label="点击次数" prop="click_count">
          <el-input-number
            v-model="form.click_count"
            :min="0"
            :max="9999999"
            controls-position="right"
            style="width: 100%"
          />
          <div class="form-tip">可手动设置初始点击次数</div>
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, nextTick, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Rank, Refresh, Search, HotWater } from '@element-plus/icons-vue'
import { loadSystemConfig, getDefaultPageSize } from '../utils/systemConfig'
import {
  getHotKeywordList,
  getHotKeywordDetail,
  getEnabledHotKeywords,
  createHotKeyword,
  updateHotKeyword,
  deleteHotKeyword,
  updateHotKeywordStatus,
  updateHotKeywordSort,
  updateHotKeywordClickCount,
  syncHotKeywordStats
} from '../api'

const tableRef = ref(null)
const formRef = ref(null)
const loading = ref(false)
const syncLoading = ref(false)
const tableData = ref([])
const enabledKeywords = ref([])
const total = ref(0)
const dialogVisible = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const submitLoading = ref(false)
const previewSearch = ref('')
const dragData = reactive({
  dragging: false,
  dragIndex: -1,
  dropIndex: -1
})

const queryForm = reactive({
  page: 1,
  page_size: 20,
  keyword: '',
  status: ''
})

const form = reactive({
  keyword: '',
  sort_order: 0,
  status: 1,
  click_count: 0
})

const rules = {
  keyword: [
    { required: true, message: '请输入关键词', trigger: 'blur' },
    { min: 1, max: 100, message: '关键词长度必须在1-100个字符之间', trigger: 'blur' }
  ],
  status: [{ required: true, message: '请选择状态', trigger: 'change' }]
}

const formatClickCount = (count) => {
  if (count >= 10000) {
    return (count / 10000).toFixed(1) + '万'
  }
  return count.toString()
}

const fetchEnabledKeywords = async () => {
  try {
    const res = await getEnabledHotKeywords()
    enabledKeywords.value = res.data.list
  } catch (error) {
    console.error('获取启用关键词失败：', error)
  }
}

const fetchData = async () => {
  loading.value = true
  try {
    const res = await getHotKeywordList(queryForm)
    tableData.value = res.data.list.map(item => ({
      ...item,
      editClickCount: item.click_count
    }))
    total.value = res.data.total
    await fetchEnabledKeywords()
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
  form.keyword = ''
  form.sort_order = 0
  form.status = 1
  form.click_count = 0
  editingId.value = null
}

const handleAdd = async () => {
  isEdit.value = false
  resetForm()
  dialogVisible.value = true
  await nextTick()
  formRef.value?.clearValidate()
}

const handleEdit = async (row) => {
  isEdit.value = true
  resetForm()
  editingId.value = row.id

  try {
    loading.value = true
    const res = await getHotKeywordDetail(row.id)
    const data = res.data
    form.keyword = data.keyword
    form.sort_order = data.sort_order
    form.status = data.status
    form.click_count = data.click_count
    dialogVisible.value = true
    await nextTick()
    formRef.value?.clearValidate()
  } catch (error) {
    console.error('获取详情失败：', error)
  } finally {
    loading.value = false
  }
}

const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    submitLoading.value = true
    try {
      const submitData = {
        keyword: form.keyword,
        sort_order: form.sort_order,
        status: form.status,
        click_count: form.click_count
      }

      if (isEdit.value && editingId.value) {
        await updateHotKeyword(editingId.value, submitData)
        ElMessage.success('更新成功')
      } else {
        await createHotKeyword(submitData)
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
  const action = newStatus === 1 ? '启用' : '禁用'

  try {
    await ElMessageBox.confirm(`确定要${action}该关键词吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    await updateHotKeywordStatus(row.id, newStatus)
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
    await ElMessageBox.confirm('确定要删除该关键词吗？删除后将无法恢复！', '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'error'
    })

    await deleteHotKeyword(row.id)
    ElMessage.success('删除成功')
    fetchData()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败：', error)
    }
  }
}

const handleClickCountChange = (row, val) => {
  row.editClickCount = val
}

const handleSaveClickCount = async (row) => {
  try {
    await updateHotKeywordClickCount(row.id, row.editClickCount)
    row.click_count = row.editClickCount
    ElMessage.success('点击次数已更新')
    fetchEnabledKeywords()
  } catch (error) {
    console.error('更新点击次数失败：', error)
    row.editClickCount = row.click_count
  }
}

const handleSyncStats = async () => {
  try {
    await ElMessageBox.confirm(
      '确定要同步统计数据吗？将为所有启用的关键词增加随机点击次数。',
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'info'
      }
    )

    syncLoading.value = true
    const res = await syncHotKeywordStats()
    ElMessage.success(`同步成功，共更新 ${res.data.synced_count} 条数据`)
    fetchData()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('同步统计失败：', error)
    }
  } finally {
    syncLoading.value = false
  }
}

const onDragStart = (row) => {
  dragData.dragging = true
  dragData.dragIndex = tableData.value.findIndex(item => item.id === row.id)
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
    await updateHotKeywordSort(sortList)
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
.hot-keyword-management :deep(.el-card) {
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

.hot-keyword-management :deep(.el-table .el-table__row) {
  cursor: move;
}

.click-count-wrapper {
  display: flex;
  align-items: center;
  gap: 4px;
}

.form-tip {
  font-size: 12px;
  color: #94a3b8;
  margin-top: 4px;
}

.hot-keyword-management :deep(.el-table th.el-table__cell) {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  font-size: 13px;
}

.hot-keyword-management :deep(.el-button--primary) {
  background: #6366f1;
  border-color: #6366f1;
}

.hot-keyword-management :deep(.el-button--primary:hover) {
  background: #4f46e5;
  border-color: #4f46e5;
}

.preview-section {
  padding: 10px 0;
}

.preview-label {
  font-size: 13px;
  color: #64748b;
  margin-bottom: 12px;
  font-weight: 500;
}

.preview-container {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px;
  background: #f8fafc;
}

.preview-search {
  margin-bottom: 16px;
}

.hot-keywords-preview {
  background: #fff;
  border-radius: 8px;
  padding: 12px;
}

.hot-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #f1f5f9;
}

.hot-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.hot-item {
  display: flex;
  align-items: center;
  padding: 8px 10px;
  border-radius: 6px;
  transition: background-color 0.2s;
  cursor: pointer;
}

.hot-item:hover {
  background: #f8fafc;
}

.hot-rank {
  width: 22px;
  height: 22px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  color: #94a3b8;
  background: #f1f5f9;
  border-radius: 4px;
  margin-right: 10px;
  flex-shrink: 0;
}

.hot-rank.top-three {
  color: #fff;
  background: linear-gradient(135deg, #f56c6c 0%, #e6a23c 100%);
}

.hot-rank.top-three + .hot-keyword {
  color: #1e293b;
  font-weight: 600;
}

.hot-keyword {
  flex: 1;
  font-size: 14px;
  color: #334155;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hot-count {
  font-size: 12px;
  color: #94a3b8;
  flex-shrink: 0;
  margin-left: 8px;
}
</style>
