<?php

namespace App\Command;

use App\Entity\Cheque;
use App\Service\Jdate;
use App\Service\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'hesabix:cheque:remind',
    description: 'Send reminders for cheques due in next 3 days'
)]
class CheckReminderCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Jdate $jdate,
        private Notification $notification
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = $this->jdate->GetTodayDate();
        $threeDaysLaterTs = time() + (3 * 86400);
        $threeDaysLater = $this->jdate->jdate('Y/m/d', $threeDaysLaterTs);

        $cheques = $this->entityManager->getRepository(Cheque::class)->findBy([
            'type' => 'output',
            'locked' => false,
            'reminderSent' => false,
        ]);

        $count = 0;
        foreach ($cheques as $cheque) {
            $payDate = $cheque->getPayDate();
            if (!$payDate) {
                continue;
            }

            // Skip rejected cheques
            if ($cheque->isRejected()) {
                continue;
            }

            // Jalali YYYY/MM/DD strings are lexicographically sortable
            if ($payDate >= $today && $payDate <= $threeDaysLater) {
                $message = 'چک پرداختنی به مبلغ ' . number_format((float)$cheque->getAmount()) . ' ریال در تاریخ ' . $cheque->getPayDate() . ' سررسید می‌شود';
                $this->notification->insert($message, '/acc/cheque/list', $cheque->getBid(), $cheque->getSubmitter());
                $cheque->setReminderSent(true);
                $this->entityManager->persist($cheque);
                $count++;
            }
        }

        $this->entityManager->flush();

        $output->writeln(sprintf('Sent %d cheque reminder(s).', $count));

        return Command::SUCCESS;
    }
}
