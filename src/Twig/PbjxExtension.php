<?php

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
use Symfony\Component\DependencyInjection\ContainerInterface;

class PbjxExtension extends \Twig_Extension
{
    /** @var ContainerInterface */
    protected $container;

    /** @var LoggerInterface */
    protected $logger;

    /** @var Pbjx */
    protected $pbjx;

    /** @var bool */
    protected $debug = false;

    /**
     * @param ContainerInterface $container
     * @param LoggerInterface|null $logger
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->logger = $logger ?: new NullLogger();
        $this->debug = $container->getParameter('kernel.debug');
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('pbj_template', [$this, 'pbjTemplate']),
            new \Twig_SimpleFunction('pbjx_request', [$this, 'pbjxRequest'])
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
     * can render your message (e.g. Article) as a "card", "modal", "slack-post", etc. and optionally that
     * template can be device view specific. (card.smartphone.html.twig)
     *
     * Example:
     *  {% include pbj_template(pbj, 'card', 'html', device_view) with {'pbj': pbj} %}
     *
     * @param Message $pbj
     * @param string $template
     * @param string $format
     * @param string $deviceView
     *
     * @return string|string[]  A single template reference or array if device view is not null
     */
    public function pbjTemplate(Message $pbj, $template, $format = 'html', $deviceView = null)
    {
        $curieStr = str_replace('::', ':_:', $pbj::schema()->getCurie()->toString());
        $default = sprintf('@%s/%s.%s.twig', str_replace(':', '/', $curieStr), $template, $format);

        if (null === $deviceView) {
            return $default;
        }

        return [
            sprintf('@%s/%s.%s.%s.twig', str_replace(':', '/', $curieStr), $template, $deviceView, $format),
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
     * @param array $data
     *
     * @return Response|null
     *
     * @throws \Exception
     */
    public function pbjxRequest($curie, array $data = [])
    {
        try {
            /** @var Request $class */
            $class = MessageResolver::resolveCurie(SchemaCurie::fromString($curie));
            $request = $class::fromArray($data);
            if (!$request instanceof Request) {
                throw new InvalidArgumentException(sprintf('The provided curie [%s] is not a request.', $curie));
            }

            return $this->getPbjx()->request($request);

        } catch (\Exception $e) {
            if ($this->debug) {
                throw $e;
            }

            $this->logger->error(
                'Unable to process twig "pbjx_request" function for {curie}.',
                ['exception' => $e, 'curie' => $curie, 'data' => $data]
            );
        }
    }

    /**
     * @return Pbjx
     */
    protected function getPbjx()
    {
        if (null === $this->pbjx) {
            $this->pbjx = $this->container->get('pbjx');
        }

        return $this->pbjx;
    }
}
