<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Twig;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbjx\Exception\InvalidArgumentException;
use Gdbots\Pbjx\Pbjx;
use Gdbots\UriTemplate\UriTemplateService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PbjxExtension extends AbstractExtension
{
    private Pbjx $pbjx;
    private LoggerInterface $logger;
    private bool $debug;

    public function __construct(Pbjx $pbjx, ?LoggerInterface $logger = null, bool $debug = false)
    {
        $this->pbjx = $pbjx;
        $this->logger = $logger ?: new NullLogger();
        $this->debug = $debug;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pbj_template', [$this, 'pbjTemplate']),
            new TwigFunction('pbj_url', [$this, 'pbjUrl']),
            new TwigFunction('pbjx_request', [$this, 'pbjxRequest']),
            new TwigFunction('uri_template_expand', [$this, 'uriTemplateExpand']),
        ];
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
     * @param Message     $pbj
     * @param string      $template
     * @param string      $format
     * @param string|null $deviceView
     *
     * @return string|string[]  A single template reference or array if device view is not null
     */
    public function pbjTemplate(Message $pbj, string $template, string $format = 'html', ?string $deviceView = null): string|array
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
     * Returns a named URL to a pbj instance.
     *
     * Example:
     *  {{ pbj_url(pbj, 'canonical') }}
     *
     * @param Message $pbj
     * @param string  $template
     *
     * @return string|null
     */
    public function pbjUrl(Message $pbj, string $template): ?string
    {
        return UriTemplateService::expand("{$pbj::schema()->getQName()}.{$template}", $pbj->getUriTemplateVars());
    }

    /**
     * Expands a URI template.
     *
     * Example:
     *  {{ uri_template_expand('acme:article.canonical', {slug: 'some-slug'}) }}
     *
     * @param string $id
     * @param array  $variables
     *
     * @return string|null
     */
    public function uriTemplateExpand(string $id, array $variables = []): ?string
    {
        return UriTemplateService::expand($id, $variables);
    }

    /**
     * Performs a pbjx->request and returns the response. If debugging is enabled an exception will
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
     * @return Message|null
     *
     * @throws \Throwable
     */
    public function pbjxRequest(string $curie, array $data = []): ?Message
    {
        try {
            $request = MessageResolver::resolveCurie($curie)::fromArray($data);
            if (!$request::schema()->hasMixin('gdbots:pbjx:mixin:request')) {
                throw new InvalidArgumentException(sprintf('The provided curie [%s] is not a request.', $curie));
            }

            // ensures permission check is bypassed
            $request->set('ctx_causator_ref', $request->generateMessageRef());

            return $this->pbjx->request($request);
        } catch (\Throwable $e) {
            if ($this->debug) {
                throw $e;
            }

            $this->logger->warning(
                'Unable to process twig "pbjx_request" function for [{curie}].',
                ['exception' => $e, 'curie' => $curie, 'data' => $data]
            );
        }

        return null;
    }
}
