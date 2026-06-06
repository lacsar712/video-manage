<template>
	<div class="video-form">
		<el-card>
			<template #header>
				<div class="card-header">
					<h3>{{ isEdit ? '编辑影片' : '新增影片' }}</h3>
				</div>
			</template>

			<el-form ref="formRef" :model="form" :rules="rules" label-width="120px" style="max-width: 600px">
				<el-form-item label="影片标题" prop="title">
					<el-input
						v-model="form.title"
						placeholder="请输入影片标题（1-200个字符）"
						maxlength="200"
						show-word-limit
						clearable
					/>
				</el-form-item>

				<el-form-item label="所属分类" prop="category_id">
					<el-select
						v-model="form.category_id"
						placeholder="请选择分类（选填）"
						clearable
						style="width: 100%"
					>
						<el-option
							v-for="cat in categoryOptions"
							:key="cat.id"
							:label="cat.name"
							:value="cat.id"
						/>
					</el-select>
				</el-form-item>

				<el-form-item label="封面图片" prop="cover_url">
					<el-upload
						class="cover-uploader"
						:action="uploadAction"
						:headers="uploadHeaders"
						:show-file-list="false"
						:on-success="handleUploadSuccess"
						:on-error="handleUploadError"
						:before-upload="beforeUpload"
						accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
					>
						<img v-if="form.cover_url" :src="getCoverUrl(form.cover_url)" class="cover-image" />
						<el-icon v-else class="cover-uploader-icon"><Plus /></el-icon>
					</el-upload>
					<div class="upload-tip">支持 JPG、PNG、GIF、WebP 格式，文件大小不超过 5MB</div>
				</el-form-item>

				<el-form-item label="影片描述" prop="description">
					<el-input
						v-model="form.description"
						type="textarea"
						:rows="4"
						placeholder="请输入影片描述（选填，最多1000个字符）"
						maxlength="1000"
						show-word-limit
						clearable
					/>
				</el-form-item>

				<el-form-item label="影片类型" prop="type">
					<el-radio-group v-model="form.type" size="large" @change="handleTypeChange">
						<el-radio label="movie" border>电影</el-radio>
						<el-radio label="series" border>剧集</el-radio>
					</el-radio-group>
					<div v-if="typeWarningVisible" class="type-warning">
						<el-alert
							type="warning"
							:closable="false"
							title="切换为电影类型后，「分集管理」入口将被隐藏，已有的分集数据不会被自动删除。"
							show-icon
						/>
					</div>
				</el-form-item>

				<el-form-item label="状态" prop="status">
					<el-radio-group v-model="form.status" size="large">
						<el-radio :label="1" border>上架</el-radio>
						<el-radio :label="0" border>下架</el-radio>
					</el-radio-group>
				</el-form-item>

				<el-divider content-position="left">参演演员</el-divider>

				<el-form-item label="参演演员">
					<div class="actor-select-wrapper">
						<el-select
							v-model="selectedActorIds"
							multiple
							filterable
							placeholder="请选择演员（支持搜索）"
							style="width: 100%"
							@change="handleActorSelectChange"
						>
							<el-option
								v-for="actor in actorOptions"
								:key="actor.id"
								:label="actor.name"
								:value="actor.id"
							>
								<div class="actor-option">
									<img
										v-if="actor.avatar_url"
										:src="getAvatarUrl(actor.avatar_url)"
										class="actor-option-avatar"
										@error="handleOptionImageError"
									/>
									<div v-else class="actor-option-avatar placeholder">
										<el-icon :size="14"><UserFilled /></el-icon>
									</div>
									<span>{{ actor.name }}</span>
								</div>
							</el-option>
						</el-select>
					</div>
				</el-form-item>

				<el-form-item v-if="selectedActors.length > 0" label="角色设置">
					<div class="actor-role-list">
						<div
							v-for="(actor, index) in selectedActors"
							:key="actor.id"
							class="actor-role-item"
						>
							<div class="actor-role-info">
								<img
									v-if="actor.avatar_url"
									:src="getAvatarUrl(actor.avatar_url)"
									class="actor-role-avatar"
									@error="handleActorAvatarError"
								/>
								<div v-else class="actor-role-avatar placeholder">
									<el-icon :size="18"><UserFilled /></el-icon>
								</div>
								<span class="actor-role-name">{{ actor.name }}</span>
							</div>
							<el-input
								v-model="actor.role_name"
								placeholder="请输入角色名（选填）"
								size="small"
								style="width: 240px"
								maxlength="100"
								clearable
							/>
							<el-button
								type="danger"
								text
								size="small"
								@click="removeActor(index)"
							>
								<el-icon><Close /></el-icon>
								移除
							</el-button>
						</div>
					</div>
				</el-form-item>

				<el-form-item>
					<el-button type="primary" :loading="loading" @click="handleSubmit">
						{{ isEdit ? '保存' : '提交' }}
					</el-button>
					<el-button @click="handleCancel">取消</el-button>
				</el-form-item>
			</el-form>
		</el-card>
	</div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus, UserFilled, Close } from '@element-plus/icons-vue'
import { getVideoDetail, createVideo, updateVideo, getCategoryList, getActorOptions } from '../api'

const router = useRouter()
const route = useRoute()
const formRef = ref(null)
const loading = ref(false)
const isEdit = ref(false)
const categoryOptions = ref([])
const typeWarningVisible = ref(false)
const actorOptions = ref([])
const selectedActorIds = ref([])
const selectedActors = ref([])

const uploadAction = computed(() => {
	const baseURL = import.meta.env.VITE_API_BASE_URL || ''
	return baseURL ? `${baseURL}/api/upload/cover` : '/api/upload/cover'
})

const uploadHeaders = computed(() => {
	const token = localStorage.getItem('token')
	return token ? { Authorization: `Bearer ${token}` } : {}
})

const form = reactive({
	title: '',
	category_id: null,
	cover_url: '',
	description: '',
	type: 'movie',
	status: 1,
})

const fetchCategories = async () => {
	try {
		const res = await getCategoryList({ status: 1 })
		categoryOptions.value = res.data.list
	} catch (error) {
		console.error('获取分类列表失败：', error)
	}
}

const fetchActorOptions = async () => {
	try {
		const res = await getActorOptions()
		actorOptions.value = res.data.list
	} catch (error) {
		console.error('获取演员列表失败：', error)
	}
}

// 获取封面完整URL
const getCoverUrl = (url) => {
	if (!url) return ''
	// 如果是完整URL，直接返回
	if (url.startsWith('http://') || url.startsWith('https://')) {
		return url
	}
	// 如果是相对路径，拼接API基础URL
	const baseURL = import.meta.env.VITE_API_BASE_URL || ''
	return baseURL ? `${baseURL}${url}` : url
}

const getAvatarUrl = (url) => {
	return getCoverUrl(url)
}

// 上传前验证
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

// 上传成功
const handleUploadSuccess = (response) => {
	if (response.code === 0) {
		form.cover_url = response.data.url
		ElMessage.success('上传成功')
	} else {
		ElMessage.error(response.message || '上传失败')
	}
}

// 上传失败
const handleUploadError = (error) => {
	console.error('上传失败：', error)
	ElMessage.error('上传失败，请重试')
}

const handleOptionImageError = (e) => {
	e.target.style.display = 'none'
}

const handleActorAvatarError = (e) => {
	e.target.style.display = 'none'
}

const handleActorSelectChange = (ids) => {
	const newSelectedActors = []
	for (const id of ids) {
		const existing = selectedActors.value.find(a => a.id === id)
		if (existing) {
			newSelectedActors.push(existing)
		} else {
			const option = actorOptions.value.find(a => a.id === id)
			if (option) {
				newSelectedActors.push({
					id: option.id,
					name: option.name,
					avatar_url: option.avatar_url || '',
					role_name: ''
				})
			}
		}
	}
	selectedActors.value = newSelectedActors
}

const removeActor = (index) => {
	const removedId = selectedActors.value[index].id
	selectedActors.value.splice(index, 1)
	selectedActorIds.value = selectedActorIds.value.filter(id => id !== removedId)
}

const rules = {
	title: [
		{ required: true, message: '请输入影片标题', trigger: 'blur' },
		{ min: 1, max: 200, message: '标题长度必须在1-200个字符之间', trigger: 'blur' },
	],
	type: [{ required: true, message: '请选择影片类型', trigger: 'change' }],
	cover_url: [{ required: true, message: '请上传影片封面', trigger: 'change' }],
	description: [{ max: 1000, message: '描述最多1000个字符', trigger: 'blur' }],
	status: [{ required: true, message: '请选择状态', trigger: 'change' }],
}

const handleTypeChange = (val) => {
	if (val === 'movie' && isEdit.value) {
		typeWarningVisible.value = true
	} else {
		typeWarningVisible.value = false
	}
}

const fetchDetail = async () => {
	const id = route.params.id
	if (!id) return

	loading.value = true
	try {
		const res = await getVideoDetail(id)
		const data = res.data
		data.status = parseInt(data.status)
		data.category_id = data.category_id ? parseInt(data.category_id) : null
		data.type = data.type || 'movie'
		Object.assign(form, data)

		if (data.actors && Array.isArray(data.actors)) {
			selectedActors.value = data.actors.map(a => ({
				id: a.id,
				name: a.name,
				avatar_url: a.avatar_url || '',
				role_name: a.role_name || ''
			}))
			selectedActorIds.value = data.actors.map(a => a.id)
		}
	} catch (error) {
		console.error('获取详情失败：', error)
		ElMessage.error('获取影片信息失败')
		router.back()
	} finally {
		loading.value = false
	}
}

const handleSubmit = async () => {
	if (!formRef.value) return

	await formRef.value.validate(async (valid) => {
		if (!valid) return

		loading.value = true
		try {
			const submitData = {
				...form,
				actors: JSON.stringify(selectedActors.value.map(a => ({
					actor_id: a.id,
					role_name: a.role_name || ''
				})))
			}

			if (isEdit.value) {
				await updateVideo(route.params.id, submitData)
				ElMessage.success('更新成功')
			} else {
				await createVideo(submitData)
				ElMessage.success('添加成功')
			}
			router.push('/videos')
		} catch (error) {
			console.error('提交失败：', error)
		} finally {
			loading.value = false
		}
	})
}

const handleCancel = () => {
	router.back()
}

onMounted(() => {
	fetchCategories()
	fetchActorOptions()
	isEdit.value = !!route.params.id
	if (isEdit.value) {
		fetchDetail()
	}
})
</script>

<style scoped>
.video-form :deep(.el-card) {
	border-radius: 12px;
	border: 1px solid #f0f0f0;
}

.card-header h3 {
	margin: 0;
	font-size: 18px;
	font-weight: 600;
	color: #1e293b;
}

.cover-uploader :deep(.el-upload) {
	border: 2px dashed #e2e8f0;
	border-radius: 10px;
	cursor: pointer;
	position: relative;
	overflow: hidden;
	transition: all 0.2s;
	width: 360px;
	height: 202px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: #f8fafc;
}

.cover-uploader :deep(.el-upload:hover) {
	border-color: #6366f1;
	background: #f0f0ff;
}

.cover-uploader-icon {
	font-size: 28px;
	color: #94a3b8;
	width: 360px;
	height: 202px;
	text-align: center;
	line-height: 202px;
}

.cover-image {
	width: 360px;
	height: 202px;
	display: block;
	object-fit: cover;
}

.upload-tip {
	margin-top: 8px;
	font-size: 12px;
	color: #94a3b8;
	line-height: 1.5;
}

.video-form :deep(.el-button--primary) {
	background: #6366f1;
	border-color: #6366f1;
}

.video-form :deep(.el-button--primary:hover) {
	background: #4f46e5;
	border-color: #4f46e5;
}

.type-warning {
	margin-top: 10px;
}

.actor-option {
	display: flex;
	align-items: center;
	gap: 8px;
}

.actor-option-avatar {
	width: 24px;
	height: 24px;
	border-radius: 50%;
	object-fit: cover;
	display: flex;
	align-items: center;
	justify-content: center;
	background: #f0f0ff;
	color: #94a3b8;
	flex-shrink: 0;
}

.actor-role-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
	width: 100%;
}

.actor-role-item {
	display: flex;
	align-items: center;
	gap: 16px;
	padding: 12px 16px;
	background: #f8fafc;
	border-radius: 8px;
	border: 1px solid #e2e8f0;
}

.actor-role-info {
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 180px;
}

.actor-role-avatar {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	object-fit: cover;
	display: flex;
	align-items: center;
	justify-content: center;
	background: #f0f0ff;
	color: #94a3b8;
	flex-shrink: 0;
	border: 2px solid #fff;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.actor-role-name {
	font-size: 14px;
	font-weight: 500;
	color: #1e293b;
}

.video-form :deep(.el-divider__text) {
	color: #475569;
	font-weight: 600;
	font-size: 14px;
	background: #fff;
	padding: 0 12px;
}
</style>
