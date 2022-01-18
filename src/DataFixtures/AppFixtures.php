<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Admin;
use App\Entity\Comment;
use App\Entity\Conference;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class AppFixtures extends Fixture
{
    private EncoderFactoryInterface $encoderFactory;

    public function __construct(EncoderFactoryInterface $encoderFactory)
    {
        $this->encoderFactory = $encoderFactory;
    }

    public function load(ObjectManager $manager): void
    {
        $amsterdam = (new Conference())
            ->setCity('Amsterdam')
            ->setYear('2022')
            ->setSlug('amsterdam-2022')
            ->setIsInternational(true);
        $manager->persist($amsterdam);

        $paris = (new Conference())
            ->setCity('Paris')
            ->setYear('2023')
            ->setSlug('paris-2023')
            ->setIsInternational(false);
        $manager->persist($paris);

        $comment1 = (new Comment())
            ->setConference($amsterdam)
            ->setAuthor('Fabien')
            ->setEmail('fabien@example.com')
            ->setText('This was a great conference.')
            ->setState('published');
        $manager->persist($comment1);

        $comment2 = (new Comment())
            ->setConference($amsterdam)
            ->setAuthor('Lucas')
            ->setEmail('lucas@example.com')
            ->setText('I think this one is going to be moderated.');
        $manager->persist($comment2);

        $admin = (new Admin())
            ->setRoles(['ROLE_ADMIN'])
            ->setUsername('admin')
            ->setPassword($this->encoderFactory->getEncoder(Admin::class)->encodePassword('admin', null));
        $manager->persist($admin);

        $manager->flush();
    }
}
