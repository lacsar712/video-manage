<?php
function getTagList() {
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';

    try {
        $db = getDB();

        $where = [];
        $params = [];

        if ($type !== '') {
            if (!in_array($type, ['region', 'language'])) {
                error('类型值必须为 region 或 language');
            }
            $where[] = "type = ?";
            $params[] = $type;
        }

        if ($status !== '') {
            $where[] = "status = ?";
            $params[] = $status;
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT id, name, type, sort_order, status, created_at, updated_at
            FROM video_tag
            {$whereClause}
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute($params);
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['created_at'] = formatDateTime($item['created_at']);
            $item['updated_at'] = formatDateTime($item['updated_at']);
        }

        success(['list' => $list]);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function getTagOptions() {
    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT id, name, type
            FROM video_tag
            WHERE status = 1
            ORDER BY type ASC, sort_order ASC, id ASC
        ");
        $stmt->execute();
        $list = $stmt->fetchAll();

        $regionList = [];
        $languageList = [];
        foreach ($list as $item) {
            if ($item['type'] === 'region') {
                $regionList[] = $item;
            } elseif ($item['type'] === 'language') {
                $languageList[] = $item;
            }
        }

        success([
            'list' => $list,
            'region_list' => $regionList,
            'language_list' => $languageList
        ]);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function getTagDetail($id) {
    validateInt($id, '标签ID');

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM video_tag WHERE id = ?");
        $stmt->execute([$id]);
        $tag = $stmt->fetch();

        if (!$tag) {
            error('标签不存在', 404);
        }

        $tag['created_at'] = formatDateTime($tag['created_at']);
        $tag['updated_at'] = formatDateTime($tag['updated_at']);

        success($tag);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function createTag() {
    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? '';
    $sortOrder = $_POST['sort_order'] ?? 0;
    $status = $_POST['status'] ?? 1;

    validateRequired([
        'name' => '标签名称',
        'type' => '标签类型'
    ], ['name' => $name, 'type' => $type]);

    validateLength($name, 1, 50, '标签名称');

    if (!in_array($type, ['region', 'language'])) {
        error('类型值必须为 region 或 language');
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);
    $sortOrder = intval($sortOrder);

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM video_tag WHERE name = ? AND type = ?");
        $stmt->execute([$name, $type]);
        if ($stmt->fetch()) {
            error($type === 'region' ? '该地区标签已存在' : '该语言标签已存在');
        }

        $stmt = $db->prepare("
            INSERT INTO video_tag (name, type, sort_order, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$name, $type, $sortOrder, $status]);

        $tagId = $db->lastInsertId();

        writeAuditLog('create', 'video_tag', $tagId, [
            'name' => $name,
            'type' => $type,
            'sort_order' => $sortOrder,
            'status' => $status
        ]);

        success(['id' => $tagId], '添加成功');

    } catch (Exception $e) {
        error('添加失败：' . $e->getMessage());
    }
}

function updateTag($id) {
    validateInt($id, '标签ID');

    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? '';
    $sortOrder = $_POST['sort_order'] ?? 0;
    $status = $_POST['status'] ?? '';

    validateRequired([
        'name' => '标签名称',
        'type' => '标签类型',
        'status' => '状态'
    ], ['name' => $name, 'type' => $type, 'status' => $status]);

    validateLength($name, 1, 50, '标签名称');

    if (!in_array($type, ['region', 'language'])) {
        error('类型值必须为 region 或 language');
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);
    $sortOrder = intval($sortOrder);

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM video_tag WHERE id = ?");
        $stmt->execute([$id]);
        $oldTag = $stmt->fetch();
        if (!$oldTag) {
            error('标签不存在', 404);
        }

        $stmt = $db->prepare("SELECT id FROM video_tag WHERE name = ? AND type = ? AND id != ?");
        $stmt->execute([$name, $type, $id]);
        if ($stmt->fetch()) {
            error($type === 'region' ? '该地区标签已存在' : '该语言标签已存在');
        }

        $stmt = $db->prepare("
            UPDATE video_tag
            SET name = ?, type = ?, sort_order = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $type, $sortOrder, $status, $id]);

        writeAuditLog('update', 'video_tag', $id, [
            'old' => [
                'name' => $oldTag['name'],
                'type' => $oldTag['type'],
                'sort_order' => intval($oldTag['sort_order']),
                'status' => intval($oldTag['status'])
            ],
            'new' => [
                'name' => $name,
                'type' => $type,
                'sort_order' => $sortOrder,
                'status' => $status
            ]
        ]);

        success(null, '更新成功');

    } catch (Exception $e) {
        error('更新失败：' . $e->getMessage());
    }
}

function deleteTag($id) {
    validateInt($id, '标签ID');

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM video_tag WHERE id = ?");
        $stmt->execute([$id]);
        $tag = $stmt->fetch();
        if (!$tag) {
            error('标签不存在', 404);
        }

        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM video_video_tag WHERE tag_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['cnt'];
        if ($count > 0) {
            error('该标签仍有关联影片，请先解除关联后再删除');
        }

        $stmt = $db->prepare("DELETE FROM video_tag WHERE id = ?");
        $stmt->execute([$id]);

        writeAuditLog('delete', 'video_tag', $id, [
            'name' => $tag['name'],
            'type' => $tag['type']
        ]);

        success(null, '删除成功');

    } catch (Exception $e) {
        error('删除失败：' . $e->getMessage());
    }
}

function updateTagStatus($id) {
    validateInt($id, '标签ID');

    $status = $_POST['status'] ?? '';

    if ($status === '') {
        error('状态不能为空');
    }

    if (!in_array($status, ['0', '1'])) {
        error('状态值不正确');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM video_tag WHERE id = ?");
        $stmt->execute([$id]);
        $tag = $stmt->fetch();
        if (!$tag) {
            error('标签不存在', 404);
        }

        $stmt = $db->prepare("UPDATE video_tag SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);

        $action = $status == 1 ? 'publish' : 'unpublish';
        writeAuditLog($action, 'video_tag', $id, [
            'name' => $tag['name'],
            'type' => $tag['type'],
            'old_status' => intval($tag['status']),
            'new_status' => intval($status)
        ]);

        success(null, $status == 1 ? '启用成功' : '禁用成功');

    } catch (Exception $e) {
        error('操作失败：' . $e->getMessage());
    }
}

function handleTagRequest($path, $method) {
    $parts = explode('/', $path);

    if ($method === 'GET' && $path === 'video-tags') {
        getTagList();
    } elseif ($method === 'GET' && $path === 'video-tags/options') {
        getTagOptions();
    } elseif ($method === 'GET' && count($parts) === 2) {
        getTagDetail($parts[1]);
    } elseif ($method === 'POST' && $path === 'video-tags') {
        createTag();
    } elseif ($method === 'POST' && count($parts) === 2) {
        updateTag($parts[1]);
    } elseif ($method === 'DELETE' && count($parts) === 2) {
        deleteTag($parts[1]);
    } elseif ($method === 'POST' && count($parts) === 3 && $parts[2] === 'status') {
        updateTagStatus($parts[1]);
    } else {
        error('接口不存在', 404);
    }
}
