<?php

declare(strict_types=1);

namespace App\Domain\Access;

/**
 * The single declaration of every permission and default role in the system.
 *
 * The seeder, the admin UI and the tests all read from here, which means a
 * permission cannot exist without being declared, and a role cannot silently drift
 * from what is documented. Adding a permission is one line in {@see self::RESOURCES}.
 */
final class PermissionCatalog
{
    /**
     * Resource → the actions that are meaningful for it.
     *
     * Actions are only listed where they mean something: there is no `posts.restore`
     * without soft deletes, and no `invoices.delete` because financial records are
     * never deleted.
     *
     * @var array<string, list<string>>
     */
    public const RESOURCES = [
        // Content
        'posts' => ['view_any', 'view', 'create', 'update', 'update.own', 'delete', 'restore', 'publish'],
        'post_categories' => ['view_any', 'create', 'update', 'delete'],
        'tags' => ['view_any', 'create', 'update', 'delete'],
        'media' => ['view_any', 'create', 'update', 'update.own', 'delete', 'delete.own'],
        'seo' => ['update'],
        // No `restore`: a release is hard-deleted, so there is nothing to restore to.
        'changelog' => ['view_any', 'view', 'create', 'update', 'delete', 'publish'],

        // Tools
        'tools' => ['view_any', 'view', 'create', 'update', 'delete', 'publish', 'bypass_access', 'bypass_quota'],
        'tool_categories' => ['view_any', 'create', 'update', 'delete'],
        'tool_grants' => ['view_any', 'create', 'delete'],
        'tool_analytics' => ['view'],

        // People
        'users' => ['view_any', 'view', 'create', 'update', 'suspend', 'delete', 'impersonate'],
        'roles' => ['view_any', 'manage'],

        // Commerce
        'plans' => ['view_any', 'create', 'update', 'delete'],
        'subscriptions' => ['view_any', 'view', 'update', 'cancel'],
        'invoices' => ['view_any', 'view', 'export'],
        'refunds' => ['create'],

        // Support
        'tickets' => ['view_any', 'view', 'update', 'assign', 'reply', 'close', 'delete'],
        'canned_responses' => ['view_any', 'create', 'update', 'delete'],

        // Platform
        'settings' => ['view', 'update'],
        'settings.scripts' => ['update'],   // separate: this is arbitrary code on the site
        'settings.secrets' => ['update'],   // separate: provider API keys
        'newsletter' => ['view', 'update', 'export'],
        'analytics' => ['view', 'export'],
        'activity_log' => ['view'],
        'reports' => ['export'],
    ];

    /**
     * Seeded roles. These are a starting point an admin can edit — not a hierarchy
     * baked into the code.
     *
     * "Some editors can view only, some can edit but not delete" is expressed here as
     * different permission sets rather than as role tiers, so an admin can compose any
     * variation they need without a deploy.
     *
     * @var array<string, array{description: string, permissions: list<string>}>
     */
    public const ROLES = [
        'super-admin' => [
            'description' => 'Unrestricted access. Bypasses every check via Gate::before. At least one must always exist.',
            'permissions' => ['*'],
        ],

        'admin' => [
            'description' => 'Runs the platform day to day, without the ability to escalate its own privileges.',
            'permissions' => ['*'],
            // Excluded below — an admin must not be able to grant itself more power,
            // read provider secrets, or log in as a customer.
        ],

        'editor' => [
            'description' => 'Owns the blog and the media library end to end, including publishing.',
            'permissions' => [
                'posts.view_any', 'posts.view', 'posts.create', 'posts.update', 'posts.delete', 'posts.restore', 'posts.publish',
                'post_categories.*', 'tags.*', 'media.*', 'seo.update',
                'changelog.*',
                'tools.view_any', 'analytics.view',
            ],
        ],

        'editor-restricted' => [
            'description' => 'Writes and edits posts but cannot publish or delete them. The common "junior editor" shape.',
            'permissions' => [
                'posts.view_any', 'posts.view', 'posts.create', 'posts.update',
                'post_categories.view_any', 'tags.view_any', 'tags.create',
                'changelog.view_any', 'changelog.view', 'changelog.create', 'changelog.update',
                'media.view_any', 'media.create', 'media.update.own', 'seo.update',
            ],
        ],

        'contributor' => [
            'description' => 'Writes drafts of their own posts only. Cannot touch anyone else’s work.',
            'permissions' => [
                'posts.view_any', 'posts.create', 'posts.update.own',
                'media.view_any', 'media.create', 'media.update.own', 'media.delete.own',
                'tags.view_any',
            ],
        ],

        'support' => [
            'description' => 'Handles customer tickets and can comp a tool for a user to resolve a complaint.',
            'permissions' => [
                'tickets.*', 'canned_responses.view_any', 'canned_responses.create', 'canned_responses.update',
                'users.view_any', 'users.view',
                'tool_grants.view_any', 'tool_grants.create',
                'subscriptions.view_any', 'subscriptions.view',
                'tools.view_any', 'tools.bypass_access',
            ],
        ],

        'accountant' => [
            'description' => 'Owns billing: invoices, subscriptions, refunds and financial exports.',
            'permissions' => [
                'invoices.*', 'refunds.create',
                'subscriptions.view_any', 'subscriptions.view', 'subscriptions.update', 'subscriptions.cancel',
                'plans.view_any', 'plans.create', 'plans.update', 'plans.delete',
                'users.view_any', 'users.view',
                'reports.export', 'analytics.view',
            ],
        ],

        'analyst' => [
            'description' => 'Read-only access to product and tool analytics.',
            'permissions' => ['analytics.view', 'analytics.export', 'tool_analytics.view', 'reports.export', 'tools.view_any', 'posts.view_any'],
        ],
    ];

    /** Permissions an `admin` must not hold — the separation that keeps `super-admin` meaningful. */
    public const ADMIN_EXCLUSIONS = [
        'roles.manage',
        'settings.secrets.update',
        'users.impersonate',
        'users.delete',
    ];

    /**
     * Every permission name, expanded from {@see self::RESOURCES}.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::RESOURCES as $resource => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        return $permissions;
    }

    /**
     * Resolve a role's permission list, expanding `*` wildcards.
     *
     * @return list<string>
     */
    public static function permissionsFor(string $role): array
    {
        $definition = self::ROLES[$role] ?? null;

        if ($definition === null) {
            return [];
        }

        $resolved = [];

        foreach ($definition['permissions'] as $pattern) {
            if ($pattern === '*') {
                $resolved = self::all();
                break;
            }

            if (str_ends_with($pattern, '.*')) {
                $resource = substr($pattern, 0, -2);

                foreach (self::RESOURCES[$resource] ?? [] as $action) {
                    $resolved[] = "{$resource}.{$action}";
                }

                continue;
            }

            $resolved[] = $pattern;
        }

        if ($role === 'admin') {
            $resolved = array_values(array_diff($resolved, self::ADMIN_EXCLUSIONS));
        }

        return array_values(array_unique(array_intersect($resolved, self::all())));
    }

    /**
     * Human-readable grouping for the admin role editor.
     *
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (array_keys(self::RESOURCES) as $resource) {
            $groups[str_contains($resource, '.') ? explode('.', $resource)[0] : $resource][] = $resource;
        }

        return $groups;
    }
}
