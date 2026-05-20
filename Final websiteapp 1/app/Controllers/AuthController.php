<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Session;
use App\Models\Customer;
use App\Models\PosSession;
use App\Models\Staff;
use InvalidArgumentException;

final class AuthController extends Controller
{
    public function login(): void
    {
        (new PosController())->index();
    }

    public function memberSession(): array
    {
        $customerId = (int) Session::get('member_customer_id', 0);
        if ($customerId <= 0) {
            return ['member' => null];
        }

        $member = (new Customer())->lookup((string) $customerId);
        if (!$member) {
            Session::forget('member_customer_id');
        }

        return ['member' => $member];
    }

    public function memberLogin(array $payload): array
    {
        $identity = require_field($payload, 'identity', 'Phone or email');
        $password = require_field($payload, 'password', 'Password');
        $customerModel = new Customer();
        $account = $customerModel->authByIdentity($identity);
        if (!$account) {
            throw new InvalidArgumentException('Không tìm thấy thành viên với số điện thoại hoặc email này.');
        }
        if (($account['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('Tài khoản thành viên không hoạt động.');
        }
        if (empty($account['password_hash']) || !password_verify($password, (string) $account['password_hash'])) {
            throw new InvalidArgumentException('Mật khẩu không đúng.');
        }

        $customerModel->touchLogin((int) $account['id']);
        $member = $customerModel->lookup((string) $account['id']);
        Session::put('member_customer_id', (int) $member['id']);
        return ['member' => $member];
    }

    public function memberRegister(array $payload): array
    {
        $password = require_field($payload, 'password', 'Password');
        $confirm = require_field($payload, 'password_confirm', 'Password confirmation');
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('Mật khẩu phải có ít nhất 6 ký tự.');
        }
        if ($password !== $confirm) {
            throw new InvalidArgumentException('Mật khẩu xác nhận không khớp.');
        }

        $phone = require_field($payload, 'phone_number', 'Phone number');
        $email = require_field($payload, 'email', 'Email');
        $customerModel = new Customer();
        if ($customerModel->lookup($phone)) {
            throw new InvalidArgumentException('Số điện thoại đã tồn tại. Vui lòng đăng nhập bằng mật khẩu.');
        }
        if ($email !== '' && $customerModel->authByIdentity($email)) {
            throw new InvalidArgumentException('Email đã tồn tại. Vui lòng đăng nhập bằng mật khẩu.');
        }

        $payload['preferred_channel'] = 'website';
        $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $member = $customerModel->create($payload);
        if (!$member) {
            throw new InvalidArgumentException('Không thể tạo thành viên mới.');
        }

        $customerModel->touchLogin((int) $member['id']);
        $member = $customerModel->lookup((string) $member['id']) ?: $member;
        Session::put('member_customer_id', (int) $member['id']);
        return ['member' => $member];
    }

    public function memberLogout(): array
    {
        Session::forget('member_customer_id');
        return ['member' => null];
    }

    public function memberProfileUpdate(array $payload): array
    {
        $member = $this->currentMember();
        $updated = (new Customer())->updateProfile((int) $member['id'], $payload);
        return ['member' => $updated];
    }

    public function memberChangePassword(array $payload): array
    {
        $member = $this->currentMember();
        $currentPassword = require_field($payload, 'current_password', 'Current password');
        $newPassword = require_field($payload, 'password', 'New password');
        $confirm = require_field($payload, 'password_confirm', 'Password confirmation');
        if (strlen($newPassword) < 6) {
            throw new InvalidArgumentException('Mật khẩu mới phải có ít nhất 6 ký tự.');
        }
        if ($newPassword !== $confirm) {
            throw new InvalidArgumentException('Mật khẩu xác nhận không khớp.');
        }

        $customer = new Customer();
        $hash = $customer->passwordHash((int) $member['id']);
        if (!$hash || !password_verify($currentPassword, $hash)) {
            throw new InvalidArgumentException('Mật khẩu hiện tại không đúng.');
        }

        $customer->updatePassword((int) $member['id'], password_hash($newPassword, PASSWORD_DEFAULT));
        return ['changed' => true];
    }

    public function memberForgotPassword(array $payload): array
    {
        $identity = require_field($payload, 'identity', 'Phone or email');
        $customer = new Customer();
        $account = $customer->authByIdentity($identity);
        if (!$account || ($account['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('Không tìm thấy tài khoản thành viên đang hoạt động.');
        }
        if (trim((string) ($account['email'] ?? '')) === '') {
            throw new InvalidArgumentException('Tài khoản này chưa có email để nhận link đặt lại mật khẩu.');
        }

        $token = bin2hex(random_bytes(32));
        $customer->createPasswordReset(
            (int) $account['id'],
            $token,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );

        $resetUrl = base_url('reset-password?token=' . urlencode($token));
        (new Mailer())->sendPasswordReset(
            (string) $account['email'],
            (string) $account['customer_name'],
            $resetUrl
        );

        return ['sent' => true, 'email' => $this->maskEmail((string) $account['email'])];
    }

    public function memberResetPassword(array $payload): array
    {
        $token = require_field($payload, 'token', 'Reset token');
        $password = require_field($payload, 'password', 'New password');
        $confirm = require_field($payload, 'password_confirm', 'Password confirmation');
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('Mật khẩu mới phải có ít nhất 6 ký tự.');
        }
        if ($password !== $confirm) {
            throw new InvalidArgumentException('Mật khẩu xác nhận không khớp.');
        }

        $customer = new Customer();
        $reset = $customer->findPasswordReset($token);
        if (!$reset || ($reset['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
        }

        $customer->updatePassword((int) $reset['customer_id'], password_hash($password, PASSWORD_DEFAULT));
        $customer->markPasswordResetUsed((int) $reset['id']);
        Session::forget('member_customer_id');

        return ['reset' => true];
    }

    public function requireStaffRole(array $payload, array $allowedRoles, bool $requireSession = true): array
    {
        $staffId = (int) ($payload['staff_id'] ?? 0);
        if ($staffId <= 0) {
            throw new InvalidArgumentException('POS request requires staff_id for role permission.');
        }

        $staff = (new Staff())->find($staffId);
        if (!$staff) {
            throw new InvalidArgumentException('Staff account not found.');
        }

        if (!in_array($staff['staff_role'], $allowedRoles, true)) {
            throw new InvalidArgumentException('Role ' . role_label((string) $staff['staff_role']) . ' không có quyền thực hiện thao tác này.');
        }

        if ($requireSession) {
            (new PosSession())->requireOpen($payload, $staff);
        }

        return $staff;
    }

    private function currentMember(): array
    {
        $customerId = (int) Session::get('member_customer_id', 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Vui lòng đăng nhập thành viên.');
        }

        $member = (new Customer())->lookup((string) $customerId);
        if (!$member) {
            Session::forget('member_customer_id');
            throw new InvalidArgumentException('Phiên đăng nhập thành viên không còn hợp lệ.');
        }

        return $member;
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($domain === '') {
            return $email;
        }

        $prefix = substr($name, 0, 2);
        return $prefix . str_repeat('*', max(2, strlen($name) - 2)) . '@' . $domain;
    }
}
