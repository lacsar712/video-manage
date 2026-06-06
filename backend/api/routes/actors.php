<?php

function getActorList() {
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
            $where[] = "a.status = ?";
            $params[] = $status;
        }

        if ($keyword !== '') {
            $where[] = "a.name LIKE ?";
            $params[] = "%{$keyword}%";
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM actor a {$whereClause}");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        $stmt = $db->prepare("
            SELECT a.id, a.name, a.avatar_url, a.bio, a.status, a.created_at, a.updated_at,
                   (SELECT COUNT(*) FROM video_actor va WHERE va.actor_id = a.id) as video_count
            FROM actor a
            {$whereClause}
            ORDER BY a.id DESC
            LIMIT {$offset}, {$pageSize}
        ");
        $stmt->execute($params);
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['created_at'] = formatDateTime($item['created_at']);
            $item['updated_at'] = formatDateTime($item['updated_at']);
            $item['video_count'] = intval($item['video_count']);
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

function getActorDetail($id) {
    validateInt($id, '演员ID');

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT a.id, a.name, a.avatar_url, a.bio, a.status, a.created_at, a.updated_at,
                   (SELECT COUNT(*) FROM video_actor va WHERE va.actor_id = a.id) as video_count
            FROM actor a
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $actor = $stmt->fetch();

        if (!$actor) {
            error('演员不存在', 404);
        }

        $actor['created_at'] = formatDateTime($actor['created_at']);
        $actor['updated_at'] = formatDateTime($actor['updated_at']);
        $actor['video_count'] = intval($actor['video_count']);

        success($actor);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function getActorOptions() {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, name, avatar_url
            FROM actor
            WHERE status = 1
            ORDER BY name ASC
        ");
        $stmt->execute();
        $list = $stmt->fetchAll();

        success(['list' => $list]);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function createActor() {
    $name = $_POST['name'] ?? '';
    $avatarUrl = $_POST['avatar_url'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $status = $_POST['status'] ?? 1;

    validateRequired([
        'name' => '演员姓名'
    ], ['name' => $name]);

    validateLength($name, 1, 100, '演员姓名');

    if (!empty($bio)) {
        validateLength($bio, 0, 1000, '演员简介');
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);

    try {
        $db = getDB();

        $stmt = $db->prepare("
            INSERT INTO actor (name, avatar_url, bio, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$name, $avatarUrl, $bio, $status]);

        $actorId = $db->lastInsertId();

        writeAuditLog('create', 'actor', $actorId, [
            'name' => $name,
            'status' => $status
        ]);

        success(['id' => $actorId], '添加成功');

    } catch (Exception $e) {
        error('添加失败：' . $e->getMessage());
    }
}

function updateActor($id) {
    validateInt($id, '演员ID');

    $name = $_POST['name'] ?? '';
    $avatarUrl = $_POST['avatar_url'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $status = $_POST['status'] ?? '';

    validateRequired([
        'name' => '演员姓名',
        'status' => '状态'
    ], ['name' => $name, 'status' => $status]);

    validateLength($name, 1, 100, '演员姓名');

    if (!empty($bio)) {
        validateLength($bio, 0, 1000, '演员简介');
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM actor WHERE id = ?");
        $stmt->execute([$id]);
        $oldActor = $stmt->fetch();
        if (!$oldActor) {
            error('演员不存在', 404);
        }

        $stmt = $db->prepare("
            UPDATE actor
            SET name = ?, avatar_url = ?, bio = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $avatarUrl, $bio, $status, $id]);

        writeAuditLog('update', 'actor', $id, [
            'old' => [
                'name' => $oldActor['name'],
                'avatar_url' => $oldActor['avatar_url'],
                'bio' => $oldActor['bio'],
                'status' => intval($oldActor['status'])
            ],
            'new' => [
                'name' => $name,
                'avatar_url' => $avatarUrl,
                'bio' => $bio,
                'status' => $status
            ]
        ]);

        success(null, '更新成功');

    } catch (Exception $e) {
        error('更新失败：' . $e->getMessage());
    }
}

function deleteActor($id) {
    validateInt($id, '演员ID');

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM actor WHERE id = ?");
        $stmt->execute([$id]);
        $actor = $stmt->fetch();
        if (!$actor) {
            error('演员不存在', 404);
        }

        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM video_actor WHERE actor_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['cnt'];
        if ($count > 0) {
            error('该演员还有关联影片，无法删除');
        }

        $stmt = $db->prepare("DELETE FROM actor WHERE id = ?");
        $stmt->execute([$id]);

        writeAuditLog('delete', 'actor', $id, [
            'name' => $actor['name']
        ]);

        success(null, '删除成功');

    } catch (Exception $e) {
        error('删除失败：' . $e->getMessage());
    }
}

function updateActorStatus($id) {
    validateInt($id, '演员ID');

    $status = $_POST['status'] ?? '';

    if ($status === '') {
        error('状态不能为空');
    }

    if (!in_array($status, ['0', '1'])) {
        error('状态值不正确');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id, name FROM actor WHERE id = ?");
        $stmt->execute([$id]);
        $actor = $stmt->fetch();
        if (!$actor) {
            error('演员不存在', 404);
        }

        $stmt = $db->prepare("UPDATE actor SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);

        writeAuditLog($status == 1 ? 'publish' : 'unpublish', 'actor', $id, [
            'name' => $actor['name']
        ]);

        success(null, $status == 1 ? '启用成功' : '禁用成功');

    } catch (Exception $e) {
        error('操作失败：' . $e->getMessage());
    }
}

function handleActorRequest($path, $method) {
    $parts = explode('/', $path);

    if ($method === 'GET' && $path === 'actors') {
        getActorList();
    } elseif ($method === 'GET' && $path === 'actors/options') {
        getActorOptions();
    } elseif ($method === 'GET' && count($parts) === 2) {
        getActorDetail($parts[1]);
    } elseif ($method === 'POST' && $path === 'actors') {
        createActor();
    } elseif ($method === 'POST' && count($parts) === 2) {
        updateActor($parts[1]);
    } elseif ($method === 'DELETE' && count($parts) === 2) {
        deleteActor($parts[1]);
    } elseif ($method === 'POST' && count($parts) === 3 && $parts[2] === 'status') {
        updateActorStatus($parts[1]);
    } else {
        error('接口不存在', 404);
    }
}
