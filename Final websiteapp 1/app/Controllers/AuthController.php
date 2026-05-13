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
        $member = (new Customer())->lookup(require_field($payload, 'identity', 'Phone or email'));
        if (!$member) {
            throw new InvalidArgumentException('Không tìm thấy thành viên với số điện thoại hoặc email này.');
        }

        Session::put('member_customer_id', (int) $member['id']);
        return ['member' => $member];
    }

    public function memberRegister(array $payload): array
    {
        $payload['preferred_channel'] = 'website';
        $member = (new Customer())->create($payload);
        if (!$member) {
            throw new InvalidArgumentException('Không thể tạo thành viên mới.');
        }

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
