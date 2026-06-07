<template>
  <div class="admin-user-management">
    <el-card>
      <template #header>
        <div class="card-header">
          <h3>账号管理</h3>
          <el-button type="primary" @click="openCreateDialog">
            <el-icon><Plus /></el-icon>
            新增账号
          </el-button>
        </div>
      </template>

      <el-table
        :data="userList"
        border
        stripe
        v-loading="loading"
      >
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="username" label="用户名" min-width="140" />
        <el-table-column prop="role" label="角色" width="120">
          <template #default="{ row }">
            <el-tag :type="row.role === 'super' ? 'danger' : 'primary'" size="small">
              {{ row.role === 'super' ? '超级管理员' : '编辑' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="row.status == 1 ? 'success' : 'info'" size="small">
              {{ row.status == 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" min-width="170" />
        <el-table-column label="操作" width="380" fixed="right">
          <template #default="{ row }">
            <el-button
              size="small"
              :type="row.status == 1 ? 'warning' : 'success'"
              :disabled="isSelf(row.id)"
              @click="handleToggleStatus(row)"
            >
              {{ row.status == 1 ? '禁用' : '启用' }}
            </el-button>
            <el-button size="small" type="primary" plain @click="openEditDialog(row)">
              编辑
            </el-button>
            <el-button
              size="small"
              type="primary"
              :disabled="isSelf(row.id)"
              @click="openResetPasswordDialog(row)"
            >
              重置密码
            </el-button>
            <el-button
              size="small"
              type="danger"
              :disabled="isSelf(row.id)"
              @click="handleDelete(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="createDialogVisible"
      title="新增账号"
      width="480px"
      @close="resetCreateForm"
    >
      <el-form
        ref="createFormRef"
        :model="createForm"
        :rules="createRules"
        label-width="100px"
      >
        <el-form-item label="用户名" prop="username">
          <el-input
            v-model="createForm.username"
            placeholder="请输入用户名（3-50字符，字母数字下划线）"
            maxlength="50"
            show-word-limit
            clearable
          />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input
            v-model="createForm.password"
            type="password"
            placeholder="至少8位，必须包含字母和数字"
            show-password
            maxlength="50"
            clearable
          />
        </el-form-item>
        <el-form-item label="角色" prop="role">
          <el-radio-group v-model="createForm.role">
            <el-radio :label="'super'" border>超级管理员</el-radio>
            <el-radio :label="'editor'" border>编辑</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="createForm.status">
            <el-radio :label="1" border>启用</el-radio>
            <el-radio :label="0" border>禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleCreate">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="editDialogVisible"
      title="编辑账号"
      width="480px"
      @close="resetEditForm"
    >
      <el-form
        ref="editFormRef"
        :model="editForm"
        :rules="editRules"
        label-width="100px"
      >
        <el-form-item label="用户名" prop="username">
          <el-input
            v-model="editForm.username"
            placeholder="请输入用户名（3-50字符，字母数字下划线）"
            maxlength="50"
            show-word-limit
            clearable
          />
        </el-form-item>
        <el-form-item label="角色" prop="role">
          <el-radio-group v-model="editForm.role" :disabled="isSelf(editTarget?.id)">
            <el-radio :label="'super'" border>超级管理员</el-radio>
            <el-radio :label="'editor'" border>编辑</el-radio>
          </el-radio-group>
          <div v-if="isSelf(editTarget?.id)" class="form-tip">不能修改当前登录账号的角色</div>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="editForm.status" :disabled="isSelf(editTarget?.id)">
            <el-radio :label="1" border>启用</el-radio>
            <el-radio :label="0" border>禁用</el-radio>
          </el-radio-group>
          <div v-if="isSelf(editTarget?.id)" class="form-tip">不能禁用当前登录账号</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="editing" @click="handleEdit">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="resetDialogVisible"
      title="重置密码"
      width="420px"
      @close="resetResetForm"
    >
      <p style="margin-bottom: 16px; color: #64748b;">
        重置 <b>{{ resetTarget?.username }}</b> 的密码：
      </p>
      <el-form
        ref="resetFormRef"
        :model="resetForm"
        :rules="resetRules"
        label-width="100px"
      >
        <el-form-item label="新密码" prop="password">
          <el-input
            v-model="resetForm.password"
            type="password"
            placeholder="至少8位，必须包含字母和数字"
            show-password
            maxlength="50"
            clearable
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="resetDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="resetting" @click="handleResetPassword">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getAdminUserList,
  createAdminUser,
  updateAdminUser,
  updateAdminUserStatus,
  resetAdminUserPassword,
  deleteAdminUser
} from '../api'

const loading = ref(false)
const submitting = ref(false)
const editing = ref(false)
const resetting = ref(false)
const userList = ref([])
const currentAdminId = ref(null)

const createDialogVisible = ref(false)
const createFormRef = ref(null)
const createForm = reactive({
  username: '',
  password: '',
  role: 'editor',
  status: 1
})
const createRules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 3, max: 50, message: '用户名长度3-50个字符', trigger: 'blur' },
    {
      pattern: /^[a-zA-Z0-9_]+$/,
      message: '用户名只能包含字母、数字和下划线',
      trigger: 'blur'
    }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 8, max: 50, message: '密码长度至少8位', trigger: 'blur' },
    {
      validator: (_rule, value, callback) => {
        if (value && (!/[A-Za-z]/.test(value) || !/[0-9]/.test(value))) {
          callback(new Error('密码必须同时包含字母和数字'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ],
  role: [
    { required: true, message: '请选择角色', trigger: 'change' }
  ],
  status: [
    { required: true, message: '请选择状态', trigger: 'change' }
  ]
}

const editDialogVisible = ref(false)
const editFormRef = ref(null)
const editTarget = ref(null)
const editForm = reactive({
  username: '',
  role: 'editor',
  status: 1
})
const editRules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 3, max: 50, message: '用户名长度3-50个字符', trigger: 'blur' },
    {
      pattern: /^[a-zA-Z0-9_]+$/,
      message: '用户名只能包含字母、数字和下划线',
      trigger: 'blur'
    }
  ],
  role: [
    { required: true, message: '请选择角色', trigger: 'change' }
  ],
  status: [
    { required: true, message: '请选择状态', trigger: 'change' }
  ]
}

const resetDialogVisible = ref(false)
const resetFormRef = ref(null)
const resetTarget = ref(null)
const resetForm = reactive({
  password: ''
})
const resetRules = {
  password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 8, max: 50, message: '密码长度至少8位', trigger: 'blur' },
    {
      validator: (_rule, value, callback) => {
        if (value && (!/[A-Za-z]/.test(value) || !/[0-9]/.test(value))) {
          callback(new Error('密码必须同时包含字母和数字'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ]
}

const isSelf = (userId) => {
  return currentAdminId.value && String(userId) === String(currentAdminId.value)
}

const fetchList = async () => {
  loading.value = true
  try {
    const res = await getAdminUserList()
    userList.value = res.data.list
    const adminIdFromToken = localStorage.getItem('admin_id')
    if (adminIdFromToken) {
      currentAdminId.value = adminIdFromToken
    }
  } catch (error) {
    console.error('获取账号列表失败：', error)
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  createDialogVisible.value = true
}

const resetCreateForm = () => {
  createForm.username = ''
  createForm.password = ''
  createForm.role = 'editor'
  createForm.status = 1
  createFormRef.value?.clearValidate()
}

const handleCreate = async () => {
  if (!createFormRef.value) return
  await createFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      await createAdminUser(createForm)
      ElMessage.success('创建成功')
      createDialogVisible.value = false
      resetCreateForm()
      await fetchList()
    } catch (error) {
      console.error('创建失败：', error)
    } finally {
      submitting.value = false
    }
  })
}

const openEditDialog = (row) => {
  editTarget.value = row
  editForm.username = row.username
  editForm.role = row.role
  editForm.status = row.status == 1 ? 1 : 0
  editDialogVisible.value = true
}

const resetEditForm = () => {
  editForm.username = ''
  editForm.role = 'editor'
  editForm.status = 1
  editTarget.value = null
  editFormRef.value?.clearValidate()
}

const handleEdit = async () => {
  if (!editFormRef.value || !editTarget.value) return
  await editFormRef.value.validate(async (valid) => {
    if (!valid) return
    editing.value = true
    try {
      await updateAdminUser(editTarget.value.id, editForm)
      ElMessage.success('更新成功')
      editDialogVisible.value = false
      resetEditForm()
      await fetchList()
    } catch (error) {
      console.error('更新失败：', error)
    } finally {
      editing.value = false
    }
  })
}

const handleToggleStatus = async (row) => {
  const newStatus = row.status == 1 ? 0 : 1
  const action = newStatus == 1 ? '启用' : '禁用'

  try {
    await ElMessageBox.confirm(`确定要${action}账号「${row.username}」吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await updateAdminUserStatus(row.id, newStatus)
    ElMessage.success(`${action}成功`)
    await fetchList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error(`${action}失败：`, error)
    }
  }
}

const openResetPasswordDialog = (row) => {
  resetTarget.value = row
  resetDialogVisible.value = true
}

const resetResetForm = () => {
  resetForm.password = ''
  resetTarget.value = null
  resetFormRef.value?.clearValidate()
}

const handleResetPassword = async () => {
  if (!resetFormRef.value || !resetTarget.value) return
  await resetFormRef.value.validate(async (valid) => {
    if (!valid) return
    resetting.value = true
    try {
      await resetAdminUserPassword(resetTarget.value.id, resetForm.password)
      ElMessage.success('密码重置成功，用户所有登录会话已失效')
      resetDialogVisible.value = false
      resetResetForm()
    } catch (error) {
      console.error('密码重置失败：', error)
    } finally {
      resetting.value = false
    }
  })
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要删除账号「${row.username}」吗？删除后无法恢复！`, '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'error'
    })
    await deleteAdminUser(row.id)
    ElMessage.success('删除成功')
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
.admin-user-management :deep(.el-card) {
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

.admin-user-management :deep(.el-table) {
  border-radius: 8px;
}

.admin-user-management :deep(.el-table th.el-table__cell) {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  font-size: 13px;
}

.admin-user-management :deep(.el-button--primary) {
  background: #6366f1;
  border-color: #6366f1;
}

.admin-user-management :deep(.el-button--primary:hover) {
  background: #4f46e5;
  border-color: #4f46e5;
}

.form-tip {
  margin-top: 4px;
  font-size: 12px;
  color: #94a3b8;
}
</style>
