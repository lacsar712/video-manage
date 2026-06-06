<?php
// 获取影片列表（管理后台）
function getVideoList() {
    $page = intval($_GET['page'] ?? 1);
    $pageSize = intval($_GET['page_size'] ?? 10);
    $status = $_GET['status'] ?? '';
    $keyword = $_GET['keyword'] ?? '';
    $categoryId = $_GET['category_id'] ?? '';
    $actorId = $_GET['actor_id'] ?? '';

    $page = max(1, $page);
    $pageSize = min(100, max(1, $pageSize));
    $offset = ($page - 1) * $pageSize;

    try {
        $db = getDB();

        $where = [];
        $params = [];
        $joinClause = '';

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

        if ($actorId !== '') {
            validateInt($actorId, '演员ID');
            $joinClause = "INNER JOIN video_actor va ON v.id = va.video_id";
            $where[] = "va.actor_id = ?";
            $params[] = $actorId;
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("SELECT COUNT(DISTINCT v.id) as total FROM video v {$joinClause} {$whereClause}");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        $stmt = $db->prepare("
            SELECT DISTINCT v.id, v.category_id, v.title, v.cover_url, v.description, v.type, v.status,
                   v.created_at, v.updated_at, vc.name as category_name
            FROM video v
            LEFT JOIN video_category vc ON v.category_id = vc.id
            {$joinClause}
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

        $stmt = $db->prepare("
            SELECT a.id, a.name, a.avatar_url, va.role_name, va.sort_order
            FROM video_actor va
            INNER JOIN actor a ON va.actor_id = a.id
            WHERE va.video_id = ?
            ORDER BY va.sort_order ASC, va.id ASC
        ");
        $stmt->execute([$id]);
        $actors = $stmt->fetchAll();
        $video['actors'] = $actors;

        success($video);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function saveVideoActors($db, $videoId, $actorsJson) {
    if ($actorsJson === null || $actorsJson === '') {
        return;
    }
    $actors = json_decode($actorsJson, true);
    if (!is_array($actors)) {
        return;
    }

    $stmt = $db->prepare("DELETE FROM video_actor WHERE video_id = ?");
    $stmt->execute([$videoId]);

    $sortOrder = 0;
    foreach ($actors as $actor) {
        $actorId = isset($actor['actor_id']) ? intval($actor['actor_id']) : (isset($actor['id']) ? intval($actor['id']) : 0);
        $roleName = isset($actor['role_name']) ? trim($actor['role_name']) : '';
        if ($actorId <= 0) {
            continue;
        }

        $stmt = $db->prepare("
            INSERT INTO video_actor (video_id, actor_id, role_name, sort_order, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), sort_order = VALUES(sort_order)
        ");
        $stmt->execute([$videoId, $actorId, $roleName, $sortOrder]);
        $sortOrder++;
    }
}

function createVideo() {
    $title = $_POST['title'] ?? '';
    $coverUrl = $_POST['cover_url'] ?? '';
    $description = $_POST['description'] ?? '';
    $type = $_POST['type'] ?? 'movie';
    $status = $_POST['status'] ?? 1;
    $categoryId = $_POST['category_id'] ?? '';
    $actors = $_POST['actors'] ?? null;

    validateRequired([
        'title' => '影片标题'
    ], ['title' => $title]);

    validateLength($title, 1, 200, '影片标题');

    if (!empty($description)) {
        validateLength($description, 0, 1000, '影片描述');
    }

    if (!in_array($type, ['movie', 'series'])) {
        error('类型值必须为 movie 或 series');
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

        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO video (category_id, title, cover_url, description, type, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$categoryId, $title, $coverUrl, $description, $type, $status]);

        $videoId = $db->lastInsertId();

        saveVideoActors($db, $videoId, $actors);

        writeAuditLog('create', 'video', $videoId, [
            'title' => $title,
            'category_id' => $categoryId,
            'type' => $type,
            'status' => $status
        ]);

        $db->commit();

        success(['id' => $videoId], '添加成功');

    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error('添加失败：' . $e->getMessage());
    }
}

function updateVideo($id) {
    validateInt($id, '影片ID');

    $title = $_POST['title'] ?? '';
    $coverUrl = $_POST['cover_url'] ?? '';
    $description = $_POST['description'] ?? '';
    $type = $_POST['type'] ?? '';
    $status = $_POST['status'] ?? '';
    $categoryId = $_POST['category_id'] ?? '';
    $actors = $_POST['actors'] ?? null;

    validateRequired([
        'title' => '影片标题',
        'status' => '状态'
    ], ['title' => $title, 'status' => $status]);

    validateLength($title, 1, 200, '影片标题');

    if (!empty($description)) {
        validateLength($description, 0, 1000, '影片描述');
    }

    if ($type !== '' && !in_array($type, ['movie', 'series'])) {
        error('类型值必须为 movie 或 series');
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

        $stmt = $db->prepare("SELECT * FROM video WHERE id = ?");
        $stmt->execute([$id]);
        $oldVideo = $stmt->fetch();
        if (!$oldVideo) {
            error('影片不存在', 404);
        }

        if ($categoryId !== null) {
            $stmt = $db->prepare("SELECT id FROM video_category WHERE id = ? AND status = 1");
            $stmt->execute([$categoryId]);
            if (!$stmt->fetch()) {
                error('所选分类不存在或已禁用');
            }
        }

        if ($type === '') {
            $type = $oldVideo['type'];
        }

        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE video
            SET category_id = ?, title = ?, cover_url = ?, description = ?, type = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$categoryId, $title, $coverUrl, $description, $type, $status, $id]);

        saveVideoActors($db, $id, $actors);

        writeAuditLog('update', 'video', $id, [
            'old' => [
                'title' => $oldVideo['title'],
                'category_id' => $oldVideo['category_id'],
                'cover_url' => $oldVideo['cover_url'],
                'description' => $oldVideo['description'],
                'type' => $oldVideo['type'],
                'status' => intval($oldVideo['status'])
            ],
            'new' => [
                'title' => $title,
                'category_id' => $categoryId,
                'cover_url' => $coverUrl,
                'description' => $description,
                'type' => $type,
                'status' => $status
            ]
        ]);

        $db->commit();

        success(null, '更新成功');

    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
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
            $stmt = $db->prepare("SELECT * FROM video WHERE id = ?");
            $stmt->execute([$id]);
            $video = $stmt->fetch();
            if (!$video) {
                error('影片不存在', 404);
            }

            // 删除播放源
            $stmt = $db->prepare("DELETE FROM video_source WHERE video_id = ?");
            $stmt->execute([$id]);

            // 删除分集
            $stmt = $db->prepare("DELETE FROM video_episode WHERE video_id = ?");
            $stmt->execute([$id]);

            // 删除演员关联
            $stmt = $db->prepare("DELETE FROM video_actor WHERE video_id = ?");
            $stmt->execute([$id]);

            // 删除影片
            $stmt = $db->prepare("DELETE FROM video WHERE id = ?");
            $stmt->execute([$id]);

            writeAuditLog('delete', 'video', $id, [
                'title' => $video['title'],
                'category_id' => $video['category_id'],
                'status' => intval($video['status'])
            ]);

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
        $stmt = $db->prepare("SELECT * FROM video WHERE id = ?");
        $stmt->execute([$id]);
        $video = $stmt->fetch();
        if (!$video) {
            error('影片不存在', 404);
        }

        // 更新状态
        $stmt = $db->prepare("UPDATE video SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);

        $action = $status == 1 ? 'publish' : 'unpublish';
        writeAuditLog($action, 'video', $id, [
            'title' => $video['title'],
            'old_status' => intval($video['status']),
            'new_status' => intval($status)
        ]);

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
