#!/usr/local/bin/php -q

<?php
/**
 * This script enables you to copy the structure and content of databases back and
 * forth between remote and local databases. Remote databases are accessed through 
 * SSH tunnels.
 * 
 * This script operates in two modes. The mode is passed in the first parameter
 * -- put: Copy a local database to a remote server
 * -- get: Copy a remote database to a local server
 * 
 * When using a config.ini file to specify your database credentials the format is:
 * 
 * Example usage: Replace your local database with a remote database
 * dbsync.php get
 * 
 * Example usage: Replace a remote database with your local database
 * dbsync.php put
 * 
 * Example usage using extrenal config file: Replace a remote database with your local database
 * dbsync.php put path/to/config.ini
 */
 
/**
 * If you have your .ssh/config file setup you can use this syntax
 *	$sshServer = 'server';
 * If you are not using your .ssh/config file you should use this syntax
 *	$sshServer = 'user@server.com
 */

/* Require search and replace db script, and supress html output in shell with output buffering */
//ob_start();
//require_once('searchreplacedb2.php');
//ob_end_clean();

/**
 * Filter and include the file name passed for a set of defines used to set up
 * WordPress db access.
 *
 * Filestream code by Joel Clermont https://github.com/joelclermont/Search-Replace-DB
 *
 * @param string $filename The file name we need to filter and include for the defines.
 *
 * @return array    List of db connection details.
 */

/**
 * Get parameters for database sync
 */

/* Set the mode to "pull" or "push" depending on whether we are pulling from or pushing to remote */
$mode = $argv[1];

/**
 * Call function to read settings from wordpress config files, based on which server is specified
 * Depends on having different wordpress database information in different *-config.php files
 * See: https://gist.github.com/ashfame/1923821
 *
 * Possible values are: local, dev, staging, production
 */

/**
 * scrape_wpconfig function.
 * Inputs a wordpress configuration file and extracts the constants specified in the $DBconstants array.
 *
 * @access public
 * @param string $filename (default: 'wp-config.php')
 * @return array
 */
function scrape_wpconfig( $filename = 'wp-config.php' )
{
	$DBconstants = array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET' );
	$file_contents = file( $filename );
	$config = array();
	foreach ( $DBconstants as $constant )
	{
		$config_line = implode( '', preg_grep( '/define\s*\(\s*[\'"]' . $constant . '[\'"]\s*,\s*[\'"].+[\'"]/' ,$file_contents ) );
		$config_string = preg_replace( '/(^.*define\s*\(\s*[\'"]' . $constant . '[\'"]\s*,\s*[\'"])(.+)[\'"].*$/', '$2',	$config_line );
		$config_string = substr( $config_string, 0, -1 ); //trim new line character
		$config[$constant] = $config_string;
	}
	return $config;
}

if ( isset( $argv[2] ) )
	$remote_server = $argv[2];
else
	$remote_server = 'dev';

// Set remote server database information based on appropriate configuration file
if ( file_exists( "$remote_server-config.php" ) )
	$remote_config = scrape_wpconfig( "$remote_server-config.php" );

// Local configuration
if ( file_exists( 'local-config.php' ) )
	$local_config = scrape_wpconfig( 'local-config.php' );

// Pull configuration from dbsync.ini file, if available
if( file_exists( 'dbsync.ini' ) )
{
	$ini = parse_ini_file( 'dbsync.ini', true);
}
else
{
	fwrite(STDERR, "No .ini file present.");
	exit(1); // A response code other than 0 is a failure
}

// Set variables
if ( isset( $ini[$remote_server]['ssh'] ) )
{
	$sshServer = $ini[$remote_server]['ssh'];
}
else
{
	fwrite(STDERR, "No ssh server defined.");
	exit(1);
}

if ( isset( $ini[$remote_server]['domain'] ) && isset( $ini['local']['domain'] )  )
{
	$domain_replace = true;
	$rDomain = $ini[$remote_server]['domain'];
	$lDomain = $ini['local']['domain'];
}
else
{
	echo "No domain configuration set. Search and replace will be skipped.\n";
	$domain_replace = false;
}

if ( isset( $ini[$remote_server]['username'] ) )
{
	$rDbUser = $ini[$remote_server]['username'];
}
elseif ( isset( $remote_config['DB_USER'] ) )
{
	$rDbUser = $remote_config['DB_USER'];
}
else
{
	fwrite(STDERR, "No remote database user defined.");
	exit(1);
} 

if ( isset( $ini[$remote_server]['password'] ) )
{
	$rDbPass = $ini[$remote_server]['password'];
}
elseif ( isset( $remote_config['DB_PASSWORD'] ) )
{
	$rDbPass = $remote_config['DB_PASSWORD'];
}
else
{
	fwrite(STDERR, "No remote database password defined.");
	exit(1);
} 

if ( isset( $ini[$remote_server]['database'] ) )
{
	$rDbName = $ini[$remote_server]['database'];
}
elseif ( isset( $remote_config['DB_NAME'] ) )
{
	$rDbName = $remote_config['DB_NAME'];
}
else
{
	fwrite(STDERR, "No remote database defined.");
	exit(1);
} 

if ( isset( $ini['local']['username'] ) )
{
	$lDbUser = $ini['local']['username'];
}
elseif ( isset( $local_config['DB_USER'] ) )
{
	$lDbUser = $local_config['DB_USER'];
}
else
{
	fwrite(STDERR, "No local database user defined.");
	exit(1);
}

if ( isset( $ini['local']['password'] ) )
{
	$lDbPass = $ini['local']['password'];
}
elseif ( isset( $local_config['DB_PASSWORD'] ) )
{
	$lDbPass = $local_config['DB_PASSWORD'];
}
else
{
	fwrite(STDERR, "No local database password defined.");
	exit(1);
}

if ( isset( $ini['local']['database'] ) )
{
	$lDbName = $ini['local']['database'];
}
elseif ( isset( $local_config['DB_NAME'] ) )
{
	$lDbName = $local_config['DB_NAME'];
}
else
{
	fwrite(STDERR, "No local database defined.");
	exit(1);
}

if ( isset( $ini['local']['charset'] ) )
{
	$lDbCharset = $ini['local']['charset'];
}
else
{
	$lDbCharset = 'utf-8';
}

if ( isset( $ini['local']['host'] ) )
{
	$lDbHost = $ini['local']['host'];
}
else
{
	$lDbHost = '127.0.0.1';
}

var_dump($sshServer);
echo " - sshServer\n";
var_dump($rDbUser);
echo " - rDbUser\n";
var_dump($rDbPass);
echo " - rDbPass\n";
var_dump($rDbName);
echo " - rDbName\n";
var_dump($lDbUser);
echo " - lDbUser\n";
var_dump($lDbPass);
echo " - lDbPass\n";
var_dump($lDbName);
echo " - lDbName\n";
var_dump($lDbCharset);
echo " - lDbCharset\n";
var_dump($lDbHost);
echo " - lDbHost\n";
var_dump($lDbName);
echo " - lDbName\n";
var_dump($remote_server);
echo " - remote_server\n";

switch( $mode )
{
	case 'pull':
		$source = "ssh -C $sshServer \"mysqldump -u $rDbUser -p'$rDbPass' $rDbName\"";
		$target = "mysql -u $lDbUser -p'$lDbPass' -D $lDbName";
		$cmd = "$source | $target";
		echo "Replacing $lDbName on localhost with $rDbName from $sshServer\n\n";
		echo `$cmd`;
		if ( true === $domain_replace )
		{
			echo "Searching for $rDomain in the local database and replacing with $lDomain";
			echo `searchreplacedb2cli.php -h "$lDbHost" -u "$lDbUser" -p "$lDbPass" -d "$lDbName" -c "$lDbCharset" -s "$rDomain" -r "$lDomain"`;
		}
		break;

	case 'push':
		$source = "mysqldump -u $lDbUser -p'$lDbPass' -D $lDbName";
		$target = "ssh -C $sshServer \"mysql -u $rDbUser -p'$rDbPass' $rDbName\"";
		$cmd = "$source | $target";
		echo "Replacing $rDbName on $sshServer with $lDbName on localhost\n\n";
		echo `$cmd`;
		break;
}

echo "\n\nTransfer complete.\n\n";
