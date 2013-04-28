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
 *	$ssh_server = 'server';
 * If you are not using your .ssh/config file you should use this syntax
 *	$ssh_server = 'user@server.com
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


define( "SCRIPT_PATH", dirname( __FILE__ ) );

/**
 * Get parameters for database sync
 */

// Set the mode to "pull" or "push" depending on whether we are pulling from or pushing to remote 
$mode = isset( $argv[ 1 ] ) ? strtolower( $argv[ 1 ] ) : false;

// Default remote server
$remote_server = isset( $argv[ 2 ] ) ? $argv[ 2 ] : 'dev';

// Make sure a proper mode was set.
if( ( !( $mode ) ) || ( $mode !== "pull" && $mode !== "push" ) ) 
{
	echo "Please define a proper mode: 'pull' or 'push'";
	exit( 1 );
}

//
// All is well, continue ....
//

/** Set server variables */

// Default ini
$ini = null;

// Remote Domain
$remote_domain = null;

// Local Domain
$local_domain = null;


// Remote database
$remote_db = null;

// Remote database user
$remote_user = null;

// Remote database password
$remote_password = null;

// Local Database
$local_db = null;

// Local database user
$local_user = null;

// Local database password
$local_password = null;

// Remote config
$remote_config = array();

// Local Config
$local_config = array();

// Default Charset Value
$lDbCharset = 'utf8'; 

// Default Host Value
$lDbHost = '127.0.0.1';

// Default domain replace
$domain_replace = true;


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
		$config_line = implode( '', preg_grep( '/define\s*\(\s*[\'"]' . $constant . '[\'"]\s*,\s*[\'"].+[\'"]/' , $file_contents ) );
		$config_string = preg_replace( '/(^.*define\s*\(\s*[\'"]' . $constant . '[\'"]\s*,\s*[\'"])(.+)[\'"].*$/', '$2', $config_line );
		$config_string = trim( $config_string ); //trim new line character
		$config[ $constant ] = $config_string;
	}
	return $config;
}

/**
 * Returns ssh shell script for mysql dump or upload
 * @param string $server
 * @param string $mysql script / mysql or mysqldump
 * @return string
 */
function get_ssh_script( $server, $mysql_script ) {
	return "ssh -C $server \"$mysql_script\"";
}

/**
 * Returns mysqldump script
 * @param string $user
 * @param string $pwd
 * @param string $db
 * @return string
 */
function get_mysql_dump_script( $user, $pwd, $db ) {
	return "mysqldump -u $user --password=$pwd $db";
}

/**
 * Returns mysql script
 * @param string $user
 * @param string $pwd
 * @param string db
 * @return string
 */
function get_mysql_script( $user, $pwd, $db ) {
	return "mysql -u $user -p'$pwd' -D $db";
}

/** 
 * Prompts user to ensure that she wants to proceed with the synch process
 * If user enters yes or y, we execute the command
 * @param string $cmd Command to execute
 * @return void
 */
function execute_or_exit( $cmd ) {
	
	echo "Are you sure you want to continue?\n\n 'yes' or 'no': ";
	
	$do_it = false;
	$handle = fopen( "php://stdin", "r" );
	$line = strtolower( trim( fgets( $handle ) ) );
	
	if( $line !== "y" && $line !== "yes" ) {
		
		echo "\n\nAborting synch process\n\n";
		exit( 0 );

	}

	// If we haven't exited, execute the command.
	exec( $cmd );

}


// Pull configuration from dbsync.ini file, if available
// If function returned false, write out the error message and exit.
// Notice, we are assigning $ini in the condition.
if( !( $ini = parse_ini_file( 'dbsync.ini', true ) ) )
{
	fwrite( STDERR, "No .ini file present." );
	exit( 1 ); // A response code other than 0 is a failure
} 

// Set Remote .ini configurations
$ini_remote = $ini[ $remote_server ];

// Set Local .ini configurations
$ini_local = $ini[ 'local' ];


// Set remote server database information based on appropriate configuration file
if ( file_exists( "{$remote_server}-config.php" ) ) 
{
	$remote_config = scrape_wpconfig( "{$remote_server}-config.php" );
}

// Local configuration
if ( file_exists( 'local-config.php' ) ) 
{
	$local_config = scrape_wpconfig( 'local-config.php' );
}


// Set variables if the value exists
// Otherwise, exit with an error.
if ( isset( $ini_remote[ 'ssh' ] ) )
{
	$ssh_server = $ini_remote[ 'ssh' ];
}
else
{
	fwrite( STDERR, "No ssh server defined." );
	exit( 1 ); // Response code other than 0 is a failure
}

// Check to see if domains are configured
if ( isset( $ini_remote[ 'domain' ] ) && isset( $ini_local[ 'domain' ] ) )
{
	$remote_domain = $ini_remote[ 'domain' ];
	$local_domain = $ini_local[ 'domain' ];
}
else
{
	$domain_replace = false;
	echo "No domain configuration set. Search and replace will be skipped.\n";
}

//
// Set username by either .ini or remote config
//
if ( isset( $ini_remote[ 'username' ] ) || isset( $remote_config[ 'DB_USER' ] ) )
{
	@( $remote_user = $ini_remote[ 'username' ] ) || ( $remote_user = $remote_config[ 'DB_USER' ] );
}
else
{
	fwrite( STDERR, "No remote database user defined." );
	exit( 1 );
} 

//
// Set password either by .ini or remote config
//
if ( isset( $ini_remote[ 'password' ] ) || isset( $remote_config[ 'DB_PASSWORD' ] ) )
{
	@( $remote_password = $ini_remote[ 'password' ] ) || ( $remote_password = $remote_config[ 'DB_PASSWORD' ] );
}
else
{
	fwrite( STDERR, "No remote database password defined." );
	exit( 1 );
}

//
// Set database either by .ini or remote config
//
if ( isset( $ini_remote[ 'database' ] ) || isset( $remote_config[ 'DB_NAME' ] ) )
{
	@( $remote_db = $ini_remote[ 'database' ] ) || ( $remote_db = $remote_config[ 'DB_NAME' ] );
}
else
{
	fwrite( STDERR, "No remote database defined." );
	exit( 1 );
} 

//
// Set the local database either by .ini or local config
//
if ( isset( $ini_local[ 'username' ] ) || isset( $local_config[ 'DB_USER' ] ) )
{
	@( $local_user = $ini_local[ 'username' ] ) || ( $local_user = $local_config[ 'DB_USER' ] );
}
else
{
	fwrite( STDERR, "No local database user defined." );
	exit( 1 );
}

//
// Set local password either by .ini or local config
//
if ( isset( $ini_local[ 'password' ] ) || isset( $local_config[ 'DB_PASSWORD' ] ) )
{
	 @( $local_password = $ini_local[ 'password' ] ) || ( $local_password = $local_config[ 'DB_PASSWORD' ] );
}
else
{
	fwrite( STDERR, "No local database password defined." );
	exit( 1 );
}

//
// Set local database either by .ini or local config
//
if ( isset( $ini_local[ 'database' ] ) || isset( $local_config[ 'DB_NAME' ] ) )
{
	@( $local_db = $ini_local[ 'database' ] ) || ( $local_db = $local_config[ 'DB_NAME' ] );
}
else
{
	fwrite( STDERR, "No local database defined." );
	exit( 1 );
}

//
// Update Charset if it exists in .ini or local config
//
if ( isset( $ini_local[ 'charset' ] ) || isset( $local_config[ 'DB_CHARSET' ] ) )
{
	@( $lDbCharset = $ini_local[ 'charset' ] ) || ( $lDbCharset = $local_config[ 'DB_CHARSET' ] );
}

//
// Update db host if it exists in .ini or local config
//
if ( isset( $ini_local[ 'host' ] ) || isset( $local_config[ 'DB_HOST' ] ) )
{
	@( $lDbHost = $ini_local[ 'host' ] ) || ( $lDbHost = $local_config[ 'DB_HOST' ] );
}

//var_dump($ssh_server);
//echo " - ssh_server\n";
//var_dump($remote_user);
//echo " - remote_user\n";
//var_dump($remote_password);
//echo " - remote_password\n";
//var_dump($remote_db);
//echo " - remote_db\n";
//var_dump($local_user);
//echo " - local_user\n";
//var_dump($local_password);
//echo " - local_password\n";
//var_dump($local_db);
//echo " - local_db\n";
//var_dump($lDbCharset);
//echo " - lDbCharset\n";
//var_dump($lDbHost);
//echo " - lDbHost\n";
//var_dump($local_db);
//echo " - local_db\n";
//var_dump($remote_server);
//echo " - remote_server\n";

switch( $mode )
{
	case 'pull':
		$source = get_ssh_script( $ssh_server, get_mysql_dump_script( $remote_user, $remote_password, $remote_db ) );
		$target = get_mysql_script( $local_user, $local_password, $local_db );
		
		echo "Preparing to pull $remote_db on $lDbHost and replace with $local_db\n\n";
		execute_or_exit( "$source > {$remote_db}.sql; $target < {$remote_db}.sql" );
		echo "Replacing $local_db on $lDbHost with $remote_db from server: $ssh_server\n\n";		
		
		// Replace the domain names in the local database?
		if ( $domain_replace )
		{
			$cmd = SCRIPT_PATH . "/searchreplacedb2cli.php -h \"$lDbHost\" -u \"$local_user\" -p \"$local_password\" -d \"$local_db\" -c \"$lDbCharset\" -s \"$remote_domain\" -r \"$local_domain\"";
			
			echo "Preparing to search local database: '$lDbHost' for domain: '$remote_domain' and replace with domain: '$local_domain'\n\n";
			execute_or_exit( $cmd );
			echo "Search and replace is complete.\n\n";
			
		}

		break;

	case 'push':
		$source = get_mysql_dump_script( $local_user, $local_password, $local_db ); 
		$target = get_ssh_script( $ssh_server, get_mysql_script( $remote_user, $remote_password, $remote_db ) );
		
		execute_or_exit( "$source > ${local_db}.sql; $target < {$local_db}.sql" );
		
		echo "Replacing $remote_db on $ssh_server with $local_db on localhost\n\n";
		
		break;
}

echo "Database import complete.\n\nDatabase sync complete.\n\n";



