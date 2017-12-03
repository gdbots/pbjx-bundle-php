<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Twig;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\Exception\InvalidArgumentException;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class PbjxExtension extends \Twig_Extension
{
    /** @var Pbjx */
    private $pbjx;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $debug = false;

    /**
     * @param Pbjx            $pbjx
     * @param LoggerInterface $logger
     * @param bool            $debug
     */
    public function __construct(Pbjx $pbjx, ?LoggerInterface $logger = null, bool $debug = false)
    {
        $this->pbjx = $pbjx;
        $this->logger = $logger ?: new NullLogger();
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('pbj_template', [$this, 'pbjTemplate']),
            new \Twig_SimpleFunction('pbjx_request', [$this, 'pbjxRequest']),
        ];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'gdbots_pbjx_extension';
    }

    /**
     * Returns a reference to a twig template based on the schema of the provided message (pbj schema).
     * This allows for component style development for pbj messages.  You are asking for a template that
     * can render your message (e.g. Article) as a "card", "modal", "slack_post", etc. and optionally that
     * template can be device view specific. (card.smartphone.html.twig)
     *
     * Example:
     *  {% include pbj_template(pbj, 'card', 'html', device_view) with {'pbj': pbj} %}
     *
     * @param Message $pbj
     * @param string  $template
     * @param string  $format
     * @param string  $deviceView
     *
     * @return string|string[]  A single template reference or array if device view is not null
     */
    public function pbjTemplate(Message $pbj, string $template, string $format = 'html', ?string $deviceView = null)
    {
        $curie = $pbj::schema()->getCurie();
        $path = str_replace('-', '_', sprintf('%s_%s/%s/%s',
            $curie->getVendor(), $curie->getPackage(), $curie->getCategory() ?: '_', $curie->getMessage()
        ));

        // example: @acme_users/request/search_users_response/page.html.twig
        $default = "@{$path}/{$template}.{$format}.twig";

        if (null === $deviceView) {
            return $default;
        }

        return [
            // example: @acme_users/request/search_users_response/page.smartphone.html.twig
            "@{$path}/{$template}.{$deviceView}.{$format}.twig",
            $default,
        ];
    }

    /**
     * Performs a pbjx->request and returns the response.  If debugging is enabled an exception will
     * be thrown (generally in dev), otherwise it will be logged and null will be returned.
     *
     * Example:
     *  {% set pbjx_response = pbjx_request('acme:blog:request:get-comments-request', {'article_id':id}) %}
     *  {% if pbjx_response %}
     *    {% include pbj_template(pbjx_response, 'list', device_view) with {'pbj': pbjx_response} %}
     *  {% endif %}
     *
     * @param string $curie
     * @param array  $data
     *
     * @return Response|null
     *
     * @throws \Throwable
     */
    public function pbjxRequest(string $curie, array $data = []): ?Response
    {
        try {
            /** @var Request $class */
            $class = MessageResolver::resolveCurie(SchemaCurie::fromString($curie));
            $request = $class::fromArray($data);
            if (!$request instanceof Request) {
                throw new InvalidArgumentException(sprintf('The provided curie [%s] is not a request.', $curie));
            }

            $response = $this->pbjx->request($request);
            if (!$response->has('ctx_request')) {
                $response->set('ctx_request', $request);
            }

            return $response;
        } catch (\Throwable $e) {
            if ($this->debug) {
                throw $e;
            }

            $this->logger->error(
                'Unable to process twig "pbjx_request" function for [{curie}].',
                ['exception' => $e, 'curie' => $curie, 'data' => $data]
            );
        }

        return null;
    }
}
