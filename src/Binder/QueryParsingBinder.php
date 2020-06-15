<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Binder;

use Gdbots\Pbjx\DependencyInjection\PbjxBinder;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\QueryParser\QueryParser;
use Gdbots\Schemas\Ncr\Mixin\SearchNodesRequest\SearchNodesRequestV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\SearchEventsRequest\SearchEventsRequestV1Mixin;

final class QueryParsingBinder implements EventSubscriber, PbjxBinder
{
    private QueryParser $queryParser;

    public static function getSubscribedEvents()
    {
        return [
            SearchEventsRequestV1Mixin::SCHEMA_CURIE . '.bind' => 'bind',
            SearchNodesRequestV1Mixin::SCHEMA_CURIE . '.bind'  => 'bind',
        ];
    }

    public function __construct()
    {
        $this->queryParser = new QueryParser();
    }

    public function bind(PbjxEvent $pbjxEvent): void
    {
        $request = $pbjxEvent->getMessage();
        $query = trim((string)$request->get(SearchEventsRequestV1Mixin::Q_FIELD));
        if (empty($query)) {
            return;
        }

        $parsedQuery = $this->queryParser->parse($query);
        $request
            ->set(SearchEventsRequestV1Mixin::Q_FIELD, $query)
            ->addToSet(SearchEventsRequestV1Mixin::FIELDS_USED_FIELD, $parsedQuery->getFieldsUsed())
            ->set(SearchEventsRequestV1Mixin::PARSED_QUERY_JSON_FIELD, json_encode($parsedQuery));
    }
}
