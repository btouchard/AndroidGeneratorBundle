<?php
/**
 * Created by IntelliJ IDEA.
 * User: benjamin
 * Date: 01/11/16
 * Time: 14:29
 */


namespace Kolapsis\Bundle\AndroidGeneratorBundle\Form;


use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntityType extends AbstractType {

    /**
     * @var ClassMetadata
     */
    private $entity;

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'entity' => null,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $this->entity = !empty($options['entity']) ? $options['entity'] : null;
        if (!empty($this->entity)) {
            foreach ($this->entity->getFieldNames() as $name) {
                if ($name != 'id' && substr($name, 0, 1) != '_') {
                    $builder->add($name);
                }
            }
        }
    }

}