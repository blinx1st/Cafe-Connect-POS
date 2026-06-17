<?php

declare(strict_types=1);

namespace App\Core;

final class RolePolicy
{
    public const MODULES = [
        'checkout' => ['label' => 'POS bán hàng', 'roles' => ['cashier', 'manager', 'owner', 'admin']],
        'orders' => ['label' => 'Bàn & order', 'roles' => ['waiter', 'cashier', 'manager', 'owner', 'admin']],
        'kitchen' => ['label' => 'Bếp pha chế', 'roles' => ['barista', 'waiter', 'manager', 'owner', 'admin']],
        'dashboard' => ['label' => 'Dashboard', 'roles' => ['manager', 'owner', 'admin']],
        'customers' => ['label' => 'Khách hàng', 'roles' => ['cashier', 'marketing', 'manager', 'owner', 'admin']],
        'campaigns' => ['label' => 'Campaign', 'roles' => ['marketing', 'manager', 'owner', 'admin']],
        'inventory' => ['label' => 'Kho', 'roles' => ['manager', 'owner', 'admin']],
        'reports' => ['label' => 'Báo cáo', 'roles' => ['manager', 'owner', 'admin']],
        'products' => ['label' => 'Sản phẩm', 'roles' => ['manager', 'owner', 'admin']],
        'staff' => ['label' => 'Nhân viên', 'roles' => ['owner', 'admin']],
        'cash' => ['label' => 'Thu chi', 'roles' => ['cashier', 'manager', 'owner', 'admin']],
    ];

    private const ENDPOINT_ROLES = [
        '/api/pos-session-report' => ['manager', 'owner', 'admin'],
        '/api/customer-create' => ['cashier', 'marketing', 'manager', 'owner', 'admin'],
        '/api/checkout' => ['cashier', 'manager', 'owner', 'admin'],
        '/api/orders' => ['waiter', 'cashier', 'manager', 'owner', 'admin'],
        '/api/create-order' => ['waiter', 'manager', 'owner', 'admin'],
        '/api/update-order-item' => ['barista', 'waiter', 'manager', 'owner', 'admin'],
        '/api/void-order-item' => ['waiter', 'manager', 'owner', 'admin'],
        '/api/cancel-order' => ['manager', 'owner', 'admin'],
        '/api/order-status-update' => ['waiter', 'cashier', 'manager', 'owner', 'admin'],
        '/api/kitchen' => ['barista', 'waiter', 'manager', 'owner', 'admin'],
        '/api/checkout-order' => ['cashier', 'manager', 'owner', 'admin'],
        '/api/refund-invoice' => ['manager', 'owner', 'admin'],
        '/api/receipt' => ['cashier', 'manager', 'owner', 'admin'],
        '/api/receipt-print-log' => ['cashier', 'manager', 'owner', 'admin'],
        '/api/checkout-closing' => ['cashier', 'manager', 'owner', 'admin'],
        '/api/shift-closing' => ['cashier', 'manager', 'owner', 'admin'],
        '/api/dashboard' => ['manager', 'owner', 'admin'],
        '/api/campaigns' => ['marketing', 'manager', 'owner', 'admin'],
        '/api/create-campaign' => ['marketing', 'manager', 'owner', 'admin'],
        '/api/campaign-save' => ['marketing', 'manager', 'owner', 'admin'],
        '/api/campaign-delete' => ['marketing', 'manager', 'owner', 'admin'],
        '/api/campaign-restore' => ['marketing', 'manager', 'owner', 'admin'],
        '/api/inventory' => ['manager', 'owner', 'admin'],
        '/api/stock-movement' => ['manager', 'owner', 'admin'],
        '/api/cash-transaction' => ['cashier', 'manager', 'owner', 'admin'],
        '/api/product-list' => ['manager', 'owner', 'admin'],
        '/api/product-save' => ['manager', 'owner', 'admin'],
        '/api/product-delete' => ['manager', 'owner', 'admin'],
        '/api/product-restore' => ['manager', 'owner', 'admin'],
        '/api/product-image-upload' => ['manager', 'owner', 'admin'],
        '/api/category-save' => ['manager', 'owner', 'admin'],
        '/api/content-save' => ['manager', 'owner', 'admin'],
        '/api/staff-list' => ['owner', 'admin'],
        '/api/staff-save' => ['owner', 'admin'],
        '/api/staff-delete' => ['owner', 'admin'],
        '/api/staff-restore' => ['owner', 'admin'],
        '/api/reports' => ['manager', 'owner', 'admin'],
        '/api/reports-export' => ['manager', 'owner', 'admin'],
    ];

    public static function modulesForRole(string $role): array
    {
        return array_values(array_filter(
            self::moduleList(),
            static fn (array $module): bool => in_array($role, $module['roles'], true)
        ));
    }

    public static function moduleList(): array
    {
        $modules = [];
        foreach (self::MODULES as $id => $definition) {
            $modules[] = ['id' => $id] + $definition;
        }

        return $modules;
    }

    public static function canAccessModule(string $role, string $module): bool
    {
        return isset(self::MODULES[$module]) && in_array($role, self::MODULES[$module]['roles'], true);
    }

    public static function canAccessCustomerCrm(string $role): bool
    {
        return self::canAccessModule($role, 'customers');
    }

    public static function rolesForEndpoint(string $endpoint): ?array
    {
        return self::ENDPOINT_ROLES[$endpoint] ?? null;
    }

    public static function canCallEndpoint(string $role, string $endpoint): bool
    {
        $roles = self::rolesForEndpoint($endpoint);
        return $roles === null || in_array($role, $roles, true);
    }

    public static function canTransitionKitchenStatus(string $role, string $from, string $to): bool
    {
        $valid = ['waiting', 'preparing', 'ready', 'served'];
        if (!in_array($from, $valid, true) || !in_array($to, $valid, true)) {
            return false;
        }
        if (self::isOverrideRole($role)) {
            return true;
        }
        if ($role === 'barista') {
            return in_array($from, ['waiting', 'preparing'], true)
                && in_array($to, ['preparing', 'ready'], true);
        }
        if ($role === 'waiter') {
            return $from === 'ready' && $to === 'served';
        }

        return false;
    }

    public static function canVoidItem(string $role, string $status): bool
    {
        if ($status === 'cancelled') {
            return false;
        }
        if (self::isOverrideRole($role)) {
            return true;
        }

        return $role === 'waiter' && !in_array($status, ['ready', 'served'], true);
    }

    public static function isOverrideRole(string $role): bool
    {
        return in_array($role, ['manager', 'owner', 'admin'], true);
    }

    public static function permissionsPayload(): array
    {
        return [
            'modules' => self::moduleList(),
            'endpoint_roles' => self::ENDPOINT_ROLES,
            'override_roles' => ['manager', 'owner', 'admin'],
        ];
    }
}
