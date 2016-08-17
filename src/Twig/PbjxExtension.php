<?php

namespace Gdbots\Bundle\PbjxBundle\Twig;

use Gdbots\Pbj\Message;

class PbjxExtension extends \Twig_Extension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('pbj_template', [$this, 'pbjTemplate'])
        ];
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
        // todo: memoize?
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

    /*
     * todo: add pbjx_request twig function... i.e. pbjx_request(curie, data)
     *
     * {% set pbjx_response = pbjx_request(curie, data) %}
     * {% if pbjx_response %}
     *   {% include pbj_template(pbjx_response, 'card', device_view) with {'pbj': pbjx_response} %}
     * {% endif %}
     */

    /**
     * @return string
     */
    public function getName()
    {
        return 'gdbots_pbjx_extension';
    }
}
