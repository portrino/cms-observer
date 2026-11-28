<?php

declare(strict_types=1);

namespace App\Service\Demo;

use App\Entity\CmsType;

/**
 * The projects of the demo seed (app:demo:seed): fictional agencies' customers with the
 * best-known public packages of their CMS, in real versions, some outdated or insecure. Each
 * reports twice, an older state and the current one, so the history isn't empty.
 */
final class DemoProjects
{
    /** the TYPO3 project the demo upgrade calculation is drafted for, and the update it reports after */
    public const string CALCULATION_PROJECT = 'Bergblick Tourismus – Website';

    public const array CALCULATION_UPDATE = ['news' => '14.0.0', 'container' => '4.1.2'];

    public const array ORGANIZATIONS = [
        'Bergblick Tourismus GmbH' => 'Tourism region in the Alps, website and event portal',
        'Stadtwerke Musterstadt' => 'Municipal utility, customer portal and careers site',
        'Nordlicht Verlag' => 'Publisher of regional magazines',
    ];

    /**
     * name => [organization, CMS, SLA, [core before, core now], PHP, DB, DB platform, packages]
     * packages: key => [title, version before, version now, Composer name, type].
     *
     * @var array<string, array{string, CmsType, bool, array{string, string}, string, string, string, array<string, array{string, string, string, ?string, string}>}>
     */
    public const array PROJECTS = [
        'Bergblick Tourismus – Website' => ['Bergblick Tourismus GmbH', CmsType::Typo3, true, ['13.4.5', '13.4.10'], '8.3.14', '10.11.9-MariaDB', 'mariadb', [
            'news' => ['News', '12.3.1', '12.3.1', 'georgringer/news', 'extension'],
            'container' => ['Container', '3.1.10', '3.1.10', 'b13/container', 'extension'],
            'content_blocks' => ['Content Blocks', '1.2.0', '1.3.0', 'friendsoftypo3/content-blocks', 'extension'],
            'bootstrap_package' => ['Bootstrap Package', '15.0.3', '15.0.3', 'bk2k/bootstrap-package', 'extension'],
            'staticfilecache' => ['StaticFileCache', '15.2.0', '15.2.0', 'lochmueller/staticfilecache', 'extension'],
            'ke_search' => ['Faceted Search', '6.5.0', '6.5.0', 'tpwd/ke_search', 'extension'],
            'vhs' => ['VHS: Fluid ViewHelpers', '7.1.0', '7.1.0', 'fluidtypo3/vhs', 'extension'],
            'pagelist' => ['Page List', '4.1.0', '4.1.0', 't3brightside/pagelist', 'extension'],
            'webp' => ['WebP for TYPO3', '14.8.3', '14.8.3', 'plan2net/webp', 'extension'],
        ]],
        'Stadtwerke Musterstadt – Kundenportal' => ['Stadtwerke Musterstadt', CmsType::Typo3, true, ['12.4.18', '12.4.20'], '8.1.31', '8.0.36', 'mysql', [
            'news' => ['News', '11.4.2', '11.4.2', 'georgringer/news', 'extension'],
            'container' => ['Container', '2.3.5', '2.3.5', 'b13/container', 'extension'],
            'bootstrap_package' => ['Bootstrap Package', '14.0.6', '14.0.6', 'bk2k/bootstrap-package', 'extension'],
            'ke_search' => ['Faceted Search', '5.5.0', '5.5.0', 'tpwd/ke_search', 'extension'],
            'vhs' => ['VHS: Fluid ViewHelpers', '7.0.7', '7.0.7', 'fluidtypo3/vhs', 'extension'],
            'pagelist' => ['Page List', '3.8.0', '3.8.0', 't3brightside/pagelist', 'extension'],
            'webp' => ['WebP for TYPO3', '14.8.3', '14.8.3', 'plan2net/webp', 'extension'],
            'staticfilecache' => ['StaticFileCache', '14.0.0', '14.0.0', 'lochmueller/staticfilecache', 'extension'],
        ]],
        'Nordlicht Verlag – Magazin' => ['Nordlicht Verlag', CmsType::Typo3, false, ['13.4.18', '13.4.20'], '8.4.5', '11.4.4-MariaDB', 'mariadb', [
            'news' => ['News', '14.1.1', '14.1.2', 'georgringer/news', 'extension'],
            'container' => ['Container', '4.1.2', '4.1.2', 'b13/container', 'extension'],
            'content_blocks' => ['Content Blocks', '1.3.0', '1.3.0', 'friendsoftypo3/content-blocks', 'extension'],
            'bootstrap_package' => ['Bootstrap Package', '16.0.1', '16.0.1', 'bk2k/bootstrap-package', 'extension'],
            'staticfilecache' => ['StaticFileCache', '15.2.3', '15.2.3', 'lochmueller/staticfilecache', 'extension'],
            'ke_search' => ['Faceted Search', '7.5.0', '7.5.0', 'tpwd/ke_search', 'extension'],
            'vhs' => ['VHS: Fluid ViewHelpers', '8.0.0', '8.0.0', 'fluidtypo3/vhs', 'extension'],
            'webp' => ['WebP for TYPO3', '14.12.0', '14.12.0', 'plan2net/webp', 'extension'],
        ]],
        'Bergblick Tourismus – Shop' => ['Bergblick Tourismus GmbH', CmsType::WordPress, true, ['6.7.1', '6.7.2'], '8.2.27', '10.6.20-MariaDB', 'mariadb', [
            'woocommerce' => ['WooCommerce', '9.6.1', '9.6.2', null, 'plugin'],
            'wordpress-seo' => ['Yoast SEO', '26.7', '26.7', null, 'plugin'],
            'contact-form-7' => ['Contact Form 7', '5.9.8', '5.9.8', null, 'plugin'],
            'elementor' => ['Elementor', '4.2.1', '4.2.1', null, 'plugin'],
            'akismet' => ['Akismet Anti-spam', '5.0.1', '5.0.1', null, 'plugin'],
            'wordfence' => ['Wordfence Security', '8.0.1', '8.0.2', null, 'plugin'],
            'litespeed-cache' => ['LiteSpeed Cache', '6.5.4', '6.5.4', null, 'plugin'],
            'classic-editor' => ['Classic Editor', '1.7.0', '1.7.0', null, 'plugin'],
            'astra' => ['Astra', '4.8.0', '4.8.0', null, 'theme'],
        ]],
        'Nordlicht Verlag – Blog' => ['Nordlicht Verlag', CmsType::WordPress, false, ['6.8.2', '6.8.3'], '8.3.14', '8.4.3', 'mysql', [
            'wordpress-seo' => ['Yoast SEO', '28.9', '28.9', null, 'plugin'],
            'akismet' => ['Akismet Anti-spam', '5.7.2', '5.7.2', null, 'plugin'],
            'contact-form-7' => ['Contact Form 7', '6.2.6', '6.2.6', null, 'plugin'],
            'wordfence' => ['Wordfence Security', '9.1.1', '9.1.1', null, 'plugin'],
            'litespeed-cache' => ['LiteSpeed Cache', '7.9.4', '7.9.4', null, 'plugin'],
            'classic-editor' => ['Classic Editor', '1.7.0', '1.7.0', null, 'plugin'],
            'wpforms-lite' => ['WPForms Lite', '2.0.5.2', '2.0.5.2', null, 'plugin'],
            'twentytwentyfive' => ['Twenty Twenty-Five', '1.0', '1.0', null, 'theme'],
        ]],
        'Stadtwerke Musterstadt – Karriere' => ['Stadtwerke Musterstadt', CmsType::Craft, false, ['5.5.8', '5.5.10'], '8.3.14', '8.0.36', 'mysql', [
            'seomatic' => ['SEOmatic', '5.1.10', '5.1.10', 'nystudio107/craft-seomatic', 'plugin'],
            'formie' => ['Formie', '3.1.25', '3.1.25', 'verbb/formie', 'plugin'],
            'blitz' => ['Blitz', '5.9.0', '5.9.0', 'putyourlightson/craft-blitz', 'plugin'],
            'ckeditor' => ['CKEditor', '4.0.0', '4.0.0', 'craftcms/ckeditor', 'plugin'],
            'retour' => ['Retour', '5.0.0', '5.0.5', 'nystudio107/craft-retour', 'plugin'],
            'neo' => ['Neo', '5.0.0', '5.0.0', 'spicyweb/craft-neo', 'plugin'],
            'navigation' => ['Navigation', '3.0.0', '3.0.0', 'verbb/navigation', 'plugin'],
            'feed-me' => ['Feed Me', '6.0.0', '6.0.0', 'craftcms/feed-me', 'plugin'],
        ]],
        'Bergblick Tourismus – Events' => ['Bergblick Tourismus GmbH', CmsType::Craft, true, ['5.8.0', '5.8.0'], '8.4.5', '16.4', 'postgresql', [
            'seomatic' => ['SEOmatic', '5.1.24', '5.1.24', 'nystudio107/craft-seomatic', 'plugin'],
            'formie' => ['Formie', '3.0.10', '3.0.10', 'verbb/formie', 'plugin'],
            'blitz' => ['Blitz', '5.10.0', '5.10.0', 'putyourlightson/craft-blitz', 'plugin'],
            'ckeditor' => ['CKEditor', '4.4.0', '4.4.0', 'craftcms/ckeditor', 'plugin'],
            'retour' => ['Retour', '5.0.5', '5.0.5', 'nystudio107/craft-retour', 'plugin'],
            'navigation' => ['Navigation', '3.0.5', '3.0.5', 'verbb/navigation', 'plugin'],
            'feed-me' => ['Feed Me', '6.5.0', '6.5.0', 'craftcms/feed-me', 'plugin'],
        ]],
    ];

    private function __construct()
    {
    }
}
