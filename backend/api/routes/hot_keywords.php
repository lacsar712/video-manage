<?php
function getHotKeywordList() {
    $page = intval($_GET['page'] ?? 1);
    $pageSize = intval($_GET['page_size'] ?? 20);
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
            $where[] = "keyword LIKE ?";
            $params[] = "%{$keyword}%";
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM hot_keyword {$whereClause}");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        $stmt = $db->prepare("
            SELECT id, keyword, sort_order, status, click_count, created_at, updated_at
            FROM hot_keyword
            {$whereClause}
            ORDER BY sort_order ASC, id DESC
            LIMIT {$offset}, {$pageSize}
        ");
        $stmt->execute($params);
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['id'] = intval($item['id']);
            $item['sort_order'] = intval($item['sort_order']);
            $item['status'] = intval($item['status']);
            $item['click_count'] = intval($item['click_count']);
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

function getEnabledHotKeywords() {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, keyword, sort_order, click_count
            FROM hot_keyword
            WHERE status = 1
            ORDER BY sort_order ASC, click_count DESC
            LIMIT 50
        ");
        $stmt->execute();
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['id'] = intval($item['id']);
            $item['sort_order'] = intval($item['sort_order']);
            $item['click_count'] = intval($item['click_count']);
        }

        success(['list' => $list]);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function getHotKeywordDetail($id) {
    validateInt($id, '热搜词ID');

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, keyword, sort_order, status, click_count, created_at, updated_at
            FROM hot_keyword WHERE id = ?
        ");
        $stmt->execute([$id]);
        $keyword = $stmt->fetch();

        if (!$keyword) {
            error('热搜词不存在', 404);
        }

        $keyword['id'] = intval($keyword['id']);
        $keyword['sort_order'] = intval($keyword['sort_order']);
        $keyword['status'] = intval($keyword['status']);
        $keyword['click_count'] = intval($keyword['click_count']);
        $keyword['created_at'] = formatDateTime($keyword['created_at']);
        $keyword['updated_at'] = formatDateTime($keyword['updated_at']);

        success($keyword);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function createHotKeyword() {
    $keyword = $_POST['keyword'] ?? '';
    $sortOrder = $_POST['sort_order'] ?? 0;
    $status = $_POST['status'] ?? 1;
    $clickCount = $_POST['click_count'] ?? 0;

    validateRequired(['keyword' => '关键词'], ['keyword' => $keyword]);
    validateLength($keyword, 1, 100, '关键词');

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);
    $sortOrder = intval($sortOrder);
    $clickCount = max(0, intval($clickCount));

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM hot_keyword WHERE keyword = ?");
        $stmt->execute([$keyword]);
        if ($stmt->fetch()) {
            error('该关键词已存在');
        }

        if ($sortOrder <= 0) {
            $maxStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) as max_sort FROM hot_keyword");
            $maxStmt->execute();
            $sortOrder = intval($maxStmt->fetch()['max_sort']) + 1;
        }

        $stmt = $db->prepare("
            INSERT INTO hot_keyword (keyword, sort_order, status, click_count, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$keyword, $sortOrder, $status, $clickCount]);

        $keywordId = $db->lastInsertId();

        writeAuditLog('create', 'hot_keyword', $keywordId, [
            'keyword' => $keyword,
            'status' => $status
        ]);

        success(['id' => $keywordId], '添加成功');

    } catch (Exception $e) {
        error('添加失败：' . $e->getMessage());
    }
}

function updateHotKeyword($id) {
    validateInt($id, '热搜词ID');

    $keyword = $_POST['keyword'] ?? '';
    $sortOrder = $_POST['sort_order'] ?? 0;
    $status = $_POST['status'] ?? '';
    $clickCount = $_POST['click_count'] ?? null;

    validateRequired([
        'keyword' => '关键词',
        'status' => '状态'
    ], ['keyword' => $keyword, 'status' => $status]);

    validateLength($keyword, 1, 100, '关键词');

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);
    $sortOrder = intval($sortOrder);

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM hot_keyword WHERE id = ?");
        $stmt->execute([$id]);
        $oldKeyword = $stmt->fetch();
        if (!$oldKeyword) {
            error('热搜词不存在', 404);
        }

        $stmt = $db->prepare("SELECT id FROM hot_keyword WHERE keyword = ? AND id != ?");
        $stmt->execute([$keyword, $id]);
        if ($stmt->fetch()) {
            error('该关键词已存在');
        }

        if ($clickCount !== null && $clickCount !== '') {
            $clickCount = max(0, intval($clickCount));
            $stmt = $db->prepare("
                UPDATE hot_keyword
                SET keyword = ?, sort_order = ?, status = ?, click_count = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$keyword, $sortOrder, $status, $clickCount, $id]);
        } else {
            $stmt = $db->prepare("
                UPDATE hot_keyword
                SET keyword = ?, sort_order = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$keyword, $sortOrder, $status, $id]);
        }

        writeAuditLog('update', 'hot_keyword', $id, [
            'old' => [
                'keyword' => $oldKeyword['keyword'],
                'status' => intval($oldKeyword['status'])
            ],
            'new' => [
                'keyword' => $keyword,
                'status' => $status
            ]
        ]);

        success(null, '更新成功');

    } catch (Exception $e) {
        error('更新失败：' . $e->getMessage());
    }
}

function deleteHotKeyword($id) {
    validateInt($id, '热搜词ID');

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM hot_keyword WHERE id = ?");
        $stmt->execute([$id]);
        $keyword = $stmt->fetch();
        if (!$keyword) {
            error('热搜词不存在', 404);
        }

        $stmt = $db->prepare("DELETE FROM hot_keyword WHERE id = ?");
        $stmt->execute([$id]);

        writeAuditLog('delete', 'hot_keyword', $id, [
            'keyword' => $keyword['keyword'],
            'status' => intval($keyword['status'])
        ]);

        success(null, '删除成功');

    } catch (Exception $e) {
        error('删除失败：' . $e->getMessage());
    }
}

function updateHotKeywordStatus($id) {
    validateInt($id, '热搜词ID');

    $status = $_POST['status'] ?? '';

    if ($status === '') {
        error('状态不能为空');
    }

    if (!in_array($status, ['0', '1'])) {
        error('状态值不正确');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM hot_keyword WHERE id = ?");
        $stmt->execute([$id]);
        $keyword = $stmt->fetch();
        if (!$keyword) {
            error('热搜词不存在', 404);
        }

        $stmt = $db->prepare("UPDATE hot_keyword SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);

        $action = $status == 1 ? 'publish' : 'unpublish';
        writeAuditLog($action, 'hot_keyword', $id, [
            'keyword' => $keyword['keyword'],
            'old_status' => intval($keyword['status']),
            'new_status' => intval($status)
        ]);

        success(null, $status == 1 ? '启用成功' : '禁用成功');

    } catch (Exception $e) {
        error('操作失败：' . $e->getMessage());
    }
}

function updateHotKeywordSort() {
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
                $keywordId = intval($item['id']);
                $sortOrder = intval($item['sort_order']);
                $stmt = $db->prepare("UPDATE hot_keyword SET sort_order = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$sortOrder, $keywordId]);
            }

            $db->commit();
            writeAuditLog('update', 'hot_keyword', null, [
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

function updateHotKeywordClickCount($id) {
    validateInt($id, '热搜词ID');

    $clickCount = $_POST['click_count'] ?? '';

    if ($clickCount === '') {
        error('点击次数不能为空');
    }
    if (!is_numeric($clickCount)) {
        error('点击次数必须是数字');
    }
    $clickCount = max(0, intval($clickCount));

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM hot_keyword WHERE id = ?");
        $stmt->execute([$id]);
        $keyword = $stmt->fetch();
        if (!$keyword) {
            error('热搜词不存在', 404);
        }

        $stmt = $db->prepare("UPDATE hot_keyword SET click_count = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$clickCount, $id]);

        writeAuditLog('update', 'hot_keyword', $id, [
            'action' => 'update_click_count',
            'keyword' => $keyword['keyword'],
            'old_click_count' => intval($keyword['click_count']),
            'new_click_count' => $clickCount
        ]);

        success(null, '点击次数更新成功');

    } catch (Exception $e) {
        error('操作失败：' . $e->getMessage());
    }
}

function syncHotKeywordStats() {
    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id, keyword, click_count FROM hot_keyword WHERE status = 1");
        $stmt->execute();
        $keywords = $stmt->fetchAll();

        $db->beginTransaction();

        try {
            $syncCount = 0;
            foreach ($keywords as $kw) {
                $increase = rand(50, 500);
                $newClickCount = intval($kw['click_count']) + $increase;

                $stmt = $db->prepare("UPDATE hot_keyword SET click_count = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newClickCount, $kw['id']]);
                $syncCount++;
            }

            $db->commit();

            writeAuditLog('update', 'hot_keyword', null, [
                'action' => 'sync_stats',
                'synced_count' => $syncCount
            ]);

            success(['synced_count' => $syncCount], '统计同步成功');

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        error('统计同步失败：' . $e->getMessage());
    }
}

function handleHotKeywordRequest($path, $method) {
    $parts = explode('/', $path);

    if ($method === 'GET' && $path === 'hot-keywords') {
        getHotKeywordList();
    } elseif ($method === 'GET' && $path === 'hot-keywords/enabled') {
        getEnabledHotKeywords();
    } elseif ($method === 'GET' && count($parts) === 2) {
        getHotKeywordDetail($parts[1]);
    } elseif ($method === 'POST' && $path === 'hot-keywords') {
        createHotKeyword();
    } elseif ($method === 'POST' && $path === 'hot-keywords/sort') {
        updateHotKeywordSort();
    } elseif ($method === 'POST' && $path === 'hot-keywords/sync-stats') {
        syncHotKeywordStats();
    } elseif ($method === 'POST' && count($parts) === 2) {
        updateHotKeyword($parts[1]);
    } elseif ($method === 'POST' && count($parts) === 3 && $parts[2] === 'status') {
        updateHotKeywordStatus($parts[1]);
    } elseif ($method === 'POST' && count($parts) === 3 && $parts[2] === 'click-count') {
        updateHotKeywordClickCount($parts[1]);
    } elseif ($method === 'DELETE' && count($parts) === 2) {
        deleteHotKeyword($parts[1]);
    } else {
        error('接口不存在', 404);
    }
}
