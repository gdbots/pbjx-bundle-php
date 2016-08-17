<?php

namespace Gdbots\Bundle\PbjxBundle\Controller;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;

trait PbjxAwareControllerTrait
{
    /**
     * @return Pbjx
     */
    public function getPbjx()
    {
        return $this->container->get('pbjx');
    }

    /**
     * Returns a reference to a twig template based on the schema of the provided message (pbj schema).
     * This allows for component style development for pbj messages.  You are asking for a template that
     * can render your message (e.g. Article) as a "card", "modal", "page".
     *
     * This can be combined with "DeviceViewRendererTrait::renderUsingDeviceView".
     *
     * E.g. return $this->renderUsingDeviceView($this->pbjTemplate($pbj, 'page%device_view%'), ['pbj' => $pbj]);
     *
     * @param Message $pbj
     * @param string $template
     * @param string $format
     *
     * @return string
     */
    public function pbjTemplate(Message $pbj, $template, $format = 'html')
    {
        $curieStr = str_replace('::', ':_:', $pbj::schema()->getCurie()->toString());
        return sprintf('@%s/%s.%s.twig', str_replace(':', '/', $curieStr), $template, $format);
    }
}
