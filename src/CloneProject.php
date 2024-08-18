<?php

namespace Sprintive;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ProcessUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 *
 */
class CloneProject extends Command {
  use Concerns\ConfiguresPrompts;
  /**
   * The Composer instance.
   *
   * @var \Illuminate\Support\Composer
   */
  protected $composer;


  /**
   * Project info.
   *
   * @var array<string, string> projectInfo
   */
  private array $projectInfo = [];

  /**
   * Github repo path.
   *
   * @var string
   */
  private string $githubRepo = "git@github.com:sprintive/{project_name}.git";


  protected OutputInterface $output;

  protected InputInterface $input;

  /**
   * Configure the command options.
   *
   * @return void
   */
  protected function configure() {
    $this
      ->setName('clone:project')
      ->setDescription('Clone Project')
      ->addArgument('repo-name', InputArgument::REQUIRED)
      ->addArgument('mysql_user', InputArgument::REQUIRED)->addArgument('mysql_password', InputArgument::OPTIONAL)
      ->addArgument('name', InputArgument::OPTIONAL)
      ->addArgument("db-name", InputArgument::OPTIONAL)
      ->addOption("force", InputArgument::OPTIONAL)
      ->addArgument("host", InputArgument::OPTIONAL);
  }

  /**
   * Interact with the user before validating the input.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return void
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    parent::interact($input, $output);
    $this->input = $input;
    $this->output = $output;
    $this->configurePrompts($this->input, $this->output);
    passthru('sudo -v');
    $this->output->write(PHP_EOL . '  <fg=red>Sprintive Projects Installer</>' . PHP_EOL . PHP_EOL);

    if (!$this->input->getArgument('repo-name')) {
      $this->input->setArgument('repo-name', text(
            label: 'What is the name of your project?',
            placeholder: 'E.g. example-app',
            required: 'The project name is required.',
            validate: function ($value) {
              if (preg_match('/[^\pL\pN\-_.]/', $value) !== 0) {
                    return 'The name may only contain letters, numbers, dashes, underscores, and periods.';
              }
            },
        ));
    }
    if (!$this->input->getArgument('name')) {
      $this->input->setArgument('name', text(
            label: 'What is the name of your folder?',
            placeholder: 'E.g. example-app',
            validate: function ($value) {
              if (preg_match('/[^\pL\pN\-]/', $value) !== 0) {
                    return 'The name may only contain letters, numbers, and dashes.';
              }
            },
        ));
    }
    if (!$this->input->getArgument('db-name')) {
      $this->input->setArgument('db-name', text(
            label: 'What is the name of your database?',
            placeholder: 'E.g. example_app_db',
            validate: function ($value) {
              if (preg_match('/[^\pL\_]/', $value) !== 0) {
                    return 'The name may only contain letters, and underscores.';
              }
            },
        ));
    }
    if (!$this->input->getArgument('host')) {
      $this->input->setArgument('host', text(
            label: 'What is the name of your host?',
            placeholder: 'E.g. example-app',
            validate: function ($value) {
              if (preg_match('/[^\pL\-]/', $value) !== 0) {
                    return 'The name may only contain letters, and dashes.';
              }
            },
        ));
    }

    if (!$this->input->getArgument('mysql_user')) {
      $this->input->setArgument('mysql_user', text(
        label: 'What is the username for mysql?',
        placeholder: 'E.g. root',
      ));
    }
    if (!$this->input->getArgument('mysql_password')) {
      $this->input->setArgument('mysql_password', text(
        label: 'What is the password for mysql?',
        placeholder: 'E.g. root',

      ));
    }

  }

  /**
   * Get the installation directory.
   *
   * @param string $name
   *
   * @return string
   */
  protected function getInstallationDirectory(string $name) {
    return $name !== '.' ? getcwd() . '/' . $name : '.';
  }

  /**
   * Verify that the application does not already exist.
   *
   * @param string $directory
   *
   * @return void
   */
  protected function verifyApplicationDoesntExist($directory) {
    if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
      throw new \RuntimeException('Application already exists!');
    }
  }

  /**
   * Get the composer command for the environment.
   *
   * @return string
   */
  protected function findComposer() {
    return implode(' ', $this->composer->findComposer());
  }

  /**
   * Get the path to the appropriate PHP binary.
   *
   * @return string
   */
  protected function phpBinary() {
    $phpBinary = (new PhpExecutableFinder)->find(FALSE);

    return $phpBinary !== FALSE
            ? ProcessUtils::escapeArgument($phpBinary)
            : 'php';
  }

  /**
   *
   */
  public function setupProjectInfo() {
    $this->projectInfo = [
      'name' => $this->input->getArgument('name'),
      'db_name' => $this->input->getArgument('db-name'),
      'project_path' => "/var/www/html/" . $this->input->getArgument('name'),
      'host' => $this->input->getArgument('host'),
      'mysql_user' => $this->input->getArgument('mysql_user'),
      'mysql_password' => $this->input->getArgument('mysql_password'),
    ];
  }

  /**
   * Execute the command.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $nput
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->setupProjectInfo();
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
  private function prepareFolder() {
    if (!is_dir($this->projectInfo['project_path'])) {
      if (mkdir($this->projectInfo['project_path'], 0777, TRUE)) {
        $this->output->writeln('<info>Project folder created</info>');
      }
      else {
        $this->output->writeln('<fg=red>Failed to create folder</>');
      }
    }
    else {
      $this->output->writeln('<comment>Project already exists!</comment>');
    }
  }

  /**
   * Clone project.
   */
  private function cloneProject() {
    // $this->output->writeln('<info>Starting clone!</info>');
    $githubRepo = "";
    if (isset($this->projectInfo['repo-name'])) {
      $githubRepo = str_replace("{project_name}", $this->projectInfo['repo-name'], $this->githubRepo);
    }
    else {
      $githubRepo = str_replace("{project_name}", $this->projectInfo['name'], $this->githubRepo);
    }

    $progressIndicator = new ProgressIndicator($this->output, 'verbose', 100, ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']);
    $progressIndicator->start('Processing...' . PHP_EOL . PHP_EOL);
    $i = 0;
    if ((new Filesystem())->exists($this->projectInfo['project_path'])) {
      return;
    }
    $checkRepoExists = $this->runCommands(["git ls-remote $githubRepo"], '.');
    if ($checkRepoExists->getExitCode() !== 0) {
      $progressIndicator->finish('Finished');
      $this->output->writeln('<fg=red>Project does not exist</>');
      return;
    }
    else {
      $this->output->writeln("<info>Starting cloning project {$this->projectInfo['name']}</>");
      $progressIndicator->advance();
    }
    $commands = [
      "git clone $githubRepo {$this->projectInfo['project_path']}",
    ];
    while ($i++ < 100) {
      $cloneProject = $this->runCommands($commands, '.');
      if ($cloneProject->getExitCode() !== 0) {
        $progressIndicator->advance();
        $this->output->writeln('<fg=red>Failed to clone project</>');
      }
      else {
        break;
      }
    }
    $progressIndicator->finish('Finished');
  }

  /**
   * Run the given commands.
   *
   * @param array $commands
   * @param string|null $workingPath
   * @param array $env
   *
   * @return \Symfony\Component\Process\Process
   */
  protected function runCommands($commands, string $workingPath = NULL, ?array $env = []) {
    if (!$this->output->isDecorated()) {
      $commands = array_map(function ($value) {
        if (str_starts_with($value, 'chmod')) {
                return $value;
        }

        if (str_starts_with($value, 'git')) {
                  return $value;
        }

                    return $value . ' --no-ansi';
      }, $commands);
    }

    $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath, $env, NULL, NULL);

    if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
      try {
        $process->setTty(TRUE);
      }
      catch (\RuntimeException $e) {
        $this->output->writeln('  <bg=yellow;fg=black> WARN </> ' . $e->getMessage() . PHP_EOL);
        $process->setTty(FALSE);
      }
    }

    $process->run(function ($type, $line) {
        $this->output->write('    ' . $line);
    });

    return $process;
  }

  /**
   * Prepare virtual host.
   */
  private function prepareVirtualHost() {
    $project = '
    <VirtualHost *:80>
        ServerName ' . $this->projectInfo['host'] . '.local
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
    127.0.0.1 ' . $this->projectInfo['host'] . '.local
    ';
    $this->output->writeln('Prepare Virtual Host!');
    exec("sudo chmod -R 777 /etc/apache2/sites-available/000-default.conf");
    $virtualHost = file_get_contents("/etc/apache2/sites-available/000-default.conf");

    if (strpos($virtualHost, $this->projectInfo['name']) === FALSE) {
      $virtualHostFile = fopen("/etc/apache2/sites-available/000-default.conf", 'a');
      if ($virtualHostFile) {
        fwrite($virtualHostFile, $project);
        fclose($virtualHostFile);
        exec("sudo chmod -R 755 /etc/apache2/sites-available/000-default.conf");
      }
    }

    // Add virtual host to hosts file.
    exec("sudo chmod -R 777 /etc/hosts");
    $hosts = file_get_contents("/etc/hosts");

    if (strpos($hosts, $this->projectInfo['name']) === FALSE) {
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
   * Restart apache.
   */
  private function restartApache() {
    $this->output->writeln('Restart Apache!' . PHP_EOL . PHP_EOL);
    $commands = [
      "sudo service apache2 restart",
    ];
    $restartApache = $this->runCommands($commands);
    if ($restartApache->getExitCode() != 0) {
      $this->output->writeln("Failed: " . $restartApache->getErrorOutput() . PHP_EOL . PHP_EOL);
    }
    else {
      $this->output->writeln("Apache Restarted!" . PHP_EOL . PHP_EOL);
    }
  }

  /**
   * Create database.
   */
  private function createDatabase() {
    $this->output->writeln('Create Database!' . PHP_EOL . PHP_EOL);
    $checkIfDatabaseExists = $this->runCommands([
      "mysql -u {$this->input->getArgument('mysql_user')} -p{$this->input->getArgument('mysql_password')} -e 'use {$this->projectInfo["db_name"]}' 2>&1",
    ]);
    if ($checkIfDatabaseExists->getExitCode() == 0) {
      $this->output->writeln("<fg=red>Database is exists</>" . PHP_EOL . PHP_EOL);
      $this->input->setOption('force', confirm(
        label: 'Do you want to remove old database?',
        default: FALSE,
        yes: 'Yes',
        no: 'No',
        hint: 'Yes option will remove old database and created it again.'
      ));
    }

    if ($this->input->hasOption('force') && $this->input->getOption("force") == TRUE) {
      $removeOldDatabase = $this->runCommands([
        "mysql -u {$this->input->getArgument('mysql_user')} -p{$this->input->getArgument('mysql_password')} -e 'DROP DATABASE {$this->projectInfo["db_name"]}' 2>&1",
      ]);

      if ($removeOldDatabase->getExitCode() == 0) {
        $createDatabase = $this->runCommands([
          "mysql -u {$this->input->getArgument('mysql_user')} -p{$this->input->getArgument('mysql_password')} -e 'CREATE DATABASE {$this->projectInfo["db_name"]}' 2>&1",
        ]);
        if ($createDatabase->getExitCode() != 0) {
          $this->output->writeln("Failed: " . $createDatabase->getErrorOutput() . PHP_EOL . PHP_EOL);
        }
      }
      else {
        $this->output->writeln("Failed: " . $removeOldDatabase->getErrorOutput() . PHP_EOL . PHP_EOL);
      }
    }
    else {
      $createDatabase = $this->runCommands([
        "mysql -u {$this->input->getArgument('mysql_user')} -p{$this->input->getArgument('mysql_password')} -e 'CREATE DATABASE {$this->projectInfo["db_name"]}' 2>&1",
      ]);
      if ($createDatabase->getExitCode() != 0) {
        $this->output->writeln("Failed: " . $createDatabase->getErrorOutput() . PHP_EOL . PHP_EOL);
      }
    }
  }

  /**
   * Create local settings file.
   */
  private function createLocalSettingsFile() {
    $projectPath = $this->projectInfo['project_path'];
    $this->output->writeln('Create Local Settings File!');
    // get stubs from stubs folder
    // check if project has local.settings.inc
    if ((new Filesystem())->exists("$projectPath/public_html/sites/default/local.settings.inc")) {
      return;
    }


    $localSettingsFilePath = (new Filesystem)->dirname(__DIR__);
    $localSettingsStubs = file_get_contents($localSettingsFilePath . "/stubs/local.settings.inc.stub");
    $localSettingsStubs = str_replace("{db_name}", $this->projectInfo["db_name"], $localSettingsStubs);
    $localSettingsStubs = str_replace("{mysql_user}", $this->projectInfo["mysql_user"], $localSettingsStubs);
    $localSettingsStubs = str_replace("{mysql_password}", $this->projectInfo["mysql_password"], $localSettingsStubs);
    $localSettingsStubs = str_replace("{host}", $this->projectInfo["host"], $localSettingsStubs);

    $localSettingsFile = fopen("$projectPath/public_html/sites/default/local.settings.inc", "w");
    if ($localSettingsFile) {
      fwrite($localSettingsFile, $localSettingsStubs);
      fclose($localSettingsFile);
      $this->output->writeln('Local Settings File Created Successfully!');
    }
    else {
      $this->output->writeln('<fg=red>Failed to create local settings file</>');
    }
  }

}
