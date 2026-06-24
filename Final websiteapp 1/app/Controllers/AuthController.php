<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\AppLogger;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\AuthLockout;
use App\Models\Customer;
use App\Models\PosSession;
use App\Models\Staff;
use App\Models\StaffAuthSession;
use InvalidArgumentException;
use RuntimeException;

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
            return ['member' => null, 'web_staff' => $this->currentWebStaff()];
        }

        $member = (new Customer())->lookup((string) $customerId);
        if (!$member) {
            Session::forget('member_customer_id');
            return ['member' => null, 'web_staff' => $this->currentWebStaff()];
        }

        return ['member' => $member, 'web_staff' => null];
    }

    public function memberLogin(array $payload): array
    {
        $identity = require_field($payload, 'identity', 'Phone, email or staff code');
        $password = require_field($payload, 'password', 'Password');
        $limitIdentity = RateLimiter::identity('member-login|' . $identity);
        $lockout = new AuthLockout();
        $lockout->assertAllowed('member-login', $identity);
        RateLimiter::hit('member-login', $limitIdentity, 8, 15 * 60);

        $customerModel = new Customer();
        $account = $customerModel->authByIdentity($identity);
        if (!$account) {
            $staffLogin = (new StaffAuthSession())->login([
                'identity' => $identity,
                'password' => $password,
            ]);

            RateLimiter::clear('member-login', $limitIdentity);
            Session::regenerate();
            Session::forget('member_customer_id');
            Session::put('web_staff_id', (int) $staffLogin['staff']['id']);
            Session::put('web_staff_auth_session_id', (int) $staffLogin['auth_session']['id']);
            Session::put('web_staff_auth_token', (string) $staffLogin['auth_session']['auth_token']);

            return [
                'account_type' => 'staff',
                'member' => null,
                'web_staff' => $staffLogin['staff'],
                'auth_session' => $staffLogin['auth_session'],
            ];
        }

        if (($account['status'] ?? '') !== 'active') {
            $lockout->recordFailure('member-login', $identity);
            throw new InvalidArgumentException('Tài khoản thành viên không hoạt động.');
        }
        if (empty($account['password_hash']) || !password_verify($password, (string) $account['password_hash'])) {
            $lockout->recordFailure('member-login', $identity);
            throw new InvalidArgumentException('Mật khẩu không đúng.');
        }

        $customerModel->touchLogin((int) $account['id']);
        $member = $customerModel->lookup((string) $account['id']);
        if (!$member) {
            throw new InvalidArgumentException('Phiên đăng nhập thành viên không còn hợp lệ.');
        }

        $this->clearWebStaffSession();
        $lockout->clear('member-login', $identity);
        RateLimiter::clear('member-login', $limitIdentity);
        Session::regenerate();
        Session::put('member_customer_id', (int) $member['id']);

        (new AuditLog())->record([
            'actor_type' => 'customer',
            'actor_id' => (int) $member['id'],
            'action' => 'member_login',
            'entity_type' => 'customer',
            'entity_id' => (int) $member['id'],
        ]);

        return ['account_type' => 'customer', 'member' => $member, 'web_staff' => null];
    }

    public function staffWebAdopt(array $payload): array
    {
        $staffId = (int) ($payload['staff_id'] ?? $payload['id'] ?? 0);
        $authSessionId = (int) ($payload['auth_session_id'] ?? 0);
        $authToken = trim((string) ($payload['auth_token'] ?? ''));
        if ($staffId <= 0 || $authSessionId <= 0 || $authToken === '') {
            throw new InvalidArgumentException('Thiếu phiên đăng nhập nhân viên để đồng bộ website.');
        }

        $current = (new StaffAuthSession())->current([
            'staff_id' => $staffId,
            'auth_session_id' => $authSessionId,
            'auth_token' => $authToken,
        ]);
        if (empty($current['staff'])) {
            throw new InvalidArgumentException('Phiên đăng nhập nhân viên không còn hợp lệ.');
        }

        Session::regenerate();
        Session::forget('member_customer_id');
        Session::put('web_staff_id', (int) $current['staff']['id']);
        Session::put('web_staff_auth_session_id', (int) $current['auth_session']['id']);
        Session::put('web_staff_auth_token', (string) $current['auth_session']['auth_token']);

        return [
            'account_type' => 'staff',
            'member' => null,
            'web_staff' => $current['staff'],
            'auth_session' => $current['auth_session'],
        ];
    }

    public function memberRegister(array $payload): array
    {
        $registerIdentity = RateLimiter::identity('member-register|' . ($payload['phone_number'] ?? '') . '|' . ($payload['email'] ?? ''));
        RateLimiter::hit('member-register', $registerIdentity, 5, 60 * 60);

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
        $this->clearWebStaffSession();
        RateLimiter::clear('member-register', $registerIdentity);
        Session::regenerate();
        Session::put('member_customer_id', (int) $member['id']);

        (new AuditLog())->record([
            'actor_type' => 'customer',
            'actor_id' => (int) $member['id'],
            'action' => 'member_register',
            'entity_type' => 'customer',
            'entity_id' => (int) $member['id'],
        ]);

        return ['member' => $member, 'web_staff' => null];
    }

    public function memberLogout(): array
    {
        $customerId = (int) Session::get('member_customer_id', 0);
        $staffId = (int) Session::get('web_staff_id', 0);
        $this->clearWebStaffSession();
        Session::forget('member_customer_id');
        Session::regenerate();

        if ($customerId > 0) {
            (new AuditLog())->record([
                'actor_type' => 'customer',
                'actor_id' => $customerId,
                'action' => 'member_logout',
                'entity_type' => 'customer',
                'entity_id' => $customerId,
            ]);
        } elseif ($staffId > 0) {
            (new AuditLog())->record([
                'actor_type' => 'staff',
                'actor_id' => $staffId,
                'action' => 'staff_web_logout',
                'entity_type' => 'staff',
                'entity_id' => $staffId,
            ]);
        }

        return ['member' => null, 'web_staff' => null];
    }

    public function memberProfileUpdate(array $payload): array
    {
        if ((int) Session::get('member_customer_id', 0) > 0) {
            $member = $this->currentMember();
            $updated = (new Customer())->updateProfile((int) $member['id'], $payload);
            (new AuditLog())->record([
                'actor_type' => 'customer',
                'actor_id' => (int) $member['id'],
                'action' => 'member_profile_update',
                'entity_type' => 'customer',
                'entity_id' => (int) $member['id'],
            ]);

            return ['member' => $updated, 'web_staff' => null];
        }

        $staff = $this->currentStaffAccount();
        $updated = (new Staff())->updateProfile((int) $staff['id'], $payload);
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) $staff['id'],
            'actor_role' => (string) $staff['staff_role'],
            'action' => 'staff_profile_update',
            'entity_type' => 'staff',
            'entity_id' => (int) $staff['id'],
        ]);

        return ['member' => null, 'web_staff' => $updated];
    }

    public function memberChangePassword(array $payload): array
    {
        $currentPassword = require_field($payload, 'current_password', 'Current password');
        $newPassword = require_field($payload, 'password', 'New password');
        $confirm = require_field($payload, 'password_confirm', 'Password confirmation');
        if (strlen($newPassword) < 6) {
            throw new InvalidArgumentException('Mật khẩu mới phải có ít nhất 6 ký tự.');
        }
        if ($newPassword !== $confirm) {
            throw new InvalidArgumentException('Mật khẩu xác nhận không khớp.');
        }

        if ((int) Session::get('member_customer_id', 0) > 0) {
            $member = $this->currentMember();
            $customer = new Customer();
            $hash = $customer->passwordHash((int) $member['id']);
            if (!$hash || !password_verify($currentPassword, $hash)) {
                throw new InvalidArgumentException('Mật khẩu hiện tại không đúng.');
            }

            $customer->updatePassword((int) $member['id'], password_hash($newPassword, PASSWORD_DEFAULT));
            (new AuditLog())->record([
                'actor_type' => 'customer',
                'actor_id' => (int) $member['id'],
                'action' => 'member_change_password',
                'entity_type' => 'customer',
                'entity_id' => (int) $member['id'],
            ]);

            return ['changed' => true, 'account_type' => 'customer'];
        }

        $staff = $this->currentStaffAccount();
        $staffModel = new Staff();
        $hash = $staffModel->passwordHash((int) $staff['id']);
        if (!$hash || !password_verify($currentPassword, $hash)) {
            throw new InvalidArgumentException('Mật khẩu hiện tại không đúng.');
        }

        $staffModel->updatePassword((int) $staff['id'], password_hash($newPassword, PASSWORD_DEFAULT));
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) $staff['id'],
            'actor_role' => (string) $staff['staff_role'],
            'action' => 'staff_change_password',
            'entity_type' => 'staff',
            'entity_id' => (int) $staff['id'],
        ]);

        return ['changed' => true, 'account_type' => 'staff'];
    }

    public function staffChangePassword(array $payload): array
    {
        $staff = $this->staffAccountForCredentialChange($payload);
        $currentPassword = require_field($payload, 'current_password', 'Current password');
        $newPassword = require_field($payload, 'password', 'New password');
        $confirm = require_field($payload, 'password_confirm', 'Password confirmation');
        if (strlen($newPassword) < 6) {
            throw new InvalidArgumentException('Mật khẩu mới phải có ít nhất 6 ký tự.');
        }
        if ($newPassword !== $confirm) {
            throw new InvalidArgumentException('Mật khẩu xác nhận không khớp.');
        }

        $staffModel = new Staff();
        $hash = $staffModel->passwordHash((int) $staff['id']);
        if (!$hash || !password_verify($currentPassword, $hash)) {
            throw new InvalidArgumentException('Mật khẩu hiện tại không đúng.');
        }

        $staffModel->updatePassword((int) $staff['id'], password_hash($newPassword, PASSWORD_DEFAULT));
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) $staff['id'],
            'actor_role' => (string) $staff['staff_role'],
            'action' => 'staff_change_password',
            'entity_type' => 'staff',
            'entity_id' => (int) $staff['id'],
        ]);

        return ['changed' => true, 'account_type' => 'staff'];
    }

    public function staffChangePin(array $payload): array
    {
        $staff = $this->staffAccountForCredentialChange($payload);
        $currentPin = require_field($payload, 'current_pin', 'Current PIN');
        $newPin = require_field($payload, 'pin', 'New PIN');
        $confirm = require_field($payload, 'pin_confirm', 'PIN confirmation');
        if (!ctype_digit($newPin) || strlen($newPin) < 4) {
            throw new InvalidArgumentException('PIN mới phải là số và có ít nhất 4 ký tự.');
        }
        if ($newPin !== $confirm) {
            throw new InvalidArgumentException('PIN xác nhận không khớp.');
        }

        $staffModel = new Staff();
        $hash = $staffModel->pinHash((int) $staff['id']);
        if (!$hash || !password_verify($currentPin, $hash)) {
            throw new InvalidArgumentException('PIN hiện tại không đúng.');
        }

        $staffModel->updatePin((int) $staff['id'], password_hash($newPin, PASSWORD_DEFAULT));
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) $staff['id'],
            'actor_role' => (string) $staff['staff_role'],
            'action' => 'staff_change_pin',
            'entity_type' => 'staff',
            'entity_id' => (int) $staff['id'],
        ]);

        return ['changed' => true, 'account_type' => 'staff'];
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

        $resetUrl = $this->absoluteUrl(base_url('reset-password?token=' . urlencode($token)));
        try {
            (new Mailer())->sendPasswordReset(
                (string) $account['email'],
                (string) $account['customer_name'],
                $resetUrl
            );
        } catch (RuntimeException $exception) {
            AppLogger::error($exception, [
                'action' => 'member_forgot_password_mail',
                'customer_id' => (int) $account['id'],
                'email' => (string) $account['email'],
            ]);
            throw new InvalidArgumentException(
                'Không thể gửi email đặt lại mật khẩu. Vui lòng kiểm tra cấu hình Gmail SMTP trong config/mail.local.php.'
            );
        }

        (new AuditLog())->record([
            'actor_type' => 'customer',
            'actor_id' => (int) $account['id'],
            'action' => 'member_forgot_password',
            'entity_type' => 'customer',
            'entity_id' => (int) $account['id'],
        ]);

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
        Session::regenerate();
        (new AuditLog())->record([
            'actor_type' => 'customer',
            'actor_id' => (int) $reset['customer_id'],
            'action' => 'member_reset_password',
            'entity_type' => 'customer',
            'entity_id' => (int) $reset['customer_id'],
        ]);

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

    private function staffAccountForCredentialChange(array $payload): array
    {
        $webStaff = $this->currentWebStaff();
        if ($webStaff) {
            return $webStaff;
        }

        $staffId = (int) ($payload['staff_id'] ?? 0);
        if ($staffId <= 0) {
            throw new InvalidArgumentException('Vui lòng đăng nhập tài khoản nhân viên.');
        }

        $staff = (new Staff())->find($staffId);
        if (!$staff) {
            throw new InvalidArgumentException('Không tìm thấy tài khoản nhân viên.');
        }

        (new PosSession())->requireOpen($payload, $staff);

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

    private function currentWebStaff(): ?array
    {
        $staffId = (int) Session::get('web_staff_id', 0);
        $sessionId = (int) Session::get('web_staff_auth_session_id', 0);
        $token = (string) Session::get('web_staff_auth_token', '');
        if ($staffId <= 0 || $sessionId <= 0 || $token === '') {
            return null;
        }

        $payload = (new StaffAuthSession())->current([
            'staff_id' => $staffId,
            'auth_session_id' => $sessionId,
            'auth_token' => $token,
        ]);

        if (empty($payload['staff'])) {
            $this->forgetWebStaffSession();
            return null;
        }

        return $payload['staff'];
    }

    private function currentStaffAccount(): array
    {
        $staff = $this->currentWebStaff();
        if (!$staff) {
            throw new InvalidArgumentException('Vui lòng đăng nhập tài khoản nhân viên.');
        }

        return $staff;
    }

    private function clearWebStaffSession(): void
    {
        $sessionId = (int) Session::get('web_staff_auth_session_id', 0);
        $token = (string) Session::get('web_staff_auth_token', '');
        if ($sessionId > 0 && $token !== '') {
            try {
                (new StaffAuthSession())->logout([
                    'auth_session_id' => $sessionId,
                    'auth_token' => $token,
                ]);
            } catch (InvalidArgumentException) {
            }
        }

        $this->forgetWebStaffSession();
    }

    private function forgetWebStaffSession(): void
    {
        Session::forget('web_staff_id');
        Session::forget('web_staff_auth_session_id');
        Session::forget('web_staff_auth_token');
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

    private function absoluteUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return $path;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';

        return ($https ? 'https' : 'http') . '://' . $host . '/' . ltrim($path, '/');
    }
}
