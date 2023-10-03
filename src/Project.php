<?php

namespace Sprintive;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

/**
 *
 */
class Project {

  protected InputInterface $input;

  protected OutputInterface $output;

  protected string $github_repo = "git@github.com:{org}/{project_name}.git";

  protected array $projectInfo = [];

  protected string $org = "";

  /**
   *
   */
  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
    $this->org = $this->input->getOption('org');
    $this->projectInfo = [
      'project_name' => $this->input->getOption('project_name'),
      'db_name' => str_replace("-", "_", $this->input->getOption('project_name')),
      'project_path' => "/var/www/html/" . $this->input->getOption('project_name'),
    ];
    $this->prepareFolder();
    $this->cloneProject();
    $this->prepareVirtualHost();
    $this->restartApache();
    $this->createDatabase();
    $this->createLocalSettingsFile();
  }

  /**
   * Prepare projectFolder.
   */
  public function prepareFolder() {
    try {
      $this->output->writeln('Prepare Folder!');

      // Create the project folder.
      if (!is_dir($this->projectInfo['project_path'])) {
        mkdir($this->projectInfo['project_path'], 0777, TRUE);
        $this->output->writeln('Project folder created');
      }
      else {
        $this->output->writeln('Project already exists!');
      }
      // Change the directory.
      chdir($this->projectInfo['project_path']);
      $this->output->writeln("Change directory to {$this->projectInfo['project_path']}");
      return TRUE;
    }
    catch (\Throwable $th) {
      $this->output->writeln($th->getMessage());
      return FALSE;
    }
  }

  /**
   *
   */
  public function cloneProject() {
    try {
      $this->output->writeln('Starting clone!');
      // Clone the project.
      $github_repo = str_replace("{project_name}", $this->projectInfo['project_name'], $this->github_repo);
      $github_repo = str_replace("{org}", $this->org, $github_repo);

      $this->output->writeln($github_repo);
      exec("git clone $github_repo .");
      return TRUE;
    }
    catch (\Throwable $th) {
      $this->output->writeln($th->getMessage());
      return FALSE;
    }
  }

  /**
   *
   */
  public function prepareVirtualHost() {
    try {
      $this->output->writeln('Prepare Virtual Host!');
      $project = '
                <VirtualHost *:80>
                    ServerName ' . $this->projectInfo['project_name'] . '.local
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
            127.0.0.1 ' . $this->projectInfo['project_name'] . '.local
        ';
      // Create the virtual host
      // check if there is configuration for project inside virtual host file.
      exec("sudo chmod -R 777 /etc/apache2/sites-available/000-default.conf");
      $virtual_host = file_get_contents("/etc/apache2/sites-available/000-default.conf");
      if (strpos($virtual_host, $this->projectInfo['project_name']) !== FALSE) {
        $this->output->writeln('Project already exists!');
      }
      else {
        $virtualHostFile = fopen("/etc/apache2/sites-available/000-default.conf", 'a');
        if (!$virtualHostFile) {
          return COMMAND::FAILURE;
        }
        fwrite($virtualHostFile, $project);
        fclose($virtualHostFile);
        exec("sudo chmod -R 755 /etc/apache2/sites-available/000-default.conf");
      }
      $this->output->writeln('Project Virtual Host Created Successfully!');

      $this->output->writeln("Add virtual host to hosts");
      exec("sudo chmod -R 777 /etc/hosts");
      $hosts = file_get_contents("/etc/hosts");
      if (strpos($virtual_host, $this->projectInfo['project_name']) !== FALSE) {
        $this->output->writeln('Project already exists!');
      }
      else {
        $hostsFile = fopen("/etc/hosts", 'a');
        if (!$virtualHostFile) {
          return COMMAND::FAILURE;
        }
        fwrite($hostsFile, $host);
        fclose($hostsFile);
        exec("sudo chmod -R 755 /etc/apache2/sites-available/000-default.conf");
        $this->output->writeln('Project host name Created Successfully!');
      }
      return TRUE;
    }
    catch (\Throwable $th) {
      $this->output->writeln($th->getMessage());
      return FALSE;
    }
  }

  /**
   *
   */
  public function restartApache() {
    try {
      $this->output->writeln('Restart Apache!');
      exec("service apache2 restart");
      return TRUE;
    }
    catch (\Throwable $th) {
      $this->output->writeln($th->getMessage());
      return TRUE;
    }
  }

  /**
   *
   */
  public function createDatabase() {
    try {
      $this->output->writeln('Create Database!');
      // Create the database
      // check if there is database with the same name.
      $result = "";
      $output = "";
      $test = exec("mysql -u root -proot -e 'CREATE DATABASE {$this->projectInfo['db_name']};'", $output, $result);
      if ($result == 0) {
        $this->output->writeln('Database Created Successfully!');
      }
      else {
        $this->output->writeln('Database Already Exists!');
      }
      return TRUE;
    }
    catch (\Throwable $th) {
      $this->output->writeln($th->getMessage());
      return FALSE;
    }
  }

  /**
   *
   */
  public function createLocalSettingsFile() {
    try {
      $projectPath = $this->projectInfo['project_path'];
      $this->output->writeln('Create Local Settings File!');
      // Create the local settings file.
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
        ";
      // Create file inside default project folder.
      $local_settings_file = fopen("$projectPath/public_html/sites/default/local.settings.inc", "w");
      fwrite($local_settings_file, $local_settings);
      fclose($local_settings_file);
      $this->output->writeln('Local Settings File Created Successfully!');
      return TRUE;
    }
    catch (\Throwable $th) {
      $this->output->writeln($th->getMessage());
      return FALSE;
    }
  }

}
