<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbjx\DependencyInjection\PbjxBinder;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\QueryParser\QueryParser;

class QueryParsingBinder implements EventSubscriber, PbjxBinder
{
    protected QueryParser $queryParser;

    public static function getSubscribedEvents(): array
    {
        return [
            'gdbots:ncr:mixin:search-nodes-request.bind'   => 'bind',
            'gdbots:pbjx:mixin:search-events-request.bind' => 'bind',
        ];
    }

    public function __construct()
    {
        $this->queryParser = new QueryParser();
    }

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
}
