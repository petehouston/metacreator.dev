<?php

declare(strict_types=1);

/**
 * HTML sanitisation profiles.
 *
 * Two profiles, because the two places we accept HTML have very different risk:
 *
 * - `richtext` is the inline markup inside a paragraph/heading/quote block. Only
 *   text-level marks are allowed; there is no reason for a paragraph to contain a
 *   div, a script or a style attribute.
 * - `embed` is the raw `html` block, where an editor is deliberately pasting
 *   markup. It is wider, but still no script, no event handlers, and iframes only
 *   from providers we name.
 *
 * Both run on save *and* again on render (docs/21-security.md): stored content is
 * treated as untrusted, because a profile can be tightened after something was
 * already saved under a looser one.
 */
return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,

    'settings' => [
        // `mark` is HTML5 and the profiles below declare an HTML 4.01 doctype, so
        // HTMLPurifier does not know it. Registering it here — rather than dropping
        // highlight from the editor — keeps the mark available in every profile.
        // Applies to all profiles; see Mews\Purifier\Purifier::getConfig().
        'custom_elements' => [
            ['mark', 'Inline', 'Inline', 'Common'],
            ['figure', 'Block', 'Flow', 'Common'],
            ['figcaption', 'Block', 'Flow', 'Common'],
        ],

        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            // Required before HTMLPurifier will accept the custom `mark` element above.
            'HTML.DefinitionID' => 'metacreator/default',
            'HTML.DefinitionRev' => 1,
            'HTML.Allowed' => 'p,br,b,strong,i,em,u,s,del,mark,code,a[href|title|rel|target],ul,ol,li',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
            'HTML.TargetBlank' => true,
            'Attr.AllowedRel' => 'nofollow,noopener,noreferrer',
        ],

        // Inline marks only. Deliberately no block elements: block structure is the
        // job of the block array, not of markup inside one block.
        'richtext' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            // Required before HTMLPurifier will accept the custom `mark` element above.
            'HTML.DefinitionID' => 'metacreator/richtext',
            'HTML.DefinitionRev' => 1,
            'HTML.Allowed' => 'b,strong,i,em,u,s,del,mark,code,sub,sup,br,a[href|title|rel|target]',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
            'HTML.TargetBlank' => true,
            'Attr.AllowedRel' => 'nofollow,noopener,noreferrer',
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
        ],

        // The `html` block. Wider, but scripts, forms, styles and event handlers are
        // all still dropped, and iframes are limited to the named providers.
        'embed' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            // Required before HTMLPurifier will accept the custom `mark` element above.
            'HTML.DefinitionID' => 'metacreator/embed',
            'HTML.DefinitionRev' => 1,
            'HTML.Allowed' => 'div[class],p,br,hr,h2,h3,h4,b,strong,i,em,u,s,del,mark,code,pre,'
                .'a[href|title|rel|target],ul,ol,li,blockquote[cite],'
                .'img[src|alt|title|width|height],figure,figcaption,'
                .'table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],'
                .'iframe[src|width|height|title|frameborder]',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
            'HTML.TargetBlank' => true,
            'Attr.AllowedRel' => 'nofollow,noopener,noreferrer',
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
            'HTML.SafeIframe' => true,
            'URI.SafeIframeRegexp' => '%^(https?:)?//('
                .'www\.youtube\.com/embed/|'
                .'www\.youtube-nocookie\.com/embed/|'
                .'player\.vimeo\.com/video/|'
                .'platform\.twitter\.com/|'
                .'www\.instagram\.com/embed|'
                .'www\.tiktok\.com/embed|'
                .'codepen\.io/|'
                .'open\.spotify\.com/embed'
                .')%',
        ],
    ],
];
