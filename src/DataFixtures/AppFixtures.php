<?php

namespace App\DataFixtures;

use App\Entity\Diocese;
use App\Entity\Messe;
use App\Entity\OccurrenceMesse;
use App\Entity\Paroisse;
use App\Entity\User;
use App\Enum\RecurrenceMesse;
use App\Enum\StatutDiocese;
use App\Enum\StatutMesse;
use App\Enum\TypeMesse;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Create Super User
        $superUser = new User();
        $superUser->setEmail('admin@amissa.bj');
        $superUser->setNom('Administrateur');
        $superUser->setPrenom('Super');
        $superUser->setRoles(['ROLE_SUPER_USER']);
        $superUser->setPassword($this->passwordHasher->hashPassword($superUser, 'admin123'));
        $manager->persist($superUser);

        // Create Diocese Cotonou
        $dioceseCotonou = new Diocese();
        $dioceseCotonou->setNom('Archidiocese de Cotonou');
        $dioceseCotonou->setStatut(StatutDiocese::ACTIF);
        $dioceseCotonou->setCreatedBy($superUser);
        $dioceseCotonou->setFedapayApiKey('sk_sandbox_cotonou_key');
        $manager->persist($dioceseCotonou);

        // Create Diocese Parakou
        $dioceseParakou = new Diocese();
        $dioceseParakou->setNom('Diocese de Parakou');
        $dioceseParakou->setStatut(StatutDiocese::ACTIF);
        $dioceseParakou->setCreatedBy($superUser);
        $dioceseParakou->setFedapayApiKey('sk_sandbox_parakou_key');
        $manager->persist($dioceseParakou);

        // Create Diocese Admin for Cotonou
        $adminCotonou = new User();
        $adminCotonou->setEmail('admin.cotonou@amissa.bj');
        $adminCotonou->setNom('Dupont');
        $adminCotonou->setPrenom('Jean');
        $adminCotonou->setRoles(['ROLE_ADMIN_DIOCESE']);
        $adminCotonou->setDiocese($dioceseCotonou);
        $adminCotonou->setPassword($this->passwordHasher->hashPassword($adminCotonou, 'diocese123'));
        $manager->persist($adminCotonou);

        // Create Diocese Admin for Parakou
        $adminParakou = new User();
        $adminParakou->setEmail('admin.parakou@amissa.bj');
        $adminParakou->setNom('Adanho');
        $adminParakou->setPrenom('Michel');
        $adminParakou->setRoles(['ROLE_ADMIN_DIOCESE']);
        $adminParakou->setDiocese($dioceseParakou);
        $adminParakou->setPassword($this->passwordHasher->hashPassword($adminParakou, 'diocese123'));
        $manager->persist($adminParakou);

        // Create Cathedrale Cotonou
        $cathedrale = new Paroisse();
        $cathedrale->setNom('Cathedrale Notre-Dame de Cotonou');
        $cathedrale->setDiocese($dioceseCotonou);
        $cathedrale->setAdresse('Avenue Clozel, Cotonou');
        $cathedrale->setNumeroMobileMoney('+22996000001');
        $cathedrale->setDelaiMinimumJours(3);
        $manager->persist($cathedrale);

        // Create Paroisse Akpakpa
        $akpakpa = new Paroisse();
        $akpakpa->setNom('Paroisse Saint-Michel d\'Akpakpa');
        $akpakpa->setDiocese($dioceseCotonou);
        $akpakpa->setAdresse('Quartier Akpakpa, Cotonou');
        $akpakpa->setNumeroMobileMoney('+22996000002');
        $akpakpa->setDelaiMinimumJours(2);
        $manager->persist($akpakpa);

        // Create Paroisse Parakou
        $paroisseParakou = new Paroisse();
        $paroisseParakou->setNom('Cathedrale Saint-Jean de Parakou');
        $paroisseParakou->setDiocese($dioceseParakou);
        $paroisseParakou->setAdresse('Centre-ville, Parakou');
        $paroisseParakou->setNumeroMobileMoney('+22997000001');
        $paroisseParakou->setDelaiMinimumJours(2);
        $manager->persist($paroisseParakou);

        // Create Parish Admin for Cathedrale
        $adminParoisse = new User();
        $adminParoisse->setEmail('admin.cathedrale@amissa.bj');
        $adminParoisse->setNom('Martin');
        $adminParoisse->setPrenom('Pierre');
        $adminParoisse->setRoles(['ROLE_ADMIN_PAROISSE']);
        $adminParoisse->setDiocese($dioceseCotonou);
        $adminParoisse->setParoisse($cathedrale);
        $adminParoisse->setPassword($this->passwordHasher->hashPassword($adminParoisse, 'paroisse123'));
        $manager->persist($adminParoisse);

        // Create Secretaire for Akpakpa
        $secretaire = new User();
        $secretaire->setEmail('secretaire.akpakpa@amissa.bj');
        $secretaire->setNom('Ahouandjinou');
        $secretaire->setPrenom('Marie');
        $secretaire->setRoles(['ROLE_SECRETAIRE']);
        $secretaire->setDiocese($dioceseCotonou);
        $secretaire->setParoisse($akpakpa);
        $secretaire->setPassword($this->passwordHasher->hashPassword($secretaire, 'secretaire123'));
        $manager->persist($secretaire);

        // Create Fidele user
        $fidele = new User();
        $fidele->setEmail('fidele@example.com');
        $fidele->setNom('Koutchika');
        $fidele->setPrenom('Prudence');
        $fidele->setRoles(['ROLE_FIDELE']);
        $fidele->setPassword($this->passwordHasher->hashPassword($fidele, 'fidele123'));
        $manager->persist($fidele);

        // Create recurring mass - Daily morning mass at Cathedrale
        $messeMatin = new Messe();
        $messeMatin->setParoisse($cathedrale);
        $messeMatin->setTitre('Messe du matin');
        $messeMatin->setType(TypeMesse::RECURRENTE);
        $messeMatin->setRecurrence(RecurrenceMesse::QUOTIDIENNE);
        $messeMatin->setHeure(new \DateTime('06:30'));
        $messeMatin->setMontantSuggere('2000.00');
        $messeMatin->setStatut(StatutMesse::ACTIVE);
        $manager->persist($messeMatin);

        // Create recurring mass - Sunday mass at Cathedrale
        $messeDimanche = new Messe();
        $messeDimanche->setParoisse($cathedrale);
        $messeDimanche->setTitre('Messe dominicale');
        $messeDimanche->setType(TypeMesse::RECURRENTE);
        $messeDimanche->setRecurrence(RecurrenceMesse::HEBDOMADAIRE);
        $messeDimanche->setHeure(new \DateTime('09:00'));
        $messeDimanche->setJourSemaine(0); // Sunday
        $messeDimanche->setMontantSuggere('5000.00');
        $messeDimanche->setStatut(StatutMesse::ACTIVE);
        $manager->persist($messeDimanche);

        // Create recurring mass - Wednesday evening at Akpakpa
        $messeMercredi = new Messe();
        $messeMercredi->setParoisse($akpakpa);
        $messeMercredi->setTitre('Messe du mercredi soir');
        $messeMercredi->setType(TypeMesse::RECURRENTE);
        $messeMercredi->setRecurrence(RecurrenceMesse::HEBDOMADAIRE);
        $messeMercredi->setHeure(new \DateTime('18:00'));
        $messeMercredi->setJourSemaine(3); // Wednesday
        $messeMercredi->setMontantSuggere('2500.00');
        $messeMercredi->setStatut(StatutMesse::ACTIVE);
        $manager->persist($messeMercredi);

        // Create recurring mass - Sunday at Akpakpa
        $messeDimancheAkpakpa = new Messe();
        $messeDimancheAkpakpa->setParoisse($akpakpa);
        $messeDimancheAkpakpa->setTitre('Messe dominicale');
        $messeDimancheAkpakpa->setType(TypeMesse::RECURRENTE);
        $messeDimancheAkpakpa->setRecurrence(RecurrenceMesse::HEBDOMADAIRE);
        $messeDimancheAkpakpa->setHeure(new \DateTime('10:00'));
        $messeDimancheAkpakpa->setJourSemaine(0); // Sunday
        $messeDimancheAkpakpa->setMontantSuggere('3000.00');
        $messeDimancheAkpakpa->setStatut(StatutMesse::ACTIVE);
        $manager->persist($messeDimancheAkpakpa);

        // Create recurring mass at Parakou
        $messeDimancheParakou = new Messe();
        $messeDimancheParakou->setParoisse($paroisseParakou);
        $messeDimancheParakou->setTitre('Messe dominicale');
        $messeDimancheParakou->setType(TypeMesse::RECURRENTE);
        $messeDimancheParakou->setRecurrence(RecurrenceMesse::HEBDOMADAIRE);
        $messeDimancheParakou->setHeure(new \DateTime('08:00'));
        $messeDimancheParakou->setJourSemaine(0); // Sunday
        $messeDimancheParakou->setMontantSuggere('2000.00');
        $messeDimancheParakou->setStatut(StatutMesse::ACTIVE);
        $manager->persist($messeDimancheParakou);

        $manager->flush();

        // Generate occurrences for the next 30 days
        $this->generateOccurrences($manager, $messeMatin, 30);
        $this->generateOccurrences($manager, $messeDimanche, 30);
        $this->generateOccurrences($manager, $messeMercredi, 30);
        $this->generateOccurrences($manager, $messeDimancheAkpakpa, 30);
        $this->generateOccurrences($manager, $messeDimancheParakou, 30);

        $manager->flush();
    }

    private function generateOccurrences(ObjectManager $manager, Messe $messe, int $days): void
    {
        $startDate = new \DateTime('today');
        $endDate = (clone $startDate)->modify("+{$days} days");

        $current = clone $startDate;
        while ($current <= $endDate) {
            $shouldCreate = match($messe->getRecurrence()) {
                RecurrenceMesse::QUOTIDIENNE => true,
                RecurrenceMesse::HEBDOMADAIRE => (int) $current->format('w') === $messe->getJourSemaine(),
                RecurrenceMesse::MENSUELLE => (int) $current->format('j') === 1,
                default => false,
            };

            if ($shouldCreate) {
                $occurrence = new OccurrenceMesse();
                $occurrence->setMesse($messe);

                $dateHeure = clone $current;
                $dateHeure->setTime(
                    (int) $messe->getHeure()->format('H'),
                    (int) $messe->getHeure()->format('i')
                );
                $occurrence->setDateHeure($dateHeure);

                $manager->persist($occurrence);
            }

            $current->modify('+1 day');
        }
    }
}
