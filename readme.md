# Oxygen CMS Starter Application

This repository contains a simple starter application for Oxygen. It includes all Oxygen-made extensions and provides a great starting point for using Oxygen as a Content Management System.

## Installation

Firstly, Oxygen CMS has a few system requirements. Most of these are inherited from the [Laravel Framework](http://laravel.com/docs/5.1/installation).

- PHP >= 5.5.9
- OpenSSL PHP Extension
- PDO PHP Extension
- Mbstring PHP Extension
- Tokenizer PHP Extension

Oxygen utilizes [Composer](http://getcomposer.org/) to manage its dependencies. So, before using Oxygen, make sure you have Composer installed on your machine.

To download the starter application and set up dependencies, run the following command:

    composer create-project oxygen/application --prefer-dist

After installing dependencies, you then need to set some config options. You can use [the Laravel setup guide] as a full reference,
but the only required configuration item for basic use is the database connection.

To change the database connection edit the `.env` file in the root of your installation:

    DB_HOST=localhost
    DB_DATABASE=homestead
    DB_USERNAME=homestead
    DB_PASSWORD=secret

Then you need to update the database schema.

    php artisan doctrine:schema:update

For Oxygen to work it needs some core content already in the database (mainly preferences). To seed the database and to initialize any custom Oxygen extensions you may have installed, run the following:

    php artisan migrate:packages

Now you should be able to access the login page over at `http://yourwebsite.here/oxygen`.

Finally, we need to create our first user account so we can login and start creating content. All users must be tied to a 'group' such as *Administrator* or *Editor*, so we must first create one.

    php artisan make:group Administrator

Then finally we can create the user. Follow the on-screen instructions and enter the require information.

    php artisan make:user

Congratulations, you can now ready to use Oxygen CMS!

## Official Documentation

Proper documentation (and unit tests) are coming soon!

## Contributing To Oxygen

Please feel free to use GitHub to submit issues/pull requests. Your help and feedback is extremely valuable as Oxygen is a young and agile project which is constantly seeking new ideas and suggestions. :)

## License

Oxygen is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
