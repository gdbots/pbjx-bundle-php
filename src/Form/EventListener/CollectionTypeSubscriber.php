<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle\Form\EventListener;

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
        return [
            FormEvents::PRE_SUBMIT => 'preSubmit',
            FormEvents::SUBMIT => 'submit'
        ];
    }

    /**
     * Remove empty items to prevent validation.
     *
     * @param FormEvent $event
     */
    public function preSubmit(FormEvent $event): void
    {
        $items = $event->getData();

        if (!$items || !is_array($items)) {
            return;
        }

        $notEmptyItems = [];

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
     * Removes empty collection elements.
     *
     * @param FormEvent $event
     */
    public function submit(FormEvent $event): void
    {
        $items = $event->getData();

        if (!$items || !is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if ($this->isEmpty($item)) {
                unset($items[$index]);
            }
        }

        $event->setData($items);
    }

    /**
     * Check if value is empty.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isEmpty($value): bool
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
