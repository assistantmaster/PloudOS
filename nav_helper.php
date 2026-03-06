<?php
/**
 * nav_helper.php
 * Erwartet: $user (array), $current (string, Seitenname)
 */
$perm = (int)($user['permissions'] ?? 0);
$navItems = [
    'dashboard'   => ['url' => 'dashboard.php',   'label' => 'Übersicht'],
    'console'     => ['url' => 'console.php',      'label' => 'Konsole'],
    'config'      => ['url' => 'config.php',       'label' => 'Konfiguration'],
    'plugins'     => ['url' => 'plugins.php',      'label' => 'Plugins'],
    'stats'       => ['url' => 'stats.php',        'label' => 'Player-Stats'],
    'bluemap'     => ['url' => 'bluemap.php',      'label' => 'BlueMap'],
    'screenshots' => ['url' => 'screenshots.php',  'label' => 'Screenshots &amp; Waypoints'],
    'players'     => ['url' => 'players.php',      'label' => 'Spieler'],
];
if ($perm >= 3) {
    $navItems['backups'] = ['url' => 'backups.php', 'label' => 'Backups', 'admin' => true];
    $navItems['users']   = ['url' => 'users.php',   'label' => 'Benutzer', 'admin' => true];
}
?>
<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar">
                <span class="sr-only">Navigation umschalten</span>
                <span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="dashboard.php">
                <img alt="Logo" height="50" src="assets/PloudOS-Small.png">
            </a>
        </div>
        <div id="navbar" class="navbar-collapse collapse">
            <ul class="nav navbar-nav">
                <li><a href="dashboard.php">Startseite</a></li>
                <li><a href="#">Über uns</a></li>
                <li><a href="#">Twitter</a></li>
                <li><a href="https://discord.gg/mg8zcK9ZVa">Discord</a></li>
                <li><a href="#">FAQ</a></li>
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <?php if (isset($user)): ?>
                <li><a href="dashboard.php">Server verwalten</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <?php echo htmlspecialchars($user['username']); ?> <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="update_profile.php"><i class="fa fa-pencil"></i> Profil bearbeiten</a></li>
                        <li class="divider"></li>
                        <li><a href="logout.php"><i class="fa fa-sign-out"></i> Abmelden</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li><a href="login.php"><i class="fa fa-sign-in"></i> Login</a></li>
                <li><a href="register.php"><i class="fa fa-user-plus"></i> Registrieren</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php if (isset($navItems)): ?>
<div class="sidebar">
    <ul>
        <?php $adminSepShown = false; foreach ($navItems as $key => $item): ?>
        <?php if (!empty($item['admin']) && !$adminSepShown): $adminSepShown = true; ?>
        <li class="sidebar-sep" style="border-top:1px solid #444;margin:6px 0;padding:0;height:1px;list-style:none;pointer-events:none;"></li>
        <?php endif; ?>
        <li class="<?= ($current ?? '') === $key ? 'active' : '' ?>" <?= !empty($item['admin']) ? 'style="opacity:0.85;"' : '' ?>>
            <a href="<?= $item['url'] ?>"><?= $item['label'] ?></a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
