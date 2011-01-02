Social Feeds Thingy
===================
By [Daniel15](http://dan.cx/)

This is a social feed aggregator. It aggregates feeds from different sites (Twitter, Foursquare, Last.fm, YouTube, etc.) into a single feed. I'm using it on [my personal website](http://dan.cx/). There's not much documentation, so I wouldn't suggest using this unless you're a developer. It was only designed for personal use; the code is here for educational purposes (and hopefully can help you if you want to make something similar!)

Configuration
-------------
 * Import the database schema in schema.sql
 * Edit the database config in includes/database.php (TODO: Move this to config.php)
 * Add your feed sources to the <em>sources</em> table. The "type" column should correspond to a file in includes/feedsource. Some sources require more data than just a username. The extra data goes into a serialized array in the <em>extra_data</em> column. Refer to the code for more details.
 * Last.fm and Foursquare require API keys. If you want to use either of these sources, sign up for an API key on the corresponding website (refer to their developer documentation for details) and then edit includes/config.php.
 * Schedule update.php to run as a cron job. Alternately, hit it via your web browser to test it.
 * On your site, do an AJAX request to loadjson.php. Alternately, include the loadhtml.php file.