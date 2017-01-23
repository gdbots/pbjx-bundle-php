<?php
declare(strict_types = 1);

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
use Symfony\Component\Form\FormView;

final class PbjxExtension extends \Twig_Extension
{
    /** @var ContainerInterface */
    private $container;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $debug = false;

    /**
     * @param ContainerInterface $container
     * @param LoggerInterface    $logger
     */
    public function __construct(ContainerInterface $container, ?LoggerInterface $logger = null)
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
            new \Twig_SimpleFunction('pbj_form_view', [$this, 'pbjFormView']),
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
     * Creates a form view and returns it.  Typically used in pbj templates that require
     * a form but may not have one provided in all scenarios so this is used as a default.
     *
     * DO NOT use this function with the "some_var|default(...)" option as this will run
     * even when "some_var" is defined.
     *
     * Example:
     *  {% if pbj_form is not defined %}
     *      {% set pbj_form = pbj_form_view('AppBundle\\Form\\SomeType') %}
     *  {% endif %}
     *
     * @param string $type    The fully qualified class name of the pbj form type
     * @param array  $input   The initial data for the form
     * @param array  $options Options for the form
     *
     * @return FormView
     */
    public function pbjFormView(string $type, array $input = [], array $options = []): FormView
    {
        return $this->container->get('form.factory')->create($type, $input, $options)->createView();
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
     * @param string  $template
     * @param string  $format
     * @param string  $deviceView
     *
     * @return string|string[]  A single template reference or array if device view is not null
     */
    public function pbjTemplate(Message $pbj, string $template, string $format = 'html', ?string $deviceView = null)
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
     * @param array  $data
     *
     * @return Response|null
     *
     * @throws \Exception
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

            $response = $this->getPbjx()->request($request);
            if (!$response->has('ctx_request')) {
                $response->set('ctx_request', $request);
            }

            return $response;
        } catch (\Exception $e) {
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

    /**
     * @return Pbjx
     */
    private function getPbjx(): Pbjx
    {
        return $this->container->get('pbjx');
    }
}
