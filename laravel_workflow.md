
article

## CICD with GitHub Actions, Laravel 7, Service contanier and MySQL

GitHub Actions ([https://github.com/features/actions](https://github.com/features/actions)) is a powerful service provided by [GitHub](https://github.com) for continuous integration and continuous delivery.

GitHub Actions allows you to execute some commands when a GitHub event is triggered. For example you can automate the execution of unit tests on your code base when you push your code in the repository.

With GitHub Actions you can:
- setup you **runner** or container (with your stack: database, compilers, executables, web server...);
- **fetch your code** from the repository and store in you running container;
- setup your **additional services** like MySQL database;
- **configure** your application (configure the database connection, generate all needed keys, warmup the cache, fix permissions);
- **execute** some command (for example the test suite via phpunit).

The scenario that I would like to explain you is:
>  I have a Web App based on Laravel 7, every time I push some new code on develop branch on the GitHub repository, I would like to execute automatically Unit tests and Feature tests using MySQL services.

## Setup your first workflow with GitHub Action

Let's start to build your workflow file from scratch.

In your project directory, you need to create a new file in .github/workflows directory.
```sh
touch .github/workflows/laravel_phpunit.yml
```
The _yml_ file will contain 3 main sections: **name**, **on** and **jobs**.

* *name*: the name of the workflow;
* *on*: the event that trigger the execution of the workflow. It could be also an array (so the action could be triggered by multiple events);
* *jobs*: the list of jobs to be executed. Jobs by default are executed in parallel. You can define a kind of dependency trees with the needs directive if you want to have some execution sequence instead of parallel execution. In this case we will use just one job. So, no dependency issue for us;


### Name: define name
You can define the name of your workflow.
This name, is used in Github Actions user interface, for grouping reports and for managing workflows.

### On: define events

You can define **when** launch the workflow.
For example you can define , when you push your code on _master_ and _develop_ branch:
```yaml
on:
  push:
    branches: [ master, develop ]
```

You could define branches _master_, _develop_ and all _feature_ branches:
```yaml
on:
  push:
    branches: [ master, develop. feature/** ]
```

Or you could define also the pull request on master branch:
```yaml
on:
  push:
    branches: [ master, develop, feature/** ]
  pull_request:
    branches: [ master ]
```


### Jobs: define Jobs

With a workflow you can define one or more jobs. A job is a specific task that needs to be executed in the workflow. You can configure to run multiple jobs in parallel or with some dependencies (for example: run the job “Test” only when the job “build assets” is completed)

#### Jobs: runs on
A job can be executed on a specific container that runs a operating system. For Laravel usually I use the latest version of Ubuntu.
```yaml
jobs:
  laravel-test-withdb:
    runs-on: ubuntu-latest
```

#### Jobs: mysql database
In the Job (__jobs__) section you can add also some service containers. For example if you are creating a job that needs MySql service you could add __service__ sub section.
This service could be used by your scripts and apps that runs in the current job.
For example in my case, I'm creating a job for running tests on my Laravel application. To do that, I need also a database. Sometimes I could add sqlite database, it is easier to configure, but probably if you want to run test with the same database that you have on production, probably you could prefer to have a MySql instance.


```yaml
    services:
      # mysql-service Label used to access the service container
      mysql-service:
        # Docker Hub image (also with version)
        image: mysql:5.7
        env:
          ## Accessing to Github secrets, where you can store your configuration
          MYSQL_ROOT_PASSWORD: ${{ secrets.DB_PASSWORD }}
          MYSQL_DATABASE: db_test
        ## map the "external" 33306 port with the "internal" 3306
        ports:
          - 33306:3306
        # Set health checks to wait until mysql database has started (it takes some seconds to start)
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
```

The most important things to highlight are:

* _mysql-service_ is the label used to access the service container
* _mysql:5.7_ we are using mysql image (version 5.7)
* *secrets.DB_PASSWORD* accessing to Github secrets, where you can store your configuration
* _33306:3306_ we are mapping the "external" 33306 port with the "internal" one 3306. In the next steps we will need to use 33306 to access to the service, because the service container exposes the 33306.
* _mysqladmin ping_ : creating service container could take a while. So you need to be sure to wait until the mysql service is started and it is ready to accept connections. In this case we will retry for 3 times every 10 seconds and with a timeout of 5 seconds.





### Steps: define the steps

Each jobs has multiple steps. In each step you can “run” your commands or you can “use” some standard actions.

Following the _yaml_ syntax, each step starts with “minus sign”. Step that uses standard action is identified with “_uses_” directive, steps that use custom commands are identified by “_name_” and “_run_” directive. “_name_” is used in the execution log as a label in the GitHub Actions UI, “_run_” is used for launch the command. With “_run_” directive you could define command with arguments and parameters. With “_run_” you can also list a set of commands.

```yaml
    steps:
    - uses: actions/checkout@v2
    - name: Laravel Setup
      run: |
        cp .env.ci .env
        composer install -q --no-ansi --no-interaction --no-scripts --no-suggest --no-progress --prefer-dist
        php artisan key:generate
        chmod -R 777 storage bootstrap/cache
    - name: Execute tests (Unit and Feature tests) via PHPUnit
      env:
        DB_CONNECTION: mysql
        DB_DATABASE: db_test
        DB_PORT: 33306
        DB_USER: root
        DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
      run: |
        php artisan migrate
        vendor/phpunit/phpunit/phpunit
```

Let me walk-through all steps in the steps section.

### First step: git checkout

The first step is retrieve the sources. To do that, GitHub Actions has a standard action identified with “actions/checkout@v2”. To perform the action you need to set a step:

```yaml
    - uses: actions/checkout@v2
```
You can use also actions/checkout@master if you like to live on the edge.


### Second Step: Laravel Setup

In Github workflow you need to think about a new fresh install of your web application everytime you execute the workflow.
It means that you need to execute all things needed by a fresh installation like:

* creating _.env_ file;
* install _PHP_ packages;
* generate the key (_key:generate_);
* permissions fine tuning.


In Laravel you can use *.env* to store your keys and parameters for the environment configuration. For example: database connection parameters, cache connection parameters, SMTP configuration etc.
You can prepare your _.env.ci_ with specific configuration for executing workflows and commit it on the repository.
Anyway we will override later some parameter like the connection with the database (some secret paramters like password or access tokens that you don't want to store in _.env.ci_).

For installing package I suggest you to use these options:

* _-q_: avoid to output messages;
* _--no-ansi_: disable ANSI ouput;
* _--no-interaction_: avoid interactrions, useful for CI building;
* _--no-scripts_: avoid execution of scripts defined in _composer.json_;
* _--no-suggest_: don't show package suggestion;
* _--no-progress_: don't show the progress display that can mess with some terminals or scripts which don't handle backspace characters;
* _--prefer-dist_: prefer to download dist and try to skip git history.




### Third step: execute migration and tests

Now you have your Laravel application installed in the runner and ready to use the MySQL service.
What we are going to do is:

* running migration (create tables on the database);
* execute tests usig phpunit.

Both commands, probably, they will need to access to database.
To do that we need to be sure that all parameters are correctly configured.
In the last step you can add _env_ sub section where you can list all your parameters. These parameters will override the parameters that you defdined in your _.env_file. And probably you want to avoid to hardcode some parameters in your workflow _yaml_ file. For that you could use the _Settings_ -> _Secrets_ functionality in your GitHub repository
In _Settings_/_Secrets_ section you can define and list all your "secrets" and you can use those secrets you your _yaml_ file with ${{ secrets.DB_PASSWORD }}. Please note that your parameter is included in the ${{ }} markers and you need to use the _secrets._  prefix to access to secrets variables. DB_PASSWORD is the name of your secret.

```yaml
    - name: Execute tests (Unit and Feature tests) via PHPUnit
      env:
        DB_CONNECTION: mysql
        DB_DATABASE: db_test
        DB_PORT: 33306
        DB_USER: root
        DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
      run: |
        php artisan migrate
        vendor/phpunit/phpunit/phpunit
```

In this case:

* *DB_CONNECTION*: *mysql* because you wnat to access to mysql database;
* *DB_DATABASE*: *db_test* the name of the database, it needs to be the same defined in *MYSQL_DATABASE* in MySQL service section;
* *DB_PORT*: 33306 the external port of the MySQL service. Do you remember ? In MySQL service section we defined 33306:3306 as "_ports_".
* *DB_USER*: root is the main user configured in MySQL service;
* *DB_PASSWORD*: ${{ secrets.DB_PASSWORD }} we are accessing to the secret named *DB_PASSWORD*.

With this configuration you can execute the migration and the phpunit. As you can see I'm using phpunit in the vendor directory.





Please write me your feedback or suggestion in the comment in order to improve this article.

Let’s automate everything!
