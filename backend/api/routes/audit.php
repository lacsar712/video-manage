<?php

function getAuditLogList() {
    $page = intval($_GET['page'] ?? 1);
    $pageSize = intval($_GET['page_size'] ?? 10);
    $startTime = $_GET['start_time'] ?? '';
    $endTime = $_GET['end_time'] ?? '';
    $adminUsername = $_GET['admin_username'] ?? '';
    $action = $_GET['action'] ?? '';
    $resourceType = $_GET['resource_type'] ?? '';

    $page = max(1, $page);
    $pageSize = min(100, max(1, $pageSize));
    $offset = ($page - 1) * $pageSize;

    try {
        $db = getDB();

        $where = [];
        $params = [];

        if ($startTime !== '') {
            $where[] = "al.created_at >= ?";
            $params[] = $startTime;
        }

        if ($endTime !== '') {
            $where[] = "al.created_at <= ?";
            $params[] = $endTime;
        }

        if ($adminUsername !== '') {
            $where[] = "al.admin_username LIKE ?";
            $params[] = "%{$adminUsername}%";
        }

        if ($action !== '') {
            $where[] = "al.action = ?";
            $params[] = $action;
        }

        if ($resourceType !== '') {
            $where[] = "al.resource_type = ?";
            $params[] = $resourceType;
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM audit_log al {$whereClause}");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        $stmt = $db->prepare("
            SELECT al.id, al.admin_id, al.admin_username, al.action, al.resource_type,
                   al.resource_id, al.summary, al.ip, al.created_at
            FROM audit_log al
            {$whereClause}
            ORDER BY al.created_at DESC, al.id DESC
            LIMIT {$offset}, {$pageSize}
        ");
        $stmt->execute($params);
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['created_at'] = formatDateTime($item['created_at']);
            if (!empty($item['summary'])) {
                $decoded = json_decode($item['summary'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $item['summary'] = $decoded;
                }
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

function getAuditLogDetail($id) {
    validateInt($id, '日志ID');

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT al.id, al.admin_id, al.admin_username, al.action, al.resource_type,
                   al.resource_id, al.summary, al.ip, al.created_at
            FROM audit_log al
            WHERE al.id = ?
        ");
        $stmt->execute([$id]);
        $log = $stmt->fetch();

        if (!$log) {
            error('日志不存在', 404);
        }

        $log['created_at'] = formatDateTime($log['created_at']);
        if (!empty($log['summary'])) {
            $decoded = json_decode($log['summary'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $log['summary'] = $decoded;
            }
        }

        success($log);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function getAuditActions() {
    $actions = [
        ['value' => 'create', 'label' => '创建'],
        ['value' => 'update', 'label' => '更新'],
        ['value' => 'delete', 'label' => '删除'],
        ['value' => 'publish', 'label' => '上架'],
        ['value' => 'unpublish', 'label' => '下架'],
        ['value' => 'login', 'label' => '登录'],
        ['value' => 'logout', 'label' => '退出']
    ];

    success($actions);
}

function getAuditResourceTypes() {
    $types = [
        ['value' => 'video', 'label' => '影片'],
        ['value' => 'source', 'label' => '播放源'],
        ['value' => 'auth', 'label' => '认证']
    ];

    success($types);
}

function handleAuditRequest($path, $method) {
    $parts = explode('/', $path);

    if ($method === 'GET' && $path === 'audit-logs') {
        getAuditLogList();
    } elseif ($method === 'GET' && $path === 'audit-logs/actions') {
        getAuditActions();
    } elseif ($method === 'GET' && $path === 'audit-logs/resource-types') {
        getAuditResourceTypes();
    } elseif ($method === 'GET' && count($parts) === 2) {
        getAuditLogDetail($parts[1]);
    } else {
        error('接口不存在', 404);
    }
}
