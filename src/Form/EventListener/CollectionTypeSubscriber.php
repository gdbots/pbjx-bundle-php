<?php

namespace Gdbots\Bundle\PbjxBundle\Form\EventListener;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CollectionTypeSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::POST_SUBMIT => 'postSubmit',
            FormEvents::PRE_SUBMIT  => 'preSubmit'
        );
    }

    /**
     * Removes empty collection elements.
     *
     * @param FormEvent $event
     */
    public function postSubmit(FormEvent $event)
    {
        /** @var Collection $items */
        $items = $event->getData();

        if (!$items || !$items instanceof Collection) {
            return;
        }

        foreach ($items as $item) {
            if ($this->isEmpty($item)) {
                $items->removeElement($item);
            }
        }
    }

    /**
     * Remove empty items to prevent validation.
     *
     * @param FormEvent $event
     */
    public function preSubmit(FormEvent $event)
    {
        $items = $event->getData();

        if (!$items || !is_array($items)) {
            return;
        }

        $notEmptyItems = array();

        // remove empty items
        foreach ($items as $index => $item) {
            if (!$this->isEmpty($item)) {
                $notEmptyItems[$index] = $item;
            }
        }

        $items = $notEmptyItems;

        $event->setData($items);
    }

    /**
     * Check if value is empty
     *
     * @param array $array
     *
     * @return bool
     */
    protected function isEmpty($value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }
        foreach ($value as $val) {
            if (is_array($val)) {
                if (!$this->isEmpty($val)) {
                    return false;
                }
            } elseif (!empty($val)) {
                return false;
            }
        }
        return true;
    }
}
