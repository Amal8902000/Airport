<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function current_user(): array
{
    return $_SESSION['user'] ?? [];
}

function role_pages(string $role = null): array
{
    $pages = [
        'accueil' => ['label' => 'Accueil', 'url' => 'accueil.php', 'description' => 'Tableau de bord personnel.'],
        'equipements' => ['label' => 'Equipements', 'url' => 'equipements.php', 'description' => 'Inventaire et fiche des equipements.'],
        'preventive' => ['label' => 'Preventive', 'url' => 'preventive.php', 'description' => 'Planning et cloture des maintenances.'],
        'corrective' => ['label' => 'Corrective', 'url' => 'corrective.php', 'description' => 'Declaration et traitement des pannes.'],
        'disponibilite' => ['label' => 'Disponibilite', 'url' => 'disponibilite.php', 'description' => 'Disponibilite des equipements.'],
        'trp' => ['label' => 'TRP', 'url' => 'trp.php', 'description' => 'Taux de rendement du parc.'],
        'utilisation' => ['label' => 'Utilisation', 'url' => 'utilisation.php', 'description' => 'Utilisation et interventions.'],
    ];

    $allowed = [
        'admin' => array_keys($pages),
        'responsable' => ['accueil', 'equipements', 'preventive', 'corrective', 'disponibilite', 'trp', 'utilisation'],
        'superviseur' => ['accueil', 'preventive', 'corrective', 'disponibilite', 'utilisation'],
        'technicien' => ['accueil', 'equipements', 'preventive', 'corrective'],
        'agent_exploitation' => ['accueil', 'corrective'],
    ];

    $roleAllowed = $allowed[$role ?? ''] ?? ['accueil'];
    return array_intersect_key($pages, array_flip($roleAllowed));
}

function can_access_page(string $page): bool
{
    $role = current_user()['role'] ?? '';
    return isset(role_pages($role)[$page]);
}

function has_role(array $roles): bool
{
    return in_array(current_user()['role'] ?? '', $roles, true);
}

function require_page_access(string $page): void
{
    require_login();
    if (!can_access_page($page)) {
        header('Location: accueil.php?denied=1');
        exit;
    }
}

function role_label(string $role = null): string
{
    $labels = [
        'admin' => 'Administrateur',
        'technicien' => 'Technicien',
        'responsable' => 'Responsable',
        'superviseur' => 'Superviseur',
        'agent_exploitation' => 'Agent exploitation',
    ];

    return $labels[$role ?? ''] ?? 'Utilisateur';
}

function e(string $value = null): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function render_navbar(string $active = ''): void
{
    $user = current_user();
    $name = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
    $pages = role_pages($user['role'] ?? '');
    ?>
    <nav class="navbar">
      <?php foreach ($pages as $key => $page): ?>
        <a href="<?= e($page['url']) ?>" data-page="<?= e($key) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($page['label']) ?></a>
      <?php endforeach; ?>
      <span class="user-info"><?= e($name ?: ($user['email'] ?? 'Utilisateur')) ?> - <?= e(role_label($user['role'] ?? '')) ?> - <?= e($user['service'] ?? '') ?></span>
      <button class="btn-logout" onclick="window.location.href='../api/logout.php'">Deconnexion</button>
    </nav>
    <script>window.USER_ROLE = <?= json_encode($user['role'] ?? '') ?>;</script>
    <?php
}
