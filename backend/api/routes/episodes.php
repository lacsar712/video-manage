<?php

function getEpisodeList() {
    $videoId = $_GET['video_id'] ?? '';

    if (empty($videoId)) {
        error('影片ID不能为空');
    }

    validateInt($videoId, '影片ID');

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id, title, type FROM video WHERE id = ?");
        $stmt->execute([$videoId]);
        $video = $stmt->fetch();

        if (!$video) {
            error('影片不存在', 404);
        }

        $stmt = $db->prepare("
            SELECT id, video_id, episode_no, title, m3u8_url, duration_seconds, status, created_at
            FROM video_episode
            WHERE video_id = ?
            ORDER BY episode_no ASC
        ");
        $stmt->execute([$videoId]);
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['created_at'] = formatDateTime($item['created_at']);
            $item['status'] = intval($item['status']);
            $item['episode_no'] = intval($item['episode_no']);
            $item['duration_seconds'] = $item['duration_seconds'] ? intval($item['duration_seconds']) : null;
        }

        success([
            'video' => $video,
            'list' => $list
        ]);

    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function createEpisode() {
    $videoId = $_POST['video_id'] ?? '';
    $episodeNo = $_POST['episode_no'] ?? '';
    $title = $_POST['title'] ?? '';
    $m3u8Url = $_POST['m3u8_url'] ?? '';
    $durationSeconds = $_POST['duration_seconds'] ?? '';
    $status = $_POST['status'] ?? 1;

    validateRequired([
        'video_id' => '影片ID',
        'episode_no' => '集号'
    ], ['video_id' => $videoId, 'episode_no' => $episodeNo]);

    validateInt($videoId, '影片ID');
    validateInt($episodeNo, '集号');

    $episodeNo = intval($episodeNo);
    if ($episodeNo < 1) {
        error('集号必须大于0');
    }

    if (!empty($title)) {
        validateLength($title, 0, 200, '分集标题');
    }

    if (!empty($m3u8Url)) {
        validateUrl($m3u8Url, 'M3U8地址');
    }

    if ($durationSeconds !== '') {
        validateInt($durationSeconds, '时长');
        $durationSeconds = intval($durationSeconds);
        if ($durationSeconds < 0) {
            error('时长不能为负数');
        }
    } else {
        $durationSeconds = null;
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM video WHERE id = ?");
        $stmt->execute([$videoId]);
        if (!$stmt->fetch()) {
            error('影片不存在', 404);
        }

        $stmt = $db->prepare("SELECT id FROM video_episode WHERE video_id = ? AND episode_no = ?");
        $stmt->execute([$videoId, $episodeNo]);
        if ($stmt->fetch()) {
            error('该集号已存在');
        }

        $stmt = $db->prepare("
            INSERT INTO video_episode (video_id, episode_no, title, m3u8_url, duration_seconds, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$videoId, $episodeNo, $title, $m3u8Url, $durationSeconds, $status]);

        $episodeId = $db->lastInsertId();

        writeAuditLog('create', 'episode', $episodeId, [
            'video_id' => intval($videoId),
            'episode_no' => $episodeNo,
            'title' => $title,
            'status' => $status
        ]);

        success(['id' => $episodeId], '添加成功');

    } catch (Exception $e) {
        error('添加失败：' . $e->getMessage());
    }
}

function updateEpisode($id) {
    validateInt($id, '分集ID');

    $episodeNo = $_POST['episode_no'] ?? '';
    $title = $_POST['title'] ?? '';
    $m3u8Url = $_POST['m3u8_url'] ?? '';
    $durationSeconds = $_POST['duration_seconds'] ?? '';
    $status = $_POST['status'] ?? '';

    validateRequired([
        'episode_no' => '集号',
        'status' => '状态'
    ], ['episode_no' => $episodeNo, 'status' => $status]);

    validateInt($episodeNo, '集号');
    $episodeNo = intval($episodeNo);
    if ($episodeNo < 1) {
        error('集号必须大于0');
    }

    if (!empty($title)) {
        validateLength($title, 0, 200, '分集标题');
    }

    if (!empty($m3u8Url)) {
        validateUrl($m3u8Url, 'M3U8地址');
    }

    if ($durationSeconds !== '') {
        validateInt($durationSeconds, '时长');
        $durationSeconds = intval($durationSeconds);
        if ($durationSeconds < 0) {
            error('时长不能为负数');
        }
    } else {
        $durationSeconds = null;
    }

    if (!in_array($status, [0, 1, '0', '1'])) {
        error('状态值必须为 0 或 1');
    }
    $status = intval($status);

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM video_episode WHERE id = ?");
        $stmt->execute([$id]);
        $oldEpisode = $stmt->fetch();
        if (!$oldEpisode) {
            error('分集不存在', 404);
        }

        if ($episodeNo != intval($oldEpisode['episode_no'])) {
            $stmt = $db->prepare("SELECT id FROM video_episode WHERE video_id = ? AND episode_no = ? AND id != ?");
            $stmt->execute([$oldEpisode['video_id'], $episodeNo, $id]);
            if ($stmt->fetch()) {
                error('该集号已存在');
            }
        }

        $stmt = $db->prepare("
            UPDATE video_episode
            SET episode_no = ?, title = ?, m3u8_url = ?, duration_seconds = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$episodeNo, $title, $m3u8Url, $durationSeconds, $status, $id]);

        writeAuditLog('update', 'episode', $id, [
            'video_id' => intval($oldEpisode['video_id']),
            'old' => [
                'episode_no' => intval($oldEpisode['episode_no']),
                'title' => $oldEpisode['title'],
                'm3u8_url' => $oldEpisode['m3u8_url'],
                'duration_seconds' => $oldEpisode['duration_seconds'] ? intval($oldEpisode['duration_seconds']) : null,
                'status' => intval($oldEpisode['status'])
            ],
            'new' => [
                'episode_no' => $episodeNo,
                'title' => $title,
                'm3u8_url' => $m3u8Url,
                'duration_seconds' => $durationSeconds,
                'status' => $status
            ]
        ]);

        success(null, '更新成功');

    } catch (Exception $e) {
        error('更新失败：' . $e->getMessage());
    }
}

function deleteEpisode($id) {
    validateInt($id, '分集ID');

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM video_episode WHERE id = ?");
        $stmt->execute([$id]);
        $episode = $stmt->fetch();
        if (!$episode) {
            error('分集不存在', 404);
        }

        $stmt = $db->prepare("DELETE FROM video_episode WHERE id = ?");
        $stmt->execute([$id]);

        writeAuditLog('delete', 'episode', $id, [
            'video_id' => intval($episode['video_id']),
            'episode_no' => intval($episode['episode_no']),
            'title' => $episode['title']
        ]);

        success(null, '删除成功');

    } catch (Exception $e) {
        error('删除失败：' . $e->getMessage());
    }
}

function batchImportEpisodes() {
    $videoId = $_POST['video_id'] ?? '';
    $episodes = $_POST['episodes'] ?? '';

    if (empty($videoId)) {
        error('影片ID不能为空');
    }
    validateInt($videoId, '影片ID');

    if (empty($episodes)) {
        error('导入数据不能为空');
    }

    $episodeList = json_decode($episodes, true);
    if (!is_array($episodeList)) {
        error('导入数据格式错误');
    }

    if (count($episodeList) === 0) {
        error('导入数据不能为空');
    }

    if (count($episodeList) > 500) {
        error('单次最多导入500条');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id, type FROM video WHERE id = ?");
        $stmt->execute([$videoId]);
        $video = $stmt->fetch();
        if (!$video) {
            error('影片不存在', 404);
        }

        $db->beginTransaction();

        $successCount = 0;
        $skipCount = 0;
        $errors = [];

        foreach ($episodeList as $index => $ep) {
            $lineNo = $index + 1;

            if (!isset($ep['episode_no']) || $ep['episode_no'] === '') {
                $errors[] = "第{$lineNo}行：集号不能为空";
                continue;
            }

            $episodeNo = intval($ep['episode_no']);
            if ($episodeNo < 1) {
                $errors[] = "第{$lineNo}行：集号必须大于0";
                continue;
            }

            $title = isset($ep['title']) ? trim($ep['title']) : '';
            $m3u8Url = isset($ep['m3u8_url']) ? trim($ep['m3u8_url']) : '';
            $durationSeconds = isset($ep['duration_seconds']) && $ep['duration_seconds'] !== '' ? intval($ep['duration_seconds']) : null;
            $status = isset($ep['status']) ? intval($ep['status']) : 1;

            if (!empty($title) && mb_strlen($title, 'UTF-8') > 200) {
                $errors[] = "第{$lineNo}行：标题长度不能超过200字符";
                continue;
            }

            if (!empty($m3u8Url) && !filter_var($m3u8Url, FILTER_VALIDATE_URL)) {
                $errors[] = "第{$lineNo}行：M3U8地址格式不正确";
                continue;
            }

            if ($durationSeconds !== null && $durationSeconds < 0) {
                $errors[] = "第{$lineNo}行：时长不能为负数";
                continue;
            }

            if (!in_array($status, [0, 1])) {
                $status = 1;
            }

            try {
                $stmt = $db->prepare("SELECT id FROM video_episode WHERE video_id = ? AND episode_no = ?");
                $stmt->execute([$videoId, $episodeNo]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $skipCount++;
                    continue;
                }

                $stmt = $db->prepare("
                    INSERT INTO video_episode (video_id, episode_no, title, m3u8_url, duration_seconds, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$videoId, $episodeNo, $title, $m3u8Url, $durationSeconds, $status]);
                $successCount++;
            } catch (Exception $e) {
                $errors[] = "第{$lineNo}行：" . $e->getMessage();
            }
        }

        $db->commit();

        writeAuditLog('batch_import', 'episode', $videoId, [
            'video_id' => intval($videoId),
            'success_count' => $successCount,
            'skip_count' => $skipCount,
            'error_count' => count($errors)
        ]);

        success([
            'success_count' => $successCount,
            'skip_count' => $skipCount,
            'error_count' => count($errors),
            'errors' => $errors
        ], '导入完成：成功' . $successCount . '条，跳过' . $skipCount . '条，失败' . count($errors) . '条');

    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error('导入失败：' . $e->getMessage());
    }
}

function handleEpisodeRequest($path, $method) {
    $parts = explode('/', $path);

    if ($method === 'GET' && $path === 'episodes') {
        getEpisodeList();
    } elseif ($method === 'POST' && $path === 'episodes') {
        createEpisode();
    } elseif ($method === 'POST' && count($parts) === 3 && $parts[2] === 'batch-import') {
        batchImportEpisodes();
    } elseif ($method === 'POST' && count($parts) === 2) {
        updateEpisode($parts[1]);
    } elseif ($method === 'DELETE' && count($parts) === 2) {
        deleteEpisode($parts[1]);
    } else {
        error('接口不存在', 404);
    }
}
