<?php

declare(strict_types=1);

namespace App\Domain\Tools\Enums;

enum ToolStatus: string
{
    /** Being built; visible to staff only. */
    case Draft = 'draft';

    /** Live in the catalog. */
    case Published = 'published';

    /** Temporarily withdrawn; the page 404s but the record and history survive. */
    case Hidden = 'hidden';

    /** Superseded. Still runnable, but the page carries a banner and a successor link. */
    case Deprecated = 'deprecated';

    /** Should this tool appear in the public catalog and sitemap? */
    public function isPublic(): bool
    {
        return $this === self::Published || $this === self::Deprecated;
    }

    /** May a run be executed at all? */
    public function isRunnable(): bool
    {
        return $this !== self::Hidden;
    }
}
