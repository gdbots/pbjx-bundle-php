<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Binder;

use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\QueryParser\QueryParser;

final class QueryParsingBinder implements EventSubscriber
{
    /** @var QueryParser */
    private $queryParser;

    public function __construct()
    {
        $this->queryParser = new QueryParser();
    }

    /**
     * @param PbjxEvent $pbjxEvent
     *
     * @throws \Exception
     */
    public function bind(PbjxEvent $pbjxEvent): void
    {
        $request = $pbjxEvent->getMessage();
        $query = trim((string)$request->get('q'));
        if (empty($query)) {
            return;
        }

        $parsedQuery = $this->queryParser->parse($query);
        $request
            ->set('q', $query)
            ->addToSet('fields_used', $parsedQuery->getFieldsUsed())
            ->set('parsed_query_json', json_encode($parsedQuery));
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'gdbots:pbjx:mixin:search-events-request.bind' => 'bind',
            'gdbots:ncr:mixin:search-nodes-request.bind'   => 'bind',
        ];
    }
}
