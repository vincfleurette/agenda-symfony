<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class SheetSelectionType extends AbstractType
{
    protected TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder->add('sheet', ChoiceType::class, [
            'choices' => array_flip($options['sheets']), // Map 'sheets' to the dropdown
            'label' => $this->translator->trans('formSheetChoice'),
            'placeholder' => $this->translator->trans('formSheetChoiceSeelct'),
            'required' => true,
            'attr' => [
                'class' => 'form-control',
            ],
            'label_attr' => [
                'class' => 'input-group-text',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('sheets');
        $resolver->setAllowedTypes('sheets', 'array');
    }
}
