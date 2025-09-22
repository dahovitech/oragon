<?php

namespace App\DataFixtures;

use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class LanguageFixtures extends Fixture implements FixtureGroupInterface
{
    public const LANGUAGE_FR_REFERENCE = 'language-fr';
    public const LANGUAGE_EN_REFERENCE = 'language-en';
    public const LANGUAGE_ES_REFERENCE = 'language-es';
    public const LANGUAGE_DE_REFERENCE = 'language-de';

    public function load(ObjectManager $manager): void
    {
        // Français - Langue par défaut
        $languageFr = new Language();
        $languageFr->setCode('fr')
                   ->setName('Français')
                   ->setNativeName('Français')
                   ->setIsActive(true)
                   ->setIsDefault(true)
                   ->setSortOrder(1);
        
        $manager->persist($languageFr);
        $this->addReference(self::LANGUAGE_FR_REFERENCE, $languageFr);

        // Anglais
        $languageEn = new Language();
        $languageEn->setCode('en')
                   ->setName('Anglais')
                   ->setNativeName('English')
                   ->setIsActive(true)
                   ->setIsDefault(false)
                   ->setSortOrder(2);
        
        $manager->persist($languageEn);
        $this->addReference(self::LANGUAGE_EN_REFERENCE, $languageEn);

        // Espagnol
        $languageEs = new Language();
        $languageEs->setCode('es')
                   ->setName('Espagnol')
                   ->setNativeName('Español')
                   ->setIsActive(true)
                   ->setIsDefault(false)
                   ->setSortOrder(3);
        
        $manager->persist($languageEs);
        $this->addReference(self::LANGUAGE_ES_REFERENCE, $languageEs);

        // Allemand
        $languageDe = new Language();
        $languageDe->setCode('de')
                   ->setName('Allemand')
                   ->setNativeName('Deutsch')
                   ->setIsActive(true)
                   ->setIsDefault(false)
                   ->setSortOrder(4);
        
        $manager->persist($languageDe);
        $this->addReference(self::LANGUAGE_DE_REFERENCE, $languageDe);

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['language', 'dev'];
    }
}
