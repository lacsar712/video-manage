<template>
  <div class="tag-management">
    <el-card>
      <template #header>
        <div class="card-header">
          <h3>标签管理</h3>
          <el-button type="primary" @click="handleAdd">
            <el-icon><Plus /></el-icon>
            新增标签
          </el-button>
        </div>
      </template>

      <el-tabs v-model="activeTab" class="tag-tabs">
        <el-tab-pane label="地区标签" name="region">
          <div class="content-wrapper">
            <div class="left-panel">
              <el-table
                :data="regionList"
                border
                stripe
                v-loading="loading"
                highlight-current-row
                @current-change="handleRowSelect"
                @row-click="handleRowClick"
              >
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="name" label="地区名称" min-width="150" />
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
                    <h4>{{ isEdit ? '编辑地区标签' : '新增地区标签' }}</h4>
                  </div>
                </template>

                <el-form
                  ref="regionFormRef"
                  :model="regionForm"
                  :rules="regionRules"
                  label-width="100px"
                >
                  <el-form-item label="标签名称" prop="name">
                    <el-input
                      v-model="regionForm.name"
                      placeholder="请输入地区名称（1-50个字符）"
                      maxlength="50"
                      show-word-limit
                      clearable
                    />
                  </el-form-item>

                  <el-form-item label="排序" prop="sort_order">
                    <el-input-number
                      v-model="regionForm.sort_order"
                      :min="0"
                      :max="9999"
                      controls-position="right"
                      style="width: 200px"
                    />
                  </el-form-item>

                  <el-form-item label="状态" prop="status">
                    <el-radio-group v-model="regionForm.status" size="large">
                      <el-radio :label="1" border>启用</el-radio>
                      <el-radio :label="0" border>禁用</el-radio>
                    </el-radio-group>
                  </el-form-item>

                  <el-form-item>
                    <el-button type="primary" :loading="submitting" @click="handleRegionSubmit">
                      保存
                    </el-button>
                    <el-button @click="handleRegionReset">重置</el-button>
                  </el-form-item>
                </el-form>
              </el-card>
            </div>
          </div>
        </el-tab-pane>

        <el-tab-pane label="语言标签" name="language">
          <div class="content-wrapper">
            <div class="left-panel">
              <el-table
                :data="languageList"
                border
                stripe
                v-loading="loading"
                highlight-current-row
                @current-change="handleLanguageRowSelect"
                @row-click="handleLanguageRowClick"
              >
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="name" label="语言名称" min-width="150" />
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
                    <h4>{{ isEdit ? '编辑语言标签' : '新增语言标签' }}</h4>
                  </div>
                </template>

                <el-form
                  ref="languageFormRef"
                  :model="languageForm"
                  :rules="languageRules"
                  label-width="100px"
                >
                  <el-form-item label="标签名称" prop="name">
                    <el-input
                      v-model="languageForm.name"
                      placeholder="请输入语言名称（1-50个字符）"
                      maxlength="50"
                      show-word-limit
                      clearable
                    />
                  </el-form-item>

                  <el-form-item label="排序" prop="sort_order">
                    <el-input-number
                      v-model="languageForm.sort_order"
                      :min="0"
                      :max="9999"
                      controls-position="right"
                      style="width: 200px"
                    />
                  </el-form-item>

                  <el-form-item label="状态" prop="status">
                    <el-radio-group v-model="languageForm.status" size="large">
                      <el-radio :label="1" border>启用</el-radio>
                      <el-radio :label="0" border>禁用</el-radio>
                    </el-radio-group>
                  </el-form-item>

                  <el-form-item>
                    <el-button type="primary" :loading="submitting" @click="handleLanguageSubmit">
                      保存
                    </el-button>
                    <el-button @click="handleLanguageReset">重置</el-button>
                  </el-form-item>
                </el-form>
              </el-card>
            </div>
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getTagList,
  createTag,
  updateTag,
  deleteTag,
  updateTagStatus
} from '../api'

const loading = ref(false)
const submitting = ref(false)
const activeTab = ref('region')
const regionList = ref([])
const languageList = ref([])
const isEdit = ref(false)
const regionFormRef = ref(null)
const languageFormRef = ref(null)

const defaultForm = {
  id: null,
  name: '',
  sort_order: 0,
  status: 1
}

const regionForm = reactive({ ...defaultForm })
const languageForm = reactive({ ...defaultForm })

const baseRules = {
  name: [
    { required: true, message: '请输入标签名称', trigger: 'blur' },
    { min: 1, max: 50, message: '名称长度必须在1-50个字符之间', trigger: 'blur' }
  ],
  sort_order: [
    { type: 'number', min: 0, max: 9999, message: '排序值必须在0-9999之间', trigger: 'blur' }
  ],
  status: [
    { required: true, message: '请选择状态', trigger: 'change' }
  ]
}

const regionRules = { ...baseRules }
const languageRules = { ...baseRules }

const fetchList = async () => {
  loading.value = true
  try {
    const res = await getTagList()
    const list = res.data.list
    regionList.value = list.filter(item => item.type === 'region')
    languageList.value = list.filter(item => item.type === 'language')
  } catch (error) {
    console.error('获取标签列表失败：', error)
  } finally {
    loading.value = false
  }
}

const handleAdd = () => {
  isEdit.value = false
  if (activeTab.value === 'region') {
    Object.assign(regionForm, defaultForm)
    regionFormRef.value?.clearValidate()
  } else {
    Object.assign(languageForm, defaultForm)
    languageFormRef.value?.clearValidate()
  }
}

const handleRowClick = (row) => {
  handleRowSelect(row)
}

const handleRowSelect = (row) => {
  if (!row) return
  isEdit.value = true
  Object.assign(regionForm, {
    id: row.id,
    name: row.name,
    sort_order: parseInt(row.sort_order) || 0,
    status: parseInt(row.status)
  })
}

const handleLanguageRowClick = (row) => {
  handleLanguageRowSelect(row)
}

const handleLanguageRowSelect = (row) => {
  if (!row) return
  isEdit.value = true
  Object.assign(languageForm, {
    id: row.id,
    name: row.name,
    sort_order: parseInt(row.sort_order) || 0,
    status: parseInt(row.status)
  })
}

const handleRegionReset = () => {
  if (isEdit.value && regionForm.id) {
    const row = regionList.value.find(item => item.id === regionForm.id)
    if (row) {
      Object.assign(regionForm, {
        id: row.id,
        name: row.name,
        sort_order: parseInt(row.sort_order) || 0,
        status: parseInt(row.status)
      })
    }
  } else {
    Object.assign(regionForm, defaultForm)
  }
  regionFormRef.value?.clearValidate()
}

const handleLanguageReset = () => {
  if (isEdit.value && languageForm.id) {
    const row = languageList.value.find(item => item.id === languageForm.id)
    if (row) {
      Object.assign(languageForm, {
        id: row.id,
        name: row.name,
        sort_order: parseInt(row.sort_order) || 0,
        status: parseInt(row.status)
      })
    }
  } else {
    Object.assign(languageForm, defaultForm)
  }
  languageFormRef.value?.clearValidate()
}

const handleRegionSubmit = async () => {
  if (!regionFormRef.value) return
  await regionFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      const data = { ...regionForm, type: 'region' }
      if (isEdit.value && regionForm.id) {
        await updateTag(regionForm.id, data)
        ElMessage.success('更新成功')
      } else {
        await createTag(data)
        ElMessage.success('添加成功')
      }
      await fetchList()
      if (!isEdit.value) {
        Object.assign(regionForm, defaultForm)
        regionFormRef.value?.clearValidate()
      }
    } catch (error) {
      console.error('提交失败：', error)
    } finally {
      submitting.value = false
    }
  })
}

const handleLanguageSubmit = async () => {
  if (!languageFormRef.value) return
  await languageFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      const data = { ...languageForm, type: 'language' }
      if (isEdit.value && languageForm.id) {
        await updateTag(languageForm.id, data)
        ElMessage.success('更新成功')
      } else {
        await createTag(data)
        ElMessage.success('添加成功')
      }
      await fetchList()
      if (!isEdit.value) {
        Object.assign(languageForm, defaultForm)
        languageFormRef.value?.clearValidate()
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
    await ElMessageBox.confirm(`确定要${action}该标签吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    await updateTagStatus(row.id, newStatus)
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
    await ElMessageBox.confirm('确定要删除该标签吗？删除后将无法恢复！', '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'error'
    })

    await deleteTag(row.id)
    ElMessage.success('删除成功')

    if (row.type === 'region' && regionForm.id === row.id) {
      Object.assign(regionForm, defaultForm)
      isEdit.value = false
    } else if (row.type === 'language' && languageForm.id === row.id) {
      Object.assign(languageForm, defaultForm)
      isEdit.value = false
    }
    await fetchList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败：', error)
    }
  }
}

watch(activeTab, () => {
  isEdit.value = false
})

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.tag-management :deep(.el-card) {
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

.tag-tabs :deep(.el-tabs__header) {
  margin-bottom: 20px;
}

.tag-tabs :deep(.el-tabs__item) {
  font-size: 15px;
  font-weight: 500;
}

.tag-tabs :deep(.el-tabs__active-bar) {
  background: #6366f1;
}

.tag-tabs :deep(.el-tabs__item.is-active) {
  color: #6366f1;
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

.tag-management :deep(.el-button--primary) {
  background: #6366f1;
  border-color: #6366f1;
}

.tag-management :deep(.el-button--primary:hover) {
  background: #4f46e5;
  border-color: #4f46e5;
}
</style>
