## PHP script to get list of files and file comparison

PHP script to get the file list on the server and allow sort it by date of last modification,
store the files map and compare this map with previously stored map.
Useful to find changed/hacked files if you are not allowed  use any version control system.

## Features

* scan files using filter (sets in script options)
* store result in to the files.map
* comparison with previously saved a map
* sorting by path, filename, extension, size, last modification, last inode change, permissions, file state

## Usage

Put script in to the server root folder or in to subfolder(then will be need chage $path option).
Change the script options if need (e.g. current path, exclude filter, default map name).
Run it! (open in the browser)
As result you will get the full file list table and the stored file map.
Script will make the new map file after each click on **Scan again**, that allow you compare the couple maps in future.

## Warning!

Do not leave this script and the .map files with free access on the server!
Use .htaccess for access control.

## License

GNU/GPL [http://www.gnu.org/licenses/gpl.html](http://www.gnu.org/licenses/gpl.html)
