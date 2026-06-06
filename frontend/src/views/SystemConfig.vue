<template>
  <div class="system-config">
    <el-card v-loading="loading">
      <template #header>
        <div class="card-header">
          <h3>系统配置</h3>
          <div>
            <el-button @click="handleReset">重置</el-button>
            <el-button type="primary" :loading="saving" @click="handleSave">
              <el-icon><Check /></el-icon>
              保存
            </el-button>
          </div>
        </div>
      </template>

      <el-alert
        v-if="!isSuper"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 20px;"
      >
        当前账号为编辑角色，部分安全相关配置不可见。如需修改请联系超级管理员。
      </el-alert>

      <div class="config-group" v-if="groupedConfigs.basic && groupedConfigs.basic.length">
        <div class="group-title">
          <el-icon><InfoFilled /></el-icon>
          <span>基础配置</span>
        </div>
        <el-form :model="formData" label-width="160px" class="config-form">
          <el-form-item
            v-for="item in groupedConfigs.basic"
            :key="item.config_key"
            :label="getItemLabel(item)"
          >
            <el-input
              v-if="item.value_type === 'string'"
              v-model="formData[item.config_key]"
              :placeholder="`请输入${item.description || item.config_key}`"
              clearable
              maxlength="200"
              show-word-limit
            />
            <el-input
              v-else-if="item.value_type === 'email'"
              v-model="formData[item.config_key]"
              type="email"
              :placeholder="`请输入${item.description || item.config_key}`"
              clearable
              maxlength="200"
            />
            <div class="form-item-meta">
              <span class="item-key">{{ item.config_key }}</span>
              <span class="item-updated">最后更新：{{ item.updated_at }}</span>
            </div>
          </el-form-item>
        </el-form>
      </div>

      <div class="config-group" v-if="groupedConfigs.list && groupedConfigs.list.length">
        <div class="group-title">
          <el-icon><List /></el-icon>
          <span>列表与排序配置</span>
        </div>
        <el-form :model="formData" label-width="160px" class="config-form">
          <el-form-item
            v-for="item in groupedConfigs.list"
            :key="item.config_key"
            :label="getItemLabel(item)"
          >
            <el-input-number
              v-if="item.value_type === 'number'"
              v-model="formData[item.config_key]"
              :min="1"
              :max="500"
              controls-position="right"
            />
            <el-switch
              v-else-if="item.value_type === 'boolean'"
              v-model="formData[item.config_key]"
              :active-value="1"
              :inactive-value="0"
              active-text="开启"
              inactive-text="关闭"
            />
            <div class="form-item-meta">
              <span class="item-key">{{ item.config_key }}</span>
              <span class="item-updated">最后更新：{{ item.updated_at }}</span>
            </div>
          </el-form-item>
        </el-form>
      </div>

      <div class="config-group" v-if="isSuper && groupedConfigs.security && groupedConfigs.security.length">
        <div class="group-title security-title">
          <el-icon><Lock /></el-icon>
          <span>安全配置</span>
          <el-tag type="danger" size="small" style="margin-left: 10px;">仅超级管理员可见</el-tag>
        </div>
        <el-form :model="formData" label-width="160px" class="config-form">
          <el-form-item
            v-for="item in groupedConfigs.security"
            :key="item.config_key"
            :label="getItemLabel(item)"
          >
            <el-input-number
              v-if="item.value_type === 'number'"
              v-model="formData[item.config_key]"
              :min="1"
              :max="100"
              controls-position="right"
            />
            <el-input
              v-else-if="item.value_type === 'string' || item.value_type === 'email'"
              v-model="formData[item.config_key]"
              :placeholder="`请输入${item.description || item.config_key}`"
              show-password
              clearable
              maxlength="200"
            />
            <el-switch
              v-else-if="item.value_type === 'boolean'"
              v-model="formData[item.config_key]"
              :active-value="1"
              :inactive-value="0"
              active-text="开启"
              inactive-text="关闭"
            />
            <div class="form-item-meta">
              <span class="item-key">{{ item.config_key }}</span>
              <span class="item-updated">最后更新：{{ item.updated_at }}</span>
            </div>
          </el-form-item>
        </el-form>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check, InfoFilled, List, Lock } from '@element-plus/icons-vue'
import { getSystemConfigList, batchUpdateSystemConfig } from '../api'
import { refreshSystemConfig } from '../utils/systemConfig'

const loading = ref(false)
const saving = ref(false)
const isSuper = ref(false)
const configList = ref([])
const originalData = ref({})
const formData = reactive({})

const labelMap = {
  site_name: '站点名称',
  support_email: '客服邮箱',
  default_page_size: '默认每页条数',
  enable_recommend_sort: '开启推荐排序',
  login_fail_lock_threshold: '登录失败锁定阈值'
}

const groupedConfigs = computed(() => {
  const groups = { basic: [], list: [], security: [] }
  configList.value.forEach(item => {
    if (groups[item.config_group]) {
      groups[item.config_group].push(item)
    }
  })
  return groups
})

const getItemLabel = (item) => {
  return labelMap[item.config_key] || item.description || item.config_key
}

const fetchConfig = async () => {
  loading.value = true
  try {
    const res = await getSystemConfigList()
    configList.value = res.data.list || []
    isSuper.value = !!res.data.is_super

    configList.value.forEach(item => {
      formData[item.config_key] = item.config_value
      originalData.value[item.config_key] = item.config_value
    })
  } catch (error) {
    console.error('获取系统配置失败：', error)
  } finally {
    loading.value = false
  }
}

const handleReset = () => {
  configList.value.forEach(item => {
    formData[item.config_key] = originalData.value[item.config_key]
  })
  ElMessage.info('已重置为最近保存的值')
}

const handleSave = async () => {
  const changedItems = []
  configList.value.forEach(item => {
    const key = item.config_key
    const original = originalData.value[key]
    const current = formData[key]
    if (String(original) !== String(current)) {
      changedItems.push({
        config_key: key,
        config_value: current
      })
    }
  })

  if (changedItems.length === 0) {
    ElMessage.info('没有需要保存的变更')
    return
  }

  try {
    await ElMessageBox.confirm(
      `确定要保存 ${changedItems.length} 项配置变更吗？`,
      '确认保存',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    saving.value = true
    const res = await batchUpdateSystemConfig(changedItems)
    ElMessage.success(res.message || '保存成功')

    changedItems.forEach(item => {
      originalData.value[item.config_key] = item.config_value
    })

    await Promise.all([fetchConfig(), refreshSystemConfig()])
  } catch (error) {
    if (error !== 'cancel') {
      console.error('保存失败：', error)
    }
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchConfig()
})
</script>

<style scoped>
.system-config :deep(.el-card) {
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

.config-group {
  margin-bottom: 28px;
  padding-bottom: 24px;
  border-bottom: 1px solid #f0f0f0;
}

.config-group:last-child {
  margin-bottom: 0;
  padding-bottom: 0;
  border-bottom: none;
}

.group-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 16px;
  padding-left: 4px;
  border-left: 3px solid #6366f1;
  height: 18px;
  line-height: 18px;
}

.group-title .el-icon {
  color: #6366f1;
}

.group-title.security-title {
  border-left-color: #ef4444;
}

.group-title.security-title .el-icon {
  color: #ef4444;
}

.config-form {
  padding-left: 12px;
}

.config-form :deep(.el-form-item) {
  margin-bottom: 22px;
}

.config-form :deep(.el-form-item__label) {
  font-weight: 500;
  color: #334155;
}

.form-item-meta {
  margin-top: 6px;
  display: flex;
  gap: 16px;
  font-size: 12px;
  color: #94a3b8;
}

.item-key {
  font-family: 'SF Mono', Monaco, Consolas, monospace;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
}

.system-config :deep(.el-button--primary) {
  background: #6366f1;
  border-color: #6366f1;
}

.system-config :deep(.el-button--primary:hover) {
  background: #4f46e5;
  border-color: #4f46e5;
}
</style>
