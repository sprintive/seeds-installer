<?php

namespace Sprintive;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;

/**
 *
 */
class Project extends Command
{
    /**
     * Command Output
     *
     * @var OutputInterface $output;
     */
    private OutputInterface $output;
    /**
     * Project info
     *
     * @var Array<string, string> $projectInfo;
     */
    private array $projectInfo = [];

    /**
     * Github repo path
     *
     * @var string $githubRepo
     */
    private string $githubRepo = "git@github.com:sprintive/{project_name}.git";


    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Clone project from sprintive github')
            ->addArgument('name', InputArgument::REQUIRED);
    }
    /**
     * Interact with the user before validating the input.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface   $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);

        $this->output = $output;
        $output->write(
            PHP_EOL.'<fg=red> Sprintive project installer</>'.PHP_EOL.PHP_EOL
        );

        if (! $input->getArgument('name')) {
            $input->setArgument('name', InputArgument::REQUIRED, 'Provide a name for the project');
        }
        $output->write(PHP_EOL. '<fg=red>Start initialize project : ' . $input->getArgument('name') . '</>'.PHP_EOL.PHP_EOL);


    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface   $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->projectInfo = [
        'name' => $input->getArgument('name'),
        'db_name' => str_replace("-", "_", $input->getArgument('name')),
        'project_path' => "/var/www/html/" . $input->getArgument('name'),
        ];
        $this->prepareFolder();
        $this->cloneProject();
        $this->prepareVirtualHost();
        $this->restartApache();
        $this->createDatabase();
        $this->createLocalSettingsFile();

        return Command::SUCCESS;

    }


    /**
     * Prepare projectFolder.
     */
    private function prepareFolder()
    {
        if (!is_dir($this->projectInfo['project_path'])) {
            if (mkdir($this->projectInfo['project_path'], 0777, true)) {
                $this->output->writeln('<info>Project folder created</info>');
            } else {
                $this->output->writeln('<fg=red>Failed to create folder</>');
            }
        } else {
            $this->output->writeln('<comment>Project already exists!</comment>');
        }

        if (chdir($this->projectInfo['project_path'])) {
            $this->output->writeln("<info>Change directory to {$this->projectInfo['project_path']}</info>");
        } else {
            $this->output->writeln("<fg=red>Failed to change directory</>");
        }
    }

    /**
     * Clone project
     */
    private function cloneProject()
    {
        $this->output->writeln('<info>Starting clone!</info>');
        $githubRepo = str_replace("{project_name}", $this->projectInfo['name'], $this->githubRepo);

        $progressIndicator = new ProgressIndicator($this->output, 'verbose', 100, ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']);
        $progressIndicator->start('Processing...');

        $i = 0;
        while ($i++ < 100) {
            $cloneProject = exec("git clone $githubRepo .");
            if ($cloneProject) {
                $progressIndicator->advance();
                $this->output->writeln('<fg=red>Failed to clone project</>');
            } else {
                break;
            }
        }

        $progressIndicator->finish('Finished');
    }

    /**
     * Prepare virtual host
     */
    private function prepareVirtualHost()
    {
            $project = '
                <VirtualHost *:80>
                    ServerName ' . $this->projectInfo['name'] . '.local
                    DocumentRoot ' . $this->projectInfo['project_path'] . '/public_html/
                    <Directory ' . $this->projectInfo['project_path'] . '/public_html/>
                        AllowOverride All
                        Order Allow,Deny
                        Allow from All
                    </Directory>
                    ErrorLog /var/log/apache2/error.log
                    LogLevel error
                    CustomLog /var/log/apache2/access.log combined
                </VirtualHost>
            ';
            $host = '
            127.0.0.1 ' . $this->projectInfo['name'] . '.local
        ';
        $this->output->writeln('Prepare Virtual Host!');

        exec("sudo chmod -R 777 /etc/apache2/sites-available/000-default.conf");
        $virtualHost = file_get_contents("/etc/apache2/sites-available/000-default.conf");

        if (strpos($virtualHost, $this->projectInfo['name']) === false) {
            $virtualHostFile = fopen("/etc/apache2/sites-available/000-default.conf", 'a');
            if ($virtualHostFile) {
                fwrite($virtualHostFile, $project);
                fclose($virtualHostFile);
                exec("sudo chmod -R 755 /etc/apache2/sites-available/000-default.conf");
            }
        }

        // Add virtual host to hosts file
        exec("sudo chmod -R 777 /etc/hosts");
        $hosts = file_get_contents("/etc/hosts");

        if (strpos($hosts, $this->projectInfo['name']) === false) {
            $hostsFile = fopen("/etc/hosts", 'a');
            if ($hostsFile) {
                fwrite($hostsFile, $host);
                fclose($hostsFile);
                exec("sudo chmod -R 755 /etc/apache2/sites-available/000-default.conf");
            }
        }

        $this->output->writeln('Project Virtual Host and host name created successfully!');
    }

    /**
     * Restart apache
     */
    private function restartApache()
    {
        $this->output->writeln('Restart Apache!');
        exec("service apache2 restart");
    }

    /**
     * Create database
     */
    private function createDatabase()
    {
        $this->output->writeln('Create Database!');
        $result = "";
        $output = "";
        $test = exec("mysql -u root -proot -e 'CREATE DATABASE {$this->projectInfo['db_name']};'", $output, $result);

        if ($result == 0) {
            $this->output->writeln('Database Created Successfully!');
        } else {
            $this->output->writeln('Database Already Exists!');
        }
    }

    /**
     * Create local settings file
     */
    private function createLocalSettingsFile()
    {
        $projectPath = $this->projectInfo['project_path'];
        $this->output->writeln('Create Local Settings File!');
        $local_settings = "
        <?php
            \$databases['default']['default'] = [
                'database' => '{$this->projectInfo['project_name']}',
                'username' => 'root',
                'password' => 'root',
                'prefix' => '',
                'host' => 'localhost',
                'port' => '3306',
                'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
                'driver' => 'mysql',
            ];


            \$config['system.performance']['css']['preprocess'] = false;
            \$config['system.performance']['js']['preprocess'] = false;
            \$settings['config_sync_directory'] = 'sites/default/config';

            // Temp file path.
            \$settings['file_temp_path'] = 'sites/default/files/tmp';

            // Private files path.
            \$settings['file_private_path'] = 'sites/default/files/private';
            ";
        $localSettingsFile = fopen("$projectPath/public_html/sites/default/local.settings.inc", "w");
        if ($localSettingsFile) {
            fwrite($localSettingsFile, $local_settings);
            fclose($localSettingsFile);
            $this->output->writeln('Local Settings File Created Successfully!');
        } else {
            $this->output->writeln('<fg=red>Failed to create local settings file</>');
        }
    }

}
