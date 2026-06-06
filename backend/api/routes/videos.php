<?php
// 获取影片列表（管理后台）
function getVideoList() {
    $page = intval($_GET['page'] ?? 1);
    $pageSize = intval($_GET['page_size'] ?? 10);
    $status = $_GET['status'] ?? '';
    $keyword = $_GET['keyword'] ?? '';
    $categoryId = $_GET['category_id'] ?? '';

    $page = max(1, $page);
    $pageSize = min(100, max(1, $pageSize));
    $offset = ($page - 1) * $pageSize;

    try {
        $db = getDB();

        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = "v.status = ?";
            $params[] = $status;
        }

        if ($keyword !== '') {
            $where[] = "v.title LIKE ?";
            $params[] = "%{$keyword}%";
        }

        if ($categoryId !== '') {
            validateInt($categoryId, '分类ID');
            $where[] = "v.category_id = ?";
            $params[] = $categoryId;
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM video v {$whereClause}");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        $stmt = $db->prepare("
            SELECT v.id, v.category_id, v.title, v.cover_url, v.description, v.status,
                   v.created_at, v.updated_at, vc.name as category_name
            FROM video v
            LEFT JOIN video_category vc ON v.category_id = vc.id
            {$whereClause}
            ORDER BY v.id DESC
            LIMIT {$offset}, {$pageSize}
        ");
        $stmt->execute($params);
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['created_at'] = formatDateTime($item['created_at']);
            $item['updated_at'] = formatDateTime($item['updated_at']);
        }

        success([
            'list' => $list,
            'total' => intval($total),
            'page' => $page,
            'page_size' => $pageSize
        ]);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function getVideoDetail($id) {
    validateInt($id, '影片ID');

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT v.*, vc.name as category_name
            FROM video v
            LEFT JOIN video_category vc ON v.category_id = vc.id
            WHERE v.id = ?
        ");
        $stmt->execute([$id]);
        $video = $stmt->fetch();

        if (!$video) {
            error('影片不存在', 404);
        }

        $video['created_at'] = formatDateTime($video['created_at']);
        $video['updated_at'] = formatDateTime($video['updated_at']);

        success($video);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function createVideo() {
    $title = $_POST['title'] ?? '';
    $coverUrl = $_POST['cover_url'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 1;
    $categoryId = $_POST['category_id'] ?? '';

    validateRequired([
        'title' => '影片标题'
    ], ['title' => $title]);

    validateLength($title, 1, 200, '影片标题');

    if (!empty($description)) {
        validateLength($description, 0, 1000, '影片描述');
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);

    if ($categoryId !== '') {
        validateInt($categoryId, '分类ID');
        $categoryId = intval($categoryId);
    } else {
        $categoryId = null;
    }

    try {
        $db = getDB();

        if ($categoryId !== null) {
            $stmt = $db->prepare("SELECT id FROM video_category WHERE id = ? AND status = 1");
            $stmt->execute([$categoryId]);
            if (!$stmt->fetch()) {
                error('所选分类不存在或已禁用');
            }
        }

        $stmt = $db->prepare("
            INSERT INTO video (category_id, title, cover_url, description, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$categoryId, $title, $coverUrl, $description, $status]);

        $videoId = $db->lastInsertId();

        success(['id' => $videoId], '添加成功');

    } catch (Exception $e) {
        error('添加失败：' . $e->getMessage());
    }
}

function updateVideo($id) {
    validateInt($id, '影片ID');

    $title = $_POST['title'] ?? '';
    $coverUrl = $_POST['cover_url'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? '';
    $categoryId = $_POST['category_id'] ?? '';

    validateRequired([
        'title' => '影片标题',
        'status' => '状态'
    ], ['title' => $title, 'status' => $status]);

    validateLength($title, 1, 200, '影片标题');

    if (!empty($description)) {
        validateLength($description, 0, 1000, '影片描述');
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);

    if ($categoryId !== '') {
        validateInt($categoryId, '分类ID');
        $categoryId = intval($categoryId);
    } else {
        $categoryId = null;
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM video WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            error('影片不存在', 404);
        }

        if ($categoryId !== null) {
            $stmt = $db->prepare("SELECT id FROM video_category WHERE id = ? AND status = 1");
            $stmt->execute([$categoryId]);
            if (!$stmt->fetch()) {
                error('所选分类不存在或已禁用');
            }
        }

        $stmt = $db->prepare("
            UPDATE video
            SET category_id = ?, title = ?, cover_url = ?, description = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$categoryId, $title, $coverUrl, $description, $status, $id]);

        success(null, '更新成功');

    } catch (Exception $e) {
        error('更新失败：' . $e->getMessage());
    }
}

// 删除影片
function deleteVideo($id) {
    validateInt($id, '影片ID');

    try {
        $db = getDB();

        // 开启事务
        $db->beginTransaction();

        try {
            // 检查影片是否存在
            $stmt = $db->prepare("SELECT id FROM video WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                error('影片不存在', 404);
            }

            // 删除播放源
            $stmt = $db->prepare("DELETE FROM video_source WHERE video_id = ?");
            $stmt->execute([$id]);

            // 删除影片
            $stmt = $db->prepare("DELETE FROM video WHERE id = ?");
            $stmt->execute([$id]);

            // 提交事务
            $db->commit();

            success(null, '删除成功');
        } catch (Exception $e) {
            // 回滚事务
            $db->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        error('删除失败：' . $e->getMessage());
    }
}

// 更新影片状态（上下架）
function updateVideoStatus($id) {
    validateInt($id, '影片ID');

    $status = $_POST['status'] ?? '';

    if ($status === '') {
        error('状态不能为空');
    }

    if (!in_array($status, ['0', '1'])) {
        error('状态值不正确');
    }

    try {
        $db = getDB();

        // 检查影片是否存在
        $stmt = $db->prepare("SELECT id FROM video WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            error('影片不存在', 404);
        }

        // 更新状态
        $stmt = $db->prepare("UPDATE video SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);

        success(null, $status == 1 ? '上架成功' : '下架成功');

    } catch (Exception $e) {
        error('操作失败：' . $e->getMessage());
    }
}

// 处理影片请求
function handleVideoRequest($path, $method) {
    // 解析路径
    $parts = explode('/', $path);

    if ($method === 'GET' && $path === 'videos') {
        // 获取列表
        getVideoList();
    } elseif ($method === 'GET' && count($parts) === 2) {
        // 获取详情
        getVideoDetail($parts[1]);
    } elseif ($method === 'POST' && $path === 'videos') {
        // 新增
        createVideo();
    } elseif ($method === 'POST' && count($parts) === 2) {
        // 更新
        updateVideo($parts[1]);
    } elseif ($method === 'DELETE' && count($parts) === 2) {
        // 删除
        deleteVideo($parts[1]);
    } elseif ($method === 'POST' && count($parts) === 3 && $parts[2] === 'status') {
        // 更新状态
        updateVideoStatus($parts[1]);
    } else {
        error('接口不存在', 404);
    }
}
