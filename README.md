### seeds-installer: Kickoff distribution for SMEs

[![Latest Stable Version](https://poser.pugx.org/sprintive/seeds-installer/v/stable)](https://packagist.org/packages/sprintive/seeds-installer) [![Total Downloads](https://poser.pugx.org/sprintive/seeds-installer/downloads)](https://packagist.org/packages/sprintive/seeds-installer) [![Latest Unstable Version](https://poser.pugx.org/sprintive/seeds-installer/v/unstable)](https://packagist.org/packages/sprintive/seeds-installer) [![License](https://poser.pugx.org/sprintive/seeds-installer/license)](https://packagist.org/packages/sprintive/seeds-installer) [![composer.lock](https://poser.pugx.org/sprintive/seeds-installer/composerlock)](https://packagist.org/packages/sprintive/seeds-installer)

[![Seeds](https://www.drupal.org/files/project-images/Seeds-Logo.png)](https://www.drupal.org/project/seeds)

Light distribution to kick off all projects regardless scale, you can use it to speed up your projects.

seeds-installer command is a Symfony console application that simplifies the process of initializing a project by performing tasks such as creating project folders, cloning a Git repository, configuring virtual hosts, and more.

## Installation

1. Install Seeds installer global in your device :

    ```bash
    composer global require sprintive/seeds-installer
    ```

2. If you have zsh on your device:
    In your terminal go write `vim ~/.zshrc`, then add in the last line this code :

    ```
     export COMPOSER_BIN="$HOME/.config/composer/bin"
    ```

    If you have `bashrc` on your device
    in your terminal go write `vim ~/.bashrc`, then add in the last line this code

    ```
    export COMPOSER_BIN="$HOME/.config/composer/bin"
    ```

## Usage

To clone a new project, use the following command:

```bash
seeds-installer new <name>
```

replace `<name>` with the desired name for your project.

The new command performs the following steps:

1. Prepares the project folder.
2. Clones a Git repository into the project folder.
3. Sets up a virtual host for the project.
4. Restarts the Apache web server.
5. Creates a database for the project.
6. Generates a local settings file.

Project will installed in this path : `/var/www/html/<name>`

You need to run `composer install` to install all packages for the project
#### Sponsored and developed by

[![Sprintive](https://www.drupal.org/files/styles/grid-3/public/drupal_4.png?itok=FXajfgGW)](http://sprintive.com)

Sprintive is a web solution provider which transform ideas into realities, where humans are the center of everything, and Drupal is the heart of our actions, it has built and delivered Drupal projects focusing on a deep understanding of business goals and objective to help companies innovate and grow.
