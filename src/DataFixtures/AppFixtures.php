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

        // 1. Create Freelance Users
        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->setEmail("user$i@test.com");
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());
            $user->setCompanyName($faker->company());
            
            $siret = str_replace(' ', '', $faker->siret());
            $user->setSiretNumber($siret);

            $password = $this->hasher->hashPassword($user, 'password');
            $user->setPassword($password);

            $manager->persist($user);

            // 2. Create Clients for each User
            for ($j = 1; $j <= 5; $j++) {
                $client = new Client();
                $client->setFirstName($faker->firstName());
                $client->setLastName($faker->lastName());
                $client->setCompanyName($faker->company());
                $client->setPhoneNumber($faker->phoneNumber());
                $client->setAddress($faker->streetAddress());
                $client->setEmail($faker->email());
                
                $clientSiret = str_replace(' ', '', $faker->siret());
                $client->setSiret($clientSiret);
                $client->setVatNumber("FR" . substr($clientSiret, 0, 11));
                
                $client->setCity($faker->city());
                $client->setPostCode(str_replace(' ', '', $faker->postcode()));
                $client->setCountry("France");
                $client->setUser($user);

                $manager->persist($client);

                // 3. Create Invoices for each Client
                for ($k = 1; $k <= rand(3, 8); $k++) {
                    $invoice = new Invoice();
                    $invoice->setClient($client);
                    $invoice->setUser($user);
                    $invoice->setProjectTitle($faker->sentence(3));
                    $invoice->setCurrency('EUR'); // New attribute default

                    // Status Logic
                    $statuses = ['SENT', 'DRAFT', 'PAID'];
                    $status = $faker->randomElement($statuses);
                    
                    // Generate a unique reference
                    $uniqueSuffix = str_pad((string)(($i * 1000) + ($j * 100) + $k), 4, '0', STR_PAD_LEFT);
                    $now = new \DateTimeImmutable();

                    if ($status === 'DRAFT') {
                        $invoice->setStatus('DRAFT');
                        $invoice->setInvoiceNumber("DRAFT-" . $uniqueSuffix);
                        // Drafts shouldn't have snapshots or dates usually
                    } else {
                        // For SENT and PAID, we use your internal logic
                        $invoice->setInvoiceNumber("INV-2026-" . $uniqueSuffix);
                        
                        $daysAgo = $faker->numberBetween(5, 60);
                        $sentDate = $now->modify("-$daysAgo days");
                        
                        $invoice->setSentAt($sentDate);
                        $invoice->setDueDate($sentDate->modify('+30 days'));
                        
                        // IMPORTANT: Use your new entity method to populate all frozen fields!
                        $invoice->collectSnapshot();

                        if ($status === 'PAID') {
                            $invoice->setStatus('PAID');
                            // Paid sometime between sent date and now
                            $invoice->setPaidAt($sentDate->modify('+' . rand(1, 15) . ' days'));
                        } else {
                            $invoice->setStatus('SENT');
                        }
                    }

                    $manager->persist($invoice);

                    // 4. Create Invoice Items
                    $totalHT = 0.0;
                    $totalVAT = 0.0;

                    for ($l = 1; $l <= rand(2, 5); $l++) {
                        $qty = $faker->numberBetween(1, 10);
                        $unit = $faker->randomFloat(2, 50, 1000);
                        $vatRate = 20.0;

                        $lineHT = $qty * $unit;
                        $lineVAT = $lineHT * ($vatRate / 100);
                        $lineTTC = $lineHT + $lineVAT;

                        $item = new InvoiceItem();
                        $item->setDescription($faker->sentence(4));
                        $item->setQuantity((string) $qty);
                        $item->setUnitPrice((string) number_format($unit, 2, '.', ''));
                        $item->setVatRate((string) $vatRate);
                        $item->setTotalHt((string) number_format($lineHT, 2, '.', ''));
                        $item->setVatAmount((string) number_format($lineVAT, 2, '.', ''));
                        $item->setTotalTtc((string) number_format($lineTTC, 2, '.', ''));
                        $item->setInvoice($invoice);
                        
                        $manager->persist($item);

                        $totalHT += $lineHT;
                        $totalVAT += $lineVAT;
                    }

                    // 5. Finalize Invoice Totals
                    $invoice->setTotalHt((string) number_format($totalHT, 2, '.', ''));
                    $invoice->setTotalVat((string) number_format($totalVAT, 2, '.', ''));
                    $invoice->setTotalAmount((string) number_format($totalHT + $totalVAT, 2, '.', ''));
                }
            }
        }

        $manager->flush();
    }
}
