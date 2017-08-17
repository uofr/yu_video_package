YU Video Package
==================
This is an non-KAF moodle plugin package for the Kaltura Video Platform.
This package is developed by the Media and Information Technology Center, Yamaguchi University.
By using this package, seamless integration between the Moodle and the Kaltura is achieved.
Please note that there is a chance this module will not work on some Moodle environment.
Also, this module is only available in English.
Stay tuned to future versions for other language supports.

This package includes the following plugins:

* local_kaltura: This plugin provides various setting menu for administrators, and provides Kaltura APIs.
* local_mymedia: This plugin provides media gallery (called "My Media") for users. Users can upload, preview, delete their media through "My Media".  Also, users can edit metadata, and can set enable/disable access restriction to their own media.
* mod_kalmediaassign: This is an activity module. Each student can submit a media from their "My Media", and teachers can play submitted medias, and grade each media.
* mod_kalmediares: This is a resource module. Teachers can create media view page (embed media) in their courses, and can view students' play/view status.

Requirements
------

PHP5 or greater (For PHP 5.2 or less, you must also install the JSON extension)
For PHP5.3 or greater, PHP may display a warning if you have not set the timezone.
Web browsers must supprot the JavaScript and HTML5.

Installation
------

Unzip this package, and copy the directories (local and mod) under /moodle directory.
Installation will be completed after you log in as an administrator and access the notification menu.

How to use
------
Soory!!
We will write soon...

Targeted Moodle versions
------
Moodle 2.9, 3.0, 3.1, 3.2, 3.3

Branches
------
* MOODLE_29_STABLE -> Moodle2.9 branch 
* MOODLE_30_STABLE -> Moodle3.0 branch
* MOODLE_31_STABLE -> Moodle3.1 branch
* MOODLE_32_STABLE -> Moodle3.2 branch
* MOODLE_33_STABLE -> Moodle3.2 branch


First clone the repository with "git clone", then "git checkout MOODLE_29_STABLE(branch name)" to switch branches.

Warning
------
We are not responsible for any problem caused by this software. 
This software follows the license policy of Moodle (GNU GPL v3)

Acknowledgements
-------
This package is based [Kaltura Video Package 3.1.02](https://moodle.org/plugins/view.php?id=447).
We sincerely thank the Kaltura Inc. and many contributors.
