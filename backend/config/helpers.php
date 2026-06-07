<?php
// 统一响应格式
function jsonResponse($code, $message, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200); // 所有业务接口返回200
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 成功响应
function success($data = null, $message = '操作成功') {
    jsonResponse(0, $message, $data);
}

// 错误响应
function error($message, $code = 1) {
    jsonResponse($code, $message, null);
}

// 验证必填字段
function validateRequired($fields, $data) {
    foreach ($fields as $field => $label) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            error("{$label}不能为空");
        }
    }
}

// 验证字符串长度
function validateLength($value, $min, $max, $label) {
    $len = mb_strlen($value, 'UTF-8');
    if ($len < $min || $len > $max) {
        error("{$label}长度必须在{$min}-{$max}个字符之间");
    }
}

// 验证URL格式
function validateUrl($url, $label) {
    if (empty($url)) {
        error("{$label}不能为空");
    }
    $parsed = parse_url($url);
    if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
        error("{$label}格式不正确");
    }
    $scheme = strtolower($parsed['scheme']);
    if (!in_array($scheme, ['http', 'https'])) {
        error("{$label}仅支持 http 或 https 协议");
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        error("{$label}格式不正确");
    }
}

// 验证整数
function validateInt($value, $label) {
    if (!is_numeric($value) || intval($value) != $value) {
        error("{$label}必须是整数");
    }
}

// 清理输入（防止XSS）
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// 清理输出（用于HTML显示）
function sanitizeOutput($output) {
    if (is_array($output)) {
        return array_map('sanitizeOutput', $output);
    }
    return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
}

// 格式化日期时间（统一格式：YYYY-MM-DD HH:mm:ss）
function formatDateTime($datetime) {
    if (empty($datetime)) return '';
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    return date('Y-m-d H:i:s', $timestamp);
}

// 生成随机token
function generateToken() {
    return bin2hex(random_bytes(32));
}

// 验证token
function validateToken() {
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);

    if (empty($token)) {
        error('未登录或登录已过期', 401);
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT at.*, au.username, au.role, au.status
            FROM admin_token at
            JOIN admin_user au ON at.admin_id = au.id
            WHERE at.token = ? AND at.expire_at > NOW()
        ");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();

        if (!$tokenData) {
            error('登录已过期，请重新登录', 401);
        }

        if ($tokenData['status'] != 1) {
            error('账号已被禁用，请联系管理员', 401);
        }

        return $tokenData;
    } catch (Exception $e) {
        error('验证失败：' . $e->getMessage());
    }
}

// 验证当前用户是否为 super 角色，不是则报错
function requireSuperRole($tokenData) {
    if (!isset($tokenData['role']) || $tokenData['role'] !== 'super') {
        error('无权访问，需要超级管理员权限', 403);
    }
}

// 验证密码强度：至少 8 位，包含字母和数字
function validatePasswordStrength($password) {
    if (strlen($password) < 8) {
        error('密码长度至少 8 位');
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        error('密码必须同时包含字母和数字');
    }
}

// 获取客户端IP地址
function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// 写入操作审计日志
function writeAuditLog($action, $resourceType, $resourceId = null, $summary = null, $tokenData = null) {
    try {
        $db = getDB();

        if ($tokenData === null && isset($GLOBALS['currentAdmin'])) {
            $tokenData = $GLOBALS['currentAdmin'];
        }

        $adminId = null;
        $adminUsername = null;

        if ($tokenData !== null) {
            $adminId = $tokenData['admin_id'] ?? null;
            $adminUsername = $tokenData['username'] ?? null;
        }

        $summaryJson = null;
        if ($summary !== null) {
            $summaryJson = is_string($summary) ? $summary : json_encode($summary, JSON_UNESCAPED_UNICODE);
        }

        $ip = getClientIp();

        $stmt = $db->prepare("
            INSERT INTO audit_log (admin_id, admin_username, action, resource_type, resource_id, summary, ip, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$adminId, $adminUsername, $action, $resourceType, $resourceId, $summaryJson, $ip]);

        return true;
    } catch (Exception $e) {
        error_log('写入审计日志失败: ' . $e->getMessage());
        return false;
    }
}
