<?php

/**
 * `GET /wp-json/whats-on/v1/events` — the same events the module renders, as JSON,
 * for the Visual Builder preview. Restricted to users who can edit posts.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Rest;

use WhatsOn\Events\DateFormatter;
use WhatsOn\Events\EventRepository;
use WhatsOn\Events\QueryOptions;
use WhatsOn\Support\Attrs;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class EventsController
{
    public const NAMESPACE = 'whats-on/v1';

    public function __construct(private readonly EventRepository $events = new EventRepository())
    {
    }

    public function register(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/events',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'index' ],
                'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
                'args'                => [
                    'count'        => [ 'type' => 'string', 'default' => (string) QueryOptions::DEFAULT_COUNT ],
                    'categories'   => [ 'type' => 'string', 'default' => '' ],
                    'featuredOnly' => [ 'type' => 'string', 'default' => 'off' ],
                    'dateFormat'   => [ 'type' => 'string', 'default' => '' ],
                    'showTime'     => [ 'type' => 'string', 'default' => 'on' ],
                ],
            ]
        );
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $options = QueryOptions::fromValues(
            $request->get_param('count'),
            $request->get_param('categories'),
            $request->get_param('featuredOnly')
        );
        $dates   = DateFormatter::fromSettings(
            sanitize_text_field(Attrs::string($request->get_param('dateFormat'))),
            QueryOptions::parseBool($request->get_param('showTime'))
        );

        $items = [];
        foreach ($this->events->upcoming($options) as $event) {
            $items[] = $event->toArray($dates->format($event));
        }

        return new WP_REST_Response(
            [
                'calendarActive' => EventRepository::isCalendarActive(),
                'events'         => $items,
            ]
        );
    }
}
