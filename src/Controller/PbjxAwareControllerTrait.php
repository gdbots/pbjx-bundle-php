<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Controller;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait PbjxAwareControllerTrait
{
    /**
     * Renders the provided message (pbj) using a template which is resolved by calling
     * the "pbjTemplate" method in this trait.
     *
     * @param Message  $pbj
     * @param string   $template
     * @param Response $response
     * @param string   $format
     *
     * @return Response
     */
    protected function renderPbj(
        Message $pbj,
        string $template = 'page',
        ?Response $response = null,
        string $format = 'html'
    ): Response {
        if (is_callable([$this, 'renderUsingDeviceView'])) {
            return $this->renderUsingDeviceView(
                $this->pbjTemplate($pbj, "{$template}%device_view%", $format), ['pbj' => $pbj], $response
            );
        }

        return $this->render($this->pbjTemplate($pbj, $template, $format), ['pbj' => $pbj], $response);
    }

    /**
     * Renders the provided message (pbj) using a template which is resolved by calling
     * the "pbjTemplate" method in this trait.
     *
     * @param Message  $pbj
     * @param FormView $formView
     * @param string   $template
     * @param Response $response
     * @param string   $format
     *
     * @return Response
     */
    protected function renderPbjForm(
        Message $pbj,
        FormView $formView,
        string $template = 'page',
        ?Response $response = null,
        string $format = 'html'
    ): Response {
        if (is_callable([$this, 'renderUsingDeviceView'])) {
            return $this->renderUsingDeviceView(
                $this->pbjTemplate($pbj, "{$template}%device_view%", $format),
                ['pbj' => $pbj, 'pbj_form' => $formView],
                $response
            );
        }

        return $this->render(
            $this->pbjTemplate($pbj, $template, $format),
            ['pbj' => $pbj, 'pbj_form' => $formView],
            $response
        );
    }

    /**
     * Returns a reference to a twig template based on the schema of the provided message (pbj schema).
     * This allows for component style development for pbj messages.  You are asking for a template that
     * can render your message (e.g. Article) as a "card", "modal", "page", etc.
     *
     * This can be combined with "DeviceViewRendererTrait::renderUsingDeviceView".
     *
     * E.g. return $this->renderUsingDeviceView($this->pbjTemplate($pbj, 'page%device_view%'), ['pbj' => $pbj]);
     *
     * This depends on twig namespaced paths, not bundle naming conventions.
     * @link http://symfony.com/doc/current/templating/namespaced_paths.html
     *
     * @param Message $pbj
     * @param string  $template
     * @param string  $format
     *
     * @return string
     */
    protected function pbjTemplate(Message $pbj, string $template, string $format = 'html'): string
    {
        $curie = $pbj::schema()->getCurie();
        $path = str_replace('-', '_', sprintf('%s_%s/%s/%s',
            $curie->getVendor(), $curie->getPackage(), $curie->getCategory() ?: '_', $curie->getMessage()
        ));

        // example: @acme_users/request/search_users_response/page.html.twig
        return "@{$path}/{$template}.{$format}.twig";
    }

    /**
     * Creates a pbj form, handles it and returns the form instance.
     *
     * @param Request $request The request instance to pass to {@see Form::handleRequest}
     * @param string  $type    The fully qualified class name of the pbj form type
     * @param array   $input   The initial data for the form
     * @param array   $options Options for the form
     *
     * @return FormInterface
     */
    protected function handlePbjForm(Request $request, string $type, array $input = [], array $options = []): FormInterface
    {
        /** @var FormInterface $form */
        $form = $this->container->get('form.factory')->create($type, $input, $options);

        try {
            $form->handleRequest($request);
        } catch (\Exception $e) {
            $form->addError(new FormError($e->getMessage()));
        }

        $request->attributes->set('pbjx_input', $form->getData() ?: []);
        return $form;
    }

    /**
     * @return Pbjx
     */
    protected function getPbjx(): Pbjx
    {
        return $this->container->get('pbjx');
    }
}
