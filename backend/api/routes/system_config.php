<?php

function getSystemConfigList($tokenData) {
    $isSuper = isset($tokenData['role']) && $tokenData['role'] === 'super';

    try {
        $db = getDB();

        if ($isSuper) {
            $stmt = $db->prepare("
                SELECT id, config_key, config_value, description, config_group,
                       value_type, is_sensitive, sort_order, updated_at
                FROM system_config
                ORDER BY config_group ASC, sort_order ASC, id ASC
            ");
            $stmt->execute();
        } else {
            $stmt = $db->prepare("
                SELECT id, config_key, config_value, description, config_group,
                       value_type, is_sensitive, sort_order, updated_at
                FROM system_config
                WHERE is_sensitive = 0
                ORDER BY config_group ASC, sort_order ASC, id ASC
            ");
            $stmt->execute();
        }

        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['is_sensitive'] = intval($item['is_sensitive']);
            $item['sort_order'] = intval($item['sort_order']);
            $item['updated_at'] = formatDateTime($item['updated_at']);

            if ($item['value_type'] === 'boolean') {
                $item['config_value'] = intval($item['config_value']);
            } elseif ($item['value_type'] === 'number') {
                $item['config_value'] = $item['config_value'] === null || $item['config_value'] === ''
                    ? null
                    : intval($item['config_value']);
            }
        }

        $grouped = [
            'basic' => [],
            'list' => [],
            'security' => []
        ];

        foreach ($list as $item) {
            $group = $item['config_group'];
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $item;
        }

        success([
            'list' => $list,
            'groups' => $grouped,
            'is_super' => $isSuper
        ]);
    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function validateConfigValue($configKey, $value, $valueType) {
    switch ($valueType) {
        case 'number':
            if ($value === '' || $value === null) {
                error("配置项「{$configKey}」不能为空");
            }
            if (!is_numeric($value)) {
                error("配置项「{$configKey}」必须是数字");
            }
            break;
        case 'email':
            if ($value === '' || $value === null) {
                error("配置项「{$configKey}」不能为空");
            }
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                error("配置项「{$configKey}」邮箱格式不正确");
            }
            break;
        case 'boolean':
            if (!in_array($value, [0, 1, '0', '1'], true)) {
                error("配置项「{$configKey}」值必须是 0 或 1");
            }
            break;
        case 'string':
        default:
            if ($value === '' || $value === null) {
                error("配置项「{$configKey}」不能为空");
            }
            break;
    }
}

function batchUpdateSystemConfig($tokenData) {
    $isSuper = isset($tokenData['role']) && $tokenData['role'] === 'super';

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $items = $input['items'] ?? [];

    if (empty($items) || !is_array($items)) {
        error('请提供要更新的配置项');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT id, config_key, config_value, description, config_group,
                   value_type, is_sensitive, sort_order
            FROM system_config
        ");
        $stmt->execute();
        $existingConfigs = $stmt->fetchAll();

        $configMap = [];
        foreach ($existingConfigs as $cfg) {
            $configMap[$cfg['config_key']] = $cfg;
        }

        $db->beginTransaction();

        $changedSummary = [];

        foreach ($items as $item) {
            $configKey = $item['config_key'] ?? '';
            $newValue = $item['config_value'] ?? null;

            if ($configKey === '') {
                $db->rollBack();
                error('配置键不能为空');
            }

            if (!isset($configMap[$configKey])) {
                $db->rollBack();
                error("配置项「{$configKey}」不存在");
            }

            $existing = $configMap[$configKey];

            if (intval($existing['is_sensitive']) === 1 && !$isSuper) {
                $db->rollBack();
                error("无权修改敏感配置项「{$configKey}」");
            }

            validateConfigValue($configKey, $newValue, $existing['value_type']);

            $oldValue = $existing['config_value'];
            if ($existing['value_type'] === 'boolean') {
                $oldValue = intval($oldValue);
                $newValue = intval($newValue);
            } elseif ($existing['value_type'] === 'number') {
                $oldValue = $oldValue === '' || $oldValue === null ? null : intval($oldValue);
                $newValue = intval($newValue);
            }

            if (strcmp((string)$oldValue, (string)$newValue) === 0) {
                continue;
            }

            $stmt = $db->prepare("
                UPDATE system_config
                SET config_value = ?, updated_at = NOW()
                WHERE config_key = ?
            ");
            $stmt->execute([(string)$newValue, $configKey]);

            $changedSummary[] = [
                'config_key' => $configKey,
                'description' => $existing['description'],
                'old_value' => $oldValue,
                'new_value' => $newValue
            ];
        }

        $db->commit();

        if (!empty($changedSummary)) {
            writeAuditLog('update', 'system_config', null, [
                'changes' => $changedSummary,
                'changed_count' => count($changedSummary)
            ], $tokenData);
        }

        success([
            'changed_count' => count($changedSummary),
            'changes' => $changedSummary
        ], count($changedSummary) > 0 ? '保存成功' : '没有需要更新的配置');

    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error('保存失败：' . $e->getMessage());
    }
}

function getSystemConfigPublicValues() {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT config_key, config_value, value_type
            FROM system_config
            WHERE is_sensitive = 0
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $value = $row['config_value'];
            if ($row['value_type'] === 'boolean') {
                $value = intval($value) === 1;
            } elseif ($row['value_type'] === 'number') {
                $value = $value === '' || $value === null ? null : intval($value);
            }
            $result[$row['config_key']] = $value;
        }

        success($result);
    } catch (Exception $e) {
        error('查询失败：' . $e->getMessage());
    }
}

function handleSystemConfigRequest($path, $method, $tokenData) {
    $parts = explode('/', $path);

    if ($method === 'GET' && $path === 'system-config') {
        getSystemConfigList($tokenData);
    } elseif ($method === 'GET' && $path === 'system-config/public') {
        getSystemConfigPublicValues();
    } elseif ($method === 'POST' && $path === 'system-config/batch-update') {
        batchUpdateSystemConfig($tokenData);
    } else {
        error('接口不存在', 404);
    }
}
