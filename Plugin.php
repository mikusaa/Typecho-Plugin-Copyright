<?php

namespace TypechoPlugin\Copyright;

use Typecho\Common;
use Typecho\Config;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element as FormElement;
use Typecho\Widget\Helper\Form\Element\Hidden;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Typecho\Widget\Helper\Layout;
use Utils\Helper;
use Utils\Markdown;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Copyright for Typecho
 *
 * @package Copyright
 * @author  mikusa
 * @version 3.0.0
 * @link https://github.com/mikusaa/Copyright-for-Typecho
 */
class Plugin implements PluginInterface
{
    private const VERSION = '3.0.0';
    private const ACTION_NAME = 'copyright';

    private const CONFIG_ENABLE_ON_POSTS = 'enableOnPosts';
    private const CONFIG_ENABLE_ON_PAGES = 'enableOnPages';
    private const CONFIG_DISPLAY_PERMALINK = 'displayPermalink';
    private const CONFIG_DEFAULT_NOTICE = 'defaultNotice';

    private const LEGACY_CONFIG_ENABLE_ON_POSTS = 'showOnPost';
    private const LEGACY_CONFIG_ENABLE_ON_PAGES = 'showOnPage';
    private const LEGACY_CONFIG_DISPLAY_PERMALINK = 'showURL';
    private const LEGACY_CONFIG_AUTHOR = 'author';
    private const LEGACY_CONFIG_NOTICE = 'notice';

    private const FIELD_MODE = 'copyrightMode';
    private const FIELD_AUTHOR = 'copyrightAuthor';
    private const FIELD_SOURCE_URL = 'copyrightSourceUrl';
    private const FIELD_NOTICE = 'copyrightNotice';

    private const LEGACY_FIELD_MODE = 'switch';
    private const LEGACY_FIELD_AUTHOR = 'author';
    private const LEGACY_FIELD_SOURCE_URL = 'url';
    private const LEGACY_FIELD_NOTICE = 'notice';

    private const MODE_INHERIT = 'inherit';
    private const MODE_ENABLED = 'enabled';
    private const MODE_DISABLED = 'disabled';

    private const BLOCK_STYLE_CLASSIC = 'classic';
    private const ALLOWED_MARKDOWN_TAGS = '<a><blockquote><br><code><em><li><ol><p><strong><ul>';
    private const BLOCK_CLASS = 'copyright-plugin-block';
    private const FRONTEND_STYLE_ID = 'typecho-copyright-plugin-style';

    /**
     * 激活插件。
     */
    public static function activate(): void
    {
        self::registerAction();
        self::registerContentsHook('contentEx', __CLASS__ . '::appendCopyrightBlock');
        self::registerContentsHook('excerptEx', __CLASS__ . '::stripCopyrightFromExcerpt');
        self::bootstrapRuntimeHooks();
    }

    /**
     * 禁用插件。
     */
    public static function deactivate(): void
    {
        self::unregisterAction();
    }

    /**
     * 插件配置面板。
     */
    public static function config(Form $form): void
    {
        $settings = self::globalSettings();

        echo '<div class="message notice">';
        echo '<p><strong>版权声明插件</strong>用于为文章或独立页面输出统一的版权说明区块。</p>';
        echo '<p>本页面配置的是<strong>全局默认设置</strong>；如需对单篇文章或页面单独覆盖，请在编辑器中的“版权声明设置”面板进行配置。</p>';
        echo '<p>单篇设置的优先级高于全局设置。作者信息将自动读取当前内容作者；声明内容支持 Markdown 语法。</p>';
        echo '</div>';

        // 保留旧版全局 author 配置键，避免 Typecho 1.3 回填遗留配置时报错。
        $form->addInput(new Hidden(self::LEGACY_CONFIG_AUTHOR, null, ''));

        $defaultNotice = new Textarea(
            self::CONFIG_DEFAULT_NOTICE,
            null,
            $settings['default_notice'] !== '' ? $settings['default_notice'] : _t('转载或引用请注明原文出处，并保留本声明。'),
            _t('默认版权声明'),
            _t('用于未单独设置声明内容的文章与页面，支持 Markdown。')
        );
        $defaultNotice->input->setAttribute('rows', '4');
        $form->addInput($defaultNotice);

        $displayPermalink = new Radio(
            self::CONFIG_DISPLAY_PERMALINK,
            ['1' => _t('显示'), '0' => _t('不显示')],
            self::isEnabled($settings['display_permalink']) ? '1' : '0',
            _t('默认显示本文链接'),
            _t('开启后，在未填写原文链接时显示当前文章或页面的固定链接。')
        );
        $form->addInput($displayPermalink);

        $enableOnPosts = new Radio(
            self::CONFIG_ENABLE_ON_POSTS,
            ['1' => _t('启用'), '0' => _t('不启用')],
            self::isEnabled($settings['enable_on_posts']) ? '1' : '0',
            _t('文章默认显示版权声明'),
            _t('控制文章在未进行单篇覆盖时的默认显示状态。')
        );
        $form->addInput($enableOnPosts);

        $enableOnPages = new Radio(
            self::CONFIG_ENABLE_ON_PAGES,
            ['1' => _t('启用'), '0' => _t('不启用')],
            self::isEnabled($settings['enable_on_pages']) ? '1' : '0',
            _t('独立页面默认显示版权声明'),
            _t('控制独立页面在未进行单篇覆盖时的默认显示状态。')
        );
        $form->addInput($enableOnPages);
    }

    /**
     * 个人用户配置面板。
     */
    public static function personalConfig(Form $form): void
    {
    }

    /**
     * 兼容旧版回调名称。
     *
     * @param string|null $content
     * @param mixed $widget
     * @param string|null $lastResult
     * @return string
     */
    public static function Copyright($content, $widget, $lastResult): string
    {
        return self::appendCopyrightBlock($content, $widget, $lastResult);
    }

    /**
     * 内容过滤器。
     *
     * @param string|null $content
     * @param mixed $widget
     * @param string|null $lastResult
     * @return string
     */
    public static function appendCopyrightBlock($content, $widget, $lastResult): string
    {
        $content = empty($lastResult) ? (string) $content : (string) $lastResult;
        if (strpos($content, self::BLOCK_CLASS) !== false) {
            return $content;
        }

        $resolved = self::resolveBlockData($widget);
        $block = self::renderBlock($resolved);

        return $block === '' ? $content : $content . $block;
    }

    /**
     * 从摘要链路中移除版权区块，避免污染 SEO 描述与分享文案。
     *
     * @param string|null $content
     * @param mixed $widget
     * @param string|null $lastResult
     * @return string
     */
    public static function stripCopyrightFromExcerpt($content, $widget, $lastResult): string
    {
        $content = empty($lastResult) ? (string) $content : (string) $lastResult;
        return self::removeRenderedBlock($content);
    }

    /**
     * 注册文章编辑字段。
     */
    public static function registerPostFields(Layout $layout): void
    {
        self::registerEditorFields($layout, _t('文章'));
    }

    /**
     * 注册页面编辑字段。
     */
    public static function registerPageFields(Layout $layout): void
    {
        self::registerEditorFields($layout, _t('页面'));
    }

    /**
     * 输出编辑器资源。
     */
    public static function renderEditorAssets(): void
    {
        static $rendered = false;

        if ($rendered) {
            return;
        }

        $rendered = true;
        $config = [
            'fields' => [
                'mode' => self::FIELD_MODE,
                'author' => self::FIELD_AUTHOR,
                'sourceUrl' => self::FIELD_SOURCE_URL,
                'notice' => self::FIELD_NOTICE,
            ],
            'legacyFields' => [
                self::LEGACY_FIELD_MODE => self::FIELD_MODE,
                self::LEGACY_FIELD_AUTHOR => self::FIELD_AUTHOR,
                self::LEGACY_FIELD_SOURCE_URL => self::FIELD_SOURCE_URL,
                self::LEGACY_FIELD_NOTICE => self::FIELD_NOTICE,
            ],
            'actions' => [
                'schema' => self::actionUrl(['schema' => '1']),
            ],
        ];
        $cssPath = 'assets/admin/editor.css';
        $jsPath = 'assets/admin/editor.js';

        echo '<script>window.TypechoCopyrightEditorConfig = '
            . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . ';</script>';
        echo '<link rel="stylesheet" href="'
            . htmlspecialchars(self::assetUrl($cssPath) . '?v=' . rawurlencode(self::assetVersion($cssPath)))
            . '" />';
        echo '<script src="'
            . htmlspecialchars(self::assetUrl($jsPath) . '?v=' . rawurlencode(self::assetVersion($jsPath)))
            . '"></script>';
    }

    /**
     * 注册默认自定义字段。
     */
    private static function registerEditorFields(Layout $layout, string $contentLabel): void
    {
        if (self::layoutHasField($layout, self::FIELD_MODE)) {
            return;
        }

        $mode = new Select(
            self::FIELD_MODE,
            [
                self::MODE_INHERIT => _t('跟随全局设置'),
                self::MODE_ENABLED => _t('本篇启用'),
                self::MODE_DISABLED => _t('本篇关闭'),
            ],
            self::MODE_INHERIT,
            _t('显示策略'),
            _t('控制当前' . $contentLabel . '是否显示版权声明。')
        );
        $layout->addItem($mode);

        $author = new Text(
            self::FIELD_AUTHOR,
            null,
            null,
            _t('作者信息（兼容保留）'),
            _t('保留用于兼容旧数据；当前版本默认自动读取内容作者。')
        );
        $author->input->setAttribute('data-copyright-hidden-field', 'author');
        $layout->addItem($author);

        $sourceUrl = new Text(
            self::FIELD_SOURCE_URL,
            null,
            null,
            _t('原文链接'),
            _t('填写后显示为“原文链接”；留空时可根据全局设置显示当前' . $contentLabel . '链接。')
        );
        $layout->addItem($sourceUrl);

        $notice = new Textarea(
            self::FIELD_NOTICE,
            null,
            null,
            _t('版权声明'),
            _t('支持 Markdown。留空时使用插件的全局默认值。')
        );
        $notice->input->setAttribute('rows', '4');
        $layout->addItem($notice);
    }

    /**
     * 统一注册内容 hook。
     */
    private static function registerContentsHook(string $component, callable $callback): void
    {
        $targets = ['Widget_Abstract_Contents'];
        if (class_exists('Widget\\Base\\Contents')) {
            $targets[] = '\\Widget\\Base\\Contents';
        }

        $registered = [];
        foreach ($targets as $target) {
            $normalized = self::normalizePluginHandle($target);
            if (isset($registered[$normalized])) {
                continue;
            }

            \Typecho\Plugin::factory($target)->{$component} = $callback;
            $registered[$normalized] = true;
        }
    }

    /**
     * 统一 Typecho 1.2 / 1.3 的句柄格式。
     */
    private static function normalizePluginHandle(string $handle): string
    {
        if (defined('__TYPECHO_CLASS_ALIASES__')) {
            $alias = array_search('\\' . ltrim($handle, '\\'), __TYPECHO_CLASS_ALIASES__, true);
            if (false !== $alias) {
                $handle = $alias;
            }
        }

        return Common::nativeClassName($handle);
    }

    /**
     * 解析最终渲染数据。
     *
     * @param mixed $widget
     * @return array
     */
    private static function resolveBlockData($widget): array
    {
        $global = self::globalSettings();
        $local = self::localSettings($widget);
        $enabled = false;
        $type = '';
        $parameter = is_object($widget) ? $widget->parameter : null;

        if (!is_object($widget) || !method_exists($widget, 'is') || !$widget->is('single')) {
            return self::emptyBlockData();
        }

        if (is_object($parameter)) {
            $type = trim((string) $parameter->type);
        }

        if ($type === 'post') {
            $enabled = self::isEnabled($global['enable_on_posts']);
        } elseif ($type === 'page') {
            $enabled = self::isEnabled($global['enable_on_pages']);
        }

        if ($local['mode'] === self::MODE_ENABLED) {
            $enabled = true;
        } elseif ($local['mode'] === self::MODE_DISABLED) {
            $enabled = false;
        }

        if (!$enabled) {
            return self::emptyBlockData();
        }

        $sourceUrl = self::normalizeUrl($local['source_url']);
        $linkLabel = '';

        if ($sourceUrl !== '') {
            $linkLabel = _t('原文链接');
        } elseif (self::isEnabled($global['display_permalink']) && !empty($widget->permalink)) {
            $sourceUrl = self::normalizeUrl((string) $widget->permalink);
            $linkLabel = $sourceUrl === '' ? '' : _t('本文链接');
        }

        return [
            'enabled' => true,
            'style' => $global['style'],
            'license' => $global['license'],
            'author' => self::firstNonEmptyText($local['author'], self::resolveAuthorText($widget)),
            'source_url' => $sourceUrl,
            'link_label' => $linkLabel,
            'notice' => self::firstNonEmptyText($local['notice'], $global['default_notice']),
        ];
    }

    /**
     * 全局配置。
     */
    private static function globalSettings(): array
    {
        $config = self::pluginConfig();

        return [
            'enable_on_posts' => self::configValue(
                $config,
                [self::CONFIG_ENABLE_ON_POSTS, self::LEGACY_CONFIG_ENABLE_ON_POSTS],
                '1'
            ),
            'enable_on_pages' => self::configValue(
                $config,
                [self::CONFIG_ENABLE_ON_PAGES, self::LEGACY_CONFIG_ENABLE_ON_PAGES],
                '0'
            ),
            'display_permalink' => self::configValue(
                $config,
                [self::CONFIG_DISPLAY_PERMALINK, self::LEGACY_CONFIG_DISPLAY_PERMALINK],
                '1'
            ),
            'default_notice' => self::firstNonEmptyText(
                self::configValue($config, [self::CONFIG_DEFAULT_NOTICE], ''),
                self::configValue($config, [self::LEGACY_CONFIG_NOTICE], '')
            ),
            'style' => self::BLOCK_STYLE_CLASSIC,
            'license' => '',
        ];
    }

    /**
     * 单篇配置。
     *
     * @param mixed $widget
     * @return array
     */
    private static function localSettings($widget): array
    {
        $fields = is_object($widget) ? $widget->fields : new Config();
        if (!is_object($fields)) {
            $fields = new Config();
        }
        $mode = self::fieldValue($fields, self::FIELD_MODE);

        if ($mode === '') {
            $mode = self::normalizeLegacyMode(self::fieldValue($fields, self::LEGACY_FIELD_MODE));
        }

        return [
            'mode' => $mode === '' ? self::MODE_INHERIT : $mode,
            'author' => self::firstNonEmptyText(
                self::fieldValue($fields, self::FIELD_AUTHOR),
                self::fieldValue($fields, self::LEGACY_FIELD_AUTHOR)
            ),
            'source_url' => self::firstNonEmptyText(
                self::fieldValue($fields, self::FIELD_SOURCE_URL),
                self::fieldValue($fields, self::LEGACY_FIELD_SOURCE_URL)
            ),
            'notice' => self::firstNonEmptyText(
                self::fieldValue($fields, self::FIELD_NOTICE),
                self::fieldValue($fields, self::LEGACY_FIELD_NOTICE)
            ),
        ];
    }

    /**
     * 渲染版权区块。
     */
    private static function renderBlock(array $data): string
    {
        if (empty($data['enabled'])) {
            return '';
        }

        switch ($data['style']) {
            case self::BLOCK_STYLE_CLASSIC:
            default:
                return self::renderClassicBlock($data);
        }
    }

    /**
     * 经典版权区块。
     */
    private static function renderClassicBlock(array $data): string
    {
        $rows = [];

        if ($data['author'] !== '') {
            $rows[] = self::renderClassicRow(_t('作者信息'), self::renderClassicValue($data['author']));
        }

        if ($data['source_url'] !== '' && $data['link_label'] !== '') {
            $rows[] = self::renderClassicRow(
                $data['link_label'],
                self::renderSourceLink($data['source_url'], $data['link_label'] === _t('原文链接'))
            );
        }

        if ($data['notice'] !== '') {
            $rows[] = self::renderClassicRow(_t('版权声明'), self::renderClassicValue($data['notice']));
        }

        if (empty($rows)) {
            return '';
        }

        return self::renderFrontendStyles() .
            '<section class="' . self::BLOCK_CLASS . ' ' . self::BLOCK_CLASS . '--classic" data-copyright-style="'
            . htmlspecialchars($data['style'])
            . '">' .
            '<hr class="content-copyright copyright-plugin-block__divider" style="margin-top:50px" />' .
            '<blockquote class="content-copyright copyright-plugin-block__quote"'
            . ' style="font-style:normal;font-size:95%;border-left:4px solid #ff5268;margin:50px -15px 0 -15px;padding:1px 20px 1px 20px;">' .
            implode('', $rows) .
            '</blockquote>' .
            '</section>';
    }

    /**
     * 渲染单行。
     */
    private static function renderRow(string $label, string $value): string
    {
        if ($value === '') {
            return '';
        }

        return '<div class="copyright-plugin-block__row">'
            . '<div class="copyright-plugin-block__label">' . htmlspecialchars($label) . '</div>'
            . '<div class="copyright-plugin-block__value">' . $value . '</div>'
            . '</div>';
    }

    /**
     * 渲染原版样式单行。
     */
    private static function renderClassicRow(string $label, string $value): string
    {
        if ($value === '') {
            return '';
        }

        return '<div class="content-copyright copyright-plugin-block__paragraph">'
            . '<strong class="copyright-plugin-block__classic-label">' . htmlspecialchars($label) . '：</strong>'
            . $value
            . '</div>';
    }

    /**
     * Markdown 片段渲染。
     */
    private static function renderMarkdownFragment(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return strip_tags(Markdown::convert($text), self::ALLOWED_MARKDOWN_TAGS);
    }

    /**
     * 为原版样式压平单段落包装，避免多余间距。
     */
    private static function renderClassicValue(string $text): string
    {
        $html = self::renderMarkdownFragment($text);
        if ($html === '') {
            return '';
        }

        if (preg_match('/^\s*<p>(.*?)<\/p>\s*$/is', $html, $matches) && stripos($matches[1], '<p') === false) {
            return $matches[1];
        }

        return $html;
    }

    /**
     * 链接渲染。
     */
    private static function renderSourceLink(string $url, bool $external): string
    {
        $attributes = ' href="' . htmlspecialchars($url) . '"';
        if ($external) {
            $attributes .= ' target="_blank" rel="noopener noreferrer"';
        }

        return '<a class="copyright-plugin-block__link"' . $attributes . '>'
            . htmlspecialchars($url)
            . '</a>';
    }

    /**
     * 输出前台样式。
     */
    private static function renderFrontendStyles(): string
    {
        static $rendered = false;

        if ($rendered) {
            return '';
        }

        $rendered = true;

        return '<style id="' . self::FRONTEND_STYLE_ID . '">'
            . '.copyright-plugin-block--classic .copyright-plugin-block__divider{margin-top:50px}'
            . '.copyright-plugin-block--classic .copyright-plugin-block__quote{margin:50px -15px 0 -15px;padding:1px 20px;border-left:4px solid #ff5268;font-style:normal;font-size:95%}'
            . '.copyright-plugin-block--classic .copyright-plugin-block__paragraph{margin:1em 0;line-height:1.8}'
            . '.copyright-plugin-block--classic .copyright-plugin-block__classic-label{font-weight:700}'
            . '.copyright-plugin-block--classic .copyright-plugin-block__paragraph p{display:inline;margin:0}'
            . '.copyright-plugin-block--classic .copyright-plugin-block__paragraph>*:last-child{margin-bottom:0}'
            . '.copyright-plugin-block--classic .copyright-plugin-block__paragraph ul,.copyright-plugin-block--classic .copyright-plugin-block__paragraph ol{margin:.75em 0 .25em;padding-left:1.25rem}'
            . '.copyright-plugin-block--classic .copyright-plugin-block__paragraph blockquote{margin:.75em 0 .25em;padding:0 0 0 1em;border-left:3px solid rgba(255,82,104,.28)}'
            . '.copyright-plugin-block--classic .copyright-plugin-block__paragraph code{padding:2px 6px;border-radius:4px;background:rgba(15,23,42,.06);font-size:.92em}'
            . '.copyright-plugin-block--classic a,.copyright-plugin-block__link{word-break:break-all}'
            . '@media (max-width:680px){.copyright-plugin-block--classic .copyright-plugin-block__quote{margin:32px 0 0;padding:1px 16px}}'
            . '</style>';
    }

    /**
     * 移除已渲染的版权区块。
     */
    private static function removeRenderedBlock(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $html = preg_replace(
            '/<style\b[^>]*id=(["\'])' . preg_quote(self::FRONTEND_STYLE_ID, '/') . '\1[^>]*>.*?<\/style>/is',
            '',
            $html
        );
        $html = preg_replace(
            '/<section\b[^>]*class=(["\'])[^"\']*\b' . preg_quote(self::BLOCK_CLASS, '/') . '\b[^"\']*\1[^>]*>.*?<\/section>/is',
            '',
            $html
        );

        return trim((string) $html);
    }

    /**
     * 获取插件配置。
     */
    private static function pluginConfig(): Config
    {
        $candidates = [self::pluginDirectoryName(), 'Copyright'];
        $candidates = array_values(array_unique(array_filter($candidates)));

        foreach ($candidates as $pluginName) {
            try {
                return Options::alloc()->plugin($pluginName);
            } catch (\Throwable $e) {
            }
        }

        return new Config();
    }

    /**
     * 运行时补充后台 hook，兼容旧版本已启用插件直接替换文件的场景。
     */
    public static function bootstrapRuntimeHooks(): void
    {
        static $bootstrapped = false;

        if ($bootstrapped) {
            return;
        }

        $bootstrapped = true;
        self::registerAction();
        \Typecho\Plugin::factory('Widget_Contents_Post_Edit')->getDefaultFieldItems = __CLASS__ . '::registerPostFields';
        \Typecho\Plugin::factory('Widget_Contents_Page_Edit')->getDefaultFieldItems = __CLASS__ . '::registerPageFields';
        \Typecho\Plugin::factory('admin/write-post.php')->bottom = __CLASS__ . '::renderEditorAssets';
        \Typecho\Plugin::factory('admin/write-page.php')->bottom = __CLASS__ . '::renderEditorAssets';
    }

    /**
     * 编辑器扩展 schema。
     */
    public static function editorSchema(): array
    {
        return [
            'version' => self::VERSION,
            'styles' => [
                [
                    'value' => self::BLOCK_STYLE_CLASSIC,
                    'label' => _t('经典样式'),
                    'builtin' => true,
                ],
            ],
            'licenses' => [],
            'capabilities' => [
                'author_auto_read' => true,
                'author_field_visible' => false,
                'markdown_notice' => true,
                'permalink_fallback' => true,
                'cc_presets' => false,
                'style_presets' => false,
            ],
            'fields' => [
                'mode' => self::FIELD_MODE,
                'author' => self::FIELD_AUTHOR,
                'source_url' => self::FIELD_SOURCE_URL,
                'notice' => self::FIELD_NOTICE,
            ],
        ];
    }

    /**
     * 配置项读取。
     *
     * @param Config $config
     * @param array $keys
     * @param mixed $default
     * @return mixed
     */
    private static function configValue(Config $config, array $keys, $default)
    {
        foreach ($keys as $key) {
            $value = $config->{$key};
            if (is_array($value)) {
                $value = reset($value);
            }
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    /**
     * 字段值读取。
     *
     * @param mixed $fields
     * @param string $name
     * @return string
     */
    private static function fieldValue($fields, string $name): string
    {
        if (!is_object($fields)) {
            return '';
        }

        $value = $fields->{$name};
        if (is_array($value)) {
            return trim((string) reset($value));
        }

        return trim((string) $value);
    }

    /**
     * 解析当前内容作者文本。
     *
     * @param mixed $widget
     * @return string
     */
    private static function resolveAuthorText($widget): string
    {
        if (!is_object($widget)) {
            return '';
        }

        $author = $widget->author ?? null;
        if (is_object($author)) {
            $screenName = trim((string) ($author->screenName ?? ''));
            if ($screenName !== '') {
                return $screenName;
            }

            $name = trim((string) ($author->name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    /**
     * 兼容旧字段开关值。
     */
    private static function normalizeLegacyMode(string $value): string
    {
        if ($value === '1') {
            return self::MODE_ENABLED;
        }

        if ($value === '0') {
            return self::MODE_DISABLED;
        }

        return '';
    }

    /**
     * 判断开关是否启用。
     *
     * @param mixed $value
     * @return bool
     */
    private static function isEnabled($value): bool
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * 返回首个非空文本。
     */
    private static function firstNonEmptyText(string ...$values): string
    {
        foreach ($values as $value) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * URL 标准化。
     */
    private static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    /**
     * 空数据结构。
     */
    private static function emptyBlockData(): array
    {
        return [
            'enabled' => false,
            'style' => self::BLOCK_STYLE_CLASSIC,
            'license' => '',
            'author' => '',
            'source_url' => '',
            'link_label' => '',
            'notice' => '',
        ];
    }

    /**
     * 插件目录名称。
     */
    private static function pluginDirectoryName(): string
    {
        return basename(__DIR__);
    }

    /**
     * Action 组件类名。
     */
    private static function actionWidgetName(): string
    {
        return __NAMESPACE__ . '\\Action';
    }

    /**
     * 注册 Action 扩展。
     */
    private static function registerAction(): void
    {
        $actionTable = Options::alloc()->actionTable;
        $widgetName = self::actionWidgetName();

        if (($actionTable[self::ACTION_NAME] ?? null) === $widgetName) {
            return;
        }

        Helper::addAction(self::ACTION_NAME, $widgetName);
    }

    /**
     * 删除 Action 扩展。
     */
    private static function unregisterAction(): void
    {
        Helper::removeAction(self::ACTION_NAME);
    }

    /**
     * 判断布局中是否已存在指定字段。
     */
    private static function layoutHasField(Layout $layout, string $fieldName): bool
    {
        foreach ($layout->getItems() as $item) {
            if (!($item instanceof FormElement) || empty($item->input)) {
                continue;
            }

            if ($item->input->getAttribute('name') === $fieldName) {
                return true;
            }
        }

        return false;
    }

    /**
     * 静态资源地址。
     */
    private static function assetUrl(string $relativePath): string
    {
        return Common::url(
            self::pluginDirectoryName() . '/' . ltrim($relativePath, '/'),
            Helper::options()->pluginUrl
        );
    }

    /**
     * Action 地址。
     */
    private static function actionUrl(array $query = []): string
    {
        $path = 'action/' . self::ACTION_NAME;
        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        return Common::url($path, Helper::options()->index);
    }

    /**
     * 静态资源版本。
     */
    private static function assetVersion(string $relativePath): string
    {
        $version = @filemtime(__DIR__ . '/' . ltrim($relativePath, '/'));
        return $version ? (string) $version : self::VERSION;
    }
}

if (!class_exists('Copyright_Plugin', false)) {
    class_alias(__NAMESPACE__ . '\\Plugin', 'Copyright_Plugin');
}

Plugin::bootstrapRuntimeHooks();
