<?php
require_once __DIR__ . '/access_control.php';

$requestUri = $_SERVER['REQUEST_URI'] ?? '';

$userSession = $_SESSION['user'] ?? [];
$userRole = userRoleLabel($userSession['role'] ?? '');
$userName = $userSession['name'] ?? 'User';
$userInitial = strtoupper(substr($userRole ?: $userName, 0, 1));

$navItems = [
    ['label' => 'Dashboard', 'href' => BASE_URL . 'dashboard.php', 'icon' => 'fa-table-cells-large', 'match' => 'dashboard.php'],
    ['label' => 'Asset Registry', 'href' => BASE_URL . 'modules/asset_registry/index.php', 'icon' => 'fa-boxes-stacked', 'match' => '/asset_registry/'],
    ['label' => 'Inventory', 'href' => BASE_URL . 'modules/inventory/index.php', 'icon' => 'fa-warehouse', 'match' => '/inventory/'],
    ['label' => 'Maintenance', 'href' => BASE_URL . 'modules/maintenance/index.php', 'icon' => 'fa-wrench', 'match' => '/maintenance/'],
    ['label' => 'Procurement', 'href' => BASE_URL . 'modules/procurement/index.php', 'icon' => 'fa-cart-shopping', 'match' => '/procurement/'],
    ['label' => 'Audit', 'href' => BASE_URL . 'modules/audit/index.php', 'icon' => 'fa-clipboard-check', 'match' => '/audit/'],
    ['label' => 'Reports', 'href' => BASE_URL . 'modules/reports/index.php', 'icon' => 'fa-chart-bar', 'match' => '/reports/'],
];

if (isAdministrator()) {
    $navItems[] = [
        'label' => 'User Management',
        'href' => BASE_URL . 'modules/users/index.php',
        'icon' => 'fa-users-gear',
        'match' => '/users/',
    ];
}

function pcmsNavIsActive(string $requestUri, string $match): bool
{
    return strpos($requestUri, $match) !== false;
}
?>

<aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">

    <div class="sidebar-inner">

        <div class="sidebar-brand">
            <img src="<?= BASE_URL ?>assets/images/logo.png" alt="Logo" class="sidebar-logo">
            <div class="sidebar-brand-text">
                <h2>Navigation</h2>
            </div>
        </div>

        <nav>
            <ul>
                <?php foreach ($navItems as $item): ?>
                    <?php $isActive = pcmsNavIsActive($requestUri, $item['match']); ?>
                    <li>
                        <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $isActive ? 'active' : '' ?>">
                            <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="sidebar-account-footer">
            <div class="sidebar-account-user">
                <div class="sidebar-user-avatar" aria-hidden="true"><?= htmlspecialchars($userInitial) ?></div>
                <div class="sidebar-user-info">
                    <span class="sidebar-user-name"><?= htmlspecialchars($userRole) ?></span>
                    <span class="sidebar-user-role"><?= htmlspecialchars($userName) ?></span>
                </div>
            </div>
            <a href="<?= BASE_URL ?>auth/logout.php" class="sidebar-logout-btn">
                <i class="fas fa-right-from-bracket"></i>
                Logout
            </a>
        </div>

    </div>

</aside>
