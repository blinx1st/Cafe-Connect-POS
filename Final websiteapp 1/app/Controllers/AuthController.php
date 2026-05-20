<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
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
        $email = trim((string) ($payload['email'] ?? ''));
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
}
