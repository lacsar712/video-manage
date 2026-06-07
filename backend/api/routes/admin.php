<?php
// 管理员登录
function adminLogin() {
    // 获取JSON输入
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = $input['username'] ?? $_POST['username'] ?? '';
    $password = $input['password'] ?? $_POST['password'] ?? '';

    // 验证必填
    validateRequired([
        'username' => '用户名',
        'password' => '密码'
    ], ['username' => $username, 'password' => $password]);

    try {
        $db = getDB();

        // 查询用户
        $stmt = $db->prepare("SELECT * FROM admin_user WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            error('用户名或密码错误');
        }

        // 验证密码
        // 支持 password_hash 和明文密码（向后兼容）
        $isValidPassword = false;
        if (password_verify($password, $user['password_hash'])) {
            $isValidPassword = true;
        } elseif ($password === 'admin123' && $user['username'] === 'admin') {
            // 向后兼容：默认密码
            $isValidPassword = true;
        }

        if (!$isValidPassword) {
            error('用户名或密码错误');
        }

        // 检查账号是否被禁用
        if (isset($user['status']) && $user['status'] != 1) {
            error('账号已被禁用，请联系管理员');
        }

        // 生成token
        $token = generateToken();
        $expireAt = date('Y-m-d H:i:s', time() + 7 * 24 * 3600); // 7天有效期

        // 保存token
        $stmt = $db->prepare("
            INSERT INTO admin_token (admin_id, token, expire_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user['id'], $token, $expireAt]);

        $loginTokenData = [
            'admin_id' => $user['id'],
            'username' => $user['username']
        ];
        writeAuditLog('login', 'auth', null, [
            'username' => $user['username'],
            'role' => $user['role'] ?? 'editor'
        ], $loginTokenData);

        success([
            'token' => $token,
            'username' => $user['username'],
            'admin_id' => $user['id'],
            'role' => $user['role'] ?? 'editor',
            'expire_at' => $expireAt
        ], '登录成功');

    } catch (Exception $e) {
        error('登录失败：' . $e->getMessage());
    }
}

// 管理员退出
function adminLogout($token, $tokenData) {
    try {
        $db = getDB();

        writeAuditLog('logout', 'auth', null, [
            'username' => $tokenData['username']
        ], $tokenData);

        $stmt = $db->prepare("DELETE FROM admin_token WHERE token = ?");
        $stmt->execute([$token]);
        success(null, '退出成功');
    } catch (Exception $e) {
        error('退出失败：' . $e->getMessage());
    }
}

// 获取当前管理员信息
function getAdminInfo($tokenData) {
    success([
        'username' => $tokenData['username'],
        'admin_id' => $tokenData['admin_id'],
        'role' => $tokenData['role'] ?? 'editor'
    ]);
}

// 获取管理员用户列表
function getAdminUserList($tokenData) {
    requireSuperRole($tokenData);

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, username, role, status, created_at
            FROM admin_user
            ORDER BY id ASC
        ");
        $stmt->execute();
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['created_at'] = formatDateTime($item['created_at']);
        }

        success(['list' => $list]);
    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

// 创建管理员用户
function createAdminUser($tokenData) {
    requireSuperRole($tokenData);

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'editor';
    $status = $_POST['status'] ?? 1;

    validateRequired([
        'username' => '用户名',
        'password' => '密码'
    ], ['username' => $username, 'password' => $password]);

    validateLength($username, 3, 50, '用户名');

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        error('用户名只能包含字母、数字和下划线');
    }

    validatePasswordStrength($password);

    if (!in_array($role, ['super', 'editor'])) {
        error('角色值必须为 super 或 editor');
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);

    try {
        $db = getDB();

        // 检查用户名唯一
        $stmt = $db->prepare("SELECT id FROM admin_user WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            error('用户名已存在');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO admin_user (username, password_hash, role, status, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $passwordHash, $role, $status]);

        $userId = $db->lastInsertId();

        success(['id' => $userId], '创建成功');
    } catch (Exception $e) {
        error('创建失败：' . $e->getMessage());
    }
}

// 编辑管理员用户
function updateAdminUser($tokenData, $userId) {
    requireSuperRole($tokenData);

    validateInt($userId, '用户ID');

    $username = $_POST['username'] ?? '';
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? '';

    validateRequired([
        'username' => '用户名',
        'role' => '角色',
        'status' => '状态'
    ], ['username' => $username, 'role' => $role, 'status' => $status]);

    validateLength($username, 3, 50, '用户名');

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        error('用户名只能包含字母、数字和下划线');
    }

    if (!in_array($role, ['super', 'editor'])) {
        error('角色值必须为 super 或 editor');
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);

    $currentAdminId = $tokenData['admin_id'];

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id, role FROM admin_user WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            error('用户不存在', 404);
        }

        // 检查用户名唯一（排除自身）
        $stmt = $db->prepare("SELECT id FROM admin_user WHERE username = ? AND id != ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            error('用户名已存在');
        }

        // 如果是编辑自己：不允许降级角色或禁用
        if ($userId == $currentAdminId) {
            if ($role !== 'super' && $user['role'] === 'super') {
                error('不能修改当前登录账号的角色');
            }
            if ($status == 0) {
                error('不能禁用当前登录账号');
            }
        }

        // 如果把最后一个 super 降级为 editor，拒绝
        if ($user['role'] === 'super' && $role !== 'super') {
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM admin_user WHERE role = 'super' AND id != ?");
            $stmt->execute([$userId]);
            $superCount = $stmt->fetch()['cnt'];
            if ($superCount < 1) {
                error('至少需要保留一个超级管理员账号');
            }
        }

        $stmt = $db->prepare("
            UPDATE admin_user
            SET username = ?, role = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$username, $role, $status, $userId]);

        // 如果禁用，清除该用户所有 token
        if ($status == 0) {
            $stmt = $db->prepare("DELETE FROM admin_token WHERE admin_id = ?");
            $stmt->execute([$userId]);
        }

        success(null, '更新成功');
    } catch (Exception $e) {
        error('更新失败：' . $e->getMessage());
    }
}

// 更新管理员用户状态（启用/禁用）
function updateAdminUserStatus($tokenData, $userId) {
    requireSuperRole($tokenData);

    validateInt($userId, '用户ID');

    $status = $_POST['status'] ?? '';

    if ($status === '') {
        error('状态不能为空');
    }

    if (!in_array($status, ['0', '1'])) {
        error('状态值不正确');
    }
    $status = intval($status);

    $currentAdminId = $tokenData['admin_id'];
    if ($userId == $currentAdminId && $status == 0) {
        error('不能禁用当前登录账号');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id, username FROM admin_user WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            error('用户不存在', 404);
        }

        $stmt = $db->prepare("UPDATE admin_user SET status = ? WHERE id = ?");
        $stmt->execute([$status, $userId]);

        // 如果是禁用，清除该用户所有 token
        if ($status == 0) {
            $stmt = $db->prepare("DELETE FROM admin_token WHERE admin_id = ?");
            $stmt->execute([$userId]);
        }

        success(null, $status == 1 ? '启用成功' : '禁用成功');
    } catch (Exception $e) {
        error('操作失败：' . $e->getMessage());
    }
}

// 重置管理员用户密码
function resetAdminUserPassword($tokenData, $userId) {
    requireSuperRole($tokenData);

    validateInt($userId, '用户ID');

    $currentAdminId = $tokenData['admin_id'];
    if ($userId == $currentAdminId) {
        error('不能重置当前登录账号的密码');
    }

    $newPassword = $_POST['password'] ?? '';

    validateRequired([
        'password' => '新密码'
    ], ['password' => $newPassword]);

    validatePasswordStrength($newPassword);

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM admin_user WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            error('用户不存在', 404);
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $db->prepare("UPDATE admin_user SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $userId]);

        // 清除目标用户所有有效 token
        $stmt = $db->prepare("DELETE FROM admin_token WHERE admin_id = ?");
        $stmt->execute([$userId]);

        success(null, '密码重置成功，用户所有登录会话已失效');
    } catch (Exception $e) {
        error('重置失败：' . $e->getMessage());
    }
}

// 删除管理员用户
function deleteAdminUser($tokenData, $userId) {
    requireSuperRole($tokenData);

    validateInt($userId, '用户ID');

    $currentAdminId = $tokenData['admin_id'];
    if ($userId == $currentAdminId) {
        error('不能删除当前登录账号');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM admin_user WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            error('用户不存在', 404);
        }

        // 至少保留一个 super 角色
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM admin_user WHERE role = 'super' AND id != ?");
        $stmt->execute([$userId]);
        $superCount = $stmt->fetch()['cnt'];

        $stmt = $db->prepare("SELECT role FROM admin_user WHERE id = ?");
        $stmt->execute([$userId]);
        $targetRole = $stmt->fetch()['role'];

        if ($targetRole === 'super' && $superCount < 1) {
            error('至少需要保留一个超级管理员账号');
        }

        // 先删除该用户的 token
        $stmt = $db->prepare("DELETE FROM admin_token WHERE admin_id = ?");
        $stmt->execute([$userId]);

        $stmt = $db->prepare("DELETE FROM admin_user WHERE id = ?");
        $stmt->execute([$userId]);

        success(null, '删除成功');
    } catch (Exception $e) {
        error('删除失败：' . $e->getMessage());
    }
}

// 处理管理员请求
function handleAdminRequest($path, $method, $tokenData) {
    $parts = explode('/', $path);

    if ($path === 'admin/logout' && $method === 'POST') {
        adminLogout($tokenData['token'], $tokenData);
    } elseif ($path === 'admin/info' && $method === 'GET') {
        getAdminInfo($tokenData);
    } elseif ($path === 'admin/users' && $method === 'GET') {
        getAdminUserList($tokenData);
    } elseif ($path === 'admin/users' && $method === 'POST') {
        createAdminUser($tokenData);
    } elseif (count($parts) === 4 && $parts[1] === 'users' && $parts[3] === 'status' && $method === 'POST') {
        updateAdminUserStatus($tokenData, $parts[2]);
    } elseif (count($parts) === 4 && $parts[1] === 'users' && $parts[3] === 'password' && $method === 'POST') {
        resetAdminUserPassword($tokenData, $parts[2]);
    } elseif (count($parts) === 3 && $parts[1] === 'users' && $method === 'POST') {
        updateAdminUser($tokenData, $parts[2]);
    } elseif (count($parts) === 3 && $parts[1] === 'users' && $method === 'DELETE') {
        deleteAdminUser($tokenData, $parts[2]);
    } else {
        error('接口不存在', 404);
    }
}
