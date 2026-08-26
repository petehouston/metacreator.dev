<?php

declare(strict_types=1);

namespace App\Domain\Blog\Blocks;

/**
 * The block types shipped at launch.
 *
 * Adding one means: a case here, a branch in {@see BlockSanitizer}, and a renderer
 * component in the frontend registry. Nothing else in the codebase needs to know.
 *
 * Unknown types are never dropped — {@see BlockSanitizer} preserves them so content
 * written by a newer deploy survives a rollback.
 */
enum BlockType: string
{
    case Paragraph = 'paragraph';
    case Heading = 'heading';
    case ListBlock = 'list';
    case Quote = 'quote';
    case Image = 'image';
    case Embed = 'embed';
    case Code = 'code';
    case Html = 'html';
    case Callout = 'callout';
    case Divider = 'divider';
    case Table = 'table';
    case Button = 'button';
    case ToolCard = 'toolCard';
    case Faq = 'faq';

    /** Does this block contribute to word count, reading time and search text? */
    public function isProse(): bool
    {
        return match ($this) {
            self::Paragraph, self::Heading, self::ListBlock,
            self::Quote, self::Callout, self::Faq => true,
            default => false,
        };
    }
}
