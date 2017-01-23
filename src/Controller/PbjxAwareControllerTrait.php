<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle\Controller;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

trait PbjxAwareControllerTrait
{
    /**
     * Returns a reference to a twig template based on the schema of the provided message (pbj schema).
     * This allows for component style development for pbj messages.  You are asking for a template that
     * can render your message (e.g. Article) as a "card", "modal", "page", etc.
     *
     * This can be combined with "DeviceViewRendererTrait::renderUsingDeviceView".
     *
     * E.g. return $this->renderUsingDeviceView($this->pbjTemplate($pbj, 'page%device_view%'), ['pbj' => $pbj]);
     *
     * @param Message $pbj
     * @param string  $template
     * @param string  $format
     *
     * @return string
     */
    protected function pbjTemplate(Message $pbj, string $template, string $format = 'html'): string
    {
        $curieStr = str_replace('::', ':_:', $pbj::schema()->getCurie()->toString());
        return sprintf('@%s/%s.%s.twig', str_replace(':', '/', $curieStr), $template, $format);
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
    protected function handlePbjForm(
        Request $request,
        string $type,
        array $input = [],
        array $options = []
    ): FormInterface {
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
