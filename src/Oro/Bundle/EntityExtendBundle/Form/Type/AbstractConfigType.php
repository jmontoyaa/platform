<?php

namespace Oro\Bundle\EntityExtendBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\ConfigIdInterface;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;

/**
 * The abstract form type for form types are used to work with entity config attributes
 * The goal of this form type is to check if an association is set
 * and mark entity as as "Required Update".
 */
abstract class AbstractConfigType extends AbstractType
{
    /** @var ConfigManager */
    protected $configManager;

    /**
     * @param ConfigManager $configManager
     */
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, array($this, 'postSubmit'));
    }

    /**
     * POST_SUBMIT event handler
     *
     * @param FormEvent $event
     */
    public function postSubmit(FormEvent $event)
    {
        $form    = $event->getForm();
        $options = $form->getConfig()->getOptions();
        /** @var ConfigIdInterface $configId */
        $configId = $options['config_id'];

        // change the entity state to "Requires update" if the attribute has "require_schema_update" option
        // and the value of the attribute was changed
        $configProvider = $this->configManager->getProvider($configId->getScope());
        if ($configProvider->getPropertyConfig()->isSchemaUpdateRequired($form->getName(), $configId)) {
            $newVal = $form->getData();
            $oldVal = $this->configManager->getConfig($configId)->get($form->getName());
            if ($this->isSchemaUpdateRequired($newVal, $oldVal)) {
                $extendConfigProvider = $this->configManager->getProvider('extend');
                $extendConfig         = $extendConfigProvider->getConfig($configId->getClassName());
                if ($extendConfig->is('state', ExtendScope::STATE_ACTIVE)) {
                    $extendConfig->set('state', ExtendScope::STATE_UPDATED);

                    $extendConfigProvider->persist($extendConfig);
                    $extendConfigProvider->flush();
                }
            }
        }
    }

    /**
     * Checks if the changes require the schema update
     *
     * @param mixed $newVal
     * @param mixed $oldVal
     *
     * @return bool
     */
    protected function isSchemaUpdateRequired($newVal, $oldVal)
    {
        // we cannot use strict comparison here because Symfony form
        // converts empty value (false, 0, empty string) => null, true => 1
        return $newVal != $oldVal;
    }
}
