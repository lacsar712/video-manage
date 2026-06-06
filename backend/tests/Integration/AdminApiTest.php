<?php

namespace Tests\Integration;

use Tests\TestCase;

/**
 * 管理员 API 集成测试
 */
class AdminApiTest extends TestCase
{
    /**
     * 测试管理员登录成功
     */
    public function testAdminLoginSuccess()
    {
        // 创建测试管理员
        $adminId = $this->createTestAdmin('admin', 'admin123');

        // 模拟登录请求
        $_POST['username'] = 'admin';
        $_POST['password'] = 'admin123';

        // 捕获输出
        ob_start();
        try {
            require __DIR__ . '/../../backend/api/routes/admin.php';
            adminLogin();
        } catch (\Exception $e) {
            // jsonResponse 会调用 exit，捕获异常
        }
        $output = ob_get_clean();

        // 解析响应
        $response = json_decode($output, true);

        // 断言
        $this->assertEquals(0, $response['code']);
        $this->assertEquals('登录成功', $response['message']);
        $this->assertArrayHasKey('token', $response['data']);
        $this->assertArrayHasKey('username', $response['data']);
        $this->assertArrayHasKey('role', $response['data']);
        $this->assertArrayHasKey('admin_id', $response['data']);
        $this->assertEquals('admin', $response['data']['username']);
        $this->assertEquals('super', $response['data']['role']);
        $this->assertEquals($adminId, $response['data']['admin_id']);

        // 验证 token 已保存到数据库
        $this->assertDatabaseHas('admin_token', [
            'admin_id' => $adminId,
            'token' => $response['data']['token']
        ]);
    }

    /**
     * 测试管理员登录失败 - 用户名错误
     */
    public function testAdminLoginFailureWrongUsername()
    {
        $this->createTestAdmin('admin', 'admin123');

        $_POST['username'] = 'wrong_user';
        $_POST['password'] = 'admin123';

        ob_start();
        try {
            require __DIR__ . '/../../backend/api/routes/admin.php';
            adminLogin();
        } catch (\Exception $e) {
        }
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotEquals(0, $response['code']);
        $this->assertStringContainsString('用户名或密码错误', $response['message']);
    }

    /**
     * 测试管理员登录失败 - 密码错误
     */
    public function testAdminLoginFailureWrongPassword()
    {
        $this->createTestAdmin('admin', 'admin123');

        $_POST['username'] = 'admin';
        $_POST['password'] = 'wrong_password';

        ob_start();
        try {
            require __DIR__ . '/../../backend/api/routes/admin.php';
            adminLogin();
        } catch (\Exception $e) {
        }
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotEquals(0, $response['code']);
        $this->assertStringContainsString('用户名或密码错误', $response['message']);
    }

    /**
     * 测试管理员登录失败 - 缺少必填字段
     */
    public function testAdminLoginFailureMissingFields()
    {
        $_POST['username'] = '';
        $_POST['password'] = '';

        ob_start();
        try {
            require __DIR__ . '/../../backend/api/routes/admin.php';
            adminLogin();
        } catch (\Exception $e) {
        }
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotEquals(0, $response['code']);
        $this->assertStringContainsString('不能为空', $response['message']);
    }

    /**
     * 测试管理员登录失败 - 账号被禁用
     */
    public function testAdminLoginFailureDisabled()
    {
        $this->createTestAdmin('admin', 'admin123', 'super', 0);

        $_POST['username'] = 'admin';
        $_POST['password'] = 'admin123';

        ob_start();
        try {
            require __DIR__ . '/../../backend/api/routes/admin.php';
            adminLogin();
        } catch (\Exception $e) {
        }
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotEquals(0, $response['code']);
        $this->assertStringContainsString('账号已被禁用', $response['message']);
    }

    /**
     * 模拟已认证的管理员请求
     */
    private function mockAdminRequest($role = 'super', $status = 1)
    {
        $adminId = $this->createTestAdmin('super_admin', 'superpass123', $role, $status);
        $token = $this->createTestToken($adminId);

        return [
            'token' => $token,
            'admin_id' => $adminId,
            'username' => 'super_admin',
            'role' => $role,
            'status' => $status
        ];
    }

    /**
     * 测试创建管理员用户成功
     */
    public function testCreateAdminUserSuccess()
    {
        $tokenData = $this->mockAdminRequest('super');

        $_POST['username'] = 'new_editor';
        $_POST['password'] = 'Pass1234';
        $_POST['role'] = 'editor';
        $_POST['status'] = 1;

        ob_start();
        try {
            require __DIR__ . '/../../backend/api/routes/admin.php';
            createAdminUser($tokenData);
        } catch (\Exception $e) {
        }
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertEquals(0, $response['code']);
        $this->assertEquals('创建成功', $response['message']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertDatabaseHas('admin_user', [
            'username' => 'new_editor',
            'role' => 'editor',
            'status' => 1
        ]);
    }

    /**
     * 测试创建管理员用户 - 用户名已存在
     */
    public function testCreateAdminUserDuplicateUsername()
    {
        $this->createTestAdmin('dup_user', 'Pass1234', 'editor', 1);
        $tokenData = $this->mockAdminRequest('super');

        $_POST['username'] = 'dup_user';
        $_POST['password'] = 'Pass1234';
        $_POST['role'] = 'editor';
        $_POST['status'] = 1;

        ob_start();
        try {
            require __DIR__ . '/../../backend/api/routes/admin.php';
            createAdminUser($tokenData);
        } catch (\Exception $e) {
        }
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotEquals(0, $response['code']);
        $this->assertStringContainsString('用户名已存在', $response['message']);
    }

    /**
     * 测试创建管理员用户 - 密码强度不足
     */
    public function testCreateAdminUserWeakPassword()
    {
        $tokenData = $this->mockAdminRequest('super');

        $_POST['username'] = 'weak_user';
        $_POST['password'] = '123456';
        $_POST['role'] = 'editor';
        $_POST['status'] = 1;

        ob_start();
        try {
            require __DIR__ . '/../../backend/api/routes/admin.php';
            createAdminUser($tokenData);
        } catch (\Exception $e) {
        }
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotEquals(0, $response['code']);
    }

    /**
     * 测试创建管理员用户 - editor 角色无权访问
     */
    public function testCreateAdminUserEditorForbidden()
    {
        $tokenData = $this->mockAdminRequest('editor');

        $_POST['username'] = 'test_user';
        $_POST['password'] = 'Pass1234';
        $_POST['role'] = 'editor';
        $_POST['status'] = 1;

        ob_start();
        try {
            require __DIR__ . '/../../backend/api/routes/admin.php';
            createAdminUser($tokenData);
        } catch (\Exception $e) {
        }
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotEquals(0, $response['code']);
        $this->assertStringContainsString('无权访问', $response['message']);
    }

    /**
     * 测试重置密码后 token 被清除
     */
    public function testResetPasswordClearsTokens()
    {
        $tokenData = $this->mockAdminRequest('super');
        $targetId = $this->createTestAdmin('target_user', 'Oldpass123', 'editor', 1);
        $targetToken = $this->createTestToken($targetId);

        $_POST['password'] = 'Newpass123';

        ob_start();
        try {
            require __DIR__ . '/../../backend/api/routes/admin.php';
            resetAdminUserPassword($tokenData, $targetId);
        } catch (\Exception $e) {
        }
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertEquals(0, $response['code']);
        $this->assertDatabaseMissing('admin_token', [
            'admin_id' => $targetId,
            'token' => $targetToken
        ]);
    }
}
