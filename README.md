# mysql-converter
A quick-and-dirty tool for converting raw MySQL query output to CSV.

## What it's for
This tool is intended as a quick way to convert raw MySQL query output to CSV without requiring a lot of manual manipulation of the data in a text editor. It does a reasonable job of handling cases where the data contains line breaks, commas, copies of the field delimiter character, different line ending character(s) than the file as a whole, etc. In general, if your data is more-or-less clean, the tool will get the job done.

That said, the tool only goes so far. Particularly if the data in your query result is user-supplied, there are no guarantees in life. The tool will do its best to sort things out, but there will be some cases where it simply isn't able to sort out where one field ends and the next begins, and in those cases it will throw an exception and you'll need to go clean up your data before you can continue.

## What this is NOT
This tool is *not* the best choice if you have filesystem access to your MySQL server. In that case, MySQL already provides tools to output CSV, and will do a much better job of dealing with particularly gnarly data than this tool. If you have access to the filesystem on your MySQL server, check the `SELECT ... INTO OUTFILE` syntax in the [MySQL documentation](https://dev.mysql.com/doc/refman/8.0/en/ "MySQL documentation").

## A word of caution
In most cases, you will be converting data to CSV so that you can import it into a spreadsheet somewhere. There are some values that a malicious user might have placed into your database which while both MySQL and this tool will handle them without incident, might cause Excel to execute possibly dangerous and arbitrary code if the data is imported with macros and VB scripting turned on.

*You should never import user data into Excel with these features enabled*. This tool makes no attempt to detect or remove such content. ~~Your users are almost certainly terrible people and shouldn't be trusted.~~ **_Proceed at your own risk_**.

## Quickstart
```
curl -s http://getcomposer.org/installer | php

echo '{
	"require": {
		"mrmarkfrench/mysql-converter": "*"
	}
}' > composer.json

php composer.phar install

./example example.txt example.csv
```

## Contributions

Please feel free to fork this repository and add new features as necessary. The existing functions should give you a solid framework to build on, and your pull requests are welcome.

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create new Pull Request
