<template>
  <div class="video-episodes">
    <el-card>
      <template #header>
        <div class="card-header">
          <div>
            <h3>分集管理</h3>
            <p class="video-title">影片：{{ videoInfo.title }}</p>
          </div>
          <div>
            <el-button @click="handleBack">返回列表</el-button>
            <el-button type="success" plain @click="handleBatchImport">
              <el-icon><Upload /></el-icon>
              批量导入
            </el-button>
            <el-button type="primary" @click="handleAdd">
              <el-icon><Plus /></el-icon>
              新增分集
            </el-button>
          </div>
        </div>
      </template>

      <el-table :data="tableData" border stripe v-loading="loading">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="episode_no" label="集号" width="100">
          <template #default="{ row }">
            <el-tag type="primary">第{{ row.episode_no }}集</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="分集标题" min-width="200">
          <template #default="{ row }">
            <span v-if="row.title">{{ row.title }}</span>
            <span v-else class="text-muted">未设置</span>
          </template>
        </el-table-column>
        <el-table-column prop="m3u8_url" label="M3U8地址" min-width="300" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.m3u8_url">{{ row.m3u8_url }}</span>
            <span v-else class="text-muted">未设置</span>
          </template>
        </el-table-column>
        <el-table-column prop="duration_seconds" label="时长" width="120">
          <template #default="{ row }">
            <span v-if="row.duration_seconds">{{ formatDuration(row.duration_seconds) }}</span>
            <span v-else class="text-muted">未设置</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status == 1 ? 'success' : 'info'">
              {{ row.status == 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      :close-on-click-modal="false"
      @closed="handleDialogClosed"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="100px"
      >
        <el-form-item label="集号" prop="episode_no">
          <el-input-number
            v-model="form.episode_no"
            :min="1"
            :max="9999"
            controls-position="right"
            style="width: 200px"
          />
        </el-form-item>

        <el-form-item label="分集标题" prop="title">
          <el-input
            v-model="form.title"
            placeholder="请输入分集标题（选填）"
            maxlength="200"
            clearable
          />
        </el-form-item>

        <el-form-item label="M3U8地址" prop="m3u8_url">
          <el-input
            v-model="form.m3u8_url"
            placeholder="请输入M3U8播放地址（选填）"
            clearable
          />
        </el-form-item>

        <el-form-item label="时长(秒)" prop="duration_seconds">
          <el-input-number
            v-model="form.duration_seconds"
            :min="0"
            :max="99999"
            controls-position="right"
            style="width: 200px"
          />
          <span class="form-tip">例如：2700 秒 = 45 分钟</span>
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

    <el-dialog
      v-model="importDialogVisible"
      title="批量导入分集"
      width="700px"
      :close-on-click-modal="false"
      @closed="handleImportDialogClosed"
    >
      <el-alert
        type="info"
        :closable="false"
        show-icon
        class="import-tip"
      >
        <template #title>
          <div>每行一条数据，格式为：<b>集号,标题,M3U8地址,时长(秒),状态</b></div>
          <div>其中标题、M3U8地址、时长、状态均为选填，状态默认为1（启用），0为禁用</div>
          <div>示例：</div>
          <div>1,第1集 启程,https://cdn.example.com/ep1.m3u8,2700,1</div>
          <div>2,第2集 相遇,https://cdn.example.com/ep2.m3u8</div>
          <div>3</div>
        </template>
      </el-alert>

      <el-form label-width="100px">
        <el-form-item label="导入内容">
          <el-input
            v-model="importText"
            type="textarea"
            :rows="12"
            placeholder="请粘贴要导入的分集数据，每行一条..."
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="importDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="importLoading" @click="handleImportSubmit">
          开始导入
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Upload } from '@element-plus/icons-vue'
import {
  getEpisodeList,
  createEpisode,
  updateEpisode,
  deleteEpisode,
  batchImportEpisodes
} from '../api'

const router = useRouter()
const route = useRoute()
const formRef = ref(null)
const loading = ref(false)
const submitLoading = ref(false)
const importLoading = ref(false)
const dialogVisible = ref(false)
const importDialogVisible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const importText = ref('')

const tableData = ref([])
const videoInfo = ref({
  id: '',
  title: '',
  type: ''
})

const form = reactive({
  video_id: '',
  episode_no: 1,
  title: '',
  m3u8_url: '',
  duration_seconds: null,
  status: 1
})

const dialogTitle = computed(() => {
  return isEdit.value ? '编辑分集' : '新增分集'
})

const formatDuration = (seconds) => {
  if (!seconds && seconds !== 0) return ''
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const s = seconds % 60
  if (h > 0) {
    return `${h}时${m}分${s}秒`
  }
  if (m > 0) {
    return `${m}分${s}秒`
  }
  return `${s}秒`
}

const validateM3u8Url = (rule, value, callback) => {
  if (value) {
    const urlPattern = /^https?:\/\/.+/
    if (!urlPattern.test(value)) {
      callback(new Error('请输入有效的URL地址'))
    } else {
      callback()
    }
  } else {
    callback()
  }
}

const rules = {
  episode_no: [
    { required: true, message: '请输入集号', trigger: 'blur' },
    { type: 'number', min: 1, message: '集号必须大于0', trigger: 'blur' }
  ],
  title: [
    { max: 200, message: '标题长度不能超过200个字符', trigger: 'blur' }
  ],
  m3u8_url: [
    { validator: validateM3u8Url, trigger: 'blur' }
  ],
  status: [
    { required: true, message: '请选择状态', trigger: 'change' }
  ]
}

const fetchData = async () => {
  const videoId = route.params.id
  if (!videoId) {
    ElMessage.error('影片ID不存在')
    router.replace('/videos')
    return
  }

  loading.value = true
  try {
    const res = await getEpisodeList(videoId)
    const video = res.data.video
    if (video.type && video.type !== 'series') {
      ElMessage.warning('该影片不是剧集类型，无法管理分集')
      router.replace('/videos')
      return
    }
    videoInfo.value = video
    tableData.value = res.data.list
  } catch (error) {
    console.error('获取列表失败：', error)
  } finally {
    loading.value = false
  }
}

const handleBack = () => {
  router.push('/videos')
}

const handleAdd = () => {
  isEdit.value = false
  editId.value = null
  form.video_id = route.params.id
  form.episode_no = tableData.value.length > 0
    ? Math.max(...tableData.value.map(e => e.episode_no)) + 1
    : 1
  form.title = ''
  form.m3u8_url = ''
  form.duration_seconds = null
  form.status = 1
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  editId.value = row.id
  form.video_id = row.video_id
  form.episode_no = row.episode_no
  form.title = row.title || ''
  form.m3u8_url = row.m3u8_url || ''
  form.duration_seconds = row.duration_seconds || null
  form.status = row.status
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    submitLoading.value = true
    try {
      if (isEdit.value) {
        await updateEpisode(editId.value, form)
        ElMessage.success('更新成功')
      } else {
        await createEpisode(form)
        ElMessage.success('添加成功')
      }
      dialogVisible.value = false
      await new Promise(resolve => setTimeout(resolve, 300))
      await fetchData()
    } catch (error) {
      console.error('提交失败：', error)
    } finally {
      submitLoading.value = false
    }
  })
}

const handleDialogClosed = () => {
  if (formRef.value) {
    formRef.value.resetFields()
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要删除第${row.episode_no}集吗？`, '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'error'
    })

    await deleteEpisode(row.id)
    ElMessage.success('删除成功')
    fetchData()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败：', error)
    }
  }
}

const handleBatchImport = () => {
  importText.value = ''
  importDialogVisible.value = true
}

const handleImportDialogClosed = () => {
  importText.value = ''
}

const handleImportSubmit = async () => {
  if (!importText.value.trim()) {
    ElMessage.warning('请输入要导入的内容')
    return
  }

  const lines = importText.value.trim().split('\n').filter(line => line.trim())
  if (lines.length === 0) {
    ElMessage.warning('没有有效的导入数据')
    return
  }

  const episodes = []
  const parseErrors = []

  lines.forEach((line, index) => {
    const lineNo = index + 1
    const parts = line.split(',').map(p => p.trim())
    const [episodeNo, title, m3u8Url, duration, status] = parts

    if (!episodeNo) {
      parseErrors.push(`第${lineNo}行：集号不能为空`)
      return
    }

    const epNo = parseInt(episodeNo)
    if (isNaN(epNo) || epNo < 1) {
      parseErrors.push(`第${lineNo}行：集号必须是大于0的整数`)
      return
    }

    const ep = {
      episode_no: epNo,
      title: title || '',
      m3u8_url: m3u8Url || '',
    }

    if (duration !== undefined && duration !== '') {
      const dur = parseInt(duration)
      if (isNaN(dur) || dur < 0) {
        parseErrors.push(`第${lineNo}行：时长必须是非负整数`)
        return
      }
      ep.duration_seconds = dur
    }

    if (status !== undefined && status !== '') {
      const st = parseInt(status)
      if (st === 0 || st === 1) {
        ep.status = st
      }
    }

    episodes.push(ep)
  })

  if (parseErrors.length > 0) {
    ElMessage.error(parseErrors.slice(0, 5).join('\n'))
    return
  }

  importLoading.value = true
  try {
    const res = await batchImportEpisodes(route.params.id, episodes)
    const data = res.data
    const msg = `导入完成：成功${data.success_count}条，跳过${data.skip_count}条，失败${data.error_count}条`
    if (data.error_count > 0 && data.errors && data.errors.length > 0) {
      ElMessage.error(msg + '\n' + data.errors.slice(0, 3).join('\n'))
    } else {
      ElMessage.success(msg)
    }
    importDialogVisible.value = false
    await new Promise(resolve => setTimeout(resolve, 300))
    await fetchData()
  } catch (error) {
    console.error('导入失败：', error)
  } finally {
    importLoading.value = false
  }
}

watch(
  () => route.params.id,
  () => {
    fetchData()
  }
)

onMounted(() => {
  fetchData()
})
</script>

<style scoped>
.video-episodes :deep(.el-card) {
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

.video-title {
  margin: 5px 0 0 0;
  font-size: 14px;
  color: #94a3b8;
}

.video-episodes :deep(.el-table) {
  border-radius: 8px;
}

.video-episodes :deep(.el-table th.el-table__cell) {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  font-size: 13px;
}

.video-episodes :deep(.el-button--primary) {
  background: #6366f1;
  border-color: #6366f1;
}

.video-episodes :deep(.el-button--primary:hover) {
  background: #4f46e5;
  border-color: #4f46e5;
}

.video-episodes :deep(.el-dialog) {
  border-radius: 12px;
}

.form-tip {
  margin-left: 10px;
  font-size: 12px;
  color: #94a3b8;
}

.import-tip {
  margin-bottom: 16px;
}

.text-muted {
  color: #94a3b8;
  font-size: 13px;
}
</style>
