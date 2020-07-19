<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Bundle\PbjxBundle\Controller\PbjxController;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\EnvelopeV1;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

final class PbjxCommand extends Command
{
    use PbjxAwareCommandTrait;

    protected static $defaultName = 'pbjx';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Handles pbjx messages (command, event, request) and returns an envelope with the result')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will create a single pbjx message using the json
payload provided and return an envelope (also json) with the results.

<info>php %command.full_name% 'gdbots:pbjx:request:echo-request' '{"msg":"hello"}'</info>

EOF
            )
            ->addOption(
                'user-agent',
                null,
                InputOption::VALUE_REQUIRED,
                'The http user agent to run as for this command.'
            )
            ->addOption(
                'in-memory',
                null,
                InputOption::VALUE_NONE,
                'Forces all transports to be "in_memory". Useful for debugging or ensuring sequential processing.'
            )
            ->addOption(
                'device-view',
                null,
                InputOption::VALUE_REQUIRED,
                'When gdbots/app-bundle is in use you can provide device-view to ' .
                'populate request and server attributes.'
            )
            ->addOption(
                'pretty',
                null,
                InputOption::VALUE_NONE,
                'Prints the json response with JSON_PRETTY_PRINT.'
            )
            ->addArgument(
                'curie',
                InputArgument::REQUIRED,
                'The pbj message curie to use for the provided payload (json).'
            )
            ->addArgument(
                'json',
                InputArgument::OPTIONAL,
                'The pbj message itself as json (on one line).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $curie = SchemaCurie::fromString($input->getArgument('curie'));
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_ACCEPT_CHARSET'] = 'utf-8';
        $_SERVER['HTTP_USER_AGENT'] = $input->getOption('user-agent') ?: 'pbjx-console/2.x';

        $deviceView = $input->getOption('device-view');
        if (!empty($deviceView)) {
            $_SERVER['DEVICE_VIEW'] = $deviceView;
        }

        $request = Request::create(
            sprintf(
                '/pbjx/%s/%s/%s/%s',
                $curie->getVendor(),
                $curie->getPackage(),
                $curie->getCategory() ?: '_',
                $curie->getMessage()
            ),
            $_SERVER['REQUEST_METHOD'],
            [], // GET and POST (aka $_REQUEST)
            $_COOKIE,
            $_FILES,
            $_SERVER,
            $input->getArgument('json') ?: '{}'
        );

        /*
         * prepare the request object so http and console processing are virtually the same
         */
        $request->setRequestFormat('json');
        $request->attributes->set('pbjx_vendor', $curie->getVendor());
        $request->attributes->set('pbjx_package', $curie->getPackage());
        $request->attributes->set('pbjx_category', $curie->getCategory());
        $request->attributes->set('pbjx_message', $curie->getMessage());
        $request->attributes->set('pbjx_bind_unrestricted', true);
        $request->attributes->set('pbjx_console', true);
        if (!empty($deviceView)) {
            $request->attributes->set('device_view', $deviceView);
        }

        $this->useInMemoryTransports($input);
        $this->getRequestStack()->push($request);
        $envelope = $this->getPbjxController()->handleAction($request);

        try {
            $this->getPbjx()->triggerLifecycle($envelope, false);
        } catch (\Throwable $e) {
            /*
             * write to std error but return payload as is. Decorating the envelope
             * is not typically an exception worthy condition.
             */
            $errOutput->writeln('<error>' . $e->getMessage() . '</error>');
        }

        $envelope->set('ok', Code::OK === $envelope->get('code'));
        $output->writeln(json_encode($envelope, $input->getOption('pretty') ? JSON_PRETTY_PRINT : 0));

        return self::SUCCESS;
    }

    protected function getPbjxController(): PbjxController
    {
        return $this->container->get('gdbots_pbjx.pbjx_controller');
    }
}
