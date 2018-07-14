<?php

namespace BigBridge\ProductImport\Model\Updater;

use BigBridge\ProductImport\Api\UrlRewriteUpdateLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Patrick van Bergen
 */
class UrlRewriteUpdateCommandLogger implements UrlRewriteUpdateLogger
{
    /**
     * @var OutputInterface
     */
    protected $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param string $info
     */
    public function info(string $info)
    {
        $this->output->writeln("<info>" . $info . "<info>");
    }
}