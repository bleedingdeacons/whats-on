<?php

/**
 * The What's On Divi 5 module: registration, front-end render and styles.
 *
 * Only loaded from the `divi_module_library_modules_dependency_tree` action, so the
 * ET\Builder classes below are guaranteed to exist whenever this file is.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Divi;

use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Packages\Module\Layout\Components\Classnames;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\Module\Options\Text\TextStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WhatsOn\Assets;
use WhatsOn\Events\DateFormatter;
use WhatsOn\Events\EventRepository;
use WhatsOn\Events\QueryOptions;
use WhatsOn\Render\CarouselOptions;
use WhatsOn\Render\CarouselRenderer;
use WhatsOn\Support\Attrs;
use WP_Block_Type_Registry;

final class EventsCarouselModule implements DependencyInterface
{
    public const NAME = 'whats-on/events-carousel';

    public function load(): void
    {
        ModuleRegistration::register_module(
            WHATS_ON_DIR . 'modules-json/events-carousel/',
            [
                'render_callback' => [ self::class, 'render_callback' ],
            ]
        );
    }

    /**
     * Front-end render.
     *
     * @param mixed          $attrs    Module attributes (merged with defaults by Divi).
     * @param mixed          $content  Inner content (unused, the module has no children).
     * @param \WP_Block      $block    Parsed block.
     * @param ModuleElements $elements Divi's element renderer for this module.
     */
    public static function render_callback(mixed $attrs, mixed $content, \WP_Block $block, ModuleElements $elements): string
    {
        $attrs = Attrs::map($attrs);

        Assets::enqueueFrontend();

        $renderer = new CarouselRenderer(
            DateFormatter::fromSettings(
                Attrs::string(Attrs::desktop($attrs, 'events.advanced.dateFormat')),
                QueryOptions::parseBool(Attrs::desktop($attrs, 'events.advanced.showTime') ?? 'on')
            )
        );

        $events = ( new EventRepository() )->upcoming(QueryOptions::fromAttrs($attrs));
        $html   = $renderer->render($events, CarouselOptions::fromAttrs($attrs));

        $id            = Attrs::string($block->parsed_block['id'] ?? '');
        $storeInstance = $block->parsed_block['storeInstance'] ?? null;
        $parent        = BlockParserStore::get_parent($id, is_int($storeInstance) ? $storeInstance : null);

        return Module::render(
            [
                // FE only.
                'orderIndex'         => $block->parsed_block['orderIndex'] ?? 0,
                'storeInstance'      => $storeInstance,

                // VB equivalent.
                'attrs'              => $attrs,
                'elements'           => $elements,
                'id'                 => $id,
                'name'               => $block->block_type->name,
                'moduleCategory'     => $block->block_type->category ?? 'module',
                'classnamesFunction' => [ self::class, 'module_classnames' ],
                'stylesComponent'    => [ self::class, 'module_styles' ],
                'parentAttrs'        => $parent->attrs ?? [],
                'parentId'           => $parent->id ?? '',
                'parentName'         => $parent->blockName ?? '',
                'children'           => $elements->style_components([ 'attrName' => 'module' ]) . $html,
            ]
        );
    }

    /**
     * @param array<string, mixed> $args Divi classnames args.
     */
    public static function module_classnames(array $args): void
    {
        $classnames = $args['classnamesInstance'] ?? null;
        if (! $classnames instanceof Classnames) {
            return;
        }
        $attrs = Attrs::map($args['attrs'] ?? []);

        $classnames->add('whats-on-module', true);
        $classnames->add(TextClassnames::text_options_classnames(Attrs::map(Attrs::at($attrs, 'module.advanced.text'))), true);
        $classnames->add(
            ElementClassnames::classnames(
                [
                    'attrs' => array_merge(
                        Attrs::map(Attrs::at($attrs, 'module.decoration')),
                        [ 'link' => Attrs::map(Attrs::at($attrs, 'module.advanced.link')) ]
                    ),
                ]
            )
        );
    }

    /**
     * @param array<string, mixed> $args Divi style args.
     */
    public static function module_styles(array $args): void
    {
        $elements = $args['elements'] ?? null;
        if (! $elements instanceof ModuleElements) {
            return;
        }
        $attrs       = Attrs::map($args['attrs'] ?? []);
        $order_class = Attrs::string($args['orderClass'] ?? '');

        Style::add(
            [
                'id'            => $args['id'] ?? '',
                'name'          => $args['name'] ?? self::NAME,
                'orderIndex'    => $args['orderIndex'] ?? 0,
                'storeInstance' => $args['storeInstance'] ?? null,
                'styles'        => [
                    $elements->style(
                        [
                            'attrName'   => 'module',
                            'styleProps' => [
                                'disabledOn' => [
                                    'disabledModuleVisibility' => Attrs::at(Attrs::map($args['settings'] ?? []), 'disabledModuleVisibility'),
                                ],
                            ],
                        ]
                    ),
                    TextStyle::style(
                        [
                            'selector'   => $order_class,
                            'attr'       => Attrs::map(Attrs::at($attrs, 'module.advanced.text')),
                            'orderClass' => $order_class,
                        ]
                    ),
                    $elements->style([ 'attrName' => 'card' ]),
                    $elements->style([ 'attrName' => 'image' ]),
                    $elements->style([ 'attrName' => 'title' ]),
                    $elements->style([ 'attrName' => 'date' ]),
                    $elements->style(
                        [
                            'attrName'   => 'arrows',
                            'styleProps' => [
                                'advancedStyles' => [
                                    [
                                        'componentName' => 'divi/common',
                                        'props'         => [
                                            'selector' => $order_class . ' .whats-on__nav',
                                            'attr'     => Attrs::at($attrs, 'arrows.advanced.color'),
                                            'property' => 'color',
                                        ],
                                    ],
                                ],
                            ],
                        ]
                    ),
                    CssStyle::style(
                        [
                            'selector'   => $order_class,
                            'attr'       => Attrs::map($attrs['css'] ?? []),
                            'cssFields'  => self::custom_css(),
                            'orderClass' => $order_class,
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function custom_css(): array
    {
        $type = WP_Block_Type_Registry::get_instance()->get_registered(self::NAME);

        return Attrs::map($type->customCssFields ?? []);
    }
}
