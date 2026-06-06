<?php
function getCategoryList() {
    $status = $_GET['status'] ?? '';

    try {
        $db = getDB();

        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = "status = ?";
            $params[] = $status;
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT id, name, slug, sort_order, status, created_at
            FROM video_category
            {$whereClause}
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute($params);
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['created_at'] = formatDateTime($item['created_at']);
        }

        success(['list' => $list]);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function getCategoryDetail($id) {
    validateInt($id, '分类ID');

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM video_category WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();

        if (!$category) {
            error('分类不存在', 404);
        }

        $category['created_at'] = formatDateTime($category['created_at']);

        success($category);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function createCategory() {
    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $sortOrder = $_POST['sort_order'] ?? 0;
    $status = $_POST['status'] ?? 1;

    validateRequired([
        'name' => '分类名称',
        'slug' => 'URL标识'
    ], ['name' => $name, 'slug' => $slug]);

    validateLength($name, 1, 50, '分类名称');
    validateLength($slug, 1, 50, 'URL标识');

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
        error('URL标识只能包含字母、数字、下划线和连字符');
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);
    $sortOrder = intval($sortOrder);

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM video_category WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            error('URL标识已存在');
        }

        $stmt = $db->prepare("
            INSERT INTO video_category (name, slug, sort_order, status, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $slug, $sortOrder, $status]);

        $categoryId = $db->lastInsertId();

        success(['id' => $categoryId], '添加成功');

    } catch (Exception $e) {
        error('添加失败：' . $e->getMessage());
    }
}

function updateCategory($id) {
    validateInt($id, '分类ID');

    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $sortOrder = $_POST['sort_order'] ?? 0;
    $status = $_POST['status'] ?? '';

    validateRequired([
        'name' => '分类名称',
        'slug' => 'URL标识',
        'status' => '状态'
    ], ['name' => $name, 'slug' => $slug, 'status' => $status]);

    validateLength($name, 1, 50, '分类名称');
    validateLength($slug, 1, 50, 'URL标识');

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
        error('URL标识只能包含字母、数字、下划线和连字符');
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);
    $sortOrder = intval($sortOrder);

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM video_category WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            error('分类不存在', 404);
        }

        $stmt = $db->prepare("SELECT id FROM video_category WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            error('URL标识已存在');
        }

        $stmt = $db->prepare("
            UPDATE video_category
            SET name = ?, slug = ?, sort_order = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $slug, $sortOrder, $status, $id]);

        success(null, '更新成功');

    } catch (Exception $e) {
        error('更新失败：' . $e->getMessage());
    }
}

function deleteCategory($id) {
    validateInt($id, '分类ID');

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM video_category WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            error('分类不存在', 404);
        }

        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM video WHERE category_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['cnt'];
        if ($count > 0) {
            error('该分类下还有影片，无法删除');
        }

        $stmt = $db->prepare("DELETE FROM video_category WHERE id = ?");
        $stmt->execute([$id]);

        success(null, '删除成功');

    } catch (Exception $e) {
        error('删除失败：' . $e->getMessage());
    }
}

function updateCategoryStatus($id) {
    validateInt($id, '分类ID');

    $status = $_POST['status'] ?? '';

    if ($status === '') {
        error('状态不能为空');
    }

    if (!in_array($status, ['0', '1'])) {
        error('状态值不正确');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM video_category WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            error('分类不存在', 404);
        }

        $stmt = $db->prepare("UPDATE video_category SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        success(null, $status == 1 ? '启用成功' : '禁用成功');

    } catch (Exception $e) {
        error('操作失败：' . $e->getMessage());
    }
}

function handleCategoryRequest($path, $method) {
    $parts = explode('/', $path);

    if ($method === 'GET' && $path === 'categories') {
        getCategoryList();
    } elseif ($method === 'GET' && count($parts) === 2) {
        getCategoryDetail($parts[1]);
    } elseif ($method === 'POST' && $path === 'categories') {
        createCategory();
    } elseif ($method === 'POST' && count($parts) === 2) {
        updateCategory($parts[1]);
    } elseif ($method === 'DELETE' && count($parts) === 2) {
        deleteCategory($parts[1]);
    } elseif ($method === 'POST' && count($parts) === 3 && $parts[2] === 'status') {
        updateCategoryStatus($parts[1]);
    } else {
        error('接口不存在', 404);
    }
}
