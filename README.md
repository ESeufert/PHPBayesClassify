<h2>PHPBayesClassify</h2>

Version 0.01. See software licensing details below.

PHPBayesClassify is a simple text classifier built in PHP.

This is provided AS IS and may not work as intended. No responsibility over accuracy of output is assumed by the author.

<h3>SETUP</h3>

Upload PHPBayesClassify.php to your web server. Edit the class variables at the top of the document (the first two sections pertain to database information). Brief overview of variables that require editing:
<ul>
<li>$dbName - the name of your database</li>
<li>$dbHost - host location</li>
<li>$dbUser - login username</li>
<li>$dbPass - login password</li>

<li>$wordMappingTableName - this is the name of the table that will store word frequency counts. If this table doesn't exist, it will be created. No need to do anything other than name this.</li>
<li>$objectTableName -- this is the table that stores the "objects" (things that are being classified). This should already exist, and the class does not modify it in any way other than querying from it. This might be a table containing blog posts or stored tweets. The class assumes this table has at least three columns: a date column that stores the date on which each object was created, a title column that stores the string that will be classified (eg. the title of a blog post or the content of a tweet), and a class column that stores a classification / category in numeric format.</li>
<li>$daysBack = the number of days of data (measured with the date column) over which the classifier should operate (eg. if this is set to 7, objects will be fetched that were created in the last 7 days, and the training routine would train the classifier on that dataset)</li>
<li>$objectDateColumnName - the name of the date column in the table</li>
<li>$objectTitleColumnName - the name of the title column in the table</li>
<li>$objectClassColumnName - the name of the category column in the table (NB! this should be numeric!)</li>
</ul>

The stopwords array can also be edited. Stopwords are words that are stripped from all titles.

<h3>HOW TO USE</h3>

<ol>
<li>Include the class in your file<br/>

(eg. require_once( '/home/public_html/PHPBayesClassify.class.php' );</li>

<li>Initialize class

eg. $bc = new bayesClassify();</li>

<li>Train against back data

eg. $bc->train( );</li>

<li>Classify a string<br/>

eg. $class = $bc->classify( 'This is a string I want to classify' );<br/>

The classify function returns an array of classifications, each with a 'yes' / 'no' probability indicator.</li>
</ol>

<h3>LICENSE</h3>

The MIT License (MIT)

Copyright (c) 2013 Eric Benjamin Seufert (eric@ufert.se)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
