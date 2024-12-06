<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SheetSelectionType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add("sheet", ChoiceType::class, [
            "choices" => array_flip($options["sheets"]), // Map 'sheets' to the dropdown
            "label" => "Choisissez une feuille",
            "placeholder" => "Sélectionnez une feuille",
            "required" => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired("sheets");
        $resolver->setAllowedTypes("sheets", "array");
    }
}
