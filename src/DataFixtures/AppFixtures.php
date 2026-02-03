<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $now = new \DateTimeImmutable();

        // 1. Create Freelance Users
        for ($i = 1; $i <= 20; $i++) {
            $user = new User();
            $user->setEmail("user$i@freelanceflow.com");
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());
            $user->setCompanyName($faker->company());
            $user->setAddress($faker->streetAddress());
            $user->setPostCode($faker->postcode());
            $user->setCity($faker->city());
            $user->setCountry("France");
            $siret = str_replace(' ', '', $faker->siret());
            $user->setSiretNumber($siret);

            $user->setPassword($this->hasher->hashPassword($user, 'password'));
            $manager->persist($user);

            // 2. Create Clients for each User
            for ($j = 1; $j <= 20; $j++) {
                $client = new Client();
                $client->setFirstName($faker->firstName());
                $client->setLastName($faker->lastName());
                $client->setCompanyName($faker->company());
                $client->setPhoneNumber($faker->phoneNumber());
                $client->setAddress($faker->streetAddress());
                $client->setEmail($faker->email());

                $clientSiret = str_replace(' ', '', $faker->siret());
                $client->setSiret($clientSiret);
                $client->setCity($faker->city());
                $client->setPostCode(str_replace(' ', '', $faker->postcode()));
                $client->setCountry("France");
                $client->setUser($user);

                $manager->persist($client);

                // 3. Create a high volume of Invoices
                for ($k = 1; $k <= rand(50, 60); $k++) {
                    $invoice = new Invoice();
                    $invoice->setClient($client);
                    $invoice->setUser($user);
                    $invoice->setProjectTitle($faker->sentence(3));
                    $invoice->setCurrency('EUR');

                    // --- TIME LOGIC: Spanning 2023 to 2026 ---
                    // Random date between Jan 2023 and Feb 2026
                    $randomDate = $faker->dateTimeBetween('-4 years', 'now');
                    $sentDate = \DateTimeImmutable::createFromMutable($randomDate);

                    // Status Logic: 60% Paid, 30% Sent (could be overdue), 10% Draft
                    $randomVal = rand(1, 10);
                    if ($randomVal <= 6) {
                        $status = 'PAID';
                    } elseif ($randomVal <= 9) {
                        $status = 'SENT';
                    } else {
                        $status = 'DRAFT';
                    }

                    $invoice->setSentAt($sentDate);
            
                    $dueDate = $sentDate->modify('+30 days');
                    $invoice->setDueDate($dueDate);

                    $uniqueSuffix = str_pad((string)(($i * 1000) + ($j * 100) + $k), 5, '0', STR_PAD_LEFT);

                    if ($status === 'DRAFT') {
                        $invoice->setStatus('DRAFT');
                        $invoice->setInvoiceNumber("DRAFT-" . $uniqueSuffix);
                    } else {
                        $invoice->setInvoiceNumber("INV-" . $sentDate->format('Y') . "-" . $uniqueSuffix);
                        $invoice->setStatus($status);

                        if ($status === 'PAID') {
                            $invoice->setPaidAt($sentDate->modify('+' . rand(1, 45) . ' days'));
                        }
                    }

                    // Populate snapshots for PDF/History
                    $invoice->collectSnapshot();
                    $manager->persist($invoice);

                    // 4. Create Invoice Items
                    $totalHT = 0.0;
                    for ($l = 1; $l <= rand(1, 4); $l++) {
                        $qty = rand(1, 5);
                        $unit = $faker->randomFloat(2, 100, 2000);
                        $lineHT = $qty * $unit;

                        $item = new InvoiceItem();
                        $item->setDescription($faker->bs());
                        $item->setQuantity((string)$qty);
                        $item->setUnitPrice((string)$unit);
                        $item->setVatRate("20.0");
                        $item->setTotalHt((string)$lineHT);
                        $item->setVatAmount((string)($lineHT * 0.20));
                        $item->setTotalTtc((string)($lineHT * 1.20));
                        $item->setInvoice($invoice);

                        $manager->persist($item);
                        $totalHT += $lineHT;
                    }

                    $invoice->setTotalHt((string)$totalHT);
                    $invoice->setTotalVat((string)($totalHT * 0.20));
                    $invoice->setTotalAmount((string)($totalHT * 1.20));
                }
            }
        }

        $manager->flush();
    }
}
