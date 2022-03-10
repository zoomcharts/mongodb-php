# Prerequisites:

1. MongoDB 3.0 or newer
2. PHP 5.6
3. PHP mongodb extension 1.1.0 or newer (http://php.net/manual/en/mongodb.installation.php.php)

Note that this repository already includes the `MongoDB PHP Library`
that was installed using `composer`. See <http://mongodb.github.io/mongo-php-library/>
on how to install that into your own project.

# Chart types:

1. TimeChart
2. PieChart
3. NetChart
4. FacetChart
5. GeoChart

# Sample data

To run all examples, you must install the sample data provided by running:

**For TimeChart:**

    mongoimport --db mongo-example --collection eur_usd --drop --file data/eur-usd.json

**For PieChart and FacetChart:**

    mongoimport --db mongo-example --collection continental --drop --file data/continental.json

**For NetChart:**

    mongoimport --db mongo-example --collection firends_nodes --drop --file data/friends-nodes.json
    mongoimport --db mongo-example --collection friends_links --drop --file data/friends-links.json

**For GeoChart:**

    mongoimport --db mongo-example --collection cities --drop --file data/cities.json

# LIVE DEMO

To view a live demo, see <https://mongodb-php.zoomcharts.com/>

For more information visit <https://zoomcharts.com/>

# License

*Note: this license applies only to the sample code in this repository.*

**The MIT License (MIT)**

Copyright (c) 2015-2016 SIA Data Visualization Software Lab

Permission is hereby granted, free of charge, to any person obtaining a copy of this software 
and associated documentation files (the "Software"), to deal in the Software without restriction,
including without limitation the rights to use, copy, modify, merge, publish, distribute,
sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or 
substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING 
BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND 
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, 
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, 
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
