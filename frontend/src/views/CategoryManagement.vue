<template>
  <div class="category-management">
    <el-card>
      <template #header>
        <div class="card-header">
          <h3>分类管理</h3>
          <el-button type="primary" @click="handleAdd">
            <el-icon><Plus /></el-icon>
            新增分类
          </el-button>
        </div>
      </template>

      <div class="content-wrapper">
        <div class="left-panel">
          <el-table
            :data="categoryList"
            border
            stripe
            v-loading="loading"
            highlight-current-row
            @current-change="handleRowSelect"
            @row-click="handleRowClick"
          >
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column prop="name" label="分类名称" min-width="120" />
            <el-table-column prop="slug" label="URL标识" min-width="120" />
            <el-table-column prop="sort_order" label="排序" width="70" />
            <el-table-column prop="status" label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status == 1 ? 'success' : 'info'" size="small">
                  {{ row.status == 1 ? '启用' : '禁用' }}
                </el-tag>
              </template>
            </el-table-column>
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
        </div>

        <div class="right-panel">
          <el-card shadow="never" class="form-card">
            <template #header>
              <div class="form-header">
                <h4>{{ isEdit ? '编辑分类' : (form.id ? '编辑分类' : '新增分类') }}</h4>
              </div>
            </template>

            <el-form
              ref="formRef"
              :model="form"
              :rules="rules"
              label-width="100px"
            >
              <el-form-item label="分类名称" prop="name">
                <el-input
                  v-model="form.name"
                  placeholder="请输入分类名称（1-50个字符）"
                  maxlength="50"
                  show-word-limit
                  clearable
                />
              </el-form-item>

              <el-form-item label="URL标识" prop="slug">
                <el-input
                  v-model="form.slug"
                  placeholder="请输入URL标识（字母、数字、下划线、连字符）"
                  maxlength="50"
                  clearable
                />
              </el-form-item>

              <el-form-item label="排序" prop="sort_order">
                <el-input-number
                  v-model="form.sort_order"
                  :min="0"
                  :max="9999"
                  controls-position="right"
                  style="width: 200px"
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getCategoryList,
  createCategory,
  updateCategory,
  deleteCategory,
  updateCategoryStatus
} from '../api'

const loading = ref(false)
const submitting = ref(false)
const categoryList = ref([])
const isEdit = ref(false)
const formRef = ref(null)

const defaultForm = {
  id: null,
  name: '',
  slug: '',
  sort_order: 0,
  status: 1
}

const form = reactive({ ...defaultForm })

const rules = {
  name: [
    { required: true, message: '请输入分类名称', trigger: 'blur' },
    { min: 1, max: 50, message: '名称长度必须在1-50个字符之间', trigger: 'blur' }
  ],
  slug: [
    { required: true, message: '请输入URL标识', trigger: 'blur' },
    { min: 1, max: 50, message: '标识长度必须在1-50个字符之间', trigger: 'blur' },
    {
      pattern: /^[a-zA-Z0-9_-]+$/,
      message: '只能包含字母、数字、下划线和连字符',
      trigger: 'blur'
    }
  ],
  sort_order: [
    { type: 'number', min: 0, max: 9999, message: '排序值必须在0-9999之间', trigger: 'blur' }
  ],
  status: [
    { required: true, message: '请选择状态', trigger: 'change' }
  ]
}

const fetchList = async () => {
  loading.value = true
  try {
    const res = await getCategoryList()
    categoryList.value = res.data.list
  } catch (error) {
    console.error('获取分类列表失败：', error)
  } finally {
    loading.value = false
  }
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
    slug: row.slug,
    sort_order: parseInt(row.sort_order) || 0,
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
    const row = categoryList.value.find(item => item.id === form.id)
    if (row) {
      Object.assign(form, {
        id: row.id,
        name: row.name,
        slug: row.slug,
        sort_order: parseInt(row.sort_order) || 0,
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
        await updateCategory(form.id, form)
        ElMessage.success('更新成功')
      } else {
        await createCategory(form)
        ElMessage.success('添加成功')
      }
      await fetchList()
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
    await ElMessageBox.confirm(`确定要${action}该分类吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    await updateCategoryStatus(row.id, newStatus)
    ElMessage.success(`${action}成功`)
    await fetchList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error(`${action}失败：`, error)
    }
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该分类吗？删除后将无法恢复！', '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'error'
    })

    await deleteCategory(row.id)
    ElMessage.success('删除成功')
    if (form.id === row.id) {
      Object.assign(form, defaultForm)
      isEdit.value = false
    }
    await fetchList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败：', error)
    }
  }
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.category-management :deep(.el-card) {
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

.content-wrapper {
  display: flex;
  gap: 20px;
  min-height: 500px;
}

.left-panel {
  flex: 1.2;
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

.category-management :deep(.el-button--primary) {
  background: #6366f1;
  border-color: #6366f1;
}

.category-management :deep(.el-button--primary:hover) {
  background: #4f46e5;
  border-color: #4f46e5;
}
</style>
