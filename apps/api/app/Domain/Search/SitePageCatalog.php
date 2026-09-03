<?php

declare(strict_types=1);

namespace App\Domain\Search;

use App\Domain\Search\Data\SearchResult;
use App\Domain\Search\Enums\SearchResultType;
use App\Domain\Settings\Settings;

/**
 * The site's hand-written pages, as search candidates.
 *
 * Everything else search covers is a database row. These are React components in
 * the frontend, so there is nothing to query — and a global search that cannot find
 * "privacy policy" or "contact" is a search people stop trusting after one try.
 *
 * The list is duplicated knowledge, and that is the honest trade: the alternative
 * is the frontend POSTing its own route table to the API on every deploy, which is
 * a synchronisation problem in exchange for a copied array. It is small, it changes
 * about once a year, and `SearchTest` asserts every entry still resolves.
 *
 * `keywords` is the body text these entries are matched on. A visitor searching for
 * "refund" or "cancel" is looking for the terms page, and the terms page does not
 * contain either word in its title or its blurb.
 */
final readonly class SitePageCatalog
{
    /**
     * @var list<array{path: string, title: string, summary: string, keywords: string, feature?: string}>
     */
    private const PAGES = [
        [
            'path' => '/',
            'title' => 'Home',
            'summary' => 'A professional toolkit for creators and influencers — analyze, optimize and grow your accounts across every major network.',
            'keywords' => 'home start homepage creator toolkit social media growth analytics',
        ],
        [
            'path' => '/tools',
            'title' => 'All Tools',
            'summary' => 'Browse the full catalog of creator tools — calculators, generators, downloaders, previews and analyzers for every major platform.',
            'keywords' => 'tools catalog directory browse all free tools generators calculators downloaders analyzers',
        ],
        [
            'path' => '/top-ranking',
            'title' => 'Top Rankings',
            'summary' => 'Leaderboards of the biggest accounts on every major network, by followers, subscribers and views.',
            'keywords' => 'top ranking leaderboard biggest most followed accounts charts records',
        ],
        [
            'path' => '/blog',
            'title' => 'Blog',
            'summary' => 'Playbooks, teardowns and analysis for creators, across every major platform.',
            'keywords' => 'blog articles guides playbooks tutorials news field notes',
            'feature' => 'features.blog_enabled',
        ],
        [
            'path' => '/changelog',
            'title' => 'Changelog',
            'summary' => 'What shipped, and when — every release of MetaCreator.Dev.',
            'keywords' => 'changelog releases updates what is new versions history',
            'feature' => 'features.changelog_enabled',
        ],
        [
            'path' => '/pricing',
            'title' => 'Pricing',
            'summary' => 'Free tools, a weekly pass, or a Pro plan — what each one includes and what it costs.',
            'keywords' => 'pricing plans cost price subscription upgrade pro premium weekly pass free trial billing',
            'feature' => 'features.billing_enabled',
        ],
        [
            'path' => '/about',
            'title' => 'About',
            'summary' => 'Who builds MetaCreator.Dev, and why the tools work the way they do.',
            'keywords' => 'about us company team story mission who we are',
        ],
        [
            'path' => '/contact',
            'title' => 'Contact',
            'summary' => 'How to reach us — support, questions, partnerships and press.',
            'keywords' => 'contact support help email get in touch reach us feedback partnership press',
        ],
        [
            'path' => '/security',
            'title' => 'Security',
            'summary' => 'How your data is protected, and how to report a vulnerability responsibly.',
            'keywords' => 'security vulnerability disclosure report bug bounty data protection encryption',
        ],
        [
            'path' => '/terms',
            'title' => 'Terms of Service',
            'summary' => 'The terms that govern your use of MetaCreator.Dev.',
            'keywords' => 'terms of service conditions agreement legal refund cancel cancellation liability acceptable use',
        ],
        [
            'path' => '/privacy',
            'title' => 'Privacy Policy',
            'summary' => 'What we collect, why we collect it, and what we never do with it.',
            'keywords' => 'privacy policy data gdpr personal information tracking delete my data retention',
        ],
        [
            'path' => '/cookies',
            'title' => 'Cookie Policy',
            'summary' => 'The cookies this site sets, what each one is for, and how to refuse them.',
            'keywords' => 'cookies policy tracking consent analytics local storage',
        ],
    ];

    public function __construct(private Settings $settings) {}

    /**
     * Every page a visitor can currently reach.
     *
     * Feature-gated entries drop out when their switch is off, for the same reason
     * the sitemap drops them: a search result that leads to a 404 is worse than no
     * result at all.
     *
     * @return list<SearchResult>
     */
    public function all(): array
    {
        $pages = [];

        foreach (self::PAGES as $page) {
            if (isset($page['feature']) && ! $this->settings->bool($page['feature'], true)) {
                continue;
            }

            $pages[] = new SearchResult(
                type: SearchResultType::Page,
                id: 'page:'.$page['path'],
                title: $page['title'],
                url: $page['path'],
                summary: $page['summary'],
                image: null,
                score: 0,
                context: null,
            );
        }

        return $pages;
    }

    /**
     * The body text a page is matched against, keyed by path.
     *
     * Kept beside `all()` rather than folded into the result object: `keywords` is
     * a matching aid, and putting it on the DTO would tempt a renderer into showing
     * a bag of search terms to a reader.
     *
     * @return array<string, string>
     */
    public function haystacks(): array
    {
        $map = [];

        foreach (self::PAGES as $page) {
            $map[$page['path']] = $page['keywords'];
        }

        return $map;
    }
}
