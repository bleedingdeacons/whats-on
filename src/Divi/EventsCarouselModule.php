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
use WP_Block_Type_Registry;

final class EventsCarouselModule implements DependencyInterface {

	public const NAME = 'whats-on/events-carousel';

	public function load(): void {
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
	 * @param array<string, mixed> $attrs    Module attributes (merged with defaults by Divi).
	 * @param string               $content  Inner content (unused, the module has no children).
	 * @param \WP_Block            $block    Parsed block.
	 * @param object               $elements ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements.
	 */
	public static function render_callback( $attrs, $content, $block, $elements ): string {
		$attrs = is_array( $attrs ) ? $attrs : [];

		Assets::enqueueFrontend();

		$advanced = $attrs['events']['advanced'] ?? [];
		$renderer = new CarouselRenderer(
			DateFormatter::fromSettings(
				(string) ( $advanced['dateFormat']['desktop']['value'] ?? '' ),
				QueryOptions::parseBool( $advanced['showTime']['desktop']['value'] ?? 'on' )
			)
		);

		$events = ( new EventRepository() )->upcoming( QueryOptions::fromAttrs( $attrs ) );
		$html   = $renderer->render( $events, CarouselOptions::fromAttrs( $attrs ) );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				// FE only.
				'orderIndex'         => $block->parsed_block['orderIndex'],
				'storeInstance'      => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'              => $attrs,
				'elements'           => $elements,
				'id'                 => $block->parsed_block['id'],
				'name'               => $block->block_type->name,
				'moduleCategory'     => $block->block_type->category,
				'classnamesFunction' => [ self::class, 'module_classnames' ],
				'stylesComponent'    => [ self::class, 'module_styles' ],
				'parentAttrs'        => $parent->attrs ?? [],
				'parentId'           => $parent->id ?? '',
				'parentName'         => $parent->blockName ?? '',
				'children'           => $elements->style_components( [ 'attrName' => 'module' ] ) . $html,
			]
		);
	}

	/**
	 * @param array<string, mixed> $args Divi classnames args.
	 */
	public static function module_classnames( array $args ): void {
		$classnames = $args['classnamesInstance'];
		$attrs      = $args['attrs'] ?? [];

		$classnames->add( 'whats-on-module', true );
		$classnames->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] ), true );
		$classnames->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						$attrs['module']['decoration'] ?? [],
						[ 'link' => $attrs['module']['advanced']['link'] ?? [] ]
					),
				]
			)
		);
	}

	/**
	 * @param array<string, mixed> $args Divi style args.
	 */
	public static function module_styles( array $args ): void {
		$attrs       = $args['attrs'] ?? [];
		$elements    = $args['elements'];
		$settings    = $args['settings'] ?? [];
		$order_class = $args['orderClass'] ?? '';

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn' => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),
					TextStyle::style(
						[
							'selector'   => $order_class,
							'attr'       => $attrs['module']['advanced']['text'] ?? [],
							'orderClass' => $order_class,
						]
					),
					$elements->style( [ 'attrName' => 'card' ] ),
					$elements->style( [ 'attrName' => 'image' ] ),
					$elements->style( [ 'attrName' => 'title' ] ),
					$elements->style( [ 'attrName' => 'date' ] ),
					$elements->style(
						[
							'attrName'   => 'arrows',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $order_class . ' .whats-on__nav',
											'attr'     => $attrs['arrows']['advanced']['color'] ?? null,
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
							'attr'       => $attrs['css'] ?? [],
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
	public static function custom_css(): array {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( self::NAME );

		return (array) ( $type->customCssFields ?? [] );
	}
}
