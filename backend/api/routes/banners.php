<?php
function getBannerList() {
    $page = intval($_GET['page'] ?? 1);
    $pageSize = intval($_GET['page_size'] ?? 10);
    $status = $_GET['status'] ?? '';
    $keyword = $_GET['keyword'] ?? '';

    $page = max(1, $page);
    $pageSize = min(100, max(1, $pageSize));
    $offset = ($page - 1) * $pageSize;

    try {
        $db = getDB();

        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = "status = ?";
            $params[] = $status;
        }

        if ($keyword !== '') {
            $where[] = "title LIKE ?";
            $params[] = "%{$keyword}%";
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM banner {$whereClause}");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        $stmt = $db->prepare("
            SELECT id, title, image_url, jump_type, jump_target, sort_order, status, start_time, end_time, created_at, updated_at
            FROM banner
            {$whereClause}
            ORDER BY sort_order ASC, id DESC
            LIMIT {$offset}, {$pageSize}
        ");
        $stmt->execute($params);
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['created_at'] = formatDateTime($item['created_at']);
            $item['updated_at'] = formatDateTime($item['updated_at']);
            if (!empty($item['start_time'])) {
                $item['start_time'] = formatDateTime($item['start_time']);
            }
            if (!empty($item['end_time'])) {
                $item['end_time'] = formatDateTime($item['end_time']);
            }
            $item['sort_order'] = intval($item['sort_order']);
            $item['status'] = intval($item['status']);
            if ($item['jump_type'] === 'video' && !empty($item['jump_target'])) {
                $videoStmt = $db->prepare("SELECT id, title, status FROM video WHERE id = ?");
                $videoStmt->execute([$item['jump_target']]);
                $video = $videoStmt->fetch();
                $item['video_info'] = $video ? [
                    'id' => intval($video['id']),
                    'title' => $video['title'],
                    'status' => intval($video['status'])
                ] : null;
            }
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

function getBannerDetail($id) {
    validateInt($id, '轮播图ID');

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, title, image_url, jump_type, jump_target, sort_order, status, start_time, end_time, created_at, updated_at
            FROM banner WHERE id = ?
        ");
        $stmt->execute([$id]);
        $banner = $stmt->fetch();

        if (!$banner) {
            error('轮播图不存在', 404);
        }

        $banner['created_at'] = formatDateTime($banner['created_at']);
        $banner['updated_at'] = formatDateTime($banner['updated_at']);
        if (!empty($banner['start_time'])) {
            $banner['start_time'] = formatDateTime($banner['start_time']);
        }
        if (!empty($banner['end_time'])) {
            $banner['end_time'] = formatDateTime($banner['end_time']);
        }
        $banner['sort_order'] = intval($banner['sort_order']);
        $banner['status'] = intval($banner['status']);

        success($banner);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function createBanner() {
    $title = $_POST['title'] ?? '';
    $imageUrl = $_POST['image_url'] ?? '';
    $jumpType = $_POST['jump_type'] ?? 'url';
    $jumpTarget = $_POST['jump_target'] ?? '';
    $sortOrder = $_POST['sort_order'] ?? 0;
    $status = $_POST['status'] ?? 1;
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';

    validateRequired([
        'title' => '轮播标题',
        'image_url' => '图片URL'
    ], ['title' => $title, 'image_url' => $imageUrl]);

    validateLength($title, 1, 200, '轮播标题');

    if (!in_array($jumpType, ['video', 'url'])) {
        error('跳转类型不合法');
    }

    if ($jumpType === 'url') {
        if (empty($jumpTarget)) {
            error('外链URL不能为空');
        }
        validateUrl($jumpTarget, '外链URL');
    }

    if ($jumpType === 'video') {
        if (empty($jumpTarget)) {
            error('请选择关联影片');
        }
        validateInt($jumpTarget, '关联影片ID');
        try {
            $db = getDB();
            $videoStmt = $db->prepare("SELECT id, status FROM video WHERE id = ?");
            $videoStmt->execute([$jumpTarget]);
            $video = $videoStmt->fetch();
            if (!$video) {
                error('关联影片不存在');
            }
            if (intval($video['status']) !== 1) {
                error('关联影片必须是上架状态');
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '关联影片') !== false) {
                throw $e;
            }
        }
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);
    $sortOrder = intval($sortOrder);

    if (!empty($startTime) && !empty($endTime)) {
        if (strtotime($startTime) >= strtotime($endTime)) {
            error('生效开始时间必须早于结束时间');
        }
    }

    try {
        $db = getDB();

        if ($sortOrder <= 0) {
            $maxStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) as max_sort FROM banner");
            $maxStmt->execute();
            $sortOrder = intval($maxStmt->fetch()['max_sort']) + 1;
        }

        $startTimeDb = empty($startTime) ? null : $startTime;
        $endTimeDb = empty($endTime) ? null : $endTime;

        $stmt = $db->prepare("
            INSERT INTO banner (title, image_url, jump_type, jump_target, sort_order, status, start_time, end_time, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$title, $imageUrl, $jumpType, $jumpTarget, $sortOrder, $status, $startTimeDb, $endTimeDb]);

        $bannerId = $db->lastInsertId();

        writeAuditLog('create', 'banner', $bannerId, [
            'title' => $title,
            'jump_type' => $jumpType,
            'status' => $status
        ]);

        success(['id' => $bannerId], '添加成功');

    } catch (Exception $e) {
        error('添加失败：' . $e->getMessage());
    }
}

function updateBanner($id) {
    validateInt($id, '轮播图ID');

    $title = $_POST['title'] ?? '';
    $imageUrl = $_POST['image_url'] ?? '';
    $jumpType = $_POST['jump_type'] ?? '';
    $jumpTarget = $_POST['jump_target'] ?? '';
    $sortOrder = $_POST['sort_order'] ?? 0;
    $status = $_POST['status'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';

    validateRequired([
        'title' => '轮播标题',
        'image_url' => '图片URL',
        'status' => '状态'
    ], ['title' => $title, 'image_url' => $imageUrl, 'status' => $status]);

    validateLength($title, 1, 200, '轮播标题');

    if (!in_array($jumpType, ['video', 'url'])) {
        error('跳转类型不合法');
    }

    if ($jumpType === 'url') {
        if (empty($jumpTarget)) {
            error('外链URL不能为空');
        }
        validateUrl($jumpTarget, '外链URL');
    }

    if ($jumpType === 'video') {
        if (empty($jumpTarget)) {
            error('请选择关联影片');
        }
        validateInt($jumpTarget, '关联影片ID');
        try {
            $db = getDB();
            $videoStmt = $db->prepare("SELECT id, status FROM video WHERE id = ?");
            $videoStmt->execute([$jumpTarget]);
            $video = $videoStmt->fetch();
            if (!$video) {
                error('关联影片不存在');
            }
            if (intval($video['status']) !== 1) {
                error('关联影片必须是上架状态');
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '关联影片') !== false) {
                throw $e;
            }
        }
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);
    $sortOrder = intval($sortOrder);

    if (!empty($startTime) && !empty($endTime)) {
        if (strtotime($startTime) >= strtotime($endTime)) {
            error('生效开始时间必须早于结束时间');
        }
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM banner WHERE id = ?");
        $stmt->execute([$id]);
        $oldBanner = $stmt->fetch();
        if (!$oldBanner) {
            error('轮播图不存在', 404);
        }

        $startTimeDb = empty($startTime) ? null : $startTime;
        $endTimeDb = empty($endTime) ? null : $endTime;

        $stmt = $db->prepare("
            UPDATE banner
            SET title = ?, image_url = ?, jump_type = ?, jump_target = ?, sort_order = ?, status = ?, start_time = ?, end_time = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$title, $imageUrl, $jumpType, $jumpTarget, $sortOrder, $status, $startTimeDb, $endTimeDb, $id]);

        writeAuditLog('update', 'banner', $id, [
            'old' => [
                'title' => $oldBanner['title'],
                'jump_type' => $oldBanner['jump_type'],
                'status' => intval($oldBanner['status'])
            ],
            'new' => [
                'title' => $title,
                'jump_type' => $jumpType,
                'status' => $status
            ]
        ]);

        success(null, '更新成功');

    } catch (Exception $e) {
        error('更新失败：' . $e->getMessage());
    }
}

function deleteBanner($id) {
    validateInt($id, '轮播图ID');

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM banner WHERE id = ?");
        $stmt->execute([$id]);
        $banner = $stmt->fetch();
        if (!$banner) {
            error('轮播图不存在', 404);
        }

        $stmt = $db->prepare("DELETE FROM banner WHERE id = ?");
        $stmt->execute([$id]);

        writeAuditLog('delete', 'banner', $id, [
            'title' => $banner['title'],
            'status' => intval($banner['status'])
        ]);

        success(null, '删除成功');

    } catch (Exception $e) {
        error('删除失败：' . $e->getMessage());
    }
}

function updateBannerStatus($id) {
    validateInt($id, '轮播图ID');

    $status = $_POST['status'] ?? '';

    if ($status === '') {
        error('状态不能为空');
    }

    if (!in_array($status, ['0', '1'])) {
        error('状态值不正确');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM banner WHERE id = ?");
        $stmt->execute([$id]);
        $banner = $stmt->fetch();
        if (!$banner) {
            error('轮播图不存在', 404);
        }

        $stmt = $db->prepare("UPDATE banner SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);

        $action = $status == 1 ? 'publish' : 'unpublish';
        writeAuditLog($action, 'banner', $id, [
            'title' => $banner['title'],
            'old_status' => intval($banner['status']),
            'new_status' => intval($status)
        ]);

        success(null, $status == 1 ? '启用成功' : '禁用成功');

    } catch (Exception $e) {
        error('操作失败：' . $e->getMessage());
    }
}

function updateBannerSort() {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $sortList = $input['sort_list'] ?? [];

    if (empty($sortList) || !is_array($sortList)) {
        error('排序数据不能为空');
    }

    try {
        $db = getDB();
        $db->beginTransaction();

        try {
            foreach ($sortList as $item) {
                if (!isset($item['id']) || !isset($item['sort_order'])) {
                    continue;
                }
                $bannerId = intval($item['id']);
                $sortOrder = intval($item['sort_order']);
                $stmt = $db->prepare("UPDATE banner SET sort_order = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$sortOrder, $bannerId]);
            }

            $db->commit();
            writeAuditLog('update', 'banner', null, [
                'action' => 'batch_sort',
                'count' => count($sortList)
            ]);
            success(null, '排序更新成功');

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        error('排序更新失败：' . $e->getMessage());
    }
}

function getVideoOptionsForBanner() {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, title, status
            FROM video
            WHERE status = 1
            ORDER BY id DESC
            LIMIT 500
        ");
        $stmt->execute();
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['id'] = intval($item['id']);
            $item['status'] = intval($item['status']);
        }

        success(['list' => $list]);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function handleBannerRequest($path, $method) {
    $parts = explode('/', $path);

    if ($method === 'GET' && $path === 'banners') {
        getBannerList();
    } elseif ($method === 'GET' && $path === 'banners/video-options') {
        getVideoOptionsForBanner();
    } elseif ($method === 'GET' && count($parts) === 2) {
        getBannerDetail($parts[1]);
    } elseif ($method === 'POST' && $path === 'banners') {
        createBanner();
    } elseif ($method === 'POST' && $path === 'banners/sort') {
        updateBannerSort();
    } elseif ($method === 'POST' && count($parts) === 2) {
        updateBanner($parts[1]);
    } elseif ($method === 'DELETE' && count($parts) === 2) {
        deleteBanner($parts[1]);
    } elseif ($method === 'POST' && count($parts) === 3 && $parts[2] === 'status') {
        updateBannerStatus($parts[1]);
    } else {
        error('接口不存在', 404);
    }
}
