# Prerequisites:

1. MongoDB 3.0 or newer
2. PHP 5.6
3. PHP mongodb extension 1.1.0 or newer (http://php.net/manual/en/mongodb.installation.php.php)

Note that this repository already includes the `MongoDB PHP Library`
that was installed using `composer`. See <http://mongodb.github.io/mongo-php-library/>
on how to install that into your own project.

# Sample data

To run the example, you must install the sample data provided by running:

    mongoimport --db zoomcharts --collection eur-usd --drop --file eur-usd.json
