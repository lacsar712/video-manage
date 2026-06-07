<?php
$VALID_STATUS = ['pending', 'processing', 'closed'];
$VALID_CHANNELS = ['app', 'website', 'wechat', 'other'];

function getFeedbackStatusOptions() {
    success([
        ['value' => 'pending', 'label' => '待处理'],
        ['value' => 'processing', 'label' => '处理中'],
        ['value' => 'closed', 'label' => '已关闭']
    ]);
}

function getFeedbackChannelOptions() {
    success([
        ['value' => 'app', 'label' => 'APP'],
        ['value' => 'website', 'label' => '网站'],
        ['value' => 'wechat', 'label' => '微信'],
        ['value' => 'other', 'label' => '其他']
    ]);
}

function getFeedbackList() {
    global $VALID_STATUS, $VALID_CHANNELS;

    $page = intval($_GET['page'] ?? 1);
    $pageSize = intval($_GET['page_size'] ?? 10);
    $status = $_GET['status'] ?? '';
    $sourceChannel = $_GET['source_channel'] ?? '';
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

        if ($sourceChannel !== '') {
            $where[] = "source_channel = ?";
            $params[] = $sourceChannel;
        }

        if ($keyword !== '') {
            $where[] = "(content LIKE ? OR contact_info LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM user_feedback {$whereClause}");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        $stmt = $db->prepare("
            SELECT id, contact_info, content, source_channel, status, handle_note, handled_by, handled_at, created_at
            FROM user_feedback
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT {$offset}, {$pageSize}
        ");
        $stmt->execute($params);
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['created_at'] = formatDateTime($item['created_at']);
            if (!empty($item['handled_at'])) {
                $item['handled_at'] = formatDateTime($item['handled_at']);
            }
            if (!empty($item['handled_by'])) {
                $adminStmt = $db->prepare("SELECT username FROM admin_user WHERE id = ?");
                $adminStmt->execute([$item['handled_by']]);
                $admin = $adminStmt->fetch();
                $item['handled_by_username'] = $admin ? $admin['username'] : null;
            } else {
                $item['handled_by_username'] = null;
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

function getFeedbackDetail($id) {
    validateInt($id, '反馈ID');

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, contact_info, content, source_channel, status, handle_note, handled_by, handled_at, created_at
            FROM user_feedback WHERE id = ?
        ");
        $stmt->execute([$id]);
        $feedback = $stmt->fetch();

        if (!$feedback) {
            error('反馈记录不存在', 404);
        }

        $feedback['created_at'] = formatDateTime($feedback['created_at']);
        if (!empty($feedback['handled_at'])) {
            $feedback['handled_at'] = formatDateTime($feedback['handled_at']);
        }
        if (!empty($feedback['handled_by'])) {
            $adminStmt = $db->prepare("SELECT username FROM admin_user WHERE id = ?");
            $adminStmt->execute([$feedback['handled_by']]);
            $admin = $adminStmt->fetch();
            $feedback['handled_by_username'] = $admin ? $admin['username'] : null;
        } else {
            $feedback['handled_by_username'] = null;
        }

        $historyStmt = $db->prepare("
            SELECT id, action, old_status, new_status, note, admin_username, created_at
            FROM user_feedback_history
            WHERE feedback_id = ?
            ORDER BY created_at ASC
        ");
        $historyStmt->execute([$id]);
        $history = $historyStmt->fetchAll();
        foreach ($history as &$h) {
            $h['created_at'] = formatDateTime($h['created_at']);
        }
        $feedback['history'] = $history;

        success($feedback);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function writeFeedbackHistory($db, $feedbackId, $tokenData, $action, $oldStatus = null, $newStatus = null, $note = null) {
    $adminId = $tokenData['admin_id'] ?? null;
    $adminUsername = $tokenData['username'] ?? null;
    $stmt = $db->prepare("
        INSERT INTO user_feedback_history (feedback_id, admin_id, admin_username, action, old_status, new_status, note, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$feedbackId, $adminId, $adminUsername, $action, $oldStatus, $newStatus, $note]);
}

function createFeedback($tokenData) {
    global $VALID_STATUS, $VALID_CHANNELS;

    $contactInfo = $_POST['contact_info'] ?? '';
    $content = $_POST['content'] ?? '';
    $sourceChannel = $_POST['source_channel'] ?? 'app';

    validateRequired([
        'content' => '反馈内容'
    ], ['content' => $content]);

    validateLength($content, 1, 2000, '反馈内容');

    if (!in_array($sourceChannel, $VALID_CHANNELS)) {
        error('来源渠道不合法');
    }

    if (!empty($contactInfo)) {
        validateLength($contactInfo, 1, 200, '联系人信息');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("
            INSERT INTO user_feedback (contact_info, content, source_channel, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$contactInfo ?: null, $content, $sourceChannel]);

        $feedbackId = $db->lastInsertId();

        writeFeedbackHistory($db, $feedbackId, $tokenData, 'create');

        writeAuditLog('create', 'feedback', $feedbackId, [
            'source_channel' => $sourceChannel,
            'content_length' => mb_strlen($content)
        ], $tokenData);

        success(['id' => $feedbackId], '录入成功');

    } catch (Exception $e) {
        error('录入失败：' . $e->getMessage());
    }
}

function updateFeedback($tokenData, $id) {
    global $VALID_STATUS;

    validateInt($id, '反馈ID');

    $status = $_POST['status'] ?? '';
    $handleNote = $_POST['handle_note'] ?? '';

    if ($status !== '' && !in_array($status, $VALID_STATUS)) {
        error('处理状态不合法');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM user_feedback WHERE id = ?");
        $stmt->execute([$id]);
        $feedback = $stmt->fetch();
        if (!$feedback) {
            error('反馈记录不存在', 404);
        }

        $updateFields = [];
        $params = [];
        $oldStatus = $feedback['status'];
        $statusChanged = false;

        if ($status !== '' && $status !== $feedback['status']) {
            $updateFields[] = "status = ?";
            $params[] = $status;
            $statusChanged = true;
        }

        if ($handleNote !== '') {
            $updateFields[] = "handle_note = ?";
            $params[] = $handleNote;
        }

        if (!empty($updateFields)) {
            $updateFields[] = "handled_by = ?";
            $params[] = $tokenData['admin_id'];
            $updateFields[] = "handled_at = NOW()";

            $params[] = $id;
            $sql = "UPDATE user_feedback SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            if ($statusChanged) {
                writeFeedbackHistory($db, $id, $tokenData, 'status_update', $oldStatus, $status, $handleNote ?: null);
            } elseif ($handleNote !== '') {
                writeFeedbackHistory($db, $id, $tokenData, 'note_update', null, null, $handleNote);
            }

            writeAuditLog('update', 'feedback', $id, [
                'old_status' => $oldStatus,
                'new_status' => $status ?: $oldStatus,
                'has_note' => !empty($handleNote)
            ], $tokenData);
        }

        success(null, '更新成功');

    } catch (Exception $e) {
        error('更新失败：' . $e->getMessage());
    }
}

function handleFeedbackRequest($path, $method, $tokenData) {
    $parts = explode('/', $path);

    if ($method === 'GET' && $path === 'feedback') {
        getFeedbackList();
    } elseif ($method === 'GET' && $path === 'feedback/status-options') {
        getFeedbackStatusOptions();
    } elseif ($method === 'GET' && $path === 'feedback/channel-options') {
        getFeedbackChannelOptions();
    } elseif ($method === 'GET' && count($parts) === 2) {
        getFeedbackDetail($parts[1]);
    } elseif ($method === 'POST' && $path === 'feedback') {
        createFeedback($tokenData);
    } elseif ($method === 'POST' && count($parts) === 2) {
        updateFeedback($tokenData, $parts[1]);
    } else {
        error('接口不存在', 404);
    }
}
