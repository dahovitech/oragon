<?php

namespace App\DataFixtures;

use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create languages
        $french = new Language();
        $french->setCode('fr')
            ->setName('Français')
            ->setNativeName('Français')
            ->setIsActive(true)
            ->setIsDefault(true)
            ->setSortOrder(1);
        $manager->persist($french);

        $english = new Language();
        $english->setCode('en')
            ->setName('Anglais')
            ->setNativeName('English')
            ->setIsActive(true)
            ->setIsDefault(false)
            ->setSortOrder(2);
        $manager->persist($english);

        $spanish = new Language();
        $spanish->setCode('es')
            ->setName('Espagnol')
            ->setNativeName('Español')
            ->setIsActive(true)
            ->setIsDefault(false)
            ->setSortOrder(3);
        $manager->persist($spanish);

        $german = new Language();
        $german->setCode('de')
            ->setName('Allemand')
            ->setNativeName('Deutsch')
            ->setIsActive(true)
            ->setIsDefault(false)
            ->setSortOrder(4);
        $manager->persist($german);

        // Flush languages
        $manager->flush();
    }
}
