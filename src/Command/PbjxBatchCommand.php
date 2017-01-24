<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Exception\DeserializeMessageFailed;
use Gdbots\Pbj\Serializer\JsonSerializer;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;

class PbjxBatchCommand extends ContainerAwareCommand
{
    use PbjxAwareCommandTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:batch')
            ->setAliases(['pbjx:lines'])
            ->setDescription('Reads messages from a newline-delimited JSON file and processes them.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will read messages (pbj commands or events) from a 
newline-delimited JSON file and run pbjx->send or pbjx->publish.

<info>php %command.full_name% --dry-run /path/to/file/message.jsonl</info>

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
                'Forces all transports to be "in_memory".  Useful for debugging or ensuring sequential processing.'
            )
            ->addOption(
                'device-view',
                null,
                InputOption::VALUE_REQUIRED,
                'When gdbots/app-bundle is in use you can provide device-view to ' .
                'populate request and server attributes.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Reads lines and creates messages but will NOT process them.'
            )
            ->addOption(
                'skip-invalid',
                null,
                InputOption::VALUE_NONE,
                'Skip any lines that fail to deserialize.'
            )
            ->addOption(
                'skip-errors',
                null,
                InputOption::VALUE_NONE,
                'Skip any messages that fail to send/publish.'
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of lines to read at a time.',
                100
            )
            ->addOption(
                'batch-delay',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of milliseconds (1000 = 1 second) to delay between batches.',
                1000
            )
            ->addOption(
                'start-line',
                null,
                InputOption::VALUE_REQUIRED,
                'Start processing AT this line number.',
                1
            )
            ->addOption(
                'end-line',
                null,
                InputOption::VALUE_REQUIRED,
                'Stop processing AFTER this line number.',
                PHP_INT_MAX
            )
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'The full path to a json line delimited file with pbj messages.'
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dryRun = $input->getOption('dry-run');
        $skipInvalid = $input->getOption('skip-invalid');
        $skipErrors = $input->getOption('skip-errors');
        $batchSize = NumberUtils::bound($input->getOption('batch-size'), 1, 5000);
        $batchDelay = NumberUtils::bound($input->getOption('batch-delay'), 100, 600000);
        $startLine = $input->getOption('start-line');
        $endLine = $input->getOption('end-line');
        $file = $input->getArgument('file');

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Reading messages from "%s"', $file));
        $this->useInMemoryTransports($input, $io);
        if (!$this->readyForPbjxTraffic($io, 'Aborting json lines processing.')) {
            return;
        }

        if (!file_exists($file) || !is_readable($file)) {
            $io->error(sprintf('File "%s" must exist and be readable.', $file));
            return;
        }

        /*
         * Pbjx processes are somewhat origination agnostic so we'll just make their
         * environments seem similar
         */
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_ACCEPT_CHARSET'] = 'utf-8';
        $_SERVER['HTTP_USER_AGENT'] = $input->getOption('user-agent') ?: 'pbjx-console/0.x';

        $deviceView = $input->getOption('device-view');
        if (!empty($deviceView)) {
            $_SERVER['DEVICE_VIEW'] = $deviceView;
            putenv('DEVICE_VIEW=' . $deviceView);
        }

        $handle = @fopen($file, 'r');
        if (!$handle) {
            $io->error(sprintf('Unable to open file "%s".', $file));
            return;
        }

        $requestStack = $this->getRequestStack();
        $pbjx = $this->getPbjx();
        $serializer = new JsonSerializer();
        $batch = 1;
        $i = 0;
        $processed = 0;
        $io->comment(sprintf('Processing batch %d from file "%s".', $batch, $file));
        $io->newLine();

        while (($line = fgets($handle)) !== false) {
            ++$i;

            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if ($i < $startLine) {
                continue;
            }

            if ($i > $endLine) {
                $io->note(sprintf('%d. End line reached, stopping process.', $endLine));
                break;
            }

            try {
                /** @var Command|Event $message */
                $message = $serializer->deserialize($line);
                $curie = $message::schema()->getCurie();
                $ref = $message->generateMessageRef()->toString();

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
                    $line
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

                $requestStack->pop();
                $requestStack->push($request);
                ++$processed;

                if ($dryRun) {
                    $io->text(sprintf(
                        '<info>%d.</info> DRY RUN <comment>ref:</comment>%s, <comment>occurred_at:</comment>%s',
                        $i,
                        $ref,
                        $message->get('occurred_at')
                    ));
                } else {
                    if ($message instanceof Command) {
                        $io->text(sprintf(
                            '<info>%d.</info> Sending <comment>ref:</comment>%s, <comment>occurred_at:</comment>%s',
                            $i,
                            $ref,
                            $message->get('occurred_at')
                        ));
                        $pbjx->send($message);
                    } elseif ($message instanceof Event) {
                        $io->text(sprintf(
                            '<info>%d.</info> Publishing <comment>ref:</comment>%s, <comment>occurred_at:</comment>%s',
                            $i,
                            $ref,
                            $message->get('occurred_at')
                        ));
                        $pbjx->publish($message);
                    } else {
                        $io->warning(sprintf('%d. Ignoring "%s" since it\'s not a command or event.', $i, $ref));
                        --$processed;
                    }
                }
            } catch (DeserializeMessageFailed $de) {
                $io->error(sprintf('%d. %s', $i, $de->getMessage()));
                $io->note(sprintf('%d. Failed to deserialize json line below:', $i));
                $io->text($line);
                $io->newLine(2);
                if (!$skipInvalid) {
                    break;
                }
            } catch (\Exception $e) {
                $io->error(sprintf('%d. %s', $i, $e->getMessage()));
                $io->newLine(2);
                if (!$skipErrors) {
                    break;
                }
            }

            if (0 === $i % $batchSize) {
                ++$batch;

                if ($batchDelay > 0) {
                    $io->newLine();
                    $io->note(sprintf('Pausing for %d milliseconds.', $batchDelay));
                    usleep($batchDelay * 1000);
                }

                $io->comment(sprintf('Processing batch %d from file "%s".', $batch, $file));
                $io->newLine();
            }
        }

        @fclose($handle);

        $io->newLine();
        $io->success(sprintf('Processed %s messages from "%s".', number_format($processed), $file));
    }
}
