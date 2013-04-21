# Sync mySQL databases between local and remote servers #

##### (please note that this script does not actually merge data; it simply dumps the contents of one database and imports those contents into another database) #####

### Documentation coming soon ###

Currently this script will pull a database from your remote server import to your local server, and do a search and replace for the domains that you specify in an .ini configuration file. It will also pull information from local-config.php and dev/staging/production-config.php files, if preset.

#### To do: ####

* Documentation
* Backup database transfers to file first instead of direct import?
* Search and replace function for transferring from local to remote servers
* Remove posts GUID column from search and replace by default (these usually shouldn't change)



