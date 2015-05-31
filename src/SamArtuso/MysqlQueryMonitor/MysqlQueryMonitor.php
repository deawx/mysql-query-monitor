<?php
/**
 * MysqlQueryMonitor.php
 *
 * @author    Sam Artuso <sam@highoctanedev.co.uk>
 * @copyright 2015 Samuele Artuso
 */

namespace SamArtuso\MysqlQueryMonitor;

use FlorianWolters\Component\Util\Singleton\SingletonTrait;
use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;
use Symfony\Component\Process\Process;
use SqlFormatter;

/**
 * Main class for the mysql-query-monitor app.
 */
class MysqlQueryMonitor
{
    use SingletonTrait;
    
    /**
     * The version number.
     *
     * @var string
     */
    protected $version;
    
    /**
     * The banner.
     *
     * @var string
     */
    protected $banner;
    
    /**
     * The command line options.
     *
     * @var Getopt
     */
    protected $getopt;
    
    /**
     * The process used to read the MySQL general log file.
     *
     * @var Process
     */
    protected $process;
    
    /**
     * Runs the app.
     */
    public function run()
    {
        /*
         * Parse command line options.
         */
        $this->getopt = new Getopt(array(
            new Option('h', 'help'),
            new Option('v', 'version'),
            new Option('f', 'general-log-file', Getopt::REQUIRED_ARGUMENT),
        ));
        $this->fetchVersion();
        $this->setUpBanner();
        
        try {
            $this->getopt->parse();
        } catch (\UnexpectedValueException $e) {
            $this->outputToStderr('Error: ' . $e->getMessage() . '.' . PHP_EOL);
            $this->printUsage(false);
            exit(1);
        }

        /*
         * Print help message
         */
        if (1 === $this->getopt->getOption('h')) {
            $this->printUsage();
            exit(0);
        }
        
        /*
         * Print version number
         */
        if (1 === $this->getopt->getOption('v')) {
         $this->printVersion();
        }
        
        /*
         * Open MySQL general log file
         */
        $logFile = $this->getopt->getOption('f');
        if (null === $logFile) {
            $this->outputToStderr('Error: You must specify the path to the MySQL general log file with the -f option.' . PHP_EOL);
            $this->printUsage(false);
            exit(1);
        }
        
        if (!file_exists($logFile)) {
            $this->outputToStderr('Error: Couldn\'t find file "' . $logFile . '".' . PHP_EOL);
            exit(1);
        }
        
        if (!is_readable($logFile)) {
            $this->outputToStderr('Error: Couldn\'t read file "' . $logFile . '".' . PHP_EOL);
            exit(1);
        }
        
        $process = new Process('tail -f ' . $logFile);
        $process->setTimeout(0);
        $process->setIdleTimeout(0);
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                // TODO
            } else {
                $lines = explode("\n", $buffer);
                foreach ($lines as $line) {
                    $matches = array();
                    if (1 === preg_match('/^[\\s\\d:]+Query(.*)?$/', $line, $matches)) {
                        $query = $matches[1];
                        echo SqlFormatter::format($query) . PHP_EOL;
                        echo '--------------------------------------' . PHP_EOL;
                    }
                }
            }
        });
    } // function run
    
    /**
     * Sets up the banner.
     */
    protected function setUpBanner()
    {
        $this->banner = "mysql-query-monitor " . $this->version . "
Copyright (C) 2015 Sam Artuso <sam@highoctanedev.co.uk>
License GPLv3+: GNU GPL version 3 or later <http://gnu.org/licenses/gpl.html>.
This is free software: you are free to change and redistribute it.
There is NO WARRANTY, to the extent permitted by law.

Written by Sam Artuso.
";
        $this->getopt->setBanner($this->banner . PHP_EOL);
    } // function setBanner
    
    /**
     * Fetches the version number from the `composer.json` file.
     */
    protected function fetchVersion()
    {
        $composerJsonFilePath = __DIR__ . '/../../../composer.json';
        $json = file_get_contents($composerJsonFilePath);
        $data = json_decode($json, true);
        $this->version = $data['version'];
    } // function fetchVersion
    
    /**
     * Prints the usage and exits.
     *
     * @param boolean $banner Whether to print the banner.
     */
    protected function printUsage($banner = true)
    {
        if (!$banner) {
            $this->getopt->setBanner('');
        }
        echo $this->getopt->getHelpText();
    } // function printUsage
    
    /**
     * Prints the version and some other metainformation about the program,
     * and exits.
     */
    protected function printVersion()
    {
        echo $this->banner;
        exit;
    } // function printVersion
    
    /**
     * Outputs a message to stderr.
     *
     * @param string $msg The message to output.
     */
    protected function outputToStderr($msg)
    {
        $stderr = fopen('php://stderr', 'w+');
        fwrite($stderr, $msg . PHP_EOL);
    } // function outputToStderr
}
