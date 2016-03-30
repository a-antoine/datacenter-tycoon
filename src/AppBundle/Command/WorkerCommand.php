<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Server\Worker;

class WorkerCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('dct:worker')
            ->setDescription('Start the DCT worker')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        \Predis\Autoloader::register();
        $predis = new \Predis\Client();

        $zmqcontext = new \ZMQContext();
        $socket = $zmqcontext->getSocket(\ZMQ::SOCKET_PUSH);
        $socket->connect('tcp://localhost:5555');

        $em = $this->getContainer()->get('doctrine')->getManager();

        $worker = new Worker($em);

        while(true)
        {
            $timeStart = microtime(true);

            $date = new \DateTime($predis->get('date'));
            $data['date'] = $date->format('Y-m-d H:i:s');

            //$servers = $em->getRepository('AppBundle:Server')->findByIds([3, 4]);
            $servers = $em->getRepository('AppBundle:Server')->findAll();
            $data['servers'] = $worker->updateServer($servers, $date);

            $jsonencode = json_encode($data);
            $socket->send($jsonencode);

            $date->add(new \DateInterval('PT10M'));
            $predis->set('date', $date->format('Y-m-d H:i:s'));

            $timeEnd = microtime(true);
            $output->writeln(number_format($timeEnd - $timeStart, 4) * 1000 . ' ms');
            usleep(1000000 - ($timeEnd - $timeStart) * 1000000);
        }
    }
}
